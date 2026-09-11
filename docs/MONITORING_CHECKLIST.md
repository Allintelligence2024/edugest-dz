# Monitoring Checklist — EduGest DZ

## Pre-Deployment

- [ ] CI passes (ci.yml): `php artisan test` — 0 failures
- [ ] Pre-deploy smoke tests pass (pre-deploy-check.yml)
- [ ] No new `exit()` or `die()` in code
- [ ] No secrets committed (API keys, passwords)
- [ ] Migration files have `hasColumn()` guards for idempotency
- [ ] Frontend build OK: `npm run build` — 0 errors

## Health Endpoints

| Endpoint | Expected | Description |
|---|---|---|
| `GET /api/v1/health` | 200 `status: ok` | Full service check (DB, Redis, storage, migrations) |
| `GET /api/v1/health/ping` | 200 `status: ok` | Lightweight ping for UptimeRobot |
| `GET /up` | 200 | Laravel built-in health check |

## Service Checks (in `/api/v1/health`)

- `services.postgresql.status` → `ok` / `error`
- `services.redis.status` → `ok` / `error`
- `services.storage.status` → `ok` / `error`
- `services.migrations.status` → `ok` (count) / `error`
- `services.queue.status` → `ok` (driver name)
- `services.meilisearch.status` → `ok` / `unavailable`

## Smoke Test Script

```bash
# Run locally
chmod +x scripts/smoke-test.sh
./scripts/smoke-test.sh http://localhost:8000

# Expected output:
# ✓ GET /api/v1/health → 200
# ✓ GET /api/v1/health/ping → 200
# ✓ GET /nonexistent-route → 404
# Results: 3 passed, 0 failed
```

## Railway Deployment

1. Merge PR to `main`
2. Railway auto-deploys from `main`
3. Post-deploy: verify `GET /api/v1/health` returns `status: ok`
4. If `migrations.count` < expected → run `php artisan migrate --force`

## Monitoring Tools

- **UptimeRobot**: monitor `GET /api/v1/health/ping` every 5 min
- **Sentry**: exceptions + événements de sécurité (voir ci-dessous)
- **Telegram alerts**: critical errors via `SecurityMonitorService`
- **Railway metrics**: CPU, memory, network via `ServiceMetrics`

## Sentry — observabilité de sécurité (Sprint 6 § 4)

### Mise en place (backend)

```bash
# backend/.env (production/staging uniquement — cf. config/sentry.php)
SENTRY_DSN=https://<clé>@sentry.io/<projet>
SENTRY_TRACES_SAMPLE_RATE=0.1
```

Sans DSN, tout le câblage ci-dessous est un no-op : rien n'est envoyé,
aucune erreur ne peut être provoquée par la télémétrie.

### Ce qui est signalé (en plus des exceptions)

| Événement | Niveau Sentry | Source |
|-----------|---------------|--------|
| Brute force, accès hors horaires, volume anormal, verrouillage d'urgence | warning → fatal | `SecurityMonitorService::alerter()` |
| Score de risque Zero-Trust > 50 | warning | `ZeroTrustMiddleware` |
| Kill-switch : vote approuvé, échec d'activation, fail-open (Redis + BDD down) | fatal / error | `KillSwitchService` |
| Chaîne d'audit Merkle rompue (vérification 03 h 30) | fatal | `audit:verify` |

### Règles d'alerte recommandées

- **fatal** → notification immédiate (astreinte) : kill-switch activé,
  chaîne d'audit rompue, verrouillage d'urgence ;
- **error** → ticket : fail-open kill-switch, échec d'activation ;
- **warning** → revue hebdomadaire : scores Zero-Trust, brute force.

### Mobile (React Native / Expo)

`sentry-expo` est installé et câblé (`App.js`) : sans
`EXPO_PUBLIC_SENTRY_DSN`, l'init est un no-op. Pour activer sur les
builds EAS :

```bash
# eas.json → build.production.env
"EXPO_PUBLIC_SENTRY_DSN": "https://<clé>@sentry.io/<projet>"
```

Le plugin `sentry-expo` dans `app.json` gère la configuration native
(crashes natifs + symboles). Attention : le binaire `@sentry/cli`
(upload des sourcemaps) se télécharge au postinstall — prévoir un
réseau autorisant `downloads.sentry-cdn.com` sur le poste de build.

### Tâches planifiées à surveiller

| Tâche | Horaire | Signal d'échec |
|-------|---------|----------------|
| `audit:verify` | 03 h 30 | exit FAILURE + Sentry fatal |
| `edugest:rgpd-retention` | 04 h 10 | log `Rétention 18-07` |
| `edugest:deadman-switch` | 06 h 00 | notifications admin

## Incident Response

1. Health check fails → check Railway logs
2. Migrations count mismatch → `php artisan migrate:status` locally
3. Redis error → verify `REDIS_HOST` env var
4. PostgreSQL error → verify `DB_*` env vars
5. Kill switch active → `kill_switch:active` dans Redis (deux-points), ou table `kill_switch_state` (fallback BDD) — ne pas confondre avec l'ancienne clé fausse `kill_switch_active`

## Rollback

```bash
# Railway: redeploy previous successful deployment
# Or rollback manually:
php artisan migrate:rollback
```
