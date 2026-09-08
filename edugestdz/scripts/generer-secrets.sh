#!/usr/bin/env bash
#
# EduGest DZ — Génération des secrets d'environnement (Sprint 3)
#
# Remplace les mots de passe par défaut qui étaient codés en dur dans
# docker-compose.yml (EduGest@2026!, PgAdmin@2026, RedisAdmin@2026...).
# Ces valeurs étaient publiques dans le dépôt : toute installation qui les
# gardait était compromise d'avance.
#
# Usage :  ./scripts/generer-secrets.sh [chemin/.env]
#
set -euo pipefail

ENV_FILE="${1:-.env}"
EXEMPLE="${ENV_FILE}.example"

aleatoire() { openssl rand -base64 "${1:-36}" | tr -d '\n=+/' | cut -c1-"${2:-32}"; }

if [[ -f "$ENV_FILE" ]]; then
  echo "⚠️  $ENV_FILE existe déjà."
  read -r -p "    Régénérer les secrets (les anciens seront perdus) ? [o/N] " reponse
  [[ "$reponse" =~ ^[oO]$ ]] || { echo "Abandon."; exit 0; }
  cp "$ENV_FILE" "${ENV_FILE}.bak.$(date +%Y%m%d%H%M%S)"
  echo "    Sauvegarde créée."
fi

if [[ -f "$EXEMPLE" ]] && [[ ! -f "$ENV_FILE" ]]; then
  cp "$EXEMPLE" "$ENV_FILE"
fi

touch "$ENV_FILE"

definir() {
  local cle="$1" valeur="$2"
  if grep -q "^${cle}=" "$ENV_FILE" 2>/dev/null; then
    # Séparateur | pour éviter les collisions avec les caractères base64.
    sed -i.tmp "s|^${cle}=.*|${cle}=${valeur}|" "$ENV_FILE" && rm -f "${ENV_FILE}.tmp"
  else
    echo "${cle}=${valeur}" >> "$ENV_FILE"
  fi
}

echo "🔐 Génération des secrets..."

definir DB_PASSWORD          "$(aleatoire 36 32)"
definir REDIS_PASSWORD       "$(aleatoire 36 32)"
definir MEILISEARCH_KEY      "$(aleatoire 48 40)"
definir PGADMIN_PASSWORD     "$(aleatoire 36 24)"
definir REDIS_UI_PASSWORD    "$(aleatoire 36 24)"

# Secrets applicatifs — voir config/security.php
definir JWT_SECRET           "$(aleatoire 64 48)"
definir QR_SIGNING_KEY       "$(aleatoire 48 40)"
definir AUDIT_CHAIN_KEY      "$(aleatoire 48 40)"
definir CRON_SECRET          "$(aleatoire 48 40)"

echo "✅ Secrets écrits dans $ENV_FILE"
echo
echo "   Étapes suivantes :"
echo "     php artisan key:generate    # APP_KEY"
echo "     docker compose up -d"
echo
echo "   ⚠️  Ne jamais committer $ENV_FILE (déjà couvert par .gitignore)."
echo "   ⚠️  AUDIT_CHAIN_KEY est volontairement distincte d'APP_KEY :"
echo "       faire tourner APP_KEY ne doit pas invalider la chaîne d'audit."
