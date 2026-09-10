#!/bin/bash
# ============================================================
# EduGest DZ — Lanceur des campagnes k6 (Sprint 6, lot G)
# Voir docs/PERF_TESTS_K6.md pour la préparation (comptes de test,
# variables, interprétation, contraintes de throttle).
#
# Usage :
#   ./tests/k6/run.sh smoke|load|isolation|qr|bulletins|auth|webhook|tous
#
# Variables principales (toutes optionnelles sauf les comptes) :
#   K6_BASE_URL     — cible (défaut http://localhost:8000)
#   K6_EMAILS       — compte(s) de test, sans 2FA (virgules = plusieurs)
#   K6_PASSWORDS    — mot(s) de passe (un seul = appliqué à tous)
#   K6_VUS/K6_HOLD  — profil de charge (défaut 5 VU / 3 min)
#   K6_REPORT_DIR   — dossier des rapports JSON (défaut tests/k6/results)
# ============================================================

set -euo pipefail

SCENARIO="${1:-}"
cd "$(dirname "$0")"

if [ -z "$SCENARIO" ]; then
    echo "Usage : $0 smoke|load|isolation|qr|bulletins|auth|webhook|tous"
    exit 1
fi

command -v k6 >/dev/null 2>&1 || {
    echo "❌ k6 introuvable — https://k6.io/docs/get-started/installation/" >&2
    exit 1
}

# Les scénarios qui s'authentifient exigent un compte de test.
case "$SCENARIO" in
    smoke|load|isolation|qr|bulletins|auth)
        if [ -z "${K6_EMAILS:-}" ] || [ -z "${K6_PASSWORDS:-}" ]; then
            echo "❌ K6_EMAILS et K6_PASSWORDS sont requis pour '$SCENARIO' (compte sans 2FA)." >&2
            exit 1
        fi
        ;;
esac

export K6_REPORT_DIR="${K6_REPORT_DIR:-results}"
mkdir -p "$K6_REPORT_DIR"

run() {
    local fichier="$1" nom="$2"
    echo "━━━ $nom — $(date '+%H:%M:%S') ━━━"
    k6 run "scenarios/$fichier"
}

case "$SCENARIO" in
    smoke)      run smoke.js "Smoke (la stack répond ?)" ;;
    load)       run load-core.js "Charge — parcours de lecture standard" ;;
    isolation)  run isolation-rls.js "Isolation multi-tenant sous charge" ;;
    qr)         run qr-presence.js "Scan QR de présence (chemin d'erreur)" ;;
    bulletins)  run bulletins.js "Génération de bulletins (écriture)" ;;
    auth)       run auth-throttle.js "Limiteur de login (throttle:auth)" ;;
    webhook)    run webhook-surveillance.js "Webhook public Dahua" ;;
    tous)
        # Ordre imposé : smoke d'abord (inutile de charger une stack
        # cassée), auth-throttle en dernier (il consomme le budget login
        # de l'IP pour 15 min).
        #
        # ⚠️ La suite complète enchaîne ~38 logins depuis la même IP :
        # avec throttle:auth à 10/15 min, il faut soit relever cette
        # limite sur l'environnement de perf, soit accepter que les
        # scénarios après le 10e login échouent (token 429).
        if [ "${K6_AUTH_THROTTLE_RELAXED:-}" != "1" ]; then
            echo "⚠️  Mode 'tous' : ~38 logins depuis une seule IP ; throttle:auth (10/15 min)"
            echo "    doit être relevé sur l'env de perf. Sinon : K6_AUTH_THROTTLE_RELAXED=1 pour"
            echo "    confirmer que vous savez ce que vous faites, ou lancez les scénarios un à un."
            exit 1
        fi
        run smoke.js "Smoke"
        run load-core.js "Charge — lecture"
        run qr-presence.js "Scan QR (erreur)"
        run webhook-surveillance.js "Webhook Dahua"
        run isolation-rls.js "Isolation multi-tenant"
        run bulletins.js "Bulletins"
        run auth-throttle.js "Limiteur de login (en dernier)"
        ;;
    *)
        echo "❌ Scénario inconnu : $SCENARIO" >&2
        echo "   smoke | load | isolation | qr | bulletins | auth | webhook | tous"
        exit 1
        ;;
esac

echo ""
echo "✅ Rapport JSON : $K6_REPORT_DIR/ (publié, pas résumé — lot G)"
