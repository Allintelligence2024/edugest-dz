#!/bin/bash
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
# deploy.sh — Script de déploiement EduGest DZ
# Usage : ./deploy.sh [--fresh]  (--fresh = reset BDD)
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
set -e

FRESH=${1:-""}
COMPOSE="docker compose -f docker-compose.prod.yml"

echo "🚀 Déploiement EduGest DZ — $(date)"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# ── 1. Vérifier que .env existe ──
if [ ! -f backend/.env ]; then
  echo "❌ backend/.env manquant — copier .env.production.example et remplir les valeurs"
  exit 1
fi

# ── 2. Tirer les dernières images / rebuild ──
echo "📦 Build des images..."
$COMPOSE build --no-cache app frontend queue scheduler

# ── 3. Arrêter les anciens conteneurs proprement ──
echo "⏹  Arrêt des conteneurs existants..."
$COMPOSE down --remove-orphans || true

# ── 4. Démarrer les services de base ──
echo "🐘 Démarrage PostgreSQL + Redis..."
$COMPOSE up -d postgres redis
sleep 10

# ── 5. Migrations ──
echo "🗄  Migrations base de données..."
if [ "$FRESH" = "--fresh" ]; then
  echo "   ⚠️  Mode FRESH — remise à zéro de la BDD"
  $COMPOSE run --rm app php artisan migrate:fresh --seed --force
else
  $COMPOSE run --rm app php artisan migrate --force
fi

# ── 6. Optimisations Laravel production ──
echo "⚡ Optimisations Laravel..."
$COMPOSE run --rm app php artisan config:cache
$COMPOSE run --rm app php artisan route:cache
$COMPOSE run --rm app php artisan view:cache
$COMPOSE run --rm app php artisan event:cache
$COMPOSE run --rm app php artisan storage:link

# ── 7. Démarrer tous les services ──
echo "🌐 Démarrage de tous les services..."
$COMPOSE up -d

# ── 8. Healthcheck ──
echo "❤️  Vérification santé des services..."
sleep 15
$COMPOSE ps

# ── 9. Résumé ──
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "✅ Déploiement terminé !"
echo "   API    : https://edugestdz.dz/api/v1"
echo "   Web    : https://edugestdz.dz"
echo "   Logs   : docker compose -f docker-compose.prod.yml logs -f"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
