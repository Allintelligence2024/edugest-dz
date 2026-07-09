# 🚨 FIX MERGE CONFLICTS — Dockerfile.railway + railway.json
## EduGest DZ · URGENT · 5 Juillet 2026

---

## PROBLÈME

Conflit de merge entre `develop` et `main` sur :
- `edugestdz/backend/Dockerfile.railway`
- `edugestdz/backend/railway.json`

Les deux branches ont modifié ces fichiers différemment.

---

## ÉTAPE 1 — Résoudre les conflits

```bash
git checkout develop
git pull origin main

# Voir les conflits
git status
# → both modified: edugestdz/backend/Dockerfile.railway
# → both modified: edugestdz/backend/railway.json
```

---

## ÉTAPE 2 — Remplacer Dockerfile.railway (garder NOTRE version)

**Écraser complètement :**
`edugestdz/backend/Dockerfile.railway`

```dockerfile
FROM php:8.2-fpm-alpine AS build

# Dépendances système
RUN apk add --no-cache \
    git curl zip unzip libpng-dev libpq-dev \
    oniguruma-dev libxml2-dev nginx supervisor \
    freetype-dev libjpeg-turbo-dev

# Extensions PHP
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install \
    pdo pdo_pgsql pgsql mbstring exif pcntl bcmath gd xml opcache intl

# Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copier les fichiers composer en premier (cache layer)
COPY composer.json composer.lock ./

# Installer les dépendances sans les scripts (plus rapide)
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --no-interaction \
    --prefer-dist

# Copier tout le reste
COPY . .

# Finaliser l'autoload
RUN composer dump-autoload --optimize --no-dev

# Permissions
RUN mkdir -p storage/logs storage/framework/{cache,sessions,views} bootstrap/cache \
 && chown -R www-data:www-data /var/www/html \
 && chmod -R 755 storage bootstrap/cache

# Nginx config
RUN printf 'server {\n\
    listen 80;\n\
    root /var/www/html/public;\n\
    index index.php index.html;\n\
    client_max_body_size 10M;\n\
    location / {\n\
        try_files $uri $uri/ /index.php?$query_string;\n\
    }\n\
    location ~ \\.php$ {\n\
        fastcgi_pass 127.0.0.1:9000;\n\
        fastcgi_index index.php;\n\
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;\n\
        include fastcgi_params;\n\
        fastcgi_read_timeout 300;\n\
    }\n\
    location /api/health { access_log off; }\n\
    location ~ /\\.ht { deny all; }\n\
}\n' > /etc/nginx/http.d/default.conf

# PHP config optimisée pour Railway
RUN printf 'opcache.enable=1\n\
opcache.memory_consumption=256\n\
opcache.max_accelerated_files=20000\n\
opcache.validate_timestamps=0\n\
upload_max_filesize=10M\n\
post_max_size=10M\n\
memory_limit=256M\n\
max_execution_time=60\n' > /usr/local/etc/php/conf.d/railway.ini

EXPOSE 80

# Script de démarrage complet
CMD ["sh", "-c", "\
    echo '=== EduGest DZ Starting ===' && \
    php artisan key:generate --force 2>/dev/null || true && \
    php artisan jwt:secret --force 2>/dev/null || true && \
    php artisan config:clear && \
    php artisan migrate --force 2>&1 | tail -5 && \
    php artisan db:seed --class=CurriculumAlgerienSeeder --force 2>/dev/null || true && \
    php artisan l5-swagger:generate 2>/dev/null || true && \
    php artisan optimize && \
    php-fpm -D && \
    echo '=== nginx starting ===' && \
    nginx -g 'daemon off;' \
"]
```

---

## ÉTAPE 3 — Remplacer railway.json (garder NOTRE version)

**Écraser complètement :**
`edugestdz/backend/railway.json`

```json
{
  "$schema": "https://railway.app/railway.schema.json",
  "build": {
    "builder": "DOCKERFILE",
    "dockerfilePath": "Dockerfile.railway"
  },
  "deploy": {
    "healthcheckPath": "/api/health",
    "healthcheckTimeout": 120,
    "restartPolicyType": "ON_FAILURE",
    "restartPolicyMaxRetries": 3
  }
}
```

---

## ÉTAPE 4 — Marquer les conflits comme résolus et committer

```bash
cd edugestdz/backend

# Marquer comme résolus
git add Dockerfile.railway railway.json

# Vérifier qu'il n'y a plus de conflits
git status
# → Doit afficher "All conflicts fixed but you are still merging"

# Committer le merge
git commit -m "fix: Résoudre conflits merge Dockerfile.railway + railway.json — garder version develop"

# Pousser
git push origin develop
```

---

## CE QUE TU DIS À DEEPSEEK

```
Conflit de merge sur PR develop → main.
Fichiers en conflit :
  - edugestdz/backend/Dockerfile.railway
  - edugestdz/backend/railway.json

Actions :
1. git checkout develop && git pull origin main
2. Écraser Dockerfile.railway avec le contenu de MISSION_FIX_CONFLICTS_MERGE.md
3. Écraser railway.json avec le contenu de MISSION_FIX_CONFLICTS_MERGE.md
4. git add edugestdz/backend/Dockerfile.railway edugestdz/backend/railway.json
5. git commit -m "fix: Résoudre conflits merge Dockerfile.railway + railway.json"
6. git push origin develop

Ne toucher à aucun autre fichier. Ne pas relancer les tests.
```
