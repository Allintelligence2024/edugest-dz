# Déploiement EduGest DZ — 100 % Vercel

> Décision 2026-09-07 : **Railway est abandonné.** Frontend et backend sont
> déployés sur Vercel. Le déploiement VPS (`deploy.yml`) passe en manuel.

---

## 1. Architecture cible

```
                        ┌──────────────────────────────┐
   Navigateur  ───────► │          VERCEL              │
                        │  ┌────────────────────────┐  │
                        │  │ Frontend (Vite/React)  │  │  ← statique, CDN
                        │  │ frontend/dist          │  │
                        │  └────────────────────────┘  │
                        │  ┌────────────────────────┐  │
                        │  │ Backend Laravel        │  │  ← fonction PHP
                        │  │ backend/api/index.php  │  │     vercel-php@0.7.3
                        │  └────────────────────────┘  │
                        │  Vercel Cron (7 tâches)      │
                        └──────────┬───────────────────┘
                                   │
              ┌────────────────────┼────────────────────┐
              ▼                    ▼                    ▼
        PostgreSQL 16         Redis                Stockage objet
        (Neon / Supabase)     (Upstash)            (Vercel Blob / S3)
```

Le frontend appelle `/api/v1/...` en **chemin relatif** : même origine, donc
pas de CORS et pas d'URL backend à configurer.

---

## 2. Services externes à provisionner

| Service | Fournisseur suggéré | Variable |
|---|---|---|
| PostgreSQL 16 | Neon, Supabase | `DATABASE_URL` ou `DB_*` |
| Redis (cache, JWT blacklist, rate limit) | Upstash | `REDIS_URL` |
| Stockage fichiers (QR, PDF, uploads) | Vercel Blob ou S3 | `FILESYSTEM_DISK=s3` + `AWS_*` |
| Recherche | ⚠️ Meilisearch self-hosted impossible | `SCOUT_DRIVER=database` |

---

## 3. Variables d'environnement Vercel

À définir dans **Settings → Environment Variables** (Production + Preview) :

```bash
# ── Application ──
APP_NAME="EduGest DZ"
APP_ENV=production
APP_KEY=base64:...              # php artisan key:generate --show
APP_DEBUG=false
APP_URL=https://votre-domaine.vercel.app

# ── Base de données (Neon/Supabase) ──
DB_CONNECTION=pgsql
DB_HOST=...
DB_PORT=5432
DB_DATABASE=edugestdz
DB_USERNAME=...
DB_PASSWORD=...
DB_SSLMODE=require

# ── Redis (Upstash) ──
REDIS_CLIENT=predis
REDIS_URL=rediss://...
CACHE_STORE=redis
SESSION_DRIVER=redis

# ── Queues : AUCUN worker en serverless ──
QUEUE_CONNECTION=sync           # voir §5, limitation majeure

# ── Sécurité ──
JWT_SECRET=...                  # min 32 caractères aléatoires
QR_SIGNING_KEY=...              # NOUVEAU — clé dédiée aux badges QR
AUDIT_CHAIN_KEY=...             # recommandé (Sprint 3)
CRON_SECRET=...                 # NOUVEAU — protège /api/v1/cron/*

# ── Stockage objet (pas de disque local !) ──
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=...
AWS_BUCKET=...

# ── Recherche ──
SCOUT_DRIVER=database

# ── Logs (pas de fichier persistant) ──
LOG_CHANNEL=stderr

# ── Frontend (build-time) ──
VITE_API_URL=/api/v1
VITE_APP_NAME="EduGest DZ"
```

Générer les secrets :
```bash
openssl rand -base64 48   # pour JWT_SECRET, QR_SIGNING_KEY, CRON_SECRET
```

---

## 4. Migrations

Aucun worker ne tourne : les migrations **ne s'exécutent pas au déploiement**.
Les lancer manuellement depuis une machine ayant accès à la base :

```bash
cd edugestdz/backend
DB_HOST=... DB_PASSWORD=... php artisan migrate --force
```

À automatiser plus tard via un job GitHub Actions déclenché après déploiement.

---

## 5. ⚠️ Limitations du serverless — à connaître avant la mise en production

Ce sont des contraintes **structurelles** de Vercel, pas des bugs :

| Domaine | Impact | Contournement |
|---|---|---|
| **Queues** | `app/Jobs/` contient 7 jobs (emails, SMS, PDF bulletins, exports RGPD, prédictions). Avec `QUEUE_CONNECTION=sync` ils s'exécutent **dans la requête HTTP** → risque de timeout à 60 s. | Externaliser vers Upstash QStash, ou garder un petit worker ailleurs. |
| **Scheduler** | `schedule:run` n'existe pas. Remplacé par **Vercel Cron** → `routes/api/cron.php` (créé, protégé par `CRON_SECRET`). | ✅ Fait. Plan Hobby = 2 crons/jour max ; le plan Pro est requis pour les 7 tâches. |
| **Filesystem** | Lecture seule sauf `/tmp` (éphémère, non partagé entre invocations). Les QR PNG et PDF écrits localement **disparaissent**. | `FILESYSTEM_DISK=s3` obligatoire. 17 usages de `Storage::disk('public')` à auditer. |
| **Meilisearch** | Impossible à self-héberger. | `SCOUT_DRIVER=database`, ou Meilisearch Cloud. |
| **Timeout 60 s** | Génération de bulletins en masse, exports RGPD, rapports ONEC peuvent dépasser. | Découper en lots, ou déporter. |
| **Cold start** | Laravel démarre à chaque invocation froide (~1–3 s). | `php artisan config:cache route:cache` au build. |
| **WebSockets** | Non supportés. | Polling ou service tiers (Pusher/Ably). |
| **PostgreSQL connections** | Chaque invocation ouvre une connexion → saturation rapide. | **Obligatoire** : pooler (PgBouncer, Neon pooled endpoint, Supabase pooler). |

---

## 6. Checklist de mise en ligne

- [ ] Provisionner PostgreSQL **avec pooler de connexions**
- [ ] Provisionner Redis (Upstash)
- [ ] Provisionner le bucket S3 / Vercel Blob
- [ ] Définir toutes les variables du §3
- [ ] `vercel link` puis premier déploiement preview
- [ ] Lancer `php artisan migrate --force`
- [ ] Vérifier `GET /api/v1/health` → 200
- [ ] Tester un `POST /api/v1/auth/login`
- [ ] Vérifier qu'un cron répond 401 sans `CRON_SECRET` et 200 avec
- [ ] Auditer les 17 `Storage::disk('public')` → basculer sur S3
- [ ] Décider du sort des 7 jobs de queue (§5)

---

## 7. Ce qui a été retiré

| Élément | Statut |
|---|---|
| `backend/railway.json` | supprimé |
| `backend/Dockerfile.railway` | supprimé |
| `backend/railway-nginx.conf` | supprimé |
| Patterns CORS `*.railway.app` | retirés de `config/cors.php` |
| Test `test_cors_patterns_inclut_railway` | remplacé par `test_cors_patterns_exclut_railway` (non-régression) |
| `.github/workflows/deploy.yml` | passé en `workflow_dispatch` manuel |

`docker-compose.prod.yml` et le VPS restent disponibles comme solution de repli
(voir `deploy.sh`), notamment si les limitations du §5 s'avèrent bloquantes.

---

## 8. Action manuelle requise — désactivation de `deploy.yml`

> ⚠️ **Non appliqué** : les workflows ne sont pas poussables depuis
> l'environnement de travail (voir `docs/SPRINT3_SECURITE.md` §1).
> Fichier de remplacement prêt : `edugestdz/docs/deploy.yml.desactive.patch`.
>
> ```bash
> cp edugestdz/docs/deploy.yml.desactive.patch .github/workflows/deploy.yml
> git add .github/workflows/deploy.yml && git commit -m "ci: désactiver le déploiement VPS" && git push
> ```

Le changement : `.github/workflows/deploy.yml` passe de `on: push` (branche `main`) à
`on: workflow_dispatch` avec une saisie de confirmation obligatoire : il faut
taper littéralement `deployer` pour lancer un déploiement VPS.

Le workflow est **conservé, pas supprimé** : il reste le filet de secours tant
que la cible serverless Vercel n'est pas validée en production.

Pour un déploiement VPS manuel : onglet *Actions* → *CD — Deploy Production
(VPS · DÉSACTIVÉ)* → *Run workflow* → saisir `deployer`.

Pour restaurer le déclenchement automatique, remettre le bloc `push:` documenté
en tête du fichier.
