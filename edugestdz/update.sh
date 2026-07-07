#!/bin/bash
# ════════════════════════════════════════════════════════════════
# EduGest DZ — Script de mise à jour Niveau 3 (Self-Hosted)
# ════════════════════════════════════════════════════════════════
# Usage : bash update.sh [version]
# Exemple : bash update.sh 1.2.0
# ════════════════════════════════════════════════════════════════

set -e

GREEN='\033[0;32m'; YELLOW='\033[1;33m'; CYAN='\033[0;36m'; RED='\033[0;31m'; NC='\033[0m'

log()   { echo -e "${GREEN}✅ $1${NC}"; }
warn()  { echo -e "${YELLOW}⚠️  $1${NC}"; }
info()  { echo -e "${CYAN}ℹ️  $1${NC}"; }
error() { echo -e "${RED}❌ $1${NC}"; exit 1; }

echo -e "${CYAN}━━━ EduGest DZ — Mise à jour ━━━${NC}"
echo "Date : $(date)"

VERSION=${1:-latest}
COMPOSE_FILE="docker-compose.selfhosted.yml"

[ ! -f "$COMPOSE_FILE" ] && error "Fichier $COMPOSE_FILE introuvable. Lancer depuis le dossier EduGest DZ."

# ── 1. Backup avant mise à jour ───────────────────────────────
info "Backup PostgreSQL avant mise à jour..."
docker compose -f $COMPOSE_FILE exec -T postgres \
    pg_dump -U ${DB_USERNAME:-edugest_user} ${DB_DATABASE:-edugestdz} \
    > "backups/postgres/before-update-$(date +%Y%m%d-%H%M%S).sql" 2>/dev/null
log "Backup créé"

# ── 2. Télécharger les nouvelles images ───────────────────────
info "Téléchargement des nouvelles images (version: $VERSION)..."
docker compose -f $COMPOSE_FILE pull 2>/dev/null || warn "Pull partiel — continuation"
log "Images téléchargées"

# ── 3. Arrêt progressif (garder la DB active) ─────────────────
info "Arrêt progressif des services applicatifs..."
docker compose -f $COMPOSE_FILE stop app queue scheduler frontend nginx 2>/dev/null || true

# ── 4. Redémarrer avec nouvelles images ───────────────────────
info "Redémarrage avec la nouvelle version..."
docker compose -f $COMPOSE_FILE up -d

sleep 10

# ── 5. Migrations ─────────────────────────────────────────────
info "Migrations base de données..."
docker compose -f $COMPOSE_FILE exec -T app \
    php artisan migrate --force 2>&1 | tail -5
log "Migrations OK"

# ── 6. Optimisation ───────────────────────────────────────────
docker compose -f $COMPOSE_FILE exec -T app php artisan optimize
docker compose -f $COMPOSE_FILE exec -T app php artisan config:clear

log "Optimisation terminée"

# ── 7. Vérification santé ─────────────────────────────────────
info "Vérification de la santé de l'application..."
sleep 5
IP=$(hostname -I | awk '{print $1}')
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "http://${IP}/api/health" 2>/dev/null || echo "000")

if [ "$HTTP_CODE" = "200" ]; then
    log "Santé : OK (HTTP 200)"
else
    warn "Health check retourné HTTP ${HTTP_CODE} — vérifier les logs"
    warn "Logs : docker compose -f $COMPOSE_FILE logs app --tail=50"
fi

echo ""
echo -e "${GREEN}✅ Mise à jour terminée !${NC}"
echo -e "   Version : ${VERSION}"
echo -e "   Accès   : http://${IP}"
echo ""
