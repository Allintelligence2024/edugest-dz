# 🚀 MISSION DEEPSEEK — Déploiement Production Railway + Vercel
## EduGest DZ · Déploiement complet avec gestion d'erreurs
## 5 Juillet 2026 — Tous les cas d'erreur couverts

---

## ARCHITECTURE FINALE

```
┌──────────────────────────────────────────────────────────────┐
│  INTERNET (utilisateur)                                       │
└──────────┬──────────────────────────────┬────────────────────┘
           │                              │
           ▼                              ▼
┌──────────────────┐           ┌──────────────────────┐
│   VERCEL         │           │   RAILWAY            │
│   Frontend React │ ←API──→  │   Backend Laravel    │
│   (GRATUIT)      │           │   (GRATUIT 500h/mois)│
│   edugest.vercel │           │   edugest.railway    │
└──────────────────┘           ├──────────────────────┤
                               │   PostgreSQL 16      │
                               │   Redis 7            │
                               │   (plugins Railway)  │
                               └──────────────────────┘
```

---

## PARTIE A — PRÉPARATION BACKEND (DeepSeek fait)

---

## ÉTAPE 1 — Créer le script de démarrage robuste

**Créer :** `edugestdz/backend/start.sh`

```bash
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
```

```bash
# Rendre exécutable
chmod +x edugestdz/backend/start.sh
```

---

## ÉTAPE 2 — Dockerfile.railway final (robuste)

**Écraser :** `edugestdz/backend/Dockerfile.railway`

```dockerfile
# ══════════════════════════════════════════════════════════════
# EduGest DZ — Dockerfile pour Railway
# PHP 8.2 + Nginx + PostgreSQL + Redis
# ══════════════════════════════════════════════════════════════

FROM php:8.2-fpm-alpine

# ── Dépendances système ────────────────────────────────────────
RUN apk add --no-cache \
    bash curl zip unzip git nginx supervisor \
    libpng-dev libpq-dev oniguruma-dev libxml2-dev \
    freetype-dev libjpeg-turbo-dev icu-dev

# ── Extensions PHP ─────────────────────────────────────────────
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j$(nproc) \
    pdo pdo_pgsql pgsql \
    mbstring exif pcntl bcmath \
    gd xml opcache intl

# Redis
RUN pecl install redis \
 && docker-php-ext-enable redis

# ── Composer ───────────────────────────────────────────────────
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# ── Répertoire de travail ──────────────────────────────────────
WORKDIR /var/www/html

# ── Installer les dépendances (cache Docker layer) ─────────────
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader=false

# ── Copier le code source ──────────────────────────────────────
COPY . .

# ── Finaliser Composer ─────────────────────────────────────────
RUN composer dump-autoload --optimize --no-dev

# ── Créer les répertoires nécessaires ─────────────────────────
RUN mkdir -p \
    storage/logs \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/app/public \
    bootstrap/cache \
 && chown -R www-data:www-data /var/www/html \
 && chmod -R 755 storage bootstrap/cache

# ── Config Nginx ───────────────────────────────────────────────
COPY <<'NGINX_CONF' /etc/nginx/http.d/default.conf
server {
    listen 80;
    server_name _;
    root /var/www/html/public;
    index index.php index.html;

    client_max_body_size 10M;
    client_body_timeout 60s;
    send_timeout 60s;

    # Sécurité
    server_tokens off;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "DENY" always;

    # Health check (pas de log pour éviter le bruit)
    location = /api/health {
        access_log off;
        try_files $uri $uri/ /index.php?$query_string;
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_param SCRIPT_FILENAME $document_root/index.php;
        include fastcgi_params;
    }

    # Application principale
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP-FPM
    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param HTTP_PROXY "";
        include fastcgi_params;
        fastcgi_read_timeout 300;
        fastcgi_connect_timeout 60;
        fastcgi_send_timeout 300;
    }

    # Fichiers statiques avec cache long
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    # Bloquer les fichiers cachés
    location ~ /\.ht { deny all; }
    location ~ /\.env { deny all; }
}
NGINX_CONF

# ── Config PHP optimisée ───────────────────────────────────────
COPY <<'PHP_INI' /usr/local/etc/php/conf.d/railway.ini
; Performance
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
opcache.revalidate_freq=0
opcache.fast_shutdown=1

; Limites
upload_max_filesize=10M
post_max_size=10M
memory_limit=256M
max_execution_time=60
max_input_time=60

; Logs
log_errors=On
error_log=/proc/self/fd/2
display_errors=Off
PHP_INI

# ── Config PHP-FPM ─────────────────────────────────────────────
COPY <<'FPM_CONF' /usr/local/etc/php-fpm.d/www.conf
[www]
user = www-data
group = www-data
listen = 127.0.0.1:9000
pm = dynamic
pm.max_children = 10
pm.start_servers = 3
pm.min_spare_servers = 2
pm.max_spare_servers = 5
pm.max_requests = 500
catch_workers_output = yes
php_admin_value[error_log] = /proc/self/fd/2
FPM_CONF

# ── Script de démarrage ────────────────────────────────────────
COPY start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 80

# Healthcheck Docker (Railway l'utilise aussi)
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD curl -f http://localhost/api/health || exit 1

CMD ["/start.sh"]
```

---

## ÉTAPE 3 — railway.json final

**Écraser :** `edugestdz/backend/railway.json`

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
    "restartPolicyMaxRetries": 5,
    "sleepApplication": false
  }
}
```

---

## ÉTAPE 4 — Variables d'environnement Railway (fichier de référence)

**Créer :** `edugestdz/backend/.env.railway.example`

```dotenv
# ══════════════════════════════════════════════════════════
# EduGest DZ — Variables Railway
# Copier ces valeurs dans Railway → Variables
# ══════════════════════════════════════════════════════════

# ── Application ───────────────────────────────────────────
APP_NAME="EduGest DZ"
APP_ENV=production
APP_DEBUG=false
APP_VERSION=1.0.0
# APP_KEY → Railway génère via: php artisan key:generate --show
APP_KEY=base64:REMPLACER_PAR_LA_VRAIE_CLE
# APP_URL → URL de ton service Railway (ex: https://edugest-backend.up.railway.app)
APP_URL=https://TON_SERVICE.up.railway.app

# ── Base de données PostgreSQL ────────────────────────────
# Railway injecte ces variables automatiquement via le plugin PostgreSQL
# Tu peux les référencer comme: ${{Postgres.PGHOST}}
DB_CONNECTION=pgsql
DB_HOST=${{Postgres.PGHOST}}
DB_PORT=${{Postgres.PGPORT}}
DB_DATABASE=${{Postgres.PGDATABASE}}
DB_USERNAME=${{Postgres.PGUSER}}
DB_PASSWORD=${{Postgres.PGPASSWORD}}

# ── Redis ─────────────────────────────────────────────────
# Railway injecte automatiquement via le plugin Redis
REDIS_URL=${{Redis.REDIS_URL}}
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=database

# ── JWT ───────────────────────────────────────────────────
# Générer: openssl rand -base64 64
JWT_SECRET=REMPLACER_PAR_UN_SECRET_LONG_ET_ALEATOIRE
JWT_TTL=60
JWT_REFRESH_TTL=20160

# ── Logs ──────────────────────────────────────────────────
LOG_CHANNEL=stderr
LOG_LEVEL=warning

# ── CORS (URL Vercel frontend) ────────────────────────────
FRONTEND_URL=https://TON_APP.vercel.app

# ── Swagger ───────────────────────────────────────────────
L5_SWAGGER_GENERATE_ALWAYS=false
L5_SWAGGER_CONST_HOST=https://TON_SERVICE.up.railway.app

# ── Twilio SMS (optionnel pour les tests) ─────────────────
TWILIO_SID=
TWILIO_TOKEN=
TWILIO_FROM=+1XXXXXXXXXX

# ── Firebase Push (optionnel pour les tests) ──────────────
FIREBASE_SERVER_KEY=

# ── Satim CIB/Dahabia (sandbox) ───────────────────────────
SATIM_MERCHANT_LOGIN=
SATIM_MERCHANT_PASSWORD=
SATIM_TERMINAL_ID=
SATIM_BASE_URL=https://test.satim.dz/payment/rest

# ── Mail (log = pas d'envoi réel) ────────────────────────
MAIL_MAILER=log

# ── Meilisearch (désactivé en prod Railway) ───────────────
SCOUT_DRIVER=null
```

---

## ÉTAPE 5 — Corriger cors.php pour Vercel

**Modifier :** `edugestdz/backend/config/cors.php`

```php
<?php

return [
    'paths'                    => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods'          => ['*'],
    'allowed_origins'          => array_filter([
        'http://localhost:5173',
        'http://localhost:3000',
        'http://127.0.0.1:5173',
        env('FRONTEND_URL', ''),
    ]),
    'allowed_origins_patterns' => [
        '#^https://.*\.vercel\.app$#',
        '#^https://edugest.*\.vercel\.app$#',
    ],
    'allowed_headers'          => ['*'],
    'exposed_headers'          => ['X-Query-Count', 'X-Response-Time'],
    'max_age'                  => 86400,
    'supports_credentials'     => false,
];
```

---

## ÉTAPE 6 — Préparer le frontend Vercel

**Créer :** `edugestdz/frontend/vercel.json`

```json
{
  "framework": "vite",
  "buildCommand": "npm run build",
  "outputDirectory": "dist",
  "installCommand": "npm install",
  "rewrites": [
    { "source": "/((?!api).*)", "destination": "/index.html" }
  ],
  "headers": [
    {
      "source": "/assets/(.*)",
      "headers": [
        { "key": "Cache-Control", "value": "public, max-age=31536000, immutable" }
      ]
    },
    {
      "source": "/(.*)",
      "headers": [
        { "key": "X-Content-Type-Options", "value": "nosniff" },
        { "key": "X-Frame-Options",        "value": "DENY" },
        { "key": "Referrer-Policy",        "value": "strict-origin-when-cross-origin" }
      ]
    }
  ],
  "env": {
    "VITE_APP_NAME": "EduGest DZ"
  }
}
```

**Créer :** `edugestdz/frontend/.env.production`

```dotenv
# Remplacer par l'URL Railway réelle après déploiement
VITE_API_BASE_URL=https://TON_SERVICE.up.railway.app/api/v1
VITE_APP_NAME=EduGest DZ
VITE_APP_ENV=production
```

**Créer :** `edugestdz/frontend/.env.development`

```dotenv
VITE_API_BASE_URL=http://localhost/api/v1
VITE_APP_NAME=EduGest DZ (Dev)
VITE_APP_ENV=development
```

---

## ÉTAPE 7 — Vite config pour support SPA

**Modifier :** `edugestdz/frontend/vite.config.js`

S'assurer que la config supporte le build production :

```javascript
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
  build: {
    outDir: 'dist',
    sourcemap: false,
    rollupOptions: {
      output: {
        manualChunks: {
          vendor: ['react', 'react-dom', 'react-router-dom'],
        },
      },
    },
  },
  server: {
    proxy: {
      '/api': {
        target: 'http://localhost',
        changeOrigin: true,
      },
    },
  },
})
```

---

## ÉTAPE 8 — Commit et push

```bash
# Résoudre les conflits d'abord
git add edugestdz/backend/Dockerfile.railway
git add edugestdz/backend/railway.json
git add edugestdz/backend/start.sh
git add edugestdz/backend/.env.railway.example
git add edugestdz/backend/config/cors.php
git add edugestdz/frontend/vercel.json
git add edugestdz/frontend/.env.production
git add edugestdz/frontend/.env.development
git add edugestdz/frontend/vite.config.js

git commit -m "feat: Fix conflits merge + Config déploiement Railway+Vercel complète avec gestion erreurs"
git push origin develop
# → PR develop → main → Merger
```

---

## PARTIE B — DÉPLOIEMENT (TOI sur Railway et Vercel)

---

## RAILWAY — Backend (15 min)

### 1. Créer le projet

```
1. Aller sur https://railway.app
2. "New Project" → "Deploy from GitHub repo"
3. Sélectionner : Allintelligence2024/edugest-dz
4. ⚠️ Root Directory : edugestdz/backend
   (IMPORTANT — sans ça Railway prend la racine du repo)
5. Railway détecte Dockerfile.railway automatiquement
```

### 2. Ajouter PostgreSQL

```
Dans le projet Railway :
→ "+ New" → "Database" → "Add PostgreSQL"
→ Railway crée automatiquement :
   PGHOST, PGPORT, PGDATABASE, PGUSER, PGPASSWORD
→ Elles sont injectées automatiquement dans ton service
```

### 3. Ajouter Redis

```
→ "+ New" → "Database" → "Add Redis"
→ Railway crée : REDIS_URL
→ Injectée automatiquement
```

### 4. Configurer les variables d'environnement

```
Dans Railway → ton service backend → Variables → Add Variable

Copier-coller chaque ligne :

APP_NAME            = EduGest DZ
APP_ENV             = production
APP_DEBUG           = false
APP_KEY             = (laisser vide — généré au démarrage)
JWT_SECRET          = (générer: openssl rand -base64 64)

DB_CONNECTION       = pgsql
DB_HOST             = ${{Postgres.PGHOST}}
DB_PORT             = ${{Postgres.PGPORT}}
DB_DATABASE         = ${{Postgres.PGDATABASE}}
DB_USERNAME         = ${{Postgres.PGUSER}}
DB_PASSWORD         = ${{Postgres.PGPASSWORD}}

REDIS_URL           = ${{Redis.REDIS_URL}}
CACHE_DRIVER        = redis
SESSION_DRIVER      = redis
QUEUE_CONNECTION    = database

LOG_CHANNEL         = stderr
LOG_LEVEL           = warning

MAIL_MAILER         = log
SCOUT_DRIVER        = null
L5_SWAGGER_GENERATE_ALWAYS = false
```

### 5. Obtenir l'URL Railway

```
Après le premier build :
Railway → Settings → Networking → Public Domain
→ Copier l'URL : https://[nom].up.railway.app

Ajouter la variable :
APP_URL     = https://[nom].up.railway.app
FRONTEND_URL = https://[ton-app].vercel.app  (à remplir après Vercel)
```

### 6. Tester le backend

```bash
# Test health check
curl https://[nom].up.railway.app/api/health
# Attendu : {"status":"ok","services":{"postgresql":"ok","redis":"ok"}}

# Test login
curl -X POST https://[nom].up.railway.app/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@edugest.dz","password":"EduGest2026!"}'
# Attendu : {"success":true,"data":{"token":"eyJ..."}}
```

### 7. Créer le premier admin

```
Railway → ton service → Shell (ou terminal Railway)

php artisan tinker

>>> App\Models\User::create([
...   'nom'       => 'Administrateur',
...   'prenom'    => 'Test',
...   'email'     => 'admin@edugest.dz',
...   'password'  => bcrypt('EduGest2026!'),
...   'role'      => 'admin',
...   'tenant_id' => Illuminate\Support\Str::uuid()->toString(),
...   'actif'     => true,
... ]);
```

---

## VERCEL — Frontend (5 min)

### 1. Créer le projet

```
1. Aller sur https://vercel.com
2. "Add New Project" → "Import Git Repository"
3. Sélectionner : Allintelligence2024/edugest-dz
4. Root Directory : edugestdz/frontend
5. Framework Preset : Vite (auto-détecté)
6. Build Command : npm run build
7. Output Directory : dist
```

### 2. Variables d'environnement Vercel

```
Settings → Environment Variables :

VITE_API_BASE_URL = https://[nom].up.railway.app/api/v1
VITE_APP_NAME     = EduGest DZ
VITE_APP_ENV      = production
```

### 3. Déployer

```
Cliquer "Deploy"
Build time : ~2 minutes

URL : https://[ton-app].vercel.app
→ Ouvrir → Page login EduGest DZ ✅
```

### 4. Mettre à jour FRONTEND_URL dans Railway

```
Railway → Variables → Modifier :
FRONTEND_URL = https://[ton-app].vercel.app
L5_SWAGGER_CONST_HOST = https://[nom].up.railway.app
```

---

## PROBLÈMES FRÉQUENTS ET SOLUTIONS

### ❌ Problème 1 — Build Docker échoue (dépendances)

```
Erreur : "composer install failed"
Solution :
→ Railway → Logs → Voir la ligne exacte
→ Souvent : package PHP manquant ou version incompatible
→ Fix : ajouter le package manquant dans le Dockerfile RUN apk add...
```

### ❌ Problème 2 — "Connection refused" PostgreSQL

```
Erreur : "SQLSTATE[08006] Connection refused"
Solution :
→ Vérifier que les variables ${{Postgres.PGHOST}} sont bien définies
→ Railway → Variables → Chercher PGHOST
→ Si absent : supprimer et re-ajouter le plugin PostgreSQL
→ Redémarrer le service backend
```

### ❌ Problème 3 — Health check timeout (app ne démarre pas en 120s)

```
Erreur : "Health check failed"
Solution :
→ Railway → Logs → Voir pourquoi l'app ne répond pas
→ Souvent : migrations qui bloquent ou APP_KEY manquant
→ Fix temporaire : mettre healthcheckTimeout à 300 dans railway.json
→ Ajouter APP_KEY manuellement dans les variables Railway
```

### ❌ Problème 4 — CORS bloqué (frontend ne peut pas appeler l'API)

```
Erreur dans le navigateur : "Access-Control-Allow-Origin"
Solution :
→ Vérifier FRONTEND_URL dans Railway = URL exacte de Vercel
→ Vérifier cors.php → allowed_origins contient bien l'URL Vercel
→ Redéployer le backend Railway
```

### ❌ Problème 5 — Page blanche sur Vercel (SPA routing)

```
Erreur : 404 sur actualisation de page
Solution :
→ Vérifier que vercel.json contient bien les "rewrites"
→ Si absent : ajouter { "rewrites": [{"source":"/(.*)", "destination":"/index.html"}] }
→ Redéployer Vercel
```

### ❌ Problème 6 — Redis non connecté

```
Erreur : "Connection refused 127.0.0.1:6379"
Solution :
→ Vérifier REDIS_URL est bien défini = ${{Redis.REDIS_URL}}
→ Si Redis plugin absent : Railway → + New → Database → Redis
→ CACHE_DRIVER=array comme fallback temporaire
```

### ❌ Problème 7 — Migrations échouent ("table already exists")

```
Erreur : "table already exists"
Solution : normal si c'est un re-déploiement
→ Les migrations sont idempotentes (IF NOT EXISTS)
→ Ignorer cette erreur
→ Si bloquant : php artisan migrate --pretend pour voir ce qui se passe
```

### ❌ Problème 8 — "Storage link" manquant (images pas affichées)

```
Erreur : images 404
Solution :
→ Ajouter dans start.sh avant nginx :
   php artisan storage:link 2>/dev/null || true
```

### ❌ Problème 9 — Plan gratuit Railway épuisé (500h/mois)

```
Symptôme : service arrêté après ~21 jours
Solution :
→ Passer au plan Hobby (5$/mois) ou Pro
→ OU : utiliser un VPS OVH/Hetzner (4-5€/mois) avec le docker-compose.prod.yml existant
→ Le deploy.sh du projet gère ça automatiquement sur VPS
```

### ❌ Problème 10 — Variables VITE_ non reconnues (frontend)

```
Erreur : "VITE_API_BASE_URL is undefined"
Solution :
→ Toutes les variables frontend doivent commencer par VITE_
→ Vérifier dans Vercel → Settings → Environment Variables
→ Redéployer après ajout de variables
→ En local : .env.local avec VITE_API_BASE_URL=http://localhost/api/v1
```

---

## CHECKLIST FINALE — Validation du déploiement

```bash
# 1. Backend health ✅
curl https://[backend].up.railway.app/api/health
# → {"status":"ok"}

# 2. Login API ✅
curl -X POST https://[backend].up.railway.app/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@edugest.dz","password":"EduGest2026!"}'
# → {"success":true,"data":{"token":"eyJ..."}}

# 3. Swagger UI ✅
# Ouvrir: https://[backend].up.railway.app/api/documentation
# → Interface Swagger complète

# 4. Frontend ✅
# Ouvrir: https://[ton-app].vercel.app
# → Page login EduGest DZ

# 5. Login frontend ✅
# Email: admin@edugest.dz
# Password: EduGest2026!
# → Dashboard admin

# 6. Test API depuis frontend ✅
# Dashboard → Voir les KPIs chargés depuis Railway
# → Données réelles de la BDD PostgreSQL
```

---

## ORDRE D'EXÉCUTION DEEPSEEK (code uniquement)

```bash
# 0. Synchroniser + résoudre conflits
git checkout develop
git pull origin main
# → Conflits sur Dockerfile.railway et railway.json

# 1. Écraser les fichiers en conflit
# (contenu dans ce fichier mission)
overwrite: edugestdz/backend/Dockerfile.railway
overwrite: edugestdz/backend/railway.json

# 2. Créer les nouveaux fichiers
create: edugestdz/backend/start.sh
create: edugestdz/backend/.env.railway.example
modify: edugestdz/backend/config/cors.php
create: edugestdz/frontend/vercel.json
create: edugestdz/frontend/.env.production
create: edugestdz/frontend/.env.development
modify: edugestdz/frontend/vite.config.js  (si modif nécessaire)

# 3. Marquer les conflits comme résolus
git add edugestdz/backend/Dockerfile.railway
git add edugestdz/backend/railway.json
git add edugestdz/backend/start.sh
git add edugestdz/backend/.env.railway.example
git add edugestdz/backend/config/cors.php
git add edugestdz/frontend/

# 4. Commit + push
git commit -m "feat: Fix conflits + Railway/Vercel config robuste avec gestion erreurs complète"
git push origin develop
```

---

## CE QUE TU DIS À DEEPSEEK

```
Repo : https://github.com/Allintelligence2024/edugest-dz.git
Branche : develop

PROBLÈME : Conflits de merge sur Dockerfile.railway et railway.json

Fichier : MISSION_DEPLOY_RAILWAY_VERCEL_COMPLET.md

Actions DeepSeek (code uniquement) :
1. git checkout develop && git pull origin main
2. Écraser Dockerfile.railway avec le contenu de ce fichier (ÉTAPE 2)
3. Écraser railway.json avec le contenu de ce fichier (ÉTAPE 3)
4. Créer start.sh (ÉTAPE 1) + chmod +x
5. Créer .env.railway.example (ÉTAPE 4)
6. Modifier config/cors.php (ÉTAPE 5)
7. Créer frontend/vercel.json + .env.production + .env.development (ÉTAPES 6-7)
8. git add tous les fichiers modifiés
9. git commit -m "feat: Fix conflits + Railway/Vercel config robuste"
10. git push origin develop → PR develop → main

NE PAS relancer les tests — juste corriger les conflits et pusher.
NE PAS modifier d'autres fichiers.
```
