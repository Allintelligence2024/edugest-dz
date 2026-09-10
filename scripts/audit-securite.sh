#!/usr/bin/env bash
#
# Audit de sécurité des dépendances et du dépôt — Sprint 4.
#
#   composer audit          vulnérabilités des paquets PHP
#   npm audit               vulnérabilités des paquets JS (frontend + mobile)
#   gitleaks                secrets commités (config : .gitleaks.toml)
#
# Chaque contrôle est exécuté jusqu'au bout : on veut le rapport complet en
# un passage, pas le premier échec. Le code de sortie agrège les résultats.

set -uo pipefail

RACINE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ECHECS=0

titre() {
  echo
  echo "══════════════════════════════════════════════════════════════"
  echo "  $1"
  echo "══════════════════════════════════════════════════════════════"
}

echouer() {
  echo "✖ $1"
  ECHECS=$((ECHECS + 1))
}

# ── Dépendances PHP ────────────────────────────────────────────────────────

titre "composer audit — backend"

if command -v composer >/dev/null 2>&1; then
  if (cd "${RACINE}/backend" && composer audit --no-interaction --format=plain); then
    echo "✔ Aucune vulnérabilité connue dans les dépendances PHP"
  else
    echouer "composer audit a signalé des vulnérabilités"
  fi
else
  echo "⊘ composer indisponible — contrôle ignoré (il tournera en CI)"
fi

# ── Dépendances JS ─────────────────────────────────────────────────────────

for projet in frontend mobile; do
  chemin="${RACINE}/${projet}"
  [ -f "${chemin}/package.json" ] || continue

  titre "npm audit — ${projet}"

  if (cd "${chemin}" && npm audit --audit-level=high); then
    echo "✔ Aucune vulnérabilité haute ou critique dans ${projet}"
  else
    echouer "npm audit (${projet}) a signalé des vulnérabilités de niveau high ou critical"
  fi
done

# ── Secrets ────────────────────────────────────────────────────────────────

titre "gitleaks — secrets commités"

if command -v gitleaks >/dev/null 2>&1; then
  if gitleaks detect \
       --source "${RACINE}" \
       --config "${RACINE}/.gitleaks.toml" \
       --no-banner --redact; then
    echo "✔ Aucun secret détecté"
  else
    echouer "gitleaks a détecté des secrets — voir le rapport ci-dessus"
  fi
else
  echo "⊘ gitleaks indisponible localement."
  echo "  Installation : https://github.com/gitleaks/gitleaks/releases"
  echo "  Le contrôle reste appliqué en CI."
fi

# ── Synthèse ───────────────────────────────────────────────────────────────

titre "Synthèse"

if [ "${ECHECS}" -eq 0 ]; then
  echo "✔ Tous les contrôles disponibles sont au vert."
  exit 0
fi

echo "✖ ${ECHECS} contrôle(s) en échec."
exit 1
