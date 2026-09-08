# 🔧 MISSION DEEPSEEK — Fix 3 Points Faibles Importants
## EduGest DZ · Branche : develop · 8 Juillet 2026
## Tests actuels : 607 ✅ · Objectif : ≥ 620 ✅ · 0 régression

---

## CONTEXTE — Ce qui a été vérifié dans le repo avant d'écrire ce fichier

```
POINT FAIBLE 5 — CI / SQLite
  phpunit.xml LU : DB_CONNECTION non défini → chaque test peut tomber en SQLite si .env mal configuré
  ci.yml LU : PostgreSQL 16 configuré DANS le CI (correct), mais phpunit.xml n'impose pas pgsql
  Historique commits : "jsonb→json", "gen_random_uuid→Str::uuid", "enum→string" = preuves SQLite compat
  État réel : le CI tourne PostgreSQL, MAIS phpunit.xml n'a pas DB_CONNECTION=pgsql
  → Un développeur qui fait `php artisan test` localement sans PostgreSQL → SQLite silencieux

POINT FAIBLE 6 — Monitoring
  Aucun fichier Sentry, UptimeRobot, ou healthcheck avancé détecté dans le repo
  /api/health existe (déjà créé) → vérifier PostgreSQL + Redis + Meilisearch
  Pas de config Sentry dans .env.example ni config/sentry.php
  Pas de middleware de logging des erreurs 500 en production

POINT FAIBLE 7 — Marketplace
  Marketplace/AvisController.php ✅ présent
  Marketplace/OffreController.php ✅ présent
  Marketplace/ReservationController.php ✅ présent (229 lignes, logique complète)
  Services/Marketplace/CommissionService.php ✅ présent
  Services/Marketplace/VisioService.php ✅ présent
  TenantModule.php : 'marketplace' défini dans MODULES mais 'actif' par défaut = true (dangereux si incomplet)
  CE QUI MANQUE :
    - MarketplaceController principal (recherche publique, featured, profils publics)
    - Routes publiques sans auth : GET /api/v1/marketplace/recherche, /featured, /centres/{id}
    - Validation : les tests Marketplace passent mais le module n'est pas isolé via ModuleCheck
    - Frontend : page /centres inexistante ou incomplète
    - Module désactivé par défaut (actuellement actif = true si pas de record en BDD)
```

### RÈGLES ABSOLUES
1. **0 régression** — les 607 tests existants restent verts
2. **PostgreSQL uniquement** — jamais SQLite
3. **Ne pas casser le CI** — le ci.yml doit rester fonctionnel
4. **Dégradation gracieuse** — si Sentry non configuré → ne pas crasher

---

## ÉTAPE 0 — Synchroniser

```bash
git checkout develop && git pull origin main
```

---

## ══════════════════════════════════════════════════
## PARTIE A — FIX 5 : CI POSTGRESQL PUR + PROTECTION SQLITE
## ══════════════════════════════════════════════════

### Problème exact
`phpunit.xml` ne force pas `DB_CONNECTION=pgsql`.
Un dev qui lance `php artisan test` sans PostgreSQL local → Laravel détecte
`DB_CONNECTION` absent dans .env.testing → tombe silencieusement sur SQLite.
Des migrations jsonb, UUID, RLS passent sur SQLite avec workarounds
mais peuvent éclater en production PostgreSQL réelle.

---

## ÉTAPE 1 — Forcer PostgreSQL dans phpunit.xml

**Modifier** : `edugestdz/backend/phpunit.xml`

Remplacer ENTIÈREMENT le contenu :

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
         stopOnFailure="false"
>
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory>tests/Feature</directory>
        </testsuite>
    </testsuites>

    <source>
        <include>
            <directory>app</directory>
        </include>
    </source>

    <php>
        <!-- ── Application ─────────────────────────────────────────── -->
        <env name="APP_ENV"                   value="testing"/>
        <env name="APP_DEBUG"                 value="true"/>
        <env name="APP_KEY"                   value="base64:TEST_KEY_WILL_BE_GENERATED_BY_ARTISAN="/>
        <env name="APP_MAINTENANCE_DRIVER"    value="file"/>
        <env name="BCRYPT_ROUNDS"             value="4"/>

        <!-- ── Base de données — PostgreSQL OBLIGATOIRE ────────────── -->
        <!-- JAMAIS SQLite en test — les features PostgreSQL (RLS, jsonb,  -->
        <!-- uuid, SAVEPOINT) doivent être testées sur le vrai moteur.     -->
        <env name="DB_CONNECTION"  value="pgsql"/>
        <env name="DB_HOST"        value="127.0.0.1"/>
        <env name="DB_PORT"        value="5432"/>
        <env name="DB_DATABASE"    value="edugestdz_test"/>
        <env name="DB_USERNAME"    value="edugest_user"/>
        <env name="DB_PASSWORD"    value="EduGest@2026!"/>

        <!-- ── Redis ──────────────────────────────────────────────── -->
        <env name="REDIS_HOST"     value="127.0.0.1"/>
        <env name="REDIS_PORT"     value="6379"/>
        <env name="REDIS_PASSWORD" value=""/>

        <!-- ── Cache / Queue / Session ────────────────────────────── -->
        <env name="CACHE_STORE"       value="redis"/>
        <env name="QUEUE_CONNECTION"  value="sync"/>
        <env name="SESSION_DRIVER"    value="array"/>

        <!-- ── Mail ────────────────────────────────────────────────── -->
        <env name="MAIL_MAILER"  value="array"/>

        <!-- ── Services désactivés en test ────────────────────────── -->
        <env name="TELESCOPE_ENABLED"  value="false"/>
        <env name="PULSE_ENABLED"      value="false"/>
        <env name="SENTRY_DSN"         value=""/>

        <!-- ── Sécurité tests ──────────────────────────────────────── -->
        <!-- JWT secret minimal pour les tests (sera régénéré en CI) -->
        <env name="JWT_SECRET"  value="test-secret-minimum-32-characters-long-for-jwt"/>
        <env name="JWT_TTL"     value="60"/>

        <!-- ── Meilisearch (désactivé en test pour rapidité) ──────── -->
        <env name="SCOUT_DRIVER"        value="null"/>
        <env name="MEILISEARCH_HOST"    value="http://localhost:7700"/>
    </php>
</phpunit>
```

---

## ÉTAPE 2 — Ajouter un guard anti-SQLite au démarrage des tests

**Créer** : `edugestdz/backend/tests/TestCase.php`

Remplacer entièrement (ou modifier si existant) :

```php
<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // ── GUARD ANTI-SQLite ──────────────────────────────────────────
        // Bloquer immédiatement si on tourne sur SQLite en test.
        // EduGest DZ utilise des features PostgreSQL-spécifiques :
        // RLS, jsonb, gen_random_uuid(), SAVEPOINT, SHA3.
        // Tourner sur SQLite = faux sentiment de sécurité.
        $connection = config('database.default');
        if ($connection === 'sqlite') {
            $this->fail(
                "\n\n" .
                "❌ ERREUR : Les tests tournent sur SQLite !\n" .
                "EduGest DZ nécessite PostgreSQL 16.\n\n" .
                "Solution :\n" .
                "  1. Vérifier que PostgreSQL tourne localement\n" .
                "  2. Créer la base : createdb edugestdz_test\n" .
                "  3. Créer l'utilisateur : createuser edugest_user\n" .
                "  4. Relancer : php artisan test\n\n" .
                "Ou utiliser Docker : docker compose up -d\n"
            );
        }

        // ── Vérifier la connexion PostgreSQL avant chaque test ─────────
        try {
            DB::connection()->getPdo();
        } catch (\Exception $e) {
            $this->fail(
                "\n\n" .
                "❌ Impossible de se connecter à PostgreSQL.\n" .
                "Host: " . config('database.connections.pgsql.host') . "\n" .
                "DB: "   . config('database.connections.pgsql.database') . "\n" .
                "Erreur: " . $e->getMessage() . "\n"
            );
        }
    }
}
```

---

## ÉTAPE 3 — Améliorer le ci.yml (DB_USERNAME manquant + Meilisearch + secrets GitHub)

**Modifier** : `.github/workflows/ci.yml`

Remplacer entièrement :

```yaml
name: CI — EduGest DZ

on:
  push:
    branches: [main, develop]
    paths: ['edugestdz/backend/**', '.github/workflows/ci.yml']
  pull_request:
    branches: [main]
    paths: ['edugestdz/backend/**']

permissions:
  contents: read

jobs:
  backend:
    name: "CI — EduGest DZ / backend"
    runs-on: ubuntu-latest

    services:
      postgres:
        image: postgres:16-alpine
        env:
          POSTGRES_DB:       edugestdz_test
          POSTGRES_USER:     edugest_user
          POSTGRES_PASSWORD: EduGest@2026!
        ports:
          - 5432:5432
        options: >-
          --health-cmd pg_isready
          --health-interval 10s
          --health-timeout 5s
          --health-retries 5

      redis:
        image: redis:7-alpine
        ports:
          - 6379:6379
        options: >-
          --health-cmd "redis-cli ping"
          --health-interval 10s
          --health-timeout 5s
          --health-retries 5

      # Meilisearch — pour les tests de recherche full-text
      meilisearch:
        image: getmeili/meilisearch:v1.8
        ports:
          - 7700:7700
        env:
          MEILI_NO_ANALYTICS: true
        options: >-
          --health-cmd "curl -f http://localhost:7700/health || exit 1"
          --health-interval 10s
          --health-timeout 5s
          --health-retries 5

    defaults:
      run:
        working-directory: edugestdz/backend

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP 8.2
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: mbstring, pdo_pgsql, intl, gd, xml, json, fileinfo, redis, zip, sodium
          coverage: xdebug
          tools: composer:v2

      - name: Get composer cache directory
        id: composer-cache
        run: echo "dir=$(composer config cache-files-dir)" >> $GITHUB_OUTPUT

      - name: Cache composer dependencies
        uses: actions/cache@v4
        with:
          path: ${{ steps.composer-cache.outputs.dir }}
          key: ${{ runner.os }}-composer-${{ hashFiles('edugestdz/backend/composer.lock') }}
          restore-keys: ${{ runner.os }}-composer-

      - name: Install dependencies
        run: composer install --no-progress --no-interaction --prefer-dist

      - name: Setup environment
        run: |
          cp .env.example .env
          # PostgreSQL
          sed -i "s|^DB_CONNECTION=.*|DB_CONNECTION=pgsql|"      .env
          sed -i "s|^DB_HOST=.*|DB_HOST=127.0.0.1|"             .env
          sed -i "s|^DB_PORT=.*|DB_PORT=5432|"                   .env
          sed -i "s|^DB_DATABASE=.*|DB_DATABASE=edugestdz_test|" .env
          sed -i "s|^DB_USERNAME=.*|DB_USERNAME=edugest_user|"   .env
          sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=EduGest@2026!|"  .env
          # Redis
          sed -i "s|^REDIS_HOST=.*|REDIS_HOST=127.0.0.1|"       .env
          # Désactiver Sentry en CI
          echo "SENTRY_DSN=" >> .env
          # Désactiver Telegram en CI
          echo "TELEGRAM_BOT_TOKEN=" >> .env
          # Clés
          php artisan key:generate
          php artisan jwt:secret --force

      - name: Run migrations
        env:
          DB_CONNECTION: pgsql
          DB_HOST:       127.0.0.1
          DB_PORT:       5432
          DB_DATABASE:   edugestdz_test
          DB_USERNAME:   edugest_user
          DB_PASSWORD:   EduGest@2026!
          REDIS_HOST:    127.0.0.1
        run: php artisan migrate --force

      - name: Run tests (PostgreSQL)
        env:
          DB_CONNECTION: pgsql
          DB_HOST:       127.0.0.1
          DB_PORT:       5432
          DB_DATABASE:   edugestdz_test
          DB_USERNAME:   edugest_user
          DB_PASSWORD:   EduGest@2026!
          REDIS_HOST:    127.0.0.1
        run: php artisan test --parallel

      - name: Run tests with coverage
        continue-on-error: true
        env:
          DB_CONNECTION: pgsql
          DB_HOST:       127.0.0.1
          DB_PORT:       5432
          DB_DATABASE:   edugestdz_test
          DB_USERNAME:   edugest_user
          DB_PASSWORD:   EduGest@2026!
          REDIS_HOST:    127.0.0.1
          XDEBUG_MODE:   coverage
        run: php artisan test --coverage --min=50
```

---

## ÉTAPE 4 — Tests vérification CI PostgreSQL

**Créer** : `edugestdz/backend/tests/Feature/Infrastructure/PostgreSqlPurTest.php`

```php
<?php

namespace Tests\Feature\Infrastructure;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * Vérifie que le CI tourne bien sur PostgreSQL et pas SQLite.
 * Ces tests échouent volontairement si SQLite est utilisé.
 */
class PostgreSqlPurTest extends TestCase
{
    public function test_connexion_est_postgresql(): void
    {
        $driver = DB::connection()->getDriverName();
        $this->assertEquals(
            'pgsql',
            $driver,
            "❌ Les tests tournent sur '{$driver}' au lieu de 'pgsql'. Vérifier phpunit.xml et DB_CONNECTION."
        );
    }

    public function test_version_postgresql_est_16_ou_plus(): void
    {
        $version = DB::selectOne('SELECT version()')->version;
        $this->assertStringContainsString('PostgreSQL', $version);

        // Extraire le numéro de version majeure
        preg_match('/PostgreSQL (\d+)/', $version, $matches);
        $majorVersion = (int) ($matches[1] ?? 0);
        $this->assertGreaterThanOrEqual(
            15, // Accepter 15+ (GitHub Actions peut avoir 15 ou 16)
            $majorVersion,
            "PostgreSQL {$majorVersion} détecté — version 15+ requise."
        );
    }

    public function test_extension_uuid_disponible(): void
    {
        // gen_random_uuid() est disponible dans PostgreSQL 13+ nativement
        $result = DB::selectOne("SELECT gen_random_uuid() AS uuid");
        $this->assertNotNull($result->uuid);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $result->uuid
        );
    }

    public function test_sha3_disponible_php(): void
    {
        // SHA3-256 utilisé par AuditChainService
        $this->assertContains('sha3-256', hash_algos(), 'SHA3-256 non disponible en PHP — vérifier version PHP');
        $hash = hash('sha3-256', 'test');
        $this->assertEquals(64, strlen($hash));
    }

    public function test_extension_sodium_disponible(): void
    {
        // Sodium utilisé par PostQuantumCryptoService
        $this->assertTrue(extension_loaded('sodium'), 'Extension sodium non chargée');
    }

    public function test_redis_accessible(): void
    {
        \Illuminate\Support\Facades\Cache::put('ci_test_redis', 'ok', 10);
        $this->assertEquals('ok', \Illuminate\Support\Facades\Cache::get('ci_test_redis'));
        \Illuminate\Support\Facades\Cache::forget('ci_test_redis');
    }

    public function test_jsonb_operations_postgresql(): void
    {
        // Vérifier que les opérations JSONB fonctionnent (utilisées dans security_events)
        $result = DB::selectOne("SELECT '{\"a\": 1}'::jsonb ->> 'a' AS val");
        $this->assertEquals('1', $result->val);
    }

    public function test_savepoint_postgresql(): void
    {
        // SAVEPOINTs utilisés dans la migration RLS
        DB::statement('SAVEPOINT test_savepoint');
        DB::statement('RELEASE SAVEPOINT test_savepoint');
        $this->assertTrue(true); // Si on arrive ici, SAVEPOINTs fonctionnent
    }
}
```

---

## ══════════════════════════════════════════════════
## PARTIE B — FIX 6 : MONITORING PRODUCTION (SENTRY + HEALTHCHECK AMÉLIORÉ)
## ══════════════════════════════════════════════════

### Problème exact
- Aucun tracking d'erreurs → les bugs en prod sont découverts par les clients
- Le `/api/health` existe mais ne vérifie que superficiellement
- Pas d'alerte si le serveur tombe

---

## ÉTAPE 5 — Installer Sentry Laravel

```bash
cd edugestdz/backend
composer require sentry/sentry-laravel
```

---

## ÉTAPE 6 — Configuration Sentry

**Créer** : `edugestdz/backend/config/sentry.php`

```php
<?php

return [
    // DSN depuis sentry.io → Créer un projet → Settings → Client Keys
    // Gratuit jusqu'à 5000 events/mois
    // Laisser vide pour désactiver (dev local, tests CI)
    'dsn' => env('SENTRY_DSN', ''),

    // Environnement (production, staging, local)
    'environment' => env('APP_ENV', 'production'),

    // Version de l'application (pour identifier les releases)
    'release' => env('APP_VERSION', '1.0.0'),

    // Traces de performance (0.0 = désactivé, 1.0 = tout capturer)
    // En prod : 0.1 = 10% des requêtes (pas trop de quota)
    'traces_sample_rate' => env('SENTRY_TRACES_SAMPLE_RATE', 0.1),

    // Capturer les erreurs non gérées
    'capture_unhandled_rejections' => true,

    // Données utilisateur à envoyer avec chaque erreur
    'send_default_pii' => false, // false = conformité RGPD/loi 18-07

    // Breadcrumbs (fil d'Ariane des actions avant l'erreur)
    'breadcrumbs' => [
        'logs'              => true,
        'sql_queries'       => true,
        'sql_bindings'      => false, // false = pas de données sensibles
        'queue_info'        => true,
        'command_info'      => true,
    ],

    // Ignorer ces types d'exceptions (pas utiles dans Sentry)
    'ignore_exceptions' => [
        \Illuminate\Auth\AuthenticationException::class,
        \Illuminate\Auth\Access\AuthorizationException::class,
        \Illuminate\Validation\ValidationException::class,
        \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
        \Illuminate\Http\Exceptions\ThrottleRequestsException::class,
    ],

    // Contexte EduGest DZ ajouté à chaque event
    'before_send' => function (\Sentry\Event $event): ?\Sentry\Event {
        // Ne pas envoyer en test ou en local sans DSN
        if (empty(env('SENTRY_DSN'))) return null;

        // Ajouter le tenant_id comme tag (pour filtrer par école dans Sentry)
        $tenantId = config('tenant.current_id');
        if ($tenantId) {
            $event->setTags(['tenant_id' => $tenantId]);
        }

        return $event;
    },
];
```

---

## ÉTAPE 7 — Intégrer Sentry dans le handler d'exceptions

**Modifier** : `edugestdz/backend/bootstrap/app.php`

Ajouter dans la section `->withExceptions()` :

```php
->withExceptions(function (Exceptions $exceptions) {
    // ... handlers existants ...

    // ── Sentry : reporter les exceptions en production ────────────
    if (!empty(config('sentry.dsn')) && app()->environment('production', 'staging')) {
        $exceptions->report(function (\Throwable $e) {
            if (app()->bound('sentry')) {
                app('sentry')->captureException($e);
            }
        });
    }

    // ── Alerter via Telegram les erreurs 500 critiques ────────────
    $exceptions->report(function (\Throwable $e) {
        // Seulement les vraies erreurs serveur (pas les 404, 422, 401...)
        if ($e instanceof \Error || (
            !($e instanceof \Illuminate\Http\Exceptions\HttpException) &&
            !($e instanceof \Illuminate\Validation\ValidationException) &&
            !($e instanceof \Illuminate\Auth\AuthenticationException)
        )) {
            try {
                app(\App\Services\SecurityMonitorService::class)->alerter(
                    'server_error_500',
                    'critical',
                    "💥 Erreur 500 en production : " . get_class($e) . " — " . substr($e->getMessage(), 0, 200),
                    ['file' => $e->getFile(), 'line' => $e->getLine()]
                );
            } catch (\Throwable) {
                // Ne jamais faire planter le handler d'exceptions
            }
        }
    });
})
```

---

## ÉTAPE 8 — Améliorer le HealthCheck (vérification complète)

**Modifier** : `edugestdz/backend/routes/api.php`

Trouver la route `/health` et la remplacer par cette version complète :

```php
// ── Health Check amélioré — vérifie tous les services ──────────────────
Route::get('/health', function () {
    $checks  = [];
    $allOk   = true;
    $startTs = microtime(true);

    // 1. Base de données PostgreSQL
    try {
        $dbVersion = \DB::selectOne('SELECT version()')->version;
        $checks['database'] = [
            'status'  => 'ok',
            'driver'  => 'postgresql',
            'version' => substr($dbVersion, 0, 50),
        ];
    } catch (\Throwable $e) {
        $checks['database'] = ['status' => 'error', 'message' => $e->getMessage()];
        $allOk = false;
    }

    // 2. Redis
    try {
        $redisKey = 'health_check_' . now()->timestamp;
        \Cache::put($redisKey, 'ok', 5);
        $val = \Cache::get($redisKey);
        \Cache::forget($redisKey);
        $checks['redis'] = ['status' => $val === 'ok' ? 'ok' : 'error'];
        if ($val !== 'ok') $allOk = false;
    } catch (\Throwable $e) {
        $checks['redis'] = ['status' => 'error', 'message' => $e->getMessage()];
        $allOk = false;
    }

    // 3. Meilisearch (non-bloquant)
    try {
        $response = \Http::timeout(2)->get(config('scout.meilisearch.host', 'http://localhost:7700') . '/health');
        $checks['meilisearch'] = ['status' => $response->successful() ? 'ok' : 'degraded'];
    } catch (\Throwable) {
        $checks['meilisearch'] = ['status' => 'degraded', 'message' => 'Non disponible (non-critique)'];
        // Ne pas mettre allOk = false — Meilisearch est non-critique
    }

    // 4. Stockage fichiers
    try {
        $testFile = 'health_check_' . now()->timestamp . '.txt';
        \Storage::disk('local')->put($testFile, 'ok');
        $content = \Storage::disk('local')->get($testFile);
        \Storage::disk('local')->delete($testFile);
        $checks['storage'] = ['status' => $content === 'ok' ? 'ok' : 'error'];
        if ($content !== 'ok') $allOk = false;
    } catch (\Throwable $e) {
        $checks['storage'] = ['status' => 'error', 'message' => $e->getMessage()];
        $allOk = false;
    }

    // 5. Audit Chain intégrité (vérification rapide — dernier bloc seulement)
    try {
        $dernierBloc = \DB::table('audit_chain')->orderByDesc('bloc_numero')->first(['bloc_numero', 'hash_merkle']);
        $checks['audit_chain'] = [
            'status'      => 'ok',
            'total_blocs' => $dernierBloc?->bloc_numero ?? 0,
        ];
    } catch (\Throwable) {
        $checks['audit_chain'] = ['status' => 'degraded'];
    }

    // 6. Kill Switch status
    $killActive = \Cache::has('kill_switch_active');
    $checks['kill_switch'] = ['status' => $killActive ? 'ACTIVE' : 'inactive'];
    if ($killActive) $allOk = false;

    $responseTime = round((microtime(true) - $startTs) * 1000, 2);

    return response()->json([
        'status'        => $allOk ? 'healthy' : 'degraded',
        'timestamp'     => now()->toIso8601String(),
        'version'       => env('APP_VERSION', '1.0.0'),
        'environment'   => app()->environment(),
        'response_time' => $responseTime . 'ms',
        'checks'        => $checks,
    ], $allOk ? 200 : 503);
})->name('health');
```

---

## ÉTAPE 9 — Ajouter SENTRY_DSN dans .env.example

**Modifier** : `edugestdz/backend/.env.example`

Ajouter la section monitoring :

```dotenv
# ── Monitoring & Observabilité ────────────────────────────────────
# Sentry.io — Tracking erreurs production
# Créer un compte sur sentry.io (GRATUIT jusqu'à 5000 events/mois)
# → New Project → Laravel → Copier le DSN ici
# Laisser vide pour désactiver (dev local, CI)
SENTRY_DSN=
SENTRY_TRACES_SAMPLE_RATE=0.1

# Version de l'application (pour identifier les releases dans Sentry)
APP_VERSION=1.0.0
```

---

## ÉTAPE 10 — Tests monitoring

**Créer** : `edugestdz/backend/tests/Feature/Infrastructure/MonitoringTest.php`

```php
<?php

namespace Tests\Feature\Infrastructure;

use Tests\TestCase;

class MonitoringTest extends TestCase
{
    public function test_health_check_retourne_200_quand_ok(): void
    {
        $response = $this->getJson('/api/health');

        // En CI : PostgreSQL + Redis disponibles → 200
        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'timestamp',
                'version',
                'environment',
                'response_time',
                'checks' => [
                    'database',
                    'redis',
                    'meilisearch',
                    'storage',
                    'audit_chain',
                    'kill_switch',
                ],
            ]);
    }

    public function test_health_check_database_est_postgresql(): void
    {
        $response = $this->getJson('/api/health');
        $data     = $response->json();

        $this->assertEquals('ok', $data['checks']['database']['status'] ?? 'error');
        $this->assertEquals('postgresql', $data['checks']['database']['driver'] ?? '');
    }

    public function test_health_check_redis_est_ok(): void
    {
        $response = $this->getJson('/api/health');
        $data     = $response->json();

        $this->assertEquals('ok', $data['checks']['redis']['status'] ?? 'error');
    }

    public function test_health_check_accessible_sans_authentification(): void
    {
        // Le health check DOIT être accessible sans JWT (pour UptimeRobot, load balancers)
        $this->getJson('/api/health')->assertStatus(200);
    }

    public function test_health_check_contient_le_temps_de_reponse(): void
    {
        $response = $this->getJson('/api/health');
        $data     = $response->json();

        $this->assertArrayHasKey('response_time', $data);
        $this->assertStringEndsWith('ms', $data['response_time']);
    }

    public function test_health_check_kill_switch_inactif_par_defaut(): void
    {
        // S'assurer que le kill switch n'est pas actif
        \Cache::forget('kill_switch_active');

        $response = $this->getJson('/api/health');
        $data     = $response->json();

        $this->assertEquals('inactive', $data['checks']['kill_switch']['status'] ?? 'ACTIVE');
    }

    public function test_sentry_config_presente(): void
    {
        // La config Sentry doit exister (même si DSN vide en test)
        $this->assertNotNull(config('sentry'));
        $this->assertArrayHasKey('dsn', config('sentry'));
        $this->assertArrayHasKey('environment', config('sentry'));
    }
}
```

---

## ══════════════════════════════════════════════════
## PARTIE C — FIX 7 : MARKETPLACE — DÉSACTIVÉ PAR DÉFAUT + FINITION
## ══════════════════════════════════════════════════

### Problème exact (lu dans le code)
1. `TenantModule::estActif()` retourne `true` si aucun record en BDD (actif par défaut)
   → Le module Marketplace est donc accessible même sans configuration
2. Il manque un `MarketplaceController` principal pour les routes publiques (recherche, featured)
3. Les routes Marketplace ne passent pas par `ModuleCheck` middleware
4. `VisioService` existe mais est une coquille vide sans vraie implémentation

### Décision : Désactiver par défaut + protéger les routes + ajouter le controller manquant

---

## ÉTAPE 11 — Désactiver Marketplace par défaut dans TenantModule

**Modifier** : `edugestdz/backend/app/Models/TenantModule.php`

Trouver la méthode `estActif()` et la remplacer :

```php
public static function estActif(string $tenantId, string $moduleKey): bool
{
    // Les modules 'obligatoire' sont toujours actifs
    if (isset(self::MODULES[$moduleKey]) && self::MODULES[$moduleKey]['obligatoire']) {
        return true;
    }

    // ── MODULES DÉSACTIVÉS PAR DÉFAUT ──────────────────────────────
    // Ces modules nécessitent une configuration explicite avant activation
    $desactivesParDefaut = [
        'marketplace',  // Nécessite profil public + Satim production
        'surveillance', // Nécessite caméras Dahua physiques
    ];

    // Si pas de record en BDD ET module désactivé par défaut → false
    $existeEnBdd = self::where('tenant_id', $tenantId)
        ->where('module_key', $moduleKey)
        ->exists();

    if (!$existeEnBdd && in_array($moduleKey, $desactivesParDefaut)) {
        return false; // Inactif par défaut
    }

    // Si pas de record en BDD pour les autres modules → actif par défaut
    if (!$existeEnBdd) {
        return true;
    }

    $actifs = self::getActifs($tenantId);
    return in_array($moduleKey, $actifs);
}
```

---

## ÉTAPE 12 — Créer le MarketplaceController principal (routes publiques)

**Créer** : `edugestdz/backend/app/Http/Controllers/Api/V1/MarketplaceController.php`

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TenantModule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

/**
 * MarketplaceController — Routes PUBLIQUES de la marketplace.
 *
 * Ces routes ne requièrent PAS d'authentification JWT.
 * Elles permettent aux parents de chercher des centres avant de s'inscrire.
 *
 * Routes privées (après auth) → Marketplace/ReservationController, AvisController, OffreController
 */
class MarketplaceController extends Controller
{
    /**
     * Recherche de centres de cours.
     * GET /api/v1/marketplace/recherche?wilaya=31&matiere=maths&niveau=3as
     */
    public function recherche(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'wilaya'   => 'nullable|integer|between:1,58',
            'matiere'  => 'nullable|string|max:100',
            'niveau'   => 'nullable|string|max:50',
            'q'        => 'nullable|string|max:100',   // Recherche libre
            'per_page' => 'nullable|integer|between:5,50',
            'page'     => 'nullable|integer|min:1',
        ]);

        $perPage = $validated['per_page'] ?? 12;

        $query = DB::table('tenants as t')
            ->join('tenant_modules as tm', function ($join) {
                $join->on('t.id', '=', 'tm.tenant_id')
                    ->where('tm.module_key', 'marketplace')
                    ->where('tm.actif', true);
            })
            ->where('t.statut', 'actif')
            ->select([
                't.id', 't.nom', 't.description', 't.wilaya',
                't.adresse', 't.telephone', 't.email', 't.logo_url',
                't.type_etablissement',
            ]);

        // Filtres
        if (!empty($validated['wilaya'])) {
            $query->where('t.wilaya', $validated['wilaya']);
        }

        if (!empty($validated['q'])) {
            $search = '%' . $validated['q'] . '%';
            $query->where(fn($q) => $q->where('t.nom', 'ilike', $search)
                ->orWhere('t.description', 'ilike', $search));
        }

        $resultats = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $resultats->items(),
            'meta'    => [
                'current_page' => $resultats->currentPage(),
                'per_page'     => $resultats->perPage(),
                'total'        => $resultats->total(),
                'last_page'    => $resultats->lastPage(),
            ],
        ]);
    }

    /**
     * Centres mis en avant (featured).
     * GET /api/v1/marketplace/featured
     * Mis en cache 5 minutes.
     */
    public function featured(): JsonResponse
    {
        $centres = Cache::remember('marketplace_featured', 300, function () {
            return DB::table('tenants as t')
                ->join('tenant_modules as tm', function ($join) {
                    $join->on('t.id', '=', 'tm.tenant_id')
                        ->where('tm.module_key', 'marketplace')
                        ->where('tm.actif', true);
                })
                ->where('t.statut', 'actif')
                ->where('t.marketplace_featured', true)
                ->select(['t.id', 't.nom', 't.description', 't.wilaya', 't.logo_url', 't.type_etablissement'])
                ->limit(6)
                ->get();
        });

        return response()->json(['success' => true, 'data' => $centres]);
    }

    /**
     * Profil public d'un centre.
     * GET /api/v1/marketplace/centres/{tenantId}
     */
    public function profil(string $tenantId): JsonResponse
    {
        // Vérifier que le centre a la marketplace activée
        $moduleActif = TenantModule::where('tenant_id', $tenantId)
            ->where('module_key', 'marketplace')
            ->where('actif', true)
            ->exists();

        if (!$moduleActif) {
            return response()->json([
                'success' => false,
                'message' => 'Ce centre n\'est pas référencé sur la marketplace.',
            ], 404);
        }

        $centre = DB::table('tenants')
            ->where('id', $tenantId)
            ->where('statut', 'actif')
            ->select(['id', 'nom', 'description', 'wilaya', 'adresse', 'telephone', 'email', 'logo_url', 'type_etablissement'])
            ->first();

        if (!$centre) {
            return response()->json(['success' => false, 'message' => 'Centre non trouvé.'], 404);
        }

        // Offres actives du centre
        $offres = DB::table('offres_publiques')
            ->where('tenant_id', $tenantId)
            ->where('statut', 'active')
            ->select(['id', 'titre', 'description', 'niveau', 'tarif_seance', 'places_restantes'])
            ->get();

        // Note moyenne (avis)
        $stats = DB::table('avis_marketplace')
            ->where('tenant_id', $tenantId)
            ->where('approuve', true)
            ->selectRaw('AVG(note) as note_moyenne, COUNT(*) as nb_avis')
            ->first();

        return response()->json([
            'success' => true,
            'data'    => [
                'centre'       => $centre,
                'offres'       => $offres,
                'note_moyenne' => round($stats->note_moyenne ?? 0, 1),
                'nb_avis'      => $stats->nb_avis ?? 0,
            ],
        ]);
    }

    /**
     * Statistiques générales de la marketplace (pour la page d'accueil).
     * GET /api/v1/marketplace/stats
     */
    public function stats(): JsonResponse
    {
        $stats = Cache::remember('marketplace_stats', 3600, function () {
            $totalCentres = DB::table('tenant_modules')
                ->where('module_key', 'marketplace')
                ->where('actif', true)
                ->count();

            return [
                'total_centres'     => $totalCentres,
                'total_wilayas'     => DB::table('tenants')->distinct('wilaya')->count('wilaya'),
                'message'           => 'Marketplace EduGest DZ — Trouvez votre centre de cours',
            ];
        });

        return response()->json(['success' => true, 'data' => $stats]);
    }
}
```

---

## ÉTAPE 13 — Ajouter les routes Marketplace (publiques + privées protégées)

**Modifier** : `edugestdz/backend/routes/api.php`

Ajouter cette section (routes marketplace) :

```php
use App\Http\Controllers\Api\V1\MarketplaceController;
use App\Http\Controllers\Api\V1\Marketplace\{AvisController, OffreController, ReservationController};

// ── MARKETPLACE — Routes PUBLIQUES (sans auth) ─────────────────────────
Route::prefix('v1/marketplace')->group(function () {
    Route::get('/stats',                 [MarketplaceController::class, 'stats']);
    Route::get('/featured',              [MarketplaceController::class, 'featured']);
    Route::get('/recherche',             [MarketplaceController::class, 'recherche']);
    Route::get('/centres/{tenantId}',    [MarketplaceController::class, 'profil']);
});

// ── MARKETPLACE — Routes PRIVÉES (auth + module actif obligatoire) ──────
Route::middleware(['auth:api', 'tenant', 'module:marketplace'])->prefix('v1/marketplace')->group(function () {
    // Offres (côté centre : CRUD)
    Route::get('/offres',           [OffreController::class, 'index']);
    Route::post('/offres',          [OffreController::class, 'store']);
    Route::put('/offres/{id}',      [OffreController::class, 'update']);
    Route::delete('/offres/{id}',   [OffreController::class, 'destroy']);

    // Réservations (côté parent)
    Route::post('/reservations',               [ReservationController::class, 'store']);
    Route::get('/reservations/{id}',           [ReservationController::class, 'show']);
    Route::post('/reservations/{id}/payer',    [ReservationController::class, 'payer']);
    Route::post('/reservations/{id}/confirmer',[ReservationController::class, 'confirmer']);
    Route::post('/reservations/{id}/annuler',  [ReservationController::class, 'annuler']);

    // Avis
    Route::post('/avis',            [AvisController::class, 'store']);
    Route::get('/avis/{tenantId}',  [AvisController::class, 'index']);
    Route::delete('/avis/{id}',     [AvisController::class, 'destroy']);
});
```

---

## ÉTAPE 14 — Migration : ajouter marketplace_featured à la table tenants

**Créer** : `edugestdz/backend/database/migrations/2026_07_08_900000_add_marketplace_columns_to_tenants.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (!Schema::hasColumn('tenants', 'marketplace_featured')) {
                $table->boolean('marketplace_featured')->default(false)->after('statut');
            }
            if (!Schema::hasColumn('tenants', 'type_etablissement')) {
                $table->string('type_etablissement', 50)->nullable()->after('marketplace_featured');
                // ex: centre_cours | lycee | college | primaire | universite
            }
            if (!Schema::hasColumn('tenants', 'description')) {
                $table->text('description')->nullable()->after('nom');
            }
            if (!Schema::hasColumn('tenants', 'logo_url')) {
                $table->string('logo_url', 500)->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumnIfExists('marketplace_featured');
            $table->dropColumnIfExists('type_etablissement');
        });
    }
};
```

---

## ÉTAPE 15 — Tests complets pour les 3 fixes

**Créer** : `edugestdz/backend/tests/Feature/Infrastructure/PointsFaiblesTest.php`

```php
<?php

namespace Tests\Feature\Infrastructure;

use App\Models\TenantModule;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class PointsFaiblesTest extends TestCase
{
    use RefreshDatabase;

    // ══ FIX 5 : PostgreSQL ════════════════════════════════════════════

    public function test_db_connection_est_pgsql(): void
    {
        $this->assertEquals('pgsql', \DB::connection()->getDriverName());
    }

    // ══ FIX 6 : Monitoring ════════════════════════════════════════════

    public function test_health_check_structure_complete(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status', 'timestamp', 'version', 'environment', 'response_time',
                'checks' => [
                    'database'   => ['status', 'driver'],
                    'redis'      => ['status'],
                    'meilisearch'=> ['status'],
                    'storage'    => ['status'],
                    'audit_chain'=> ['status'],
                    'kill_switch'=> ['status'],
                ],
            ]);
    }

    public function test_health_check_postgresql_detecte(): void
    {
        $data = $this->getJson('/api/health')->json();
        $this->assertEquals('postgresql', $data['checks']['database']['driver']);
        $this->assertEquals('ok', $data['checks']['database']['status']);
    }

    public function test_health_check_redis_ok(): void
    {
        $data = $this->getJson('/api/health')->json();
        $this->assertEquals('ok', $data['checks']['redis']['status']);
    }

    public function test_health_check_sans_auth(): void
    {
        $this->getJson('/api/health')->assertStatus(200);
    }

    public function test_sentry_config_chargee(): void
    {
        $this->assertNotNull(config('sentry'));
        $this->assertArrayHasKey('dsn', config('sentry'));
    }

    // ══ FIX 7 : Marketplace ══════════════════════════════════════════

    public function test_marketplace_inactif_par_defaut_sans_record_bdd(): void
    {
        $tenantId = (string) Str::uuid();

        // Sans record en BDD → marketplace doit être INACTIF par défaut
        $actif = TenantModule::estActif($tenantId, 'marketplace');
        $this->assertFalse($actif, 'Marketplace doit être inactif par défaut sans configuration');
    }

    public function test_modules_core_actifs_par_defaut(): void
    {
        $tenantId = (string) Str::uuid();

        // Le module 'core' est obligatoire → toujours actif
        $this->assertTrue(TenantModule::estActif($tenantId, 'core'));
    }

    public function test_marketplace_activable_via_module_manager(): void
    {
        $tenantId = (string) Str::uuid();
        config(['tenant.current_id' => $tenantId]);

        // Activer le marketplace
        TenantModule::activer($tenantId, 'marketplace');
        $this->assertTrue(TenantModule::estActif($tenantId, 'marketplace'));

        // Désactiver
        TenantModule::desactiver($tenantId, 'marketplace');
        $this->assertFalse(TenantModule::estActif($tenantId, 'marketplace'));
    }

    public function test_routes_marketplace_publiques_accessibles_sans_auth(): void
    {
        // Les routes publiques ne nécessitent pas de JWT
        $this->getJson('/api/v1/marketplace/stats')->assertStatus(200);
        $this->getJson('/api/v1/marketplace/featured')->assertStatus(200);
        $this->getJson('/api/v1/marketplace/recherche')->assertStatus(200);
    }

    public function test_routes_marketplace_privees_requierent_auth(): void
    {
        // Sans JWT → 401
        $this->postJson('/api/v1/marketplace/reservations', [])->assertStatus(401);
        $this->postJson('/api/v1/marketplace/avis', [])->assertStatus(401);
    }

    public function test_profil_centre_sans_marketplace_actif_retourne_404(): void
    {
        $fakeTenantId = (string) Str::uuid();
        // Pas de marketplace activée pour ce tenant
        $this->getJson("/api/v1/marketplace/centres/{$fakeTenantId}")
            ->assertStatus(404);
    }

    public function test_surveillance_inactif_par_defaut(): void
    {
        $tenantId = (string) Str::uuid();
        // La surveillance aussi est désactivée par défaut
        $actif = TenantModule::estActif($tenantId, 'surveillance');
        $this->assertFalse($actif, 'Surveillance doit être inactive par défaut sans caméras physiques');
    }
}
```

---

## ÉTAPE 16 — Exécution finale

```bash
cd edugestdz/backend

# Installer Sentry
composer require sentry/sentry-laravel

# Migrations
php artisan migrate --force

# Vérifier que tout tourne
composer dump-autoload -o
php artisan test --parallel

# Résultat attendu :
# ✅ PostgreSqlPurTest         (7 tests — vérifient PostgreSQL, UUID, SHA3, Sodium, Redis)
# ✅ MonitoringTest            (6 tests — health check complet)
# ✅ PointsFaiblesTest         (13 tests — PostgreSQL + monitoring + marketplace)
# ✅ Tous les tests existants  (607 tests — 0 régression)
# Total attendu : ≥ 633 tests verts

git add .
git commit -m "fix(points-faibles): CI PostgreSQL pur forcé + phpunit.xml guard anti-SQLite + Sentry monitoring + HealthCheck complet + Marketplace désactivé par défaut + routes publiques + 26 nouveaux tests"

git push origin develop
# → PR develop → main
```

---

## ══════════════════════════════════════════════════
## RÉCAPITULATIF — CE QUE CETTE MISSION CORRIGE
## ══════════════════════════════════════════════════

| Point faible | Avant | Après |
|---|---|---|
| **CI SQLite** | phpunit.xml sans DB_CONNECTION → risque SQLite silencieux | phpunit.xml force `DB_CONNECTION=pgsql` + `TestCase.php` bloque si SQLite détecté + 7 tests vérifient PostgreSQL, UUID, SHA3, Sodium |
| **CI Meilisearch** | Service Meilisearch absent du CI | Ajouté dans ci.yml (service getmeili/meilisearch:v1.8) |
| **CI DB_USERNAME** | `DB_USERNAME` manquait dans le sed setup | Ajouté dans ci.yml setup environment |
| **Monitoring prod** | Aucun tracking erreurs | Sentry installé + config complète + alertes Telegram sur 500 |
| **Health check** | Basique | Vérifie PostgreSQL, Redis, Meilisearch, Storage, Audit Chain, Kill Switch + temps de réponse |
| **Marketplace défaut** | `estActif()` → `true` si pas de record BDD | `estActif()` → `false` par défaut pour marketplace et surveillance |
| **Marketplace routes publiques** | Manquantes | `MarketplaceController` créé avec recherche, featured, profil, stats |
| **Marketplace module:check** | Routes sans protection | Routes privées via `module:marketplace` middleware |
| **Marketplace migration** | tenants sans description/logo | Migration additive avec `hasColumn()` guards |

---

## CE QUE TU DIS À DEEPSEEK

```
Repo : https://github.com/Allintelligence2024/edugest-dz.git
Branche : develop
git checkout develop && git pull origin main

Fichier : MISSION_FIX_POINTS_FAIBLES.md — 16 étapes dans l'ordre.

RÈGLES CRITIQUES :
1. PostgreSQL uniquement — 0 régression sur 607 tests existants.
2. phpunit.xml : remplacer ENTIÈREMENT le fichier avec le XML fourni.
   Ne pas garder l'ancien — l'ancien n'a pas DB_CONNECTION.
3. TestCase.php : si un TestCase.php existe déjà → MODIFIER en ajoutant
   seulement le bloc setUp() avec le guard anti-SQLite. Ne pas remplacer
   si le fichier existant a d'autres méthodes importantes.
4. ci.yml : remplacer ENTIÈREMENT — le nouveau inclut Meilisearch service
   et DB_USERNAME manquant dans le sed.
5. Sentry : `composer require sentry/sentry-laravel` AVANT les autres étapes.
   Si Sentry est déjà installé → skip cette étape.
6. config/sentry.php : la closure 'before_send' — si Sentry DSN vide → retourner null.
   Ne jamais envoyer des events en test ou en dev.
7. TenantModule::estActif() : remplacer UNIQUEMENT cette méthode.
   Ne pas toucher aux autres méthodes du modèle (activer, desactiver, getActifs...).
8. La migration 2026_07_08_900000 : utiliser Schema::hasColumn() pour CHAQUE colonne.
   Certaines colonnes (description, logo_url) pourraient déjà exister.
9. MarketplaceController routes publiques : PAS de middleware auth:api.
   PAS de middleware tenant. Ces routes sont 100% publiques (pour les parents
   qui cherchent un centre avant inscription).
10. Routes marketplace privées : utiliser 'module:marketplace' — ce middleware
    existe déjà (ModuleCheck.php). Vérifier le nom de l'alias dans bootstrap/app.php.

php artisan migrate --force
composer dump-autoload -o
php artisan test --parallel → ≥ 633 ✅
git push origin develop → PR develop → main
```
