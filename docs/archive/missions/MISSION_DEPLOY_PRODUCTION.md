# 🚀 MISSION DEEPSEEK — Déploiement Production
## EduGest DZ · Branche : develop → main · 1er Juillet 2026
## Objectif : rendre le logiciel accessible sur une vraie URL HTTPS

---

## CONTEXTE — Ce qui EXISTE déjà

- `edugestdz/docker-compose.yml` → 9 services complets (dev) ✅
- `edugestdz/nginx/conf.d/default.conf` → config Nginx locale ✅
- `edugestdz/frontend/Dockerfile` → 3 stages (dev / builder / production Nginx) ✅
- `edugestdz/backend/docker/php/` → Dockerfile PHP (stage dev existant)
- `edugestdz/Makefile` → commandes make existantes ✅

## Ce qui MANQUE pour la production
```
1. docker-compose.prod.yml          ← compose dédié production (sans pgadmin, redis-ui)
2. backend/Dockerfile               ← stage production PHP-FPM optimisé
3. nginx/conf.d/production.conf     ← config Nginx avec SSL + domaine réel
4. .env.production.example          ← template .env pour le serveur
5. deploy.sh                        ← script de déploiement automatisé
6. .github/workflows/deploy.yml     ← CD automatique sur merge main
```

---

## ÉTAPE 1 — Dockerfile backend production

**Modifier :** `edugestdz/backend/Dockerfile`

Ajouter le stage production à la fin du Dockerfile existant (après le stage development) :

```dockerfile
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
# STAGE : Production PHP-FPM optimisé
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
FROM php:8.2-fpm-alpine AS production

WORKDIR /var/www/html

# ── Extensions PHP nécessaires ──
RUN apk add --no-cache \
    postgresql-dev \
    libzip-dev \
    libpng-dev \
    oniguruma-dev \
    && docker-php-ext-install \
        pdo_pgsql \
        pgsql \
        zip \
        gd \
        bcmath \
        opcache \
        pcntl

# ── Redis extension ──
RUN pecl install redis && docker-php-ext-enable redis

# ── OPcache config pour la production ──
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.memory_consumption=256" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.max_accelerated_files=20000" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.revalidate_freq=0" >> /usr/local/etc/php/conf.d/opcache.ini

# ── Composer ──
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ── Copier le code ──
COPY . .

# ── Installer les dépendances sans dev ──
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

# ── Permissions Laravel ──
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# ── PHP-FPM config production ──
RUN echo "[www]" > /usr/local/etc/php-fpm.d/www.conf \
    && echo "user = www-data" >> /usr/local/etc/php-fpm.d/www.conf \
    && echo "group = www-data" >> /usr/local/etc/php-fpm.d/www.conf \
    && echo "listen = 0.0.0.0:9000" >> /usr/local/etc/php-fpm.d/www.conf \
    && echo "pm = dynamic" >> /usr/local/etc/php-fpm.d/www.conf \
    && echo "pm.max_children = 20" >> /usr/local/etc/php-fpm.d/www.conf \
    && echo "pm.start_servers = 5" >> /usr/local/etc/php-fpm.d/www.conf \
    && echo "pm.min_spare_servers = 3" >> /usr/local/etc/php-fpm.d/www.conf \
    && echo "pm.max_spare_servers = 10" >> /usr/local/etc/php-fpm.d/www.conf

EXPOSE 9000

CMD ["php-fpm"]
```

---

## ÉTAPE 2 — docker-compose.prod.yml

**Créer :** `edugestdz/docker-compose.prod.yml`

```yaml
# docker-compose.prod.yml
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
# EDUGEST DZ — ENVIRONNEMENT PRODUCTION
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
# Différences vs docker-compose.yml (dev) :
#   - target: production pour tous les builds
#   - APP_ENV: production, APP_DEBUG: false
#   - Pas de pgadmin, pas de redis-commander
#   - Volumes montés en lecture seule pour le code
#   - Restart: always (pas unless-stopped)
#   - Logs vers fichiers

version: '3.9'

services:

  # ── PostgreSQL ──
  postgres:
    image: postgres:16-alpine
    container_name: edugestdz_postgres
    restart: always
    environment:
      POSTGRES_DB: ${DB_DATABASE}
      POSTGRES_USER: ${DB_USERNAME}
      POSTGRES_PASSWORD: ${DB_PASSWORD}
      PGDATA: /data/postgres
      TZ: Africa/Algiers
    volumes:
      - postgres_data:/data/postgres
      - ./docker/postgres/init.sql:/docker-entrypoint-initdb.d/init.sql:ro
    expose:
      - "5432"       # pas exposé sur l'hôte en prod
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U ${DB_USERNAME} -d ${DB_DATABASE}"]
      interval: 10s
      timeout: 5s
      retries: 5
    networks:
      - edugestdz_net

  # ── Redis ──
  redis:
    image: redis:7-alpine
    container_name: edugestdz_redis
    restart: always
    command: >
      redis-server
      --appendonly yes
      --requirepass ${REDIS_PASSWORD}
      --maxmemory 512mb
      --maxmemory-policy allkeys-lru
    volumes:
      - redis_data:/data
    expose:
      - "6379"
    healthcheck:
      test: ["CMD", "redis-cli", "--pass", "${REDIS_PASSWORD}", "ping"]
      interval: 10s
      timeout: 3s
      retries: 5
    networks:
      - edugestdz_net

  # ── Backend Laravel (Production) ──
  app:
    build:
      context: ./backend
      dockerfile: Dockerfile
      target: production
    image: edugestdz/backend:latest
    container_name: edugestdz_app
    restart: always
    working_dir: /var/www/html
    environment:
      APP_ENV: production
      APP_DEBUG: "false"
      APP_URL: ${APP_URL}
      TZ: Africa/Algiers
    env_file:
      - ./backend/.env
    depends_on:
      postgres:
        condition: service_healthy
      redis:
        condition: service_healthy
    networks:
      - edugestdz_net

  # ── Frontend React (Production — build statique) ──
  frontend:
    build:
      context: ./frontend
      dockerfile: Dockerfile
      target: production
    image: edugestdz/frontend:latest
    container_name: edugestdz_frontend
    restart: always
    expose:
      - "80"
    networks:
      - edugestdz_net

  # ── Nginx (Reverse proxy + SSL) ──
  nginx:
    image: nginx:1.25-alpine
    container_name: edugestdz_nginx
    restart: always
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./nginx/conf.d/production.conf:/etc/nginx/conf.d/default.conf:ro
      - ./backend:/var/www/html:ro
      - ./backend/storage/app/public:/var/www/html/storage:ro
      - certbot_www:/var/www/certbot:ro
      - certbot_conf:/etc/letsencrypt:ro
      - nginx_logs:/var/log/nginx
    depends_on:
      - app
      - frontend
    networks:
      - edugestdz_net

  # ── Certbot (Let's Encrypt SSL) ──
  certbot:
    image: certbot/certbot:latest
    container_name: edugestdz_certbot
    volumes:
      - certbot_www:/var/www/certbot
      - certbot_conf:/etc/letsencrypt
    # Renouvellement auto toutes les 12h
    entrypoint: >
      sh -c "trap exit TERM;
             while :; do
               certbot renew --webroot -w /var/www/certbot --quiet;
               sleep 12h & wait $${!};
             done"
    networks:
      - edugestdz_net

  # ── Meilisearch ──
  meilisearch:
    image: getmeili/meilisearch:v1.8
    container_name: edugestdz_search
    restart: always
    environment:
      MEILI_MASTER_KEY: ${MEILISEARCH_KEY}
      MEILI_NO_ANALYTICS: "true"
      MEILI_ENV: production
    volumes:
      - meilisearch_data:/meili_data
    expose:
      - "7700"
    networks:
      - edugestdz_net

  # ── Queue Worker ──
  queue:
    build:
      context: ./backend
      dockerfile: Dockerfile
      target: production
    container_name: edugestdz_queue
    restart: always
    command: php artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
    env_file:
      - ./backend/.env
    depends_on:
      - app
      - redis
    networks:
      - edugestdz_net

  # ── Scheduler ──
  scheduler:
    build:
      context: ./backend
      dockerfile: Dockerfile
      target: production
    container_name: edugestdz_scheduler
    restart: always
    command: >
      sh -c "while [ true ]; do
        php artisan schedule:run --no-interaction &
        sleep 60
      done"
    env_file:
      - ./backend/.env
    depends_on:
      - app
    networks:
      - edugestdz_net

volumes:
  postgres_data:    { driver: local }
  redis_data:       { driver: local }
  meilisearch_data: { driver: local }
  nginx_logs:       { driver: local }
  certbot_www:      { driver: local }
  certbot_conf:     { driver: local }

networks:
  edugestdz_net:
    driver: bridge
    ipam:
      config:
        - subnet: 172.20.0.0/16
```

---

## ÉTAPE 3 — Config Nginx production avec SSL

**Créer :** `edugestdz/nginx/conf.d/production.conf`

```nginx
# nginx/conf.d/production.conf
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
# EduGest DZ — Nginx Production avec SSL Let's Encrypt
# Remplacer edugestdz.dz par votre vrai domaine
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

upstream php_backend {
    server app:9000;
    keepalive 32;
}

upstream react_frontend {
    server frontend:80;
}

# ── Redirect HTTP → HTTPS ──
server {
    listen 80;
    listen [::]:80;
    server_name edugestdz.dz www.edugestdz.dz;

    # Certbot challenge
    location /.well-known/acme-challenge/ {
        root /var/www/certbot;
    }

    location / {
        return 301 https://$host$request_uri;
    }
}

# ── HTTPS principal ──
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name edugestdz.dz www.edugestdz.dz;

    # ── SSL Let's Encrypt ──
    ssl_certificate     /etc/letsencrypt/live/edugestdz.dz/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/edugestdz.dz/privkey.pem;

    # ── SSL sécurisation ──
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 1d;
    ssl_stapling on;
    ssl_stapling_verify on;

    # ── Headers sécurité ──
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;

    root /var/www/html/public;
    index index.php;

    # ── Taille max upload ──
    client_max_body_size 50M;

    # ── API Backend ──
    location /api {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass php_backend;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
        fastcgi_buffer_size 128k;
        fastcgi_buffers 4 256k;

        # CORS pour l'app mobile
        add_header 'Access-Control-Allow-Origin' '*' always;
        add_header 'Access-Control-Allow-Methods' 'GET, POST, PUT, DELETE, OPTIONS' always;
        add_header 'Access-Control-Allow-Headers' 'Authorization, Content-Type, Accept' always;

        if ($request_method = 'OPTIONS') {
            return 204;
        }
    }

    # ── Frontend React (build statique) ──
    location / {
        proxy_pass http://react_frontend;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # ── Fichiers storage ──
    location /storage {
        alias /var/www/html/storage/app/public;
        expires 30d;
        access_log off;
        add_header Cache-Control "public, no-transform";
    }

    # ── Sécurité fichiers sensibles ──
    location ~ /\. {
        deny all;
    }
    location ~ /(\.env|composer\.(json|lock)|package\.json) {
        deny all;
    }

    # ── Logs ──
    access_log /var/log/nginx/edugestdz_access.log;
    error_log  /var/log/nginx/edugestdz_error.log;

    # ── Gzip ──
    gzip on;
    gzip_vary on;
    gzip_types text/plain application/json application/javascript text/css application/xml;
    gzip_min_length 1000;
}
```

---

## ÉTAPE 4 — Template .env production

**Créer :** `edugestdz/backend/.env.production.example`

```env
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
# EDUGEST DZ — Variables d'environnement PRODUCTION
# Copier en .env et remplir toutes les valeurs
# JAMAIS committer ce fichier avec de vraies valeurs
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

APP_NAME="EduGest DZ"
APP_ENV=production
APP_KEY=                        # php artisan key:generate
APP_DEBUG=false
APP_URL=https://edugestdz.dz    # ← votre domaine

# ── Base de données ──
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=edugestdz
DB_USERNAME=edugest_user
DB_PASSWORD=                    # mot de passe fort 32+ chars

# ── Redis ──
REDIS_HOST=redis
REDIS_PASSWORD=                 # mot de passe fort 32+ chars
REDIS_PORT=6379

# ── Cache & Sessions ──
CACHE_STORE=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120
QUEUE_CONNECTION=redis

# ── JWT ──
JWT_SECRET=                     # php artisan jwt:secret

# ── Mail ──
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@edugestdz.dz
MAIL_FROM_NAME="EduGest DZ"

# ── SMS Twilio ──
TWILIO_SID=
TWILIO_TOKEN=
TWILIO_FROM=

# ── Firebase (Push Notifications) ──
FIREBASE_PROJECT_ID=
FIREBASE_CREDENTIALS=           # chemin vers le fichier JSON

# ── Meilisearch ──
MEILISEARCH_HOST=http://meilisearch:7700
MEILISEARCH_KEY=                # clé maître forte

# ── Satim CIB/Dahabia ──
SATIM_SANDBOX=false             # true = test, false = production réelle
SATIM_URL=https://satim.dz/payment/rest
SATIM_TERMINAL_ID=
SATIM_MERCHANT_ID=
SATIM_PASSWORD=

# ── WhatsApp (optionnel) ──
WHATSAPP_API_TOKEN=
WHATSAPP_PHONE_NUMBER_ID=

# ── Logs ──
LOG_CHANNEL=daily
LOG_LEVEL=error
LOG_DEPRECATIONS_CHANNEL=null

# ── Timezone ──
APP_TIMEZONE=Africa/Algiers
```

---

## ÉTAPE 5 — Script de déploiement automatisé

**Créer :** `edugestdz/deploy.sh`

```bash
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
```

---

## ÉTAPE 6 — Script d'installation initiale du serveur

**Créer :** `edugestdz/server-setup.sh`

```bash
#!/bin/bash
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
# server-setup.sh — Installation initiale sur VPS
# À exécuter UNE SEULE FOIS en root sur le serveur
# Testé sur Ubuntu 22.04 LTS (OVH/Hetzner)
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
set -e

DOMAIN="edugestdz.dz"        # ← remplacer par votre domaine
EMAIL="admin@edugestdz.dz"   # ← votre email pour Let's Encrypt
APP_USER="edugest"

echo "🔧 Configuration serveur EduGest DZ"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# ── 1. Mise à jour système ──
apt-get update && apt-get upgrade -y

# ── 2. Installer Docker ──
echo "🐳 Installation Docker..."
apt-get install -y ca-certificates curl gnupg
install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg
chmod a+r /etc/apt/keyrings/docker.gpg
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] \
  https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo "$VERSION_CODENAME") stable" \
  | tee /etc/apt/sources.list.d/docker.list > /dev/null
apt-get update
apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

# ── 3. Créer l'utilisateur applicatif ──
echo "👤 Création utilisateur $APP_USER..."
useradd -m -s /bin/bash $APP_USER
usermod -aG docker $APP_USER
usermod -aG sudo $APP_USER

# ── 4. Firewall UFW ──
echo "🔒 Configuration firewall..."
ufw --force enable
ufw allow 22/tcp    # SSH
ufw allow 80/tcp    # HTTP
ufw allow 443/tcp   # HTTPS
ufw status

# ── 5. Cloner le repo ──
echo "📥 Clonage du repo..."
su - $APP_USER -c "
  git clone https://github.com/Allintelligence2024/edugest-dz.git /home/$APP_USER/edugest-dz
  cd /home/$APP_USER/edugest-dz/edugestdz
"

# ── 6. Certificat SSL Let's Encrypt (pré-déploiement) ──
echo "🔐 Certificat SSL Let's Encrypt..."
docker run --rm -p 80:80 \
  -v /home/$APP_USER/certbot/www:/var/www/certbot \
  -v /home/$APP_USER/certbot/conf:/etc/letsencrypt \
  certbot/certbot certonly \
    --standalone \
    --email $EMAIL \
    --agree-tos \
    --no-eff-email \
    -d $DOMAIN \
    -d www.$DOMAIN

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "✅ Serveur prêt !"
echo ""
echo "Prochaines étapes :"
echo "  1. su - $APP_USER"
echo "  2. cd ~/edugest-dz/edugestdz"
echo "  3. cp backend/.env.production.example backend/.env"
echo "  4. nano backend/.env  ← remplir toutes les valeurs"
echo "  5. chmod +x deploy.sh && ./deploy.sh --fresh"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
```

---

## ÉTAPE 7 — CD automatique GitHub Actions

**Créer :** `.github/workflows/deploy.yml`

```yaml
name: CD — Deploy Production

on:
  push:
    branches: [main]
    paths:
      - 'edugestdz/backend/**'
      - 'edugestdz/frontend/**'
      - 'edugestdz/docker-compose.prod.yml'
      - 'edugestdz/nginx/**'

jobs:
  deploy:
    name: Deploy to production
    runs-on: ubuntu-latest
    # ⚠️ Ne déploie QUE si le CI backend passe
    needs: []

    steps:
      - name: Checkout
        uses: actions/checkout@v4

      - name: Deploy via SSH
        uses: appleboy/ssh-action@v1.0.3
        with:
          host:     ${{ secrets.SERVER_HOST }}
          username: ${{ secrets.SERVER_USER }}
          key:      ${{ secrets.SERVER_SSH_KEY }}
          port:     ${{ secrets.SERVER_PORT || 22 }}
          script: |
            set -e
            cd ~/edugest-dz

            echo "📥 Pull des dernières modifications..."
            git pull origin main

            cd edugestdz

            echo "🚀 Déploiement..."
            chmod +x deploy.sh
            ./deploy.sh

            echo "✅ Déploiement terminé — $(date)"
```

---

## ÉTAPE 8 — Ajouter secrets GitHub pour le CD

**Sur GitHub → Settings → Secrets and variables → Actions → New repository secret :**

```
SERVER_HOST     → IP de votre serveur (ex: 51.75.123.456)
SERVER_USER     → edugest
SERVER_SSH_KEY  → contenu de la clé SSH privée (~/.ssh/id_rsa)
SERVER_PORT     → 22 (ou votre port SSH personnalisé)
```

---

## ORDRE D'EXÉCUTION DEEPSEEK

```bash
# ─── Sur le repo (local) ───

# 0. Synchroniser develop avec main
git checkout develop
git pull origin main

# 1. Modifier Dockerfile backend — ajouter stage production
modify: edugestdz/backend/Dockerfile

# 2. Créer docker-compose.prod.yml
create: edugestdz/docker-compose.prod.yml

# 3. Créer config Nginx production
create: edugestdz/nginx/conf.d/production.conf

# 4. Créer template .env production
create: edugestdz/backend/.env.production.example

# 5. Créer script deploy.sh
create: edugestdz/deploy.sh
chmod +x edugestdz/deploy.sh

# 6. Créer script server-setup.sh
create: edugestdz/server-setup.sh
chmod +x edugestdz/server-setup.sh

# 7. Créer workflow CD GitHub Actions
create: .github/workflows/deploy.yml

# 8. Commit et push
git add .
git commit -m "feat: Infrastructure production — Docker prod + Nginx SSL + deploy.sh + CD GitHub Actions"
git push origin develop

# 9. PR develop → main
# → CI backend doit passer au vert avant de merger

# ─── Sur le serveur VPS (OVH/Hetzner Ubuntu 22.04) ───

# 10. Exécuter l'installation initiale (UNE SEULE FOIS)
scp edugestdz/server-setup.sh root@<IP_SERVEUR>:/tmp/
ssh root@<IP_SERVEUR> "bash /tmp/server-setup.sh"

# 11. Se connecter et configurer le .env
ssh edugest@<IP_SERVEUR>
cd ~/edugest-dz/edugestdz
cp backend/.env.production.example backend/.env
nano backend/.env   # ← remplir toutes les valeurs

# 12. Premier déploiement
./deploy.sh --fresh

# 13. Vérifier
curl https://edugestdz.dz/api/v1/auth/login
# → Doit retourner 422 (validation) = API répond ✅
```

---

## CHECKLIST SERVEUR VPS — Avant de commencer

Avant de donner ces instructions à DeepSeek, **toi tu dois** :

- [ ] Acheter un VPS Ubuntu 22.04 (OVH, Hetzner, DigitalOcean — ~6€/mois)
  - **Recommandé Hetzner :** CX22 = 2 vCPU, 4GB RAM, 40GB SSD = 4.15€/mois
  - **Recommandé OVH :** VPS Starter = 2 vCPU, 2GB RAM = 3.99€/mois
- [ ] Acheter un nom de domaine .dz ou .com (~1000-3000 DA/an)
  - `.dz` : chez ANIC Algérie (https://www.nic.dz)
  - `.com` : chez Namecheap ou GoDaddy (~12 USD/an)
- [ ] Configurer les DNS : A record → IP du serveur
  - `edugestdz.dz` → `51.75.xxx.xxx`
  - `www.edugestdz.dz` → `51.75.xxx.xxx`
- [ ] Attendre propagation DNS (5-30 min)
- [ ] Générer une clé SSH : `ssh-keygen -t ed25519 -C "deploy@edugestdz"`
- [ ] Ajouter la clé publique sur le serveur et la clé privée dans GitHub Secrets

---

## CE QUE TU DIS À DEEPSEEK

```
Repo : https://github.com/Allintelligence2024/edugest-dz.git
Branche : develop
git checkout develop && git pull origin main

Fichier : MISSION_DEPLOY_PRODUCTION.md — 9 étapes repo + 4 étapes serveur.

Les étapes 1-9 sont à faire sur le repo (code + commit + PR).
Les étapes 10-13 seront exécutées APRÈS que j'ai acheté le serveur VPS
et configuré le domaine DNS.

Pour l'instant : exécuter uniquement les étapes 1-9.
Commit message : "feat: Infrastructure production — Docker prod + Nginx SSL + deploy.sh + CD GitHub Actions"
PR develop → main à la fin.
```
