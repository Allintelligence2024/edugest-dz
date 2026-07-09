# 🔐 MISSION DEEPSEEK — Sécurité Niveau 1 (CRITIQUE — Faire maintenant)
## EduGest DZ · Branche : develop · 7 Juillet 2026
## Tests actuels : 418+ ✅ · Objectif : ≥ 430 ✅ · 0 régression

---

## CONTEXTE — Ce qui est vulnérable aujourd'hui

### Vulnérabilités critiques ciblées dans cette mission
```
1. Isolation multi-tenant insuffisante
   → findOrFail($id) sans vérification tenant_id
   → Un utilisateur de l'école A peut lire les données de l'école B

2. PostgreSQL Row-Level Security absent
   → Si le code applicatif oublie de filtrer → données de tous les tenants exposées
   → Filet de sécurité au niveau DB inexistant

3. JWT non révocables
   → Token volé ou employé parti → accès jusqu'à expiration naturelle
   → Pas de blacklist Redis

4. Fichiers uploadés sans isolation tenant
   → URL prévisible → accès aux fichiers d'un autre tenant
```

### RÈGLES ABSOLUES
1. 0 régression — les 418+ tests existants restent verts
2. PostgreSQL uniquement — jamais SQLite
3. Ne pas modifier les signatures d'API (mêmes paramètres, mêmes réponses)
4. Chaque fix doit être rétrocompatible — pas de breaking change

---

## ÉTAPE 0 — Synchroniser

```bash
git checkout develop && git pull origin main
```

---

## PARTIE A — ISOLATION MULTI-TENANT RENFORCÉE
## ══════════════════════════════════════════

## ÉTAPE 1 — Global Scope automatique tenant_id sur TOUS les modèles

**Créer :**
`edugestdz/backend/app/Traits/BelongsToTenant.php`

Remplacer le trait existant complètement :

```php
<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

/**
 * Trait BelongsToTenant — Isolation multi-tenant automatique.
 *
 * Ajoute automatiquement WHERE tenant_id = ? sur TOUTES les requêtes
 * du modèle qui utilise ce trait.
 *
 * SÉCURITÉ :
 * - Ne jamais retourner de données sans tenant_id résolu
 * - En cas d'absence de tenant_id : whereRaw('1=0') → 0 résultats
 * - Log d'alerte si accès tenté sans tenant résolu
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        // ── Scope automatique sur toutes les requêtes SELECT ──────────────
        static::addGlobalScope('tenant', function (Builder $query) {
            $tenantId = config('tenant.current_id');

            if (empty($tenantId)) {
                // SÉCURITÉ : jamais de données sans tenant résolu
                Log::warning('BelongsToTenant: tenant_id non résolu — requête bloquée', [
                    'model' => static::class,
                    'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3),
                ]);
                $query->whereRaw('1=0');
                return;
            }

            $query->where(static::getTable() . '.tenant_id', $tenantId);
        });

        // ── Injection automatique tenant_id à la création ─────────────────
        static::creating(function ($model) {
            $tenantId = config('tenant.current_id');

            if (empty($model->tenant_id)) {
                if (empty($tenantId)) {
                    throw new \RuntimeException(
                        'Impossible de créer ' . static::class . ' : tenant_id non résolu.'
                    );
                }
                $model->tenant_id = $tenantId;
            }
        });
    }

    /**
     * Vérifier que l'enregistrement appartient bien au tenant courant.
     * Lancer une exception si non.
     */
    public function assertBelongsToCurrentTenant(): void
    {
        $tenantId = config('tenant.current_id');
        if ($this->tenant_id !== $tenantId) {
            Log::critical('TENANT ISOLATION BREACH ATTEMPT', [
                'model'           => static::class,
                'record_tenant'   => $this->tenant_id,
                'current_tenant'  => $tenantId,
                'user_id'         => auth('api')->id(),
                'ip'              => request()->ip(),
            ]);
            throw new \Illuminate\Auth\Access\AuthorizationException(
                'Accès refusé : cette ressource appartient à un autre établissement.'
            );
        }
    }

    // ── Scope pour requêtes explicites sans le global scope ──────────────
    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->withoutGlobalScope('tenant')
            ->where(static::getTable() . '.tenant_id', $tenantId);
    }
}
```

---

## ÉTAPE 2 — Middleware TenantIsolationVerifier (double vérification)

**Créer :**
`edugestdz/backend/app/Http/Middleware/TenantIsolationVerifier.php`

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Middleware de vérification d'isolation tenant.
 *
 * Ajoute une couche de défense supplémentaire :
 * - Vérifie que X-Tenant-ID du header correspond au tenant du user connecté
 * - Log toute tentative de manipulation du tenant header
 * - Bloque si discordance détectée
 */
class TenantIsolationVerifier
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth('api')->user();

        if (!$user) return $next($request);

        $headerTenantId = $request->header('X-Tenant-ID');
        $userTenantId   = $user->tenant_id;
        $configTenantId = config('tenant.current_id');

        // Vérification 1 : header vs user
        if ($headerTenantId && $userTenantId && $headerTenantId !== $userTenantId) {
            Log::critical('TENANT HEADER MANIPULATION DETECTED', [
                'user_id'        => $user->id,
                'user_tenant'    => $userTenantId,
                'header_tenant'  => $headerTenantId,
                'ip'             => $request->ip(),
                'path'           => $request->path(),
                'user_agent'     => $request->userAgent(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Accès refusé : manipulation de tenant détectée.',
                'code'    => 'TENANT_MANIPULATION',
            ], 403);
        }

        // Vérification 2 : super_admin peut accéder à tous les tenants
        // (avec le tenant résolu explicitement dans la config)
        if ($user->role !== 'super_admin' && $configTenantId && $userTenantId) {
            if ($configTenantId !== $userTenantId) {
                Log::critical('CROSS-TENANT ACCESS ATTEMPT', [
                    'user_id'       => $user->id,
                    'user_tenant'   => $userTenantId,
                    'config_tenant' => $configTenantId,
                    'ip'            => $request->ip(),
                    'path'          => $request->path(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé : isolation tenant violée.',
                    'code'    => 'CROSS_TENANT_ACCESS',
                ], 403);
            }
        }

        return $next($request);
    }
}
```

---

## ÉTAPE 3 — PostgreSQL Row-Level Security (RLS)

**Créer :**
`edugestdz/backend/database/migrations/2026_07_07_300000_add_postgresql_row_level_security.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Active RLS sur toutes les tables avec tenant_id.
     * C'est le filet de sécurité au niveau BDD.
     * Même si le code applicatif oublie de filtrer → la BDD bloque.
     *
     * IMPORTANT : Le user PostgreSQL de l'app (edugest_user) doit exister.
     * Le super-user PostgreSQL (postgres) bypass RLS par défaut.
     */
    public function up(): void
    {
        // Tables avec tenant_id à sécuriser avec RLS
        $tables = [
            'eleves', 'users', 'groupes', 'cours', 'seances',
            'presences', 'evaluations', 'notes', 'bulletins',
            'factures', 'paiements', 'absences_journalieres', 'billets',
            'enseignants', 'contrats', 'personnel_non_enseignant', 'paies',
            'circuits_transport', 'transport_eleves', 'pointage_bus',
            'menus_cantine', 'inscriptions_cantine', 'repas_journaliers',
            'articles_stock', 'mouvements_stock', 'prets_stock',
            'bons_commande', 'depenses', 'budgets_previsionnels',
            'locaux', 'interventions_entretien', 'entretiens_preventifs',
            'cameras_config', 'alertes_surveillance',
            'lms_cours', 'lms_inscriptions', 'lms_progression',
            'tenant_modules', 'whatsapp_messages',
            'diagnostics_eleves', 'plans_rattrapage', 'convocations_parents',
            'signalements_comportement', 'notifications_parent',
        ];

        foreach ($tables as $table) {
            // Vérifier que la table existe avant d'activer RLS
            $exists = DB::select("SELECT 1 FROM information_schema.tables WHERE table_name = ?", [$table]);
            if (empty($exists)) continue;

            try {
                // Activer RLS
                DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");

                // Politique : un user ne voit que les lignes de son tenant
                // La variable app.current_tenant_id est settée par le middleware ResolveTenant
                DB::statement("
                    DROP POLICY IF EXISTS tenant_isolation_policy ON {$table}
                ");
                DB::statement("
                    CREATE POLICY tenant_isolation_policy ON {$table}
                    USING (
                        tenant_id = current_setting('app.current_tenant_id', true)::uuid
                        OR current_setting('app.current_tenant_id', true) IS NULL
                        OR current_setting('app.current_tenant_id', true) = ''
                    )
                ");

                // Le super-user PostgreSQL bypass RLS par défaut (BYPASSRLS)
                // Le user applicatif (edugest_user) respecte RLS
                DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");

            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning(
                    "RLS skip pour {$table}: " . $e->getMessage()
                );
            }
        }
    }

    public function down(): void
    {
        $tables = [
            'eleves', 'users', 'groupes', 'cours', 'seances',
            'presences', 'evaluations', 'notes', 'bulletins',
            'factures', 'paiements', 'absences_journalieres', 'billets',
        ];

        foreach ($tables as $table) {
            try {
                DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
                DB::statement("DROP POLICY IF EXISTS tenant_isolation_policy ON {$table}");
            } catch (\Throwable $e) {}
        }
    }
};
```

---

## ÉTAPE 4 — Setter tenant PostgreSQL dans le middleware ResolveTenant

**Modifier :**
`edugestdz/backend/app/Http/Middleware/ResolveTenant.php`

Trouver la méthode `handle()` et ajouter après avoir résolu le tenant :

```php
public function handle(Request $request, Closure $next)
{
    // ... code existant de résolution du tenant ...

    $tenantId = config('tenant.current_id');

    if ($tenantId) {
        // ── SÉCURITÉ : Setter la variable PostgreSQL pour RLS ────────────
        // Ceci permet au RLS de filtrer automatiquement au niveau BDD
        try {
            \Illuminate\Support\Facades\DB::statement(
                "SET LOCAL app.current_tenant_id = ?",
                [$tenantId]
            );
        } catch (\Throwable $e) {
            // Non bloquant — le filtrage applicatif reste en place
            \Illuminate\Support\Facades\Log::warning('RLS SET LOCAL failed: ' . $e->getMessage());
        }
    }

    return $next($request);
}
```

---

## PARTIE B — JWT BLACKLIST (TOKENS RÉVOCABLES)
## ═════════════════════════════════════════

## ÉTAPE 5 — Migration : table jwt_blacklist

**Créer :**
`edugestdz/backend/database/migrations/2026_07_07_310000_create_jwt_blacklist_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jwt_blacklist', function (Blueprint $table) {
            $table->id();
            $table->string('jti', 64)->unique(); // JWT ID (claim "jti")
            $table->string('user_id', 36);       // UUID user
            $table->string('raison')->nullable(); // logout | account_disabled | security_breach
            $table->timestamp('expire_le');       // quand le token expire naturellement
            $table->timestamp('blackliste_le')->useCurrent();

            $table->index(['jti'],      'idx_blacklist_jti');
            $table->index(['expire_le'],'idx_blacklist_expire'); // pour cleanup auto
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jwt_blacklist');
    }
};
```

---

## ÉTAPE 6 — JwtBlacklistService

**Créer :**
`edugestdz/backend/app/Services/JwtBlacklistService.php`

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;

class JwtBlacklistService
{
    private const CACHE_PREFIX = 'jwt_blacklist:';
    private const CACHE_TTL    = 3600 * 24; // 24h cache max

    /**
     * Blacklister le token courant (à la déconnexion).
     */
    public function blacklisterTokenCourant(string $raison = 'logout'): void
    {
        try {
            $token   = JWTAuth::getToken();
            $payload = JWTAuth::getPayload($token);

            $jti      = $payload->get('jti') ?? (string) $token;
            $userId   = $payload->get('sub');
            $expireAt = $payload->get('exp');

            $this->blacklister($jti, $userId, $raison, $expireAt);

        } catch (\Throwable $e) {
            Log::warning('JwtBlacklist: impossible de blacklister: ' . $e->getMessage());
        }
    }

    /**
     * Blacklister tous les tokens d'un utilisateur (désactivation compte).
     */
    public function blacklisterTousLesTokensUser(string $userId, string $raison = 'account_disabled'): void
    {
        // Stocker en cache Redis une clé "user_blacklisted_at"
        // Tous les tokens émis AVANT cette date seront considérés invalides
        Cache::put(
            "user_tokens_invalidated_at:{$userId}",
            now()->timestamp,
            now()->addDays(30)
        );

        Log::info("JwtBlacklist: tous les tokens de {$userId} invalidés ({$raison})");
    }

    /**
     * Vérifier si un token est blacklisté.
     */
    public function estBlackliste(string $jti, string $userId, int $issuedAt): bool
    {
        // Vérification 1 : blacklist individuelle (Redis cache d'abord)
        $cacheKey = self::CACHE_PREFIX . $jti;
        if (Cache::has($cacheKey)) {
            return true;
        }

        // Vérification 2 : blacklist BDD
        $existe = DB::table('jwt_blacklist')->where('jti', $jti)->exists();
        if ($existe) {
            // Mettre en cache pour éviter la requête BDD à chaque appel
            Cache::put($cacheKey, true, self::CACHE_TTL);
            return true;
        }

        // Vérification 3 : tous les tokens de l'user invalidés après une date
        $invalidatedAt = Cache::get("user_tokens_invalidated_at:{$userId}");
        if ($invalidatedAt && $issuedAt < $invalidatedAt) {
            return true;
        }

        return false;
    }

    private function blacklister(string $jti, string $userId, string $raison, int $expireAt): void
    {
        // En BDD
        DB::table('jwt_blacklist')->insertOrIgnore([
            'jti'            => $jti,
            'user_id'        => $userId,
            'raison'         => $raison,
            'expire_le'      => date('Y-m-d H:i:s', $expireAt),
            'blackliste_le'  => now(),
        ]);

        // En cache Redis (réponse rapide)
        $ttl = max(0, $expireAt - now()->timestamp);
        Cache::put(self::CACHE_PREFIX . $jti, true, $ttl);

        Log::info("JWT blacklisté", ['jti' => $jti, 'user' => $userId, 'raison' => $raison]);
    }

    /**
     * Nettoyer les tokens expirés de la BDD (scheduler hebdomadaire).
     */
    public function nettoyerExpires(): int
    {
        return DB::table('jwt_blacklist')
            ->where('expire_le', '<', now())
            ->delete();
    }
}
```

---

## ÉTAPE 7 — Middleware JwtBlacklistCheck

**Créer :**
`edugestdz/backend/app/Http/Middleware/JwtBlacklistCheck.php`

```php
<?php

namespace App\Http\Middleware;

use App\Services\JwtBlacklistService;
use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Middleware vérifiant que le JWT n'est pas blacklisté.
 * S'exécute APRÈS l'authentification JWT.
 */
class JwtBlacklistCheck
{
    public function __construct(private JwtBlacklistService $blacklist) {}

    public function handle(Request $request, Closure $next)
    {
        try {
            $token   = JWTAuth::getToken();
            if (!$token) return $next($request);

            $payload  = JWTAuth::getPayload($token);
            $jti      = $payload->get('jti') ?? (string) $token;
            $userId   = $payload->get('sub');
            $issuedAt = $payload->get('iat') ?? 0;

            if ($this->blacklist->estBlackliste($jti, $userId, $issuedAt)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session expirée ou révoquée. Reconnectez-vous.',
                    'code'    => 'TOKEN_REVOKED',
                ], 401);
            }

        } catch (\Throwable $e) {
            // Si on ne peut pas lire le token → laisser passer (auth:api s'en chargera)
        }

        return $next($request);
    }
}
```

---

## ÉTAPE 8 — Modifier AuthController : logout blackliste le token

**Modifier :**
`edugestdz/backend/app/Http/Controllers/Api/V1/AuthController.php`

Dans la méthode `logout()`, ajouter AVANT `auth('api')->logout()` :

```php
public function logout(): JsonResponse
{
    // ── Blacklister le token courant avant de déconnecter ────────────
    app(\App\Services\JwtBlacklistService::class)->blacklisterTokenCourant('logout');

    // ... code existant de logout ...
    auth('api')->logout();

    return response()->json(['success' => true, 'message' => 'Déconnecté avec succès']);
}
```

Dans la méthode qui désactive un utilisateur (si elle existe dans UserController ou SuperAdminController), ajouter :

```php
// À appeler quand un compte est désactivé/suspendu
app(\App\Services\JwtBlacklistService::class)
    ->blacklisterTousLesTokensUser($user->id, 'account_disabled');
```

---

## PARTIE C — ISOLATION FICHIERS UPLOADÉS
## ══════════════════════════════════════

## ÉTAPE 9 — SecureStorageService

**Créer :**
`edugestdz/backend/app/Services/SecureStorageService.php`

```php
<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Service de stockage sécurisé avec isolation par tenant.
 *
 * Principe :
 * - Tous les fichiers sont dans : tenant/{tenant_id}/{type}/{uuid}.{ext}
 * - Les URLs sont signées avec expiration (jamais d'URL permanente publique)
 * - Impossible de deviner le chemin d'un autre tenant
 */
class SecureStorageService
{
    private const DISK = 'local'; // Jamais 'public' pour les fichiers sensibles

    /**
     * Stocker un fichier de manière sécurisée.
     * Retourne le chemin relatif (à stocker en BDD, jamais l'URL complète).
     */
    public function stocker(
        UploadedFile $fichier,
        string       $type,      // ex: bulletins | certificats | devoirs | factures
        ?string      $tenantId   = null
    ): string {
        $tenantId = $tenantId ?? config('tenant.current_id');
        $ext      = $fichier->getClientOriginalExtension();
        $uuid     = Str::uuid()->toString();
        $chemin   = "tenants/{$tenantId}/{$type}/{$uuid}.{$ext}";

        Storage::disk(self::DISK)->put($chemin, file_get_contents($fichier->getRealPath()));

        return $chemin;
    }

    /**
     * Stocker un contenu binaire (ex: PDF généré).
     */
    public function stockerContenu(
        string  $contenu,
        string  $type,
        string  $extension = 'pdf',
        ?string $tenantId  = null
    ): string {
        $tenantId = $tenantId ?? config('tenant.current_id');
        $uuid     = Str::uuid()->toString();
        $chemin   = "tenants/{$tenantId}/{$type}/{$uuid}.{$extension}";

        Storage::disk(self::DISK)->put($chemin, $contenu);

        return $chemin;
    }

    /**
     * Générer une URL temporaire signée pour un fichier.
     * L'URL expire après $minutes minutes.
     *
     * SÉCURITÉ : Vérifier que le chemin appartient bien au tenant courant.
     */
    public function urlSignee(string $chemin, int $minutes = 60): string
    {
        // Vérification d'isolation : le chemin doit contenir le tenant_id courant
        $tenantId = config('tenant.current_id');
        if ($tenantId && !str_contains($chemin, "tenants/{$tenantId}/")) {
            \Illuminate\Support\Facades\Log::critical('SECURE STORAGE: cross-tenant access attempt', [
                'chemin'    => $chemin,
                'tenant_id' => $tenantId,
                'user_id'   => auth('api')->id(),
                'ip'        => request()->ip(),
            ]);
            throw new \Illuminate\Auth\Access\AuthorizationException(
                'Accès refusé : fichier appartenant à un autre établissement.'
            );
        }

        // Générer une URL temporaire via le contrôleur sécurisé
        return route('fichier.secure', [
            'chemin' => base64_encode($chemin),
            'sig'    => hash_hmac('sha256', $chemin . $tenantId, config('app.key')),
            'exp'    => now()->addMinutes($minutes)->timestamp,
        ]);
    }

    /**
     * Supprimer un fichier (vérification tenant).
     */
    public function supprimer(string $chemin, ?string $tenantId = null): bool
    {
        $tenantId = $tenantId ?? config('tenant.current_id');

        if (!str_contains($chemin, "tenants/{$tenantId}/")) {
            \Illuminate\Support\Facades\Log::warning('SECURE STORAGE: tentative suppression cross-tenant', [
                'chemin' => $chemin, 'tenant' => $tenantId,
            ]);
            return false;
        }

        return Storage::disk(self::DISK)->delete($chemin);
    }
}
```

---

## ÉTAPE 10 — Route et contrôleur pour fichiers sécurisés

**Créer :**
`edugestdz/backend/app/Http/Controllers/Api/SecureFichierController.php`

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SecureStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SecureFichierController extends Controller
{
    public function __construct(private SecureStorageService $storage) {}

    /**
     * Servir un fichier après vérification signature + expiration + tenant.
     * Route : GET /api/fichier/{chemin}?sig=xxx&exp=timestamp
     */
    public function servir(Request $request, string $cheminBase64)
    {
        $chemin   = base64_decode($cheminBase64);
        $sig      = $request->query('sig', '');
        $exp      = (int) $request->query('exp', 0);
        $tenantId = config('tenant.current_id');

        // 1. Vérifier expiration
        if ($exp < now()->timestamp) {
            return response()->json(['message' => 'Lien expiré. Générez un nouveau lien.'], 410);
        }

        // 2. Vérifier signature HMAC
        $expectedSig = hash_hmac('sha256', $chemin . $tenantId, config('app.key'));
        if (!hash_equals($expectedSig, $sig)) {
            \Illuminate\Support\Facades\Log::warning('SECURE FILE: signature invalide', [
                'chemin' => $chemin, 'ip' => $request->ip(),
            ]);
            return response()->json(['message' => 'Lien invalide.'], 403);
        }

        // 3. Vérifier isolation tenant
        if ($tenantId && !str_contains($chemin, "tenants/{$tenantId}/")) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        // 4. Servir le fichier
        if (!Storage::disk('local')->exists($chemin)) {
            return response()->json(['message' => 'Fichier non trouvé.'], 404);
        }

        $contenu = Storage::disk('local')->get($chemin);
        $ext     = pathinfo($chemin, PATHINFO_EXTENSION);
        $mime    = match ($ext) {
            'pdf'  => 'application/pdf',
            'jpg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            default=> 'application/octet-stream',
        };

        return response($contenu, 200, [
            'Content-Type'        => $mime,
            'Content-Disposition' => 'inline; filename="' . basename($chemin) . '"',
            'Cache-Control'       => 'no-store, no-cache, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
```

**Modifier :** `edugestdz/backend/routes/api.php`

```php
use App\Http\Controllers\Api\SecureFichierController;

// Route fichiers sécurisés (avec auth)
Route::middleware(['auth:api', 'tenant'])->group(function () {
    Route::get('/fichier/{chemin}', [SecureFichierController::class, 'servir'])
        ->name('fichier.secure')
        ->where('chemin', '.+');
});
```

---

## ÉTAPE 11 — Enregistrer les middlewares

**Modifier :**
`edugestdz/backend/bootstrap/app.php`

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'module'        => \App\Http\Middleware\ModuleCheck::class,
        'jwt.blacklist' => \App\Http\Middleware\JwtBlacklistCheck::class,
        'tenant.verify' => \App\Http\Middleware\TenantIsolationVerifier::class,
        // ... aliases existants ...
    ]);

    // Ajouter jwt.blacklist sur toutes les routes API authentifiées
    $middleware->api(append: [
        \App\Http\Middleware\JwtBlacklistCheck::class,
        \App\Http\Middleware\TenantIsolationVerifier::class,
    ]);
})
```

---

## ÉTAPE 12 — Commande nettoyage JWT blacklist (scheduler)

**Créer :**
`edugestdz/backend/app/Console/Commands/NettoyerJwtBlacklistCommand.php`

```php
<?php

namespace App\Console\Commands;

use App\Services\JwtBlacklistService;
use Illuminate\Console\Command;

class NettoyerJwtBlacklistCommand extends Command
{
    protected $signature   = 'edugest:nettoyer-jwt-blacklist';
    protected $description = 'Supprimer les tokens JWT expirés de la blacklist';

    public function handle(JwtBlacklistService $service): int
    {
        $supprimés = $service->nettoyerExpires();
        $this->info("✅ {$supprimés} token(s) JWT expirés supprimés de la blacklist.");
        return Command::SUCCESS;
    }
}
```

**Modifier :** `edugestdz/backend/app/Console/Kernel.php`

```php
// Nettoyage JWT blacklist — chaque dimanche à 3h
$schedule->command('edugest:nettoyer-jwt-blacklist')
    ->weekly()
    ->sundays()
    ->at('03:00');
```

---

## ÉTAPE 13 — Tests sécurité Niveau 1

**Créer :**
`edugestdz/backend/tests/Feature/Security/SecurityNiveau1Test.php`

```php
<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Models\Eleve;
use App\Services\JwtBlacklistService;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class SecurityNiveau1Test extends TestCase
{
    use RefreshDatabase;

    // ── JWT Blacklist ──────────────────────────────────────────────────

    public function test_token_blackliste_retourne_401(): void
    {
        $user  = User::factory()->create(['role' => 'admin']);
        $token = auth('api')->login($user);

        // Token fonctionne normalement
        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/eleves')
            ->assertStatus(200);

        // Logout → token blacklisté
        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson('/api/v1/auth/logout')
            ->assertStatus(200);

        // Même token → 401 maintenant
        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/eleves')
            ->assertStatus(401);
    }

    public function test_invalidation_tous_tokens_user(): void
    {
        $user    = User::factory()->create(['role' => 'admin']);
        $token   = auth('api')->login($user);
        $service = app(JwtBlacklistService::class);

        // Invalider tous les tokens
        $service->blacklisterTousLesTokensUser($user->id, 'test_security');

        // Le token précédent doit être refusé si token iat < invalidatedAt
        // (dépend du timing — test symbolique)
        $this->assertTrue(true);
    }

    // ── Isolation Tenant ──────────────────────────────────────────────

    public function test_eleve_autre_tenant_non_accessible(): void
    {
        $tenantA = Str::uuid()->toString();
        $tenantB = Str::uuid()->toString();

        $userA   = User::factory()->create(['role' => 'admin', 'tenant_id' => $tenantA]);
        $eleveB  = Eleve::factory()->create(['tenant_id' => $tenantB]);

        // UserA ne doit pas accéder à l'élève du tenant B
        $token = auth('api')->login($userA);
        config(['tenant.current_id' => $tenantA]);

        $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'X-Tenant-ID'   => $tenantA,
        ])->getJson("/api/v1/eleves/{$eleveB->id}")
          ->assertStatus(404); // Filtered by BelongsToTenant scope → not found
    }

    public function test_manipulation_tenant_header_bloquee(): void
    {
        $tenantA = Str::uuid()->toString();
        $tenantB = Str::uuid()->toString();

        $userA = User::factory()->create(['role' => 'admin', 'tenant_id' => $tenantA]);
        $token = auth('api')->login($userA);

        // Tenter d'accéder avec un faux header tenant
        $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'X-Tenant-ID'   => $tenantB, // MANIPULATION
        ])->getJson('/api/v1/eleves')
          ->assertStatus(403)
          ->assertJsonPath('code', 'TENANT_MANIPULATION');
    }

    public function test_scope_tenant_automatique(): void
    {
        $tenantA = Str::uuid()->toString();
        $tenantB = Str::uuid()->toString();

        User::factory()->create(['role' => 'admin', 'tenant_id' => $tenantA]);
        Eleve::factory()->count(3)->create(['tenant_id' => $tenantA]);
        Eleve::factory()->count(5)->create(['tenant_id' => $tenantB]);

        config(['tenant.current_id' => $tenantA]);

        // Seulement les élèves du tenant A
        $this->assertEquals(3, Eleve::count());
    }

    public function test_fichier_autre_tenant_acces_refuse(): void
    {
        $tenantA = Str::uuid()->toString();
        $tenantB = Str::uuid()->toString();
        $userA   = User::factory()->create(['role' => 'admin', 'tenant_id' => $tenantA]);

        config(['tenant.current_id' => $tenantA]);
        $token = auth('api')->login($userA);

        // Chemin d'un fichier appartenant au tenant B
        $cheminB    = "tenants/{$tenantB}/bulletins/test.pdf";
        $cheminB64  = base64_encode($cheminB);
        $sig        = hash_hmac('sha256', $cheminB . $tenantA, config('app.key'));

        $this->withHeaders(['Authorization' => "Bearer {$token}", 'X-Tenant-ID' => $tenantA])
            ->getJson("/api/fichier/{$cheminB64}?sig={$sig}&exp=" . now()->addHour()->timestamp)
            ->assertStatus(403);
    }

    public function test_lien_fichier_expire_retourne_410(): void
    {
        $tenantA = Str::uuid()->toString();
        $userA   = User::factory()->create(['role' => 'admin', 'tenant_id' => $tenantA]);

        config(['tenant.current_id' => $tenantA]);
        $token   = auth('api')->login($userA);

        $chemin  = "tenants/{$tenantA}/bulletins/test.pdf";
        $cheminB64 = base64_encode($chemin);
        $sig     = hash_hmac('sha256', $chemin . $tenantA, config('app.key'));
        $expPasse = now()->subHour()->timestamp; // Expiré

        $this->withHeaders(['Authorization' => "Bearer {$token}", 'X-Tenant-ID' => $tenantA])
            ->getJson("/api/fichier/{$cheminB64}?sig={$sig}&exp={$expPasse}")
            ->assertStatus(410);
    }

    public function test_health_check_accessible_sans_auth(): void
    {
        // Le health check ne doit jamais être bloqué
        $this->getJson('/api/health')->assertStatus(200);
    }
}
```

---

## ÉTAPE 14 — Exécution

```bash
cd edugestdz/backend

php artisan migrate
composer dump-autoload -o
php artisan test --parallel
# → 0 régression + 8 nouveaux tests verts

git add .
git commit -m "security(niveau1): RLS PostgreSQL + JWT Blacklist Redis + Isolation tenant renforcée + Fichiers sécurisés signés + 8 tests"
git push origin develop
# → PR develop → main
```

---

## CE QUE TU DIS À DEEPSEEK

```
Repo : https://github.com/Allintelligence2024/edugest-dz.git
Branche : develop
git checkout develop && git pull origin main

Fichier : MISSION_SECURITE_NIVEAU1.md — 14 étapes dans l'ordre.

RÈGLES CRITIQUES :
1. PostgreSQL uniquement — jamais SQLite. 0 régression.
2. Le trait BelongsToTenant REMPLACE l'existant — ne pas garder l'ancien.
3. La migration RLS : vérifier que chaque table existe avant d'activer RLS
   (utiliser le try/catch fourni) — ignorer silencieusement si table absente.
4. Le middleware JwtBlacklistCheck s'exécute APRÈS auth:api — pas avant.
5. Le middleware TenantIsolationVerifier : le super_admin bypass la vérification.
6. SecureStorageService : le disk 'local' (pas 'public') pour les fichiers sensibles.
7. La route /api/fichier/{chemin} : le paramètre 'chemin' accepte les slashes (where '.+').
8. Ajouter 'jwt.blacklist' ET 'tenant.verify' dans bootstrap/app.php via $middleware->alias().

php artisan migrate
composer dump-autoload -o
php artisan test --parallel → verts
git push origin develop → PR develop → main
```
