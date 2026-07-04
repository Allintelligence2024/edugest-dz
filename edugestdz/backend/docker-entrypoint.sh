#!/bin/sh
set -e

php artisan key:generate --force 2>/dev/null || true
php artisan jwt:secret --force 2>/dev/null || true

php artisan migrate --force 2>/dev/null &
php artisan config:cache 2>/dev/null || true

exec /usr/bin/supervisord -c /etc/supervisord.conf
