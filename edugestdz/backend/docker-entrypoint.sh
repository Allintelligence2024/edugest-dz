#!/bin/sh
set -e

echo "==> EduGest DZ Entrypoint"

# Cache config from env vars
echo "==> Caching Laravel config..."
php artisan config:cache 2>&1 || true

# Generate app key if not set
echo "==> Checking APP_KEY..."
php artisan key:generate --force 2>&1 || true

# Run migrations
echo "==> Running migrations..."
php artisan migrate --force 2>&1 || true

# Start php-fpm in background
echo "==> Starting php-fpm..."
php-fpm -D

# Wait for php-fpm to be ready
sleep 1

# Start nginx in foreground
echo "==> Starting nginx..."
exec nginx -g 'daemon off;'
