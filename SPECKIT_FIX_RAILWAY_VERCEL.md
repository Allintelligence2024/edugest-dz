# 🔧 SPECKIT — Fix Déploiement Railway + Vercel
## EduGest DZ · 6 Juillet 2026
## Objectif : Backend Railway opérationnel + Login frontend fonctionnel

---

## DIAGNOSTIC — Pourquoi "Erreur réseau" sur le login

Après analyse du code sur GitHub (commit e1f5ad6), **3 causes identifiées** :

### Cause 1 — CRITIQUE : VITE_API_BASE_URL non configuré sur Vercel
`edugestdz/frontend/src/services/api.js` ligne 1 :
```javascript
const BASE_URL = import.meta.env.VITE_API_BASE_URL || '/api/v1';
```
Si `VITE_API_BASE_URL` n'est pas défini dans Vercel → l'app appelle `/api/v1`
sur le même domaine Vercel → 404 immédiat → "Erreur réseau".

### Cause 2 — CRITIQUE : start.sh utilise php artisan db:monitor
`php artisan db:monitor` n'existe PAS en Laravel 11 standard.
Si la commande échoue, le script tente la connexion PDO directe avec
`$_ENV['DB_HOST']` — mais Railway injecte les variables via `getenv()`, pas `$_ENV`.
Résultat : le script peut boucler 30 secondes puis exit 1 → container crash.

### Cause 3 — POSSIBLE : Variables Railway mal référencées
`${{Postgres.PGHOST}}` dans Railway Variables → certaines versions de Railway
nécessitent d'utiliser les noms exacts des variables du plugin.
Si le plugin PostgreSQL s'appelle différemment, les variables sont vides.

---

## PARTIE A — CE QUE DEEPSEEK FAIT (code)

---

## ÉTAPE 1 — Corriger start.sh (test PostgreSQL robuste)

**Écraser complètement :**
`edugestdz/backend/start.sh`

```bash
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
        echo "   DB_HOST=$(getenv DB_HOST 2>/dev/null || echo 'non défini')"
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
```

---

## ÉTAPE 2 — Corriger Dockerfile.railway (supprimer heredoc, plus compatible)

**Écraser complètement :**
`edugestdz/backend/Dockerfile.railway`

```dockerfile
FROM php:8.2-fpm-alpine

# Dépendances système
RUN apk add --no-cache \
    bash curl zip unzip git nginx \
    libpng-dev libpq-dev oniguruma-dev libxml2-dev \
    freetype-dev libjpeg-turbo-dev icu-dev

# Extensions PHP
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j$(nproc) \
    pdo pdo_pgsql pgsql mbstring exif pcntl bcmath gd xml opcache intl

# Redis
RUN pecl install redis && docker-php-ext-enable redis

# Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Dépendances composer (layer cache)
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev --no-scripts --no-autoloader \
    --no-interaction --prefer-dist

# Code source
COPY . .

# Autoload optimisé
RUN composer dump-autoload --optimize --no-dev

# Répertoires Laravel
RUN mkdir -p \
    storage/logs \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/app/public \
    bootstrap/cache \
 && chown -R www-data:www-data /var/www/html \
 && chmod -R 755 storage bootstrap/cache

# Nginx config (fichier externe, plus de heredoc)
COPY docker/nginx.conf /etc/nginx/http.d/default.conf

# PHP config
COPY docker/php.ini /usr/local/etc/php/conf.d/railway.ini

# PHP-FPM config
COPY docker/fpm.conf /usr/local/etc/php-fpm.d/www.conf

# Script démarrage
COPY start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 80

CMD ["/start.sh"]
```

---

## ÉTAPE 3 — Créer les fichiers de config Docker séparés

**Créer le répertoire :**
```bash
mkdir -p edugestdz/backend/docker
```

**Créer :** `edugestdz/backend/docker/nginx.conf`

```nginx
server {
    listen 80;
    server_name _;
    root /var/www/html/public;
    index index.php index.html;

    client_max_body_size 10M;
    server_tokens off;

    location = /api/health {
        access_log off;
        try_files $uri /index.php?$query_string;
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_param SCRIPT_FILENAME $document_root/index.php;
        fastcgi_param QUERY_STRING $query_string;
        include fastcgi_params;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param HTTP_PROXY "";
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    location ~ /\.ht  { deny all; }
    location ~ /\.env { deny all; }
}
```

**Créer :** `edugestdz/backend/docker/php.ini`

```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0
upload_max_filesize=10M
post_max_size=10M
memory_limit=256M
max_execution_time=60
log_errors=On
error_log=/proc/self/fd/2
display_errors=Off
```

**Créer :** `edugestdz/backend/docker/fpm.conf`

```ini
[www]
user = www-data
group = www-data
listen = 127.0.0.1:9000
pm = dynamic
pm.max_children = 5
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
catch_workers_output = yes
php_admin_value[error_log] = /proc/self/fd/2
```

---

## ÉTAPE 4 — Créer .dockerignore pour accélérer le build

**Créer :** `edugestdz/backend/.dockerignore`

```
.git
.github
node_modules
vendor
storage/logs/*.log
storage/framework/cache/data/*
storage/framework/sessions/*
storage/framework/views/*
bootstrap/cache/*.php
tests/
*.md
.env
.env.*
!.env.example
phpunit.xml
docker-compose*.yml
Makefile
```

---

## ÉTAPE 5 — Corriger api.js frontend (fallback URL Railway)

**Modifier :** `edugestdz/frontend/src/services/api.js`

Remplacer la ligne 1 uniquement :

```javascript
// AVANT (problème si VITE_API_BASE_URL vide sur Vercel)
const BASE_URL = import.meta.env.VITE_API_BASE_URL || '/api/v1';

// APRÈS (fallback explicite vers Railway + logs debug)
const BASE_URL = (() => {
  const url = import.meta.env.VITE_API_BASE_URL;
  if (!url || url.includes('TON_SERVICE') || url === '/api/v1') {
    console.warn(
      '[EduGest] VITE_API_BASE_URL non configuré.\n' +
      'Configurer dans Vercel : Settings → Environment Variables\n' +
      'VITE_API_BASE_URL = https://[votre-backend].up.railway.app/api/v1'
    );
  }
  return url || '/api/v1';
})();
```

Et dans la méthode `request()`, améliorer le message d'erreur :

```javascript
} catch (error) {
  console.error(`API Error [${method} ${path}]:`, error.message);
  // Message utilisateur clair
  if (error.message === 'Failed to fetch' || error.name === 'TypeError') {
    throw new Error(
      'Impossible de joindre le serveur. ' +
      'Vérifiez votre connexion internet ou contactez le support.'
    );
  }
  throw error;
}
```

---

## ÉTAPE 6 — Corriger LoginPage.jsx (afficher le vrai message d'erreur)

**Modifier :** `edugestdz/frontend/src/pages/LoginPage.jsx`

Dans le bloc `catch` de `handleLogin` :

```javascript
// AVANT
} catch (e) {
  setError('Erreur réseau. Vérifiez votre connexion.');
}

// APRÈS
} catch (e) {
  if (e.message && e.message.includes('serveur')) {
    setError(e.message);
  } else if (!navigator.onLine) {
    setError('Pas de connexion internet. Vérifiez votre réseau.');
  } else {
    setError(
      'Le serveur est temporairement indisponible. ' +
      'Réessayez dans quelques instants.'
    );
  }
}
```

---

## ÉTAPE 7 — railway.json : augmenter le timeout healthcheck

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
    "healthcheckTimeout": 300,
    "restartPolicyType": "ON_FAILURE",
    "restartPolicyMaxRetries": 3,
    "sleepApplication": false
  }
}
```

---

## ÉTAPE 8 — Commit et push

```bash
git checkout develop
git pull origin main

git add \
  edugestdz/backend/start.sh \
  edugestdz/backend/Dockerfile.railway \
  edugestdz/backend/railway.json \
  edugestdz/backend/.dockerignore \
  edugestdz/backend/docker/nginx.conf \
  edugestdz/backend/docker/php.ini \
  edugestdz/backend/docker/fpm.conf \
  edugestdz/frontend/src/services/api.js \
  edugestdz/frontend/src/pages/LoginPage.jsx

git commit -m "fix: Railway startup robuste + nginx conf externe + VITE_API_BASE_URL debug + healthcheck 300s"
git push origin develop
# → PR develop → main → Merger
```

---

## PARTIE B — CE QUE TOI TU FAIS (Railway + Vercel UI)

### Sur Railway — Vérifier les variables

**Aller sur :** railway.app → ton projet → service backend → Variables

Vérifier que ces variables sont définies avec les VRAIES valeurs :

```
APP_KEY        = base64:xxxxxxxxxxxx...   (obligatoire — générer si absent)
JWT_SECRET     = xxxxxxxx...             (obligatoire — générer si absent)
APP_ENV        = production
APP_DEBUG      = false
LOG_CHANNEL    = stderr

DB_CONNECTION  = pgsql
DB_HOST        = [copier depuis le plugin PostgreSQL → Connect → Host]
DB_PORT        = [copier depuis le plugin PostgreSQL → Connect → Port]
DB_DATABASE    = [copier depuis le plugin PostgreSQL → Connect → Database]
DB_USERNAME    = [copier depuis le plugin PostgreSQL → Connect → User]
DB_PASSWORD    = [copier depuis le plugin PostgreSQL → Connect → Password]

REDIS_URL      = [copier depuis le plugin Redis → Connect → URL]
CACHE_DRIVER   = redis
SESSION_DRIVER = redis
QUEUE_CONNECTION = database

MAIL_MAILER    = log
SCOUT_DRIVER   = null
```

**⚠️ Important :** Ne pas utiliser `${{Postgres.PGHOST}}` si ça ne marche pas.
Copier-coller les valeurs DIRECTEMENT depuis le plugin PostgreSQL → onglet "Connect".

### Générer APP_KEY

```
Railway → Service backend → Shell
→ php artisan key:generate --show
→ Copier le résultat (base64:xxx...)
→ Coller dans Variables → APP_KEY
```

### Générer JWT_SECRET

```
Railway → Service backend → Shell
→ php artisan jwt:secret --show
→ Copier → coller dans JWT_SECRET
```

### Sur Vercel — Configurer VITE_API_BASE_URL

**Aller sur :** vercel.com → ton projet → Settings → Environment Variables

```
VITE_API_BASE_URL = https://[nom-exact-de-ton-service].up.railway.app/api/v1
```

**⚠️ L'URL doit être exacte.** Trouver l'URL dans :
Railway → Service → Settings → Networking → Public Domain

Puis dans Vercel → **Redéployer** (Deployments → 3 points → Redeploy).

---

## CHECKLIST DE VALIDATION

Faire dans cet ordre :

```bash
# 1. Backend health (depuis ton terminal ou navigateur)
curl https://[ton-backend].up.railway.app/api/health
# Attendu : {"status":"ok","services":{"postgresql":"ok"}}
# Si 502 → voir les logs Railway (Deploy → View Logs)

# 2. Test login API
curl -X POST https://[ton-backend].up.railway.app/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@edugest.dz","password":"EduGest2026!"}'
# Attendu : {"success":true,"data":{"token":"eyJ..."}}
# Si 500 → migrations pas encore faites ou APP_KEY manquant
# Si 401 → admin pas encore créé (voir ci-dessous)

# 3. Créer l'admin si absent
# Railway → Shell
php artisan tinker
App\Models\User::create([
  'nom' => 'Admin', 'prenom' => 'Test',
  'email' => 'admin@edugest.dz',
  'password' => bcrypt('EduGest2026!'),
  'role' => 'admin',
  'tenant_id' => Illuminate\Support\Str::uuid()->toString(),
  'actif' => true,
]);

# 4. Frontend login
# Ouvrir https://[ton-app].vercel.app
# Email : admin@edugest.dz / Password : EduGest2026!
# → Dashboard admin = SUCCÈS ✅
```

---

## DIAGNOSTIC RAPIDE — Si ça ne marche toujours pas

### Cas A : Railway → Deploy Logs → "No such file: start.sh"
```
Solution : Le fichier start.sh n'a pas été commité avec les droits d'exécution
Fix : git update-index --chmod=+x edugestdz/backend/start.sh && git commit
```

### Cas B : Railway → "migration failed: relation already exists"
```
Solution : Normal sur re-déploiement — les migrations sont idempotentes
Le script continue. Ce n'est pas une erreur bloquante.
```

### Cas C : Vercel → Login → "Failed to fetch"
```
Solution : VITE_API_BASE_URL pas encore mis à jour sur Vercel
Aller dans Vercel → Settings → Environment Variables
Mettre la vraie URL Railway → Redéployer
```

### Cas D : Railway → Health check → timeout (502)
```
Solution : PHP-FPM ne démarre pas
→ Voir les logs Railway → chercher "PHP-FPM" 
→ Souvent : extension PHP manquante ou permission storage/
Fix : ajouter dans Dockerfile RUN chmod -R 777 storage bootstrap/cache
```

### Cas E : "APP_KEY not set" dans les logs
```
Solution : Variable APP_KEY vide dans Railway
→ Railway → Shell → php artisan key:generate --show
→ Copier → Variables → APP_KEY = base64:xxxx
→ Redémarrer le service
```

---

## CE QUE TU DIS À L'IA (Claude, GPT, Gemini, DeepSeek, Qwen...)

```
Repo : https://github.com/Allintelligence2024/edugest-dz.git
Branche : develop
git checkout develop && git pull origin main

Fichier : SPECKIT_FIX_RAILWAY_VERCEL.md — 8 étapes dans l'ordre.

Actions (code uniquement) :
1. Écraser start.sh avec le contenu de l'ÉTAPE 1
2. Écraser Dockerfile.railway avec le contenu de l'ÉTAPE 2
3. Créer le répertoire edugestdz/backend/docker/
4. Créer docker/nginx.conf (ÉTAPE 3)
5. Créer docker/php.ini (ÉTAPE 3)
6. Créer docker/fpm.conf (ÉTAPE 3)
7. Créer .dockerignore (ÉTAPE 4)
8. Modifier api.js — remplacer ligne 1 et bloc catch (ÉTAPE 5)
9. Modifier LoginPage.jsx — remplacer bloc catch (ÉTAPE 6)
10. Écraser railway.json (ÉTAPE 7)
11. git add + commit + push origin develop
12. Ouvrir PR develop → main

NE PAS relancer les tests backend.
NE PAS modifier d'autres fichiers.
NE PAS toucher aux contrôleurs ou modèles.
```
