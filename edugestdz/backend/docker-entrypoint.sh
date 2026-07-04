#!/bin/sh
set -e

php artisan key:generate --force || true
php artisan jwt:secret --force || true
php artisan migrate --force || true
php artisan config:clear || true

exec /usr/bin/supervisord -c /etc/supervisord.conf
