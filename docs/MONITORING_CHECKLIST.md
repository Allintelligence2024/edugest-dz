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
chmod +x edugestdz/scripts/smoke-test.sh
./edugestdz/scripts/smoke-test.sh http://localhost:8000

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
- **Sentry**: exception tracking (production + staging)
- **Telegram alerts**: critical errors via `SecurityMonitorService`
- **Railway metrics**: CPU, memory, network via `ServiceMetrics`

## Incident Response

1. Health check fails → check Railway logs
2. Migrations count mismatch → `php artisan migrate:status` locally
3. Redis error → verify `REDIS_HOST` env var
4. PostgreSQL error → verify `DB_*` env vars
5. Kill switch active → check `kill_switch_active` cache key

## Rollback

```bash
# Railway: redeploy previous successful deployment
# Or rollback manually:
php artisan migrate:rollback
```
