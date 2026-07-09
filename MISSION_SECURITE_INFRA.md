# 🤖 MISSION DEEPSEEK — Sécurité + Infra : Backup PG + Rate Limiting + Monitoring
## EduGest DZ · Branche : develop · 3 Juillet 2026
## Tests actuels : 423+ ✅ · Objectif : ≥ 435 ✅ · 0 régression

---

## CONTEXTE

Dernière mission technique avant prod. 3 zones :
1. **Backup PostgreSQL** automatique quotidien (docker-compose.prod.yml)
2. **Rate limiting** renforcé par rôle et par endpoint sensible
3. **Monitoring** : Sentry Laravel + health check endpoint

### RÈGLES
1. PostgreSQL uniquement
2. 0 régression
3. Ne pas modifier les contrôleurs existants

---

## ÉTAPE 0 — Synchroniser

```bash
git checkout develop && git pull origin main
```

---

## ÉTAPE 1 — Rate Limiting renforcé

**Modifier :** `edugestdz/backend/app/Providers/RouteServiceProvider.php`

Si ce fichier n'existe pas (Laravel 11 utilise bootstrap/app.php), modifier :

**Modifier :** `edugestdz/backend/bootstrap/app.php`

Ajouter dans `->withMiddleware()` :

```php
->withMiddleware(function (Middleware $middleware) {
    // Rate limiting par type de route
    $middleware->throttleApi();

    // Rate limiting API — définir les limiteurs
    $middleware->api(prepend: [
        \App\Http\Middleware\QueryMonitor::class, // déjà créé en mission perf
    ]);
})
```

**Créer :** `edugestdz/backend/app/Providers/AppServiceProvider.php`

Dans la méthode `boot()`, ajouter les rate limiters :

```php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;

// Dans boot() :

// ── Authentification : 5 tentatives / 15 min par IP ──────────────────────
RateLimiter::for('auth', function (Request $request) {
    return Limit::perMinutes(15, 5)
        ->by($request->ip())
        ->response(function () {
            return response()->json([
                'success' => false,
                'message' => 'Trop de tentatives de connexion. Réessayez dans 15 minutes.',
            ], 429);
        });
});

// ── API générale : 100 req/min par tenant ────────────────────────────────
RateLimiter::for('api', function (Request $request) {
    $tenantId = $request->header('X-Tenant-ID', $request->ip());
    return Limit::perMinute(100)
        ->by($tenantId)
        ->response(function () {
            return response()->json([
                'success' => false,
                'message' => 'Limite de requêtes atteinte (100/min). Contactez le support si ce problème persiste.',
            ], 429);
        });
});

// ── Upload / Export PDF : 10 req/min par user ────────────────────────────
RateLimiter::for('exports', function (Request $request) {
    return Limit::perMinute(10)
        ->by(optional($request->user())->id ?? $request->ip())
        ->response(function () {
            return response()->json([
                'success' => false,
                'message' => 'Trop d\'exports. Attendez 1 minute.',
            ], 429);
        });
});

// ── SMS / Notifications : 20 req/heure par tenant ────────────────────────
RateLimiter::for('notifications', function (Request $request) {
    return Limit::perHour(20)
        ->by($request->header('X-Tenant-ID', $request->ip()));
});

// ── WhatsApp webhook : liste blanche IP Twilio ───────────────────────────
RateLimiter::for('webhook', function (Request $request) {
    // Twilio IPs (à compléter avec la liste officielle)
    $twilioIPs = [
        '54.172.60.0/23', '54.244.51.0/24',
        '54.171.127.192/26', '54.65.63.192/26',
    ];
    // Si IP inconnue : limiter strictement
    return Limit::perMinute(30)->by($request->ip());
});
```

**Modifier :** `edugestdz/backend/routes/api.php`

Appliquer les rate limiters aux routes sensibles :

```php
// Auth routes — rate limiter "auth"
Route::middleware('throttle:auth')->group(function () {
    Route::post('/v1/auth/login',   [AuthController::class, 'login']);
    Route::post('/v1/auth/refresh', [AuthController::class, 'refresh']);
});

// Exports/PDF — rate limiter "exports"
Route::middleware(['auth:api', 'tenant', 'throttle:exports'])->group(function () {
    Route::get('/v1/rapports/absences-pdf',       [RapportController::class, 'absencesPDF']);
    Route::get('/v1/bulletins/{id}/telecharger',  [BulletinController::class, 'telecharger']);
    Route::get('/v1/planning/ical',               [PlanningController::class, 'exportICal']);
});

// WhatsApp webhook
Route::middleware('throttle:webhook')->group(function () {
    Route::post('/v1/whatsapp/webhook', [WhatsAppController::class, 'webhook']);
});
```

---

## ÉTAPE 2 — Health Check endpoint

**Créer :** `edugestdz/backend/app/Http/Controllers/Api/HealthController.php`

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class HealthController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/health",
     *     summary="Health check — statut de tous les services",
     *     tags={"SuperAdmin"},
     *     @OA\Response(
     *         response=200,
     *         description="Tous les services sont opérationnels",
     *         @OA\JsonContent(
     *             @OA\Property(property="status",   type="string", example="ok"),
     *             @OA\Property(property="version",  type="string", example="1.0.0"),
     *             @OA\Property(property="services", type="object")
     *         )
     *     ),
     *     @OA\Response(response=503, description="Un ou plusieurs services sont dégradés")
     * )
     */
    public function check(): JsonResponse
    {
        $services = [];
        $allOk    = true;

        // ── PostgreSQL ───────────────────────────────────────────────────
        try {
            DB::select('SELECT 1');
            $services['postgresql'] = ['status' => 'ok', 'latency_ms' => $this->measureLatency(fn() => DB::select('SELECT 1'))];
        } catch (\Throwable $e) {
            $services['postgresql'] = ['status' => 'error', 'error' => $e->getMessage()];
            $allOk = false;
        }

        // ── Redis ────────────────────────────────────────────────────────
        try {
            $key = 'health_check_' . uniqid();
            Cache::put($key, 'ok', 5);
            $val = Cache::get($key);
            Cache::forget($key);
            $services['redis'] = ['status' => $val === 'ok' ? 'ok' : 'degraded'];
        } catch (\Throwable $e) {
            $services['redis'] = ['status' => 'error', 'error' => $e->getMessage()];
            $allOk = false;
        }

        // ── Storage ──────────────────────────────────────────────────────
        try {
            $path = storage_path('app/health_test.tmp');
            file_put_contents($path, 'ok');
            $ok = file_get_contents($path) === 'ok';
            unlink($path);
            $services['storage'] = ['status' => $ok ? 'ok' : 'degraded'];
        } catch (\Throwable $e) {
            $services['storage'] = ['status' => 'error', 'error' => $e->getMessage()];
            $allOk = false;
        }

        // ── Queue ────────────────────────────────────────────────────────
        $services['queue'] = ['status' => 'ok', 'driver' => config('queue.default')];

        // ── Meilisearch (optionnel) ───────────────────────────────────────
        try {
            $client = app(\Laravel\Scout\Engines\MeilisearchEngine::class);
            $services['meilisearch'] = ['status' => 'ok'];
        } catch (\Throwable) {
            $services['meilisearch'] = ['status' => 'unavailable'];
            // Non critique — ne fait pas échouer le health check
        }

        $httpStatus = $allOk ? 200 : 503;

        return response()->json([
            'status'      => $allOk ? 'ok' : 'degraded',
            'version'     => config('app.version', '1.0.0'),
            'environment' => app()->environment(),
            'timestamp'   => now()->toIso8601String(),
            'services'    => $services,
        ], $httpStatus);
    }

    private function measureLatency(callable $fn): int
    {
        $start = microtime(true);
        $fn();
        return (int) ((microtime(true) - $start) * 1000);
    }
}
```

**Ajouter la route dans `routes/api.php`** (sans auth — pour le monitoring externe) :

```php
// Health check public
Route::get('/health', [\App\Http\Controllers\Api\HealthController::class, 'check']);
```

---

## ÉTAPE 3 — Backup PostgreSQL dans docker-compose.prod.yml

**Modifier :** `edugestdz/docker-compose.prod.yml`

Ajouter le service backup après les services existants :

```yaml
  # ── Backup PostgreSQL automatique ───────────────────────────────────────
  backup:
    image: prodrigestivill/postgres-backup-local:16
    container_name: edugest_backup
    restart: unless-stopped
    depends_on:
      - postgres
    environment:
      - POSTGRES_HOST=postgres
      - POSTGRES_DB=${DB_DATABASE:-edugestdz}
      - POSTGRES_USER=${DB_USERNAME:-edugest_user}
      - POSTGRES_PASSWORD=${DB_PASSWORD}
      - SCHEDULE=@daily          # Backup tous les jours à minuit
      - BACKUP_KEEP_DAYS=7       # Garder 7 jours
      - BACKUP_KEEP_WEEKS=4      # Garder 4 semaines
      - BACKUP_KEEP_MONTHS=6     # Garder 6 mois
      - HEALTHCHECK_PORT=8080
    volumes:
      - ./backups:/backups
    networks:
      - edugest-network
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:8080/"]
      interval: 60s
      timeout: 10s
      retries: 3
```

**Créer :** `edugestdz/backups/.gitkeep`

```bash
mkdir -p edugestdz/backups
touch edugestdz/backups/.gitkeep
echo "backups/*.sql.gz" >> edugestdz/.gitignore
echo "backups/*.dump" >> edugestdz/.gitignore
```

---

## ÉTAPE 4 — Script de restore backup

**Créer :** `edugestdz/scripts/restore-backup.sh`

```bash
#!/bin/bash
# ============================================================
# EduGest DZ — Restaurer un backup PostgreSQL
# Usage : ./scripts/restore-backup.sh backups/edugestdz_2026-07-01.sql.gz
# ============================================================

set -e

BACKUP_FILE=$1

if [ -z "$BACKUP_FILE" ]; then
    echo "❌ Usage : $0 <fichier_backup.sql.gz>"
    exit 1
fi

if [ ! -f "$BACKUP_FILE" ]; then
    echo "❌ Fichier non trouvé : $BACKUP_FILE"
    exit 1
fi

echo "⚠️  ATTENTION : Cette opération va écraser la base de données actuelle."
read -p "Continuer ? (oui/non) : " confirm
if [ "$confirm" != "oui" ]; then
    echo "Annulé."
    exit 0
fi

echo "🔄 Restauration en cours..."

# Arrêter l'application pour éviter les écritures pendant la restore
docker-compose -f docker-compose.prod.yml stop app

# Restore
gunzip -c "$BACKUP_FILE" | docker exec -i edugest_postgres \
    psql -U "${DB_USERNAME:-edugest_user}" -d "${DB_DATABASE:-edugestdz}"

# Redémarrer
docker-compose -f docker-compose.prod.yml start app

echo "✅ Restauration terminée depuis $BACKUP_FILE"
```

```bash
chmod +x edugestdz/scripts/restore-backup.sh
```

---

## ÉTAPE 5 — Middleware sécurité headers

**Créer :** `edugestdz/backend/app/Http/Middleware/SecurityHeaders.php`

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Ajouter les en-têtes de sécurité HTTP sur toutes les réponses API.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options',    'nosniff');
        $response->headers->set('X-Frame-Options',           'DENY');
        $response->headers->set('X-XSS-Protection',         '1; mode=block');
        $response->headers->set('Referrer-Policy',           'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy',        'camera=(), microphone=(), geolocation=()');

        // HSTS — uniquement en production
        if (app()->environment('production')) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        }

        // Supprimer les headers qui révèlent la stack
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        return $response;
    }
}
```

**Modifier :** `edugestdz/backend/bootstrap/app.php`

Ajouter dans `->withMiddleware()` :

```php
$middleware->api(prepend: [
    \App\Http\Middleware\SecurityHeaders::class,
    \App\Http\Middleware\QueryMonitor::class,
]);
```

---

## ÉTAPE 6 — Nginx : config sécurité renforcée

**Modifier :** `edugestdz/nginx/conf.d/production.conf`

Ajouter dans le bloc `server` après les directives SSL existantes :

```nginx
    # ── Sécurité ───────────────────────────────────────────────────────────
    # Masquer la version Nginx
    server_tokens off;

    # Limiter la taille des requêtes (éviter les uploads malveillants)
    client_max_body_size 10M;

    # Rate limiting Nginx (couche complémentaire au Laravel throttle)
    limit_req_zone $binary_remote_addr zone=api_limit:10m rate=60r/m;
    limit_req_zone $binary_remote_addr zone=auth_limit:1m  rate=5r/m;

    # Appliquer sur /api/v1/auth/login
    location /api/v1/auth/login {
        limit_req zone=auth_limit burst=2 nodelay;
        limit_req_status 429;
        try_files $uri $uri/ /index.php?$query_string;
        fastcgi_pass app:9000;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # Appliquer sur toute l'API
    location /api/ {
        limit_req zone=api_limit burst=20 nodelay;
        limit_req_status 429;
        try_files $uri $uri/ /index.php?$query_string;
        fastcgi_pass app:9000;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # Headers de sécurité globaux
    add_header X-Content-Type-Options "nosniff"         always;
    add_header X-Frame-Options        "DENY"            always;
    add_header X-XSS-Protection       "1; mode=block"   always;
    add_header Referrer-Policy        "strict-origin-when-cross-origin" always;
```

---

## ÉTAPE 7 — Tests sécurité et health check

**Créer :** `edugestdz/backend/tests/Feature/SecurityTest.php`

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    // ── Health Check ──────────────────────────────────────────────────────

    public function test_health_check_retourne_200(): void
    {
        $this->getJson('/api/health')
            ->assertStatus(200)
            ->assertJsonStructure(['status', 'version', 'services' => ['postgresql', 'redis', 'storage']]);
    }

    public function test_health_check_postgresql_ok(): void
    {
        $response = $this->getJson('/api/health');
        $response->assertStatus(200);
        $this->assertEquals('ok', $response->json('services.postgresql.status'));
    }

    // ── Security Headers ─────────────────────────────────────────────────

    public function test_api_retourne_security_headers(): void
    {
        $this->getJson('/api/health')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options',        'DENY')
            ->assertHeader('X-XSS-Protection',       '1; mode=block');
    }

    // ── Rate Limiting ─────────────────────────────────────────────────────

    public function test_login_rate_limiter_bloque_apres_5_tentatives(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email'    => 'fake@test.com',
                'password' => 'wrongpassword',
            ]);
        }

        // La 6ème tentative doit être bloquée (429)
        $this->postJson('/api/v1/auth/login', [
            'email'    => 'fake@test.com',
            'password' => 'wrongpassword',
        ])->assertStatus(429);
    }

    // ── Tenant Isolation ─────────────────────────────────────────────────

    public function test_api_sans_token_retourne_401(): void
    {
        $this->getJson('/api/v1/eleves')->assertStatus(401);
        $this->getJson('/api/v1/budget/dashboard')->assertStatus(401);
        $this->getJson('/api/v1/transport/circuits')->assertStatus(401);
    }

    public function test_parent_ne_peut_pas_acceder_super_admin(): void
    {
        $parent = User::factory()->create(['role' => 'parent']);
        $this->actingAs($parent, 'api')
            ->getJson('/api/v1/super-admin/tenants')
            ->assertStatus(403);
    }

    public function test_enseignant_ne_peut_pas_supprimer_eleve(): void
    {
        $enseignant = User::factory()->create(['role' => 'enseignant']);
        $eleve      = \App\Models\Eleve::factory()->create();

        $this->actingAs($enseignant, 'api')
            ->deleteJson("/api/v1/eleves/{$eleve->id}")
            ->assertStatus(403);
    }
}
```

---

## ÉTAPE 8 — Variables d'environnement manquantes

**Modifier :** `edugestdz/backend/.env.example`

Ajouter les variables manquantes :

```dotenv
# ── Application ───────────────────────────────────────────────
APP_VERSION=1.0.0

# ── Firebase Push Notifications ───────────────────────────────
FIREBASE_SERVER_KEY=
FIREBASE_PROJECT_ID=

# ── Backup ────────────────────────────────────────────────────
BACKUP_KEEP_DAYS=7
BACKUP_S3_BUCKET=        # optionnel — pour upload S3

# ── Monitoring ────────────────────────────────────────────────
SENTRY_LARAVEL_DSN=      # https://xxx@sentry.io/xxx

# ── Satim (CIB/Dahabia) ───────────────────────────────────────
SATIM_MERCHANT_LOGIN=
SATIM_MERCHANT_PASSWORD=
SATIM_TERMINAL_ID=
SATIM_BASE_URL=https://test.satim.dz/payment/rest   # sandbox
# SATIM_BASE_URL=https://satim.dz/payment/rest      # production
```

---

## ORDRE D'EXÉCUTION

```bash
git checkout develop && git pull origin main

# PARTIE A — Laravel
# 1. Modifier AppServiceProvider.php (rate limiters)
# 2. Modifier bootstrap/app.php (middleware prepend)
# 3. Créer HealthController.php
# 4. Ajouter route /health dans routes/api.php
# 5. Créer SecurityHeaders.php middleware

# PARTIE B — Infrastructure
# 6. Modifier docker-compose.prod.yml (service backup)
# 7. Créer backups/.gitkeep
# 8. Créer scripts/restore-backup.sh
# 9. Modifier nginx/conf.d/production.conf

# PARTIE C — Config
# 10. Modifier .env.example

# TESTS
cd edugestdz/backend
php artisan test --parallel
# → 0 régression + 7 nouveaux tests

git add .
git commit -m "feat: Sécurité — Rate limiting + Security headers + Health check + Backup PG + Nginx hardening"
git push origin develop
# → PR develop → main
```

---

## CE QUE TU DIS À DEEPSEEK

```
Repo : https://github.com/Allintelligence2024/edugest-dz.git
Branche : develop
git checkout develop && git pull origin main

Fichier : MISSION_SECURITE_INFRA.md — 8 étapes.

RÈGLES :
1. PostgreSQL uniquement.
2. 0 régression.
3. Le test rate limiter (429) peut être flakey en CI — utiliser
   RateLimiter::clear('auth') dans setUp() si nécessaire.
4. Le health check /api/health est PUBLIC — pas de middleware auth dessus.
5. Ne pas modifier les contrôleurs existants.

php artisan test --parallel → verts → git push → PR develop → main.
```
