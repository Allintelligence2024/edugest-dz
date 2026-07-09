# 🤖 MISSION DEEPSEEK — Déploiement Test : Vercel + Railway
## EduGest DZ · Branche : main · 4 Juillet 2026
## Objectif : logiciel accessible sur le web en 30 minutes, gratuit

---

## ARCHITECTURE DU DÉPLOIEMENT TEST

```
┌─────────────────────────────────────────────────────────┐
│  FRONTEND React                                          │
│  Vercel (gratuit)                                        │
│  URL : edugest-dz.vercel.app                            │
└──────────────────┬──────────────────────────────────────┘
                   │ API calls
                   ▼
┌─────────────────────────────────────────────────────────┐
│  BACKEND Laravel                                         │
│  Railway (gratuit 500h/mois)                            │
│  URL : edugest-backend.up.railway.app                   │
├─────────────────────────────────────────────────────────┤
│  PostgreSQL 16   │  Redis 7                              │
│  Railway plugin  │  Railway plugin                       │
│  (gratuit)       │  (gratuit)                            │
└─────────────────────────────────────────────────────────┘
```

### Pourquoi pas Vercel pour le backend ?
Vercel est serverless — Laravel a besoin d'un serveur persistant (queues, schedulers, sessions).
Railway = serveur Docker = parfait pour Laravel.

---

## ÉTAPE 0 — Prérequis (5 min — toi)

```
1. Créer un compte Railway : https://railway.app (GitHub login)
2. Créer un compte Vercel  : https://vercel.com  (GitHub login)
3. S'assurer que le repo GitHub est public ou lié aux deux comptes
```

---

## ÉTAPE 1 — Préparer le backend pour Railway

### 1.1 Créer railway.json

**Créer :** `edugestdz/backend/railway.json`

```json
{
  "$schema": "https://railway.app/railway.schema.json",
  "build": {
    "builder": "DOCKERFILE",
    "dockerfilePath": "Dockerfile.railway"
  },
  "deploy": {
    "startCommand": "php artisan migrate --force && php artisan db:seed --class=CurriculumAlgerienSeeder --force && php artisan l5-swagger:generate && php artisan optimize && php-fpm -D && nginx -g 'daemon off;'",
    "healthcheckPath": "/api/health",
    "healthcheckTimeout": 60,
    "restartPolicyType": "ON_FAILURE",
    "restartPolicyMaxRetries": 3
  }
}
```

### 1.2 Créer Dockerfile.railway

**Créer :** `edugestdz/backend/Dockerfile.railway`

```dockerfile
# ── Build stage ──────────────────────────────────────────────────────────────
FROM php:8.2-fpm-alpine AS build

RUN apk add --no-cache \
    git curl zip unzip libpng-dev libpq-dev \
    oniguruma-dev libxml2-dev nginx supervisor

RUN docker-php-ext-install \
    pdo pdo_pgsql pgsql mbstring exif pcntl bcmath gd xml opcache

# Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copier les fichiers
COPY . .

# Installer les dépendances (sans dev)
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# ── Nginx config pour Railway ─────────────────────────────────────────────────
RUN echo 'server { \
    listen 80; \
    root /var/www/html/public; \
    index index.php; \
    location / { try_files $uri $uri/ /index.php?$query_string; } \
    location ~ \.php$ { \
        fastcgi_pass 127.0.0.1:9000; \
        fastcgi_index index.php; \
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; \
        include fastcgi_params; \
    } \
    location /api/health { access_log off; } \
}' > /etc/nginx/http.d/default.conf

# PHP config optimisée
RUN echo 'opcache.enable=1 \n\
opcache.memory_consumption=256 \n\
opcache.max_accelerated_files=20000 \n\
upload_max_filesize=10M \n\
post_max_size=10M \n\
memory_limit=256M' >> /usr/local/etc/php/conf.d/railway.ini

EXPOSE 80

CMD ["sh", "-c", "php artisan key:generate --force; php artisan jwt:secret --force; php artisan migrate --force; php-fpm -D; nginx -g 'daemon off;'"]
```

### 1.3 Créer .env.railway

**Créer :** `edugestdz/backend/.env.railway`

```dotenv
APP_NAME="EduGest DZ"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://edugest-backend.up.railway.app

# Ces valeurs seront injectées par Railway automatiquement
# via les variables d'environnement du projet
DB_CONNECTION=pgsql
DB_HOST=${PGHOST}
DB_PORT=${PGPORT}
DB_DATABASE=${PGDATABASE}
DB_USERNAME=${PGUSER}
DB_PASSWORD=${PGPASSWORD}

REDIS_URL=${REDIS_URL}
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=database

# JWT — Railway injectera APP_KEY et JWT_SECRET
# via les variables d'environnement

# Twilio SMS
TWILIO_SID=
TWILIO_TOKEN=
TWILIO_FROM=

# Firebase Push
FIREBASE_SERVER_KEY=

# Satim CIB (sandbox pour test)
SATIM_MERCHANT_LOGIN=
SATIM_MERCHANT_PASSWORD=
SATIM_TERMINAL_ID=
SATIM_BASE_URL=https://test.satim.dz/payment/rest

# Swagger
L5_SWAGGER_GENERATE_ALWAYS=false
L5_SWAGGER_CONST_HOST=https://edugest-backend.up.railway.app

# Logs
LOG_CHANNEL=stderr
LOG_LEVEL=warning

# Mail (optionnel pour test)
MAIL_MAILER=log
```

### 1.4 Modifier cors.php pour autoriser Vercel

**Modifier :** `edugestdz/backend/config/cors.php`

```php
<?php

return [
    'paths'                    => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods'          => ['*'],
    'allowed_origins'          => [
        'http://localhost:5173',          // dev local
        'http://localhost:3000',          // dev local
        'https://edugest-dz.vercel.app',  // Vercel prod
        'https://*.vercel.app',           // Vercel previews
        env('FRONTEND_URL', ''),          // URL custom configurable
    ],
    'allowed_origins_patterns' => ['#^https://edugest-dz.*\.vercel\.app$#'],
    'allowed_headers'          => ['*'],
    'exposed_headers'          => [
        'X-Query-Count', 'X-Response-Time', // QueryMonitor headers
    ],
    'max_age'                  => 0,
    'supports_credentials'     => false,
];
```

---

## ÉTAPE 2 — Préparer le frontend pour Vercel

### 2.1 Créer vercel.json

**Créer :** `edugestdz/frontend/vercel.json`

```json
{
  "framework": "vite",
  "buildCommand": "npm run build",
  "outputDirectory": "dist",
  "installCommand": "npm install",
  "rewrites": [
    { "source": "/(.*)", "destination": "/index.html" }
  ],
  "headers": [
    {
      "source": "/assets/(.*)",
      "headers": [
        { "key": "Cache-Control", "value": "public, max-age=31536000, immutable" }
      ]
    }
  ]
}
```

### 2.2 Créer .env.production pour le frontend

**Créer :** `edugestdz/frontend/.env.production`

```dotenv
VITE_API_BASE_URL=https://edugest-backend.up.railway.app/api/v1
VITE_APP_NAME=EduGest DZ
VITE_APP_ENV=production
```

**Créer :** `edugestdz/frontend/.env.development`

```dotenv
VITE_API_BASE_URL=http://localhost/api/v1
VITE_APP_NAME=EduGest DZ (Dev)
VITE_APP_ENV=development
```

### 2.3 Créer un fichier api.js centralisé dans le frontend

**Créer :** `edugestdz/frontend/src/services/api.js`

```javascript
// URL de base dynamique selon l'environnement
const BASE_URL = import.meta.env.VITE_API_BASE_URL || '/api/v1';

/**
 * Client API centralisé — gère automatiquement :
 * - L'injection du token JWT
 * - Le header X-Tenant-ID
 * - Les erreurs 401 (redirection login)
 * - Les erreurs réseau
 */
const apiClient = {
  getHeaders() {
    const token    = localStorage.getItem('token');
    const tenantId = localStorage.getItem('tenantId');
    return {
      'Content-Type': 'application/json',
      'Accept':       'application/json',
      ...(token    ? { 'Authorization': `Bearer ${token}` } : {}),
      ...(tenantId ? { 'X-Tenant-ID': tenantId }            : {}),
    };
  },

  async request(method, path, body = null) {
    const config = {
      method,
      headers: this.getHeaders(),
    };
    if (body) config.body = JSON.stringify(body);

    try {
      const response = await fetch(`${BASE_URL}${path}`, config);

      // Token expiré → rediriger vers login
      if (response.status === 401) {
        localStorage.removeItem('token');
        localStorage.removeItem('tenantId');
        window.location.href = '/login';
        return null;
      }

      return response.json();
    } catch (error) {
      console.error(`API Error [${method} ${path}]:`, error);
      throw error;
    }
  },

  get:    (path)         => apiClient.request('GET',    path),
  post:   (path, body)   => apiClient.request('POST',   path, body),
  put:    (path, body)   => apiClient.request('PUT',    path, body),
  patch:  (path, body)   => apiClient.request('PATCH',  path, body),
  delete: (path)         => apiClient.request('DELETE', path),
};

export default apiClient;

// ── Helpers raccourcis ───────────────────────────────────────────────────────
export const authApi = {
  login:   (email, password) => apiClient.post('/auth/login', { email, password }),
  logout:  ()                => apiClient.post('/auth/logout'),
  refresh: ()                => apiClient.post('/auth/refresh'),
  me:      ()                => apiClient.get('/auth/me'),
};

export const elevesApi = {
  list:    (params = '') => apiClient.get(`/eleves?${params}`),
  get:     (id)          => apiClient.get(`/eleves/${id}`),
  create:  (data)        => apiClient.post('/eleves', data),
  update:  (id, data)    => apiClient.put(`/eleves/${id}`, data),
  delete:  (id)          => apiClient.delete(`/eleves/${id}`),
  qrCode:  (id)          => `${BASE_URL}/eleves/${id}/qr-code`,
};

export const financeApi = {
  dashboard:      ()          => apiClient.get('/finance/tableau-bord'),
  factures:       (params='') => apiClient.get(`/finance/factures?${params}`),
  payer:          (factureId) => apiClient.post('/paiements/cib/initier', { facture_id: factureId }),
};

export const absencesApi = {
  list:      (params='') => apiClient.get(`/absences?${params}`),
  create:    (data)      => apiClient.post('/absences', data),
  justifier: (id, motif) => apiClient.patch(`/absences/${id}/justifier`, { motif }),
};

export const rapportsApi = {
  absencesPDF:   (mois, annee) => `${BASE_URL}/rapports/absences-pdf?mois=${mois}&annee=${annee}`,
  simulationBEM: (eleveId)     => apiClient.get(`/rapports/simulation-bem/${eleveId}`),
  simulationBAC: (eleveId, filiere) => apiClient.get(`/rapports/simulation-bac/${eleveId}?filiere=${filiere}`),
};

export const surveillanceApi = {
  alertes:  (params='') => apiClient.get(`/surveillance/alertes?${params}`),
  cameras:  ()          => apiClient.get('/surveillance/cameras'),
  traiter:  (id, note)  => apiClient.post(`/surveillance/alertes/${id}/traiter`, { note_admin: note }),
  addCamera:(data)      => apiClient.post('/surveillance/cameras', data),
};

export const marketplaceApi = {
  recherche: (params='') => fetch(`${BASE_URL}/marketplace/recherche?${params}`).then(r => r.json()),
  featured:  ()          => fetch(`${BASE_URL}/marketplace/featured`).then(r => r.json()),
  profil:    (tenantId)  => fetch(`${BASE_URL}/marketplace/centres/${tenantId}`).then(r => r.json()),
  reserver:  (data)      => apiClient.post('/marketplace/reserver', data),
};
```

### 2.4 Créer la page Login complète

**Créer :** `edugestdz/frontend/src/pages/LoginPage.jsx`

```jsx
import { useState } from 'react';
import { authApi } from '../services/api';

export default function LoginPage() {
  const [email, setEmail]       = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading]   = useState(false);
  const [error, setError]       = useState('');

  const handleLogin = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError('');

    try {
      const res = await authApi.login(email, password);

      if (res?.success && res?.data?.token) {
        localStorage.setItem('token',    res.data.token);
        localStorage.setItem('tenantId', res.data.user?.tenant_id ?? '');
        localStorage.setItem('user',     JSON.stringify(res.data.user));
        localStorage.setItem('role',     res.data.user?.role ?? '');

        // Rediriger selon le rôle
        const role = res.data.user?.role;
        if (role === 'super_admin') window.location.href = '/super-admin';
        else                        window.location.href = '/dashboard';
      } else {
        setError(res?.message ?? 'Email ou mot de passe incorrect.');
      }
    } catch (e) {
      setError('Erreur réseau. Vérifiez votre connexion.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div style={{
      minHeight: '100vh', background: '#08090f',
      display: 'flex', alignItems: 'center', justifyContent: 'center',
      padding: '20px',
    }}>
      <div style={{
        background: '#111318', border: '1px solid #1e293b',
        borderRadius: '16px', padding: '40px', width: '100%', maxWidth: '420px',
      }}>
        {/* Logo */}
        <div style={{ textAlign: 'center', marginBottom: '32px' }}>
          <div style={{ fontSize: '40px', marginBottom: '8px' }}>🎓</div>
          <h1 style={{ fontSize: '24px', fontWeight: 900, color: '#fff', marginBottom: '4px' }}>
            EduGest DZ
          </h1>
          <p style={{ fontSize: '12px', color: '#64748b' }}>
            Plateforme de gestion scolaire
          </p>
        </div>

        {/* Erreur */}
        {error && (
          <div style={{
            background: '#450a0a', border: '1px solid #b91c1c',
            borderRadius: '8px', padding: '12px', marginBottom: '16px',
            color: '#f87171', fontSize: '12px',
          }}>
            ❌ {error}
          </div>
        )}

        {/* Formulaire */}
        <form onSubmit={handleLogin}>
          <div style={{ marginBottom: '14px' }}>
            <label style={{ fontSize: '11px', color: '#64748b', display: 'block', marginBottom: '6px' }}>
              Adresse email
            </label>
            <input
              type="email"
              value={email}
              onChange={e => setEmail(e.target.value)}
              placeholder="directeur@ecole.dz"
              required
              style={{
                width: '100%', background: '#1e293b', border: '1px solid #334155',
                borderRadius: '8px', color: '#e2e8f0', padding: '12px 14px', fontSize: '13px',
              }}
            />
          </div>

          <div style={{ marginBottom: '20px' }}>
            <label style={{ fontSize: '11px', color: '#64748b', display: 'block', marginBottom: '6px' }}>
              Mot de passe
            </label>
            <input
              type="password"
              value={password}
              onChange={e => setPassword(e.target.value)}
              placeholder="••••••••"
              required
              style={{
                width: '100%', background: '#1e293b', border: '1px solid #334155',
                borderRadius: '8px', color: '#e2e8f0', padding: '12px 14px', fontSize: '13px',
              }}
            />
          </div>

          <button
            type="submit"
            disabled={loading}
            style={{
              width: '100%',
              background: loading ? '#1e293b' : 'linear-gradient(135deg,#3b82f6,#1d4ed8)',
              color: '#fff', border: 'none', borderRadius: '8px',
              padding: '13px', fontSize: '14px', fontWeight: 700,
              cursor: loading ? 'not-allowed' : 'pointer',
              transition: 'opacity .2s',
            }}
          >
            {loading ? '⏳ Connexion...' : '🔐 Se connecter'}
          </button>
        </form>

        <p style={{ textAlign: 'center', marginTop: '24px', fontSize: '11px', color: '#475569' }}>
          Problème de connexion ? Contactez l'administrateur de votre établissement.
        </p>

        <div style={{ marginTop: '24px', borderTop: '1px solid #1e293b', paddingTop: '16px', textAlign: 'center' }}>
          <p style={{ fontSize: '10px', color: '#334155' }}>
            Vous êtes un centre ? Rejoignez la Marketplace →{' '}
            <a href="/marketplace" style={{ color: '#60a5fa', textDecoration: 'none' }}>
              Trouver un cours
            </a>
          </p>
        </div>
      </div>
    </div>
  );
}
```

---

## ÉTAPE 3 — Déploiement Railway (Backend) — 15 min

### 3.1 Créer le projet Railway

```
1. Aller sur https://railway.app
2. "New Project" → "Deploy from GitHub repo"
3. Sélectionner : Allintelligence2024/edugest-dz
4. Root directory : edugestdz/backend
5. Railway détecte le Dockerfile.railway automatiquement
```

### 3.2 Ajouter PostgreSQL

```
Dans le projet Railway :
→ "+ New" → "Database" → "Add PostgreSQL"
→ Railway crée automatiquement les variables :
   PGHOST, PGPORT, PGDATABASE, PGUSER, PGPASSWORD
→ Ces variables sont injectées dans le backend automatiquement
```

### 3.3 Ajouter Redis

```
Dans le projet Railway :
→ "+ New" → "Database" → "Add Redis"
→ Railway crée automatiquement : REDIS_URL
→ Variable injectée automatiquement dans le backend
```

### 3.4 Configurer les variables d'environnement

```
Dans Railway → ton service backend → Variables :

APP_NAME           = EduGest DZ
APP_ENV            = production
APP_DEBUG          = false
APP_KEY            = (laisser vide — Railway génère ou mettre : php artisan key:generate --show)
JWT_SECRET         = (générer : openssl rand -base64 64)
APP_URL            = https://[ton-projet].up.railway.app

DB_CONNECTION      = pgsql
DB_HOST            = ${{Postgres.PGHOST}}
DB_PORT            = ${{Postgres.PGPORT}}
DB_DATABASE        = ${{Postgres.PGDATABASE}}
DB_USERNAME        = ${{Postgres.PGUSER}}
DB_PASSWORD        = ${{Postgres.PGPASSWORD}}

REDIS_URL          = ${{Redis.REDIS_URL}}
CACHE_DRIVER       = redis
SESSION_DRIVER     = redis
QUEUE_CONNECTION   = database

LOG_CHANNEL        = stderr
LOG_LEVEL          = warning

FRONTEND_URL       = https://edugest-dz.vercel.app

L5_SWAGGER_GENERATE_ALWAYS = false
L5_SWAGGER_CONST_HOST      = https://[ton-projet].up.railway.app
```

### 3.5 Déployer

```
Railway démarre automatiquement le build Docker.
Build time : ~3-5 minutes.

Vérifier les logs :
→ "Migrations ran successfully"
→ "Application key set successfully"
→ nginx started

URL du backend : https://[nom-projet].up.railway.app
Tester : https://[nom-projet].up.railway.app/api/health
→ Doit retourner : {"status":"ok","services":{...}}
```

---

## ÉTAPE 4 — Déploiement Vercel (Frontend) — 5 min

### 4.1 Créer le projet Vercel

```
1. Aller sur https://vercel.com
2. "Add New Project" → "Import Git Repository"
3. Sélectionner : Allintelligence2024/edugest-dz
4. Framework Preset : Vite
5. Root Directory : edugestdz/frontend
6. Build Command : npm run build
7. Output Directory : dist
```

### 4.2 Configurer les variables d'environnement Vercel

```
Dans Vercel → Settings → Environment Variables :

VITE_API_BASE_URL = https://[ton-projet].up.railway.app/api/v1
VITE_APP_NAME     = EduGest DZ
VITE_APP_ENV      = production
```

### 4.3 Déployer

```
Cliquer "Deploy"
Build time : ~1-2 minutes

URL du frontend : https://edugest-dz.vercel.app
→ Ouvrir dans le navigateur
→ La page de login doit s'afficher
```

---

## ÉTAPE 5 — Créer le premier compte admin (seed)

Après le déploiement Railway :

```bash
# Dans Railway → ton service → Shell (ou via Railway CLI)
railway run --service=backend php artisan tinker

# Dans tinker :
App\Models\User::create([
    'nom'       => 'Administrateur',
    'prenom'    => 'Test',
    'email'     => 'admin@edugest.dz',
    'password'  => bcrypt('EduGest2026!'),
    'role'      => 'admin',
    'tenant_id' => \Illuminate\Support\Str::uuid(),
    'actif'     => true,
]);
```

**OU** via l'API directement :
```bash
curl -X POST https://[backend].up.railway.app/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "nom": "Admin",
    "prenom": "Test",
    "email": "admin@edugest.dz",
    "password": "EduGest2026!",
    "password_confirmation": "EduGest2026!"
  }'
```

---

## ÉTAPE 6 — Tester le déploiement complet

### Checklist de validation

```bash
# 1. Health check backend
curl https://[backend].up.railway.app/api/health
# → {"status":"ok"}

# 2. Login
curl -X POST https://[backend].up.railway.app/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@edugest.dz","password":"EduGest2026!"}'
# → {"success":true,"data":{"token":"eyJ..."}}

# 3. Liste élèves (avec le token)
curl https://[backend].up.railway.app/api/v1/eleves \
  -H "Authorization: Bearer eyJ..." \
  -H "X-Tenant-ID: [uuid-du-tenant]"
# → {"success":true,"data":{...}}

# 4. Swagger UI
# Ouvrir : https://[backend].up.railway.app/api/documentation
# → Interface Swagger complète

# 5. Frontend
# Ouvrir : https://edugest-dz.vercel.app
# → Page login EduGest DZ
# → Se connecter avec admin@edugest.dz / EduGest2026!
# → Dashboard admin
```

---

## ÉTAPE 7 — Domaine personnalisé (optionnel)

### Sur Railway (backend)
```
Railway → Settings → Networking → Custom Domain
→ Ajouter : api.edugest.dz
→ Copier le CNAME chez ton registrar de domaine
```

### Sur Vercel (frontend)
```
Vercel → Settings → Domains → Add Domain
→ Ajouter : app.edugest.dz
→ Copier le CNAME chez ton registrar de domaine
```

---

## LIMITATIONS DU PLAN GRATUIT

| Service | Limite gratuite | Impact |
|---|---|---|
| Railway | 500h/mois (~21 jours) | Suffisant pour les tests |
| Railway RAM | 512MB | OK pour 1-5 utilisateurs |
| Railway Disk | 1GB | OK pour les tests |
| Vercel | 100GB bandwidth | Largement suffisant |
| Vercel | Unlimited deploys | ✅ |
| PostgreSQL Railway | 1GB | OK pour les tests |
| Redis Railway | 25MB | OK pour les tests |

**Pour la production réelle → migrer vers VPS (OVH/Hetzner ~5€/mois)**

---

## ORDRE D'EXÉCUTION COMPLET

```bash
# ── DEEPSEEK fait ──────────────────────────────────────────────────────

git checkout develop && git pull origin main

# 1. Créer railway.json
create: edugestdz/backend/railway.json

# 2. Créer Dockerfile.railway
create: edugestdz/backend/Dockerfile.railway

# 3. Créer .env.railway
create: edugestdz/backend/.env.railway

# 4. Modifier cors.php (autoriser Vercel)
modify: edugestdz/backend/config/cors.php

# 5. Créer vercel.json
create: edugestdz/frontend/vercel.json

# 6. Créer .env.production frontend
create: edugestdz/frontend/.env.production
create: edugestdz/frontend/.env.development

# 7. Créer api.js centralisé frontend
create: edugestdz/frontend/src/services/api.js

# 8. Créer/compléter LoginPage.jsx
create: edugestdz/frontend/src/pages/LoginPage.jsx

# 9. Commit & push
git add .
git commit -m "feat: Config déploiement Railway + Vercel — Dockerfile, cors, vercel.json, api.js centralisé"
git push origin main

# ── TOI tu fais (Railway + Vercel UI) ────────────────────────────────

# Railway :
# 1. https://railway.app → New Project → GitHub → edugest-dz → root: edugestdz/backend
# 2. Add PostgreSQL plugin
# 3. Add Redis plugin
# 4. Configurer les variables d'env (voir étape 3.4)
# 5. Deploy → attendre les logs → tester /api/health

# Vercel :
# 1. https://vercel.com → New Project → GitHub → edugest-dz → root: edugestdz/frontend
# 2. Configurer VITE_API_BASE_URL avec l'URL Railway
# 3. Deploy → tester l'URL Vercel
```

---

## CE QUE TU DIS À DEEPSEEK

```
Repo : https://github.com/Allintelligence2024/edugest-dz.git
Branche : main (pas develop pour ce déploiement)
git checkout main && git pull

Fichier : MISSION_DEPLOY_VERCEL_RAILWAY.md — 7 étapes.

Actions DeepSeek (code uniquement) :
1. Créer railway.json + Dockerfile.railway + .env.railway dans edugestdz/backend/
2. Modifier config/cors.php (ajouter les domaines Vercel)
3. Créer vercel.json + .env.production + .env.development dans edugestdz/frontend/
4. Créer edugestdz/frontend/src/services/api.js (client API centralisé avec VITE_API_BASE_URL)
5. Compléter/remplacer LoginPage.jsx avec la version complète fournie

IMPORTANT :
- Ne pas modifier les contrôleurs ou la logique backend
- Ne pas toucher aux tests
- Committer sur main (pas develop)

git add . && git commit -m "feat: Config Railway + Vercel deployment" && git push origin main
```
