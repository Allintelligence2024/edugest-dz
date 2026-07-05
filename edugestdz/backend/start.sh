#!/bin/sh
set -e

echo "╔══════════════════════════════════════╗"
echo "║     EduGest DZ — Starting Up         ║"
echo "╚══════════════════════════════════════╝"

# ── Vérifications critiques ────────────────────────────────────────────

# 1. Vérifier la connexion PostgreSQL (retry jusqu'à 30s)
echo "⏳ Attente PostgreSQL..."
MAX_TRIES=30
TRIES=0
until php artisan db:monitor --databases=pgsql 2>/dev/null || \
      php -r "new PDO('pgsql:host='.\$_ENV['DB_HOST'].';port='.\$_ENV['DB_PORT'].';dbname='.\$_ENV['DB_DATABASE'], \$_ENV['DB_USERNAME'], \$_ENV['DB_PASSWORD']);" 2>/dev/null; do
    TRIES=$((TRIES+1))
    if [ $TRIES -ge $MAX_TRIES ]; then
        echo "❌ PostgreSQL non disponible après ${MAX_TRIES}s. Vérifiez les variables DB_*"
        exit 1
    fi
    echo "   Tentative $TRIES/$MAX_TRIES..."
    sleep 1
done
echo "✅ PostgreSQL connecté"

# 2. Générer APP_KEY si manquant
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:" ]; then
    echo "⚙️  Génération APP_KEY..."
    php artisan key:generate --force
fi

# 3. Générer JWT_SECRET si manquant
if [ -z "$JWT_SECRET" ]; then
    echo "⚙️  Génération JWT_SECRET..."
    php artisan jwt:secret --force
fi

# 4. Vider les caches (important après chaque déploiement)
echo "🧹 Nettoyage caches..."
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

# 5. Migrations (avec retry en cas d'échec transitoire)
echo "🗄️  Migrations PostgreSQL..."
MIGRATION_TRIES=3
for i in $(seq 1 $MIGRATION_TRIES); do
    if php artisan migrate --force; then
        echo "✅ Migrations OK"
        break
    else
        if [ $i -eq $MIGRATION_TRIES ]; then
            echo "❌ Migrations échouées après $MIGRATION_TRIES tentatives"
            exit 1
        fi
        echo "   Retry $i/$MIGRATION_TRIES dans 3s..."
        sleep 3
    fi
done

# 6. Seeder curriculum algérien (ignoré si déjà fait)
echo "🌱 Seeder curriculum DZ..."
php artisan db:seed --class=CurriculumAlgerienSeeder --force 2>/dev/null && \
    echo "✅ Curriculum DZ seedé" || \
    echo "⚠️  Seeder ignoré (déjà fait ou erreur non critique)"

# 7. Générer Swagger (non bloquant)
echo "📖 Génération Swagger..."
php artisan l5-swagger:generate 2>/dev/null && \
    echo "✅ Swagger généré" || \
    echo "⚠️  Swagger ignoré (non critique)"

# 8. Optimiser (cache routes + config + views)
echo "⚡ Optimisation..."
php artisan optimize
echo "✅ Application optimisée"

# ── Démarrer les services ──────────────────────────────────────────────

echo "🚀 Démarrage PHP-FPM..."
php-fpm -D

echo "🌐 Démarrage Nginx..."
echo "════════════════════════════════════════"
echo "   EduGest DZ prêt sur le port 80"
echo "   Health: /api/health"
echo "════════════════════════════════════════"
exec nginx -g 'daemon off;'
