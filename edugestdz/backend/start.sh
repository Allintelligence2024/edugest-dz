#!/bin/sh
# EduGest DZ — Script de démarrage Railway
# Robuste : gère les erreurs, retry PostgreSQL, jamais de crash silencieux

set -e

echo "╔══════════════════════════════════════╗"
echo "║     EduGest DZ — Starting Up         ║"
echo "╚══════════════════════════════════════╝"
echo "Timestamp: $(date)"
echo "PHP: $(php --version | head -1)"

# ── ÉTAPE 1 : Attendre PostgreSQL (méthode fiable) ─────────────────────
echo ""
echo "⏳ [1/7] Attente PostgreSQL..."

MAX_TRIES=60
TRIES=0

# Utiliser pg_isready si disponible, sinon PHP PDO
while [ $TRIES -lt $MAX_TRIES ]; do
    TRIES=$((TRIES + 1))

    # Test avec PHP PDO (compatible Laravel 11)
    if php -r "
        \$host = getenv('DB_HOST') ?: getenv('PGHOST') ?: 'localhost';
        \$port = getenv('DB_PORT') ?: getenv('PGPORT') ?: '5432';
        \$db   = getenv('DB_DATABASE') ?: getenv('PGDATABASE') ?: 'railway';
        \$user = getenv('DB_USERNAME') ?: getenv('PGUSER') ?: 'postgres';
        \$pass = getenv('DB_PASSWORD') ?: getenv('PGPASSWORD') ?: '';
        try {
            new PDO(\"pgsql:host=\$host;port=\$port;dbname=\$db\", \$user, \$pass, [PDO::ATTR_TIMEOUT => 3]);
            exit(0);
        } catch (Exception \$e) {
            exit(1);
        }
    " 2>/dev/null; then
        echo "✅ PostgreSQL connecté (tentative $TRIES)"
        break
    fi

    if [ $TRIES -ge $MAX_TRIES ]; then
        echo "❌ PostgreSQL indisponible après ${MAX_TRIES} secondes."
        echo "   Vérifiez les variables : DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD"
        echo "   Variables détectées :"
        echo "   DB_HOST=$(printenv DB_HOST 2>/dev/null || echo 'non défini')"
        echo "   PGHOST=$(printenv PGHOST 2>/dev/null || echo 'non défini')"
        exit 1
    fi

    echo "   Tentative $TRIES/$MAX_TRIES..."
    sleep 1
done

# ── ÉTAPE 2 : Variables d'environnement Laravel ─────────────────────────
echo ""
echo "⚙️  [2/7] Configuration Laravel..."

# Vérifier APP_KEY
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:" ] || [ "$APP_KEY" = "" ]; then
    echo "   Génération APP_KEY..."
    php artisan key:generate --force 2>&1 || echo "   ⚠️ key:generate ignoré"
else
    echo "   APP_KEY : présent"
fi

# Vérifier JWT_SECRET
if [ -z "$JWT_SECRET" ]; then
    echo "   Génération JWT_SECRET..."
    php artisan jwt:secret --force 2>&1 || echo "   ⚠️ jwt:secret ignoré"
else
    echo "   JWT_SECRET : présent"
fi

# ── ÉTAPE 3 : Nettoyer les caches ───────────────────────────────────────
echo ""
echo "🧹 [3/7] Nettoyage caches..."
php artisan config:clear  2>&1 | tail -1
php artisan cache:clear   2>&1 | tail -1
php artisan route:clear   2>&1 | tail -1
php artisan view:clear    2>&1 | tail -1
echo "   ✅ Caches nettoyés"

# ── ÉTAPE 4 : Migrations ─────────────────────────────────────────────────
echo ""
echo "🗄️  [4/7] Migrations PostgreSQL..."

for i in 1 2 3; do
    if php artisan migrate --force 2>&1; then
        echo "   ✅ Migrations OK"
        break
    else
        if [ $i -eq 3 ]; then
            echo "   ❌ Migrations échouées après 3 tentatives — arrêt"
            exit 1
        fi
        echo "   Retry $i/3 dans 5s..."
        sleep 5
    fi
done

# ── ÉTAPE 5 : Optimisation ───────────────────────────────────────────────
echo ""
echo "⚡ [5/7] Optimisation..."
php artisan config:cache 2>&1 | tail -1 || echo "   config:cache ignoré"
php artisan route:cache  2>&1 | tail -1 || echo "   route:cache ignoré"
php artisan view:cache   2>&1 | tail -1 || echo "   view:cache ignoré"
echo "   ✅ Optimisé"

# ── ÉTAPE 6 : Démarrer PHP-FPM ──────────────────────────────────────────
echo ""
echo "🚀 [6/7] Démarrage PHP-FPM..."
php-fpm -D 2>&1
sleep 2

# Vérifier que PHP-FPM est bien lancé
if ! pgrep php-fpm > /dev/null 2>&1; then
    echo "❌ PHP-FPM n'a pas démarré — vérifiez les logs"
    exit 1
fi
echo "   ✅ PHP-FPM actif sur 127.0.0.1:9000"

# ── ÉTAPE 7 : Démarrer Nginx ─────────────────────────────────────────────
echo ""
echo "🌐 [7/7] Démarrage Nginx..."
echo "═══════════════════════════════════════"
echo "   EduGest DZ opérationnel"
echo "   Port : 80"
echo "   Health : /api/health"
echo "   Swagger : /api/documentation"
echo "═══════════════════════════════════════"
exec nginx -g 'daemon off;'
