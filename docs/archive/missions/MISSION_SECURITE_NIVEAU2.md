# 🔐 MISSION DEEPSEEK — Sécurité Niveau 2 (IMPORTANT — Ce mois)
## EduGest DZ · Branche : develop · 7 Juillet 2026
## Tests actuels : 418+ ✅ · Objectif : ≥ 428 ✅ · 0 régression

---

## CONTEXTE — Ce qui est ciblé dans cette mission

```
1. Chiffrement colonnes sensibles en BDD
   → Tokens Satim, Google OAuth, Firebase en clair → si dump BDD → tout exposé

2. MFA obligatoire pour les admins
   → La 2FA existe mais est optionnelle → PowerSchool piraté à cause de ça

3. Détection d'anomalies + alertes temps réel
   → Brute force, accès suspect, volume anormal → découvert après coup

4. Headers de sécurité HTTP complets
   → XSS, clickjacking, MIME sniffing → non bloqués actuellement
```

### RÈGLES ABSOLUES
1. 0 régression — tests existants restent verts
2. PostgreSQL uniquement — jamais SQLite
3. Compatibilité rétroactive — les données existantes doivent rester lisibles
4. Ne pas casser l'auth existante

---

## ÉTAPE 0 — Synchroniser

```bash
git checkout develop && git pull origin main
```

---

## PARTIE A — CHIFFREMENT COLONNES SENSIBLES
## ══════════════════════════════════════════

## ÉTAPE 1 — Migration : colonnes chiffrées

**Créer :**
`edugestdz/backend/database/migrations/2026_07_07_400000_encrypt_sensitive_columns.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Convertir les colonnes sensibles existantes en TEXT (pour stocker le chiffrement)
        // Les colonnes sont déjà TEXT dans la plupart des cas

        // google_classroom_connexions : access_token + refresh_token déjà text ✅
        // (déjà chiffrés avec Crypt::encryptString dans GoogleClassroomService)

        // Ajouter colonne pour le vecteur d'initialisation (IV) si chiffrement symétrique
        // En pratique : Crypt::encryptString() de Laravel gère tout automatiquement

        // Marquer les colonnes sensibles dans la config
        // (pas de changement de schéma nécessaire si déjà TEXT)

        // Chiffrer les données existantes non chiffrées
        // (à faire via artisan command ci-dessous)
    }

    public function down(): void {}
};
```

---

## ÉTAPE 2 — EncryptedCast pour colonnes sensibles

**Créer :**
`edugestdz/backend/app/Casts/EncryptedString.php`

```php
<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Cast Eloquent pour chiffrer/déchiffrer automatiquement une colonne.
 *
 * Usage dans un Model :
 *   protected $casts = [
 *       'satim_password' => EncryptedString::class,
 *       'firebase_key'   => EncryptedString::class,
 *   ];
 *
 * La valeur est chiffrée automatiquement avant sauvegarde en BDD.
 * Elle est déchiffrée automatiquement à la lecture.
 * Si la valeur n'est pas chiffrée (données avant migration), retourne la valeur brute.
 */
class EncryptedString implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): ?string
    {
        if ($value === null) return null;

        try {
            return Crypt::decryptString($value);
        } catch (\Illuminate\Contracts\Encryption\DecryptException) {
            // Valeur non chiffrée (données existantes) → retourner telle quelle
            // Et logger pour audit
            Log::info("EncryptedString: valeur non chiffrée détectée pour {$key} — migration recommandée");
            return $value;
        }
    }

    public function set($model, string $key, $value, array $attributes): ?string
    {
        if ($value === null) return null;

        // Ne pas re-chiffrer si déjà chiffré
        try {
            Crypt::decryptString($value);
            return $value; // Déjà chiffré
        } catch (\Illuminate\Contracts\Encryption\DecryptException) {
            return Crypt::encryptString($value);
        }
    }
}
```

---

## ÉTAPE 3 — Appliquer le chiffrement aux modèles sensibles

**Modifier les modèles suivants** (ajouter le cast) :

**`edugestdz/backend/app/Models/GoogleClassroomConnexion.php`**
```php
// Déjà chiffré avec Crypt::encryptString → ajouter le cast pour automatiser
protected $casts = [
    'access_token'    => \App\Casts\EncryptedString::class,
    'refresh_token'   => \App\Casts\EncryptedString::class,
    'token_expire_le' => 'datetime',
    'actif'           => 'boolean',
];
// Supprimer protected $hidden = ['access_token','refresh_token'];
// (le cast gère maintenant le chiffrement automatiquement)
```

**Créer `edugestdz/backend/app/Models/ConfigSatim.php`** (si table config_satim existe)
```php
// Si tu stockes les credentials Satim en BDD par tenant :
protected $casts = [
    'merchant_password' => \App\Casts\EncryptedString::class,
    'terminal_id'       => \App\Casts\EncryptedString::class,
];
```

**`edugestdz/backend/app/Models/DeviceToken.php`**
```php
// Les push tokens Firebase ne sont pas ultra-sensibles mais on les chiffre par précaution
// Ne pas chiffrer ici car besoin de chercher par token (WHERE token = ?) → index impossible sur chiffré
// Laisser tel quel pour DeviceToken
```

---

## ÉTAPE 4 — Commande de migration des données existantes

**Créer :**
`edugestdz/backend/app/Console/Commands/ChiffrerDonneesExistantesCommand.php`

```php
<?php

namespace App\Console\Commands;

use App\Models\GoogleClassroomConnexion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class ChiffrerDonneesExistantesCommand extends Command
{
    protected $signature   = 'edugest:chiffrer-donnees';
    protected $description = 'Chiffrer les données sensibles existantes non encore chiffrées';

    public function handle(): int
    {
        $this->info('🔐 Chiffrement des données sensibles...');
        $total = 0;

        // Connexions Google Classroom
        DB::table('google_classroom_connexions')->lazyById()->each(function ($row) use (&$total) {
            $updated = [];

            foreach (['access_token', 'refresh_token'] as $col) {
                if (!$row->$col) continue;
                try {
                    Crypt::decryptString($row->$col); // Déjà chiffré → skip
                } catch (\Throwable) {
                    $updated[$col] = Crypt::encryptString($row->$col);
                    $total++;
                }
            }

            if (!empty($updated)) {
                DB::table('google_classroom_connexions')
                    ->where('id', $row->id)
                    ->update($updated);
            }
        });

        $this->info("✅ {$total} valeur(s) chiffrée(s).");
        return Command::SUCCESS;
    }
}
```

---

## PARTIE B — MFA OBLIGATOIRE POUR LES ADMINS
## ═══════════════════════════════════════════

## ÉTAPE 5 — Middleware MfaRequired

**Créer :**
`edugestdz/backend/app/Http/Middleware/MfaRequired.php`

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Middleware MFA Obligatoire.
 *
 * Si le user est admin/super_admin et n'a pas activé la 2FA :
 * → Retourner 403 avec instructions pour activer
 *
 * Exception : routes de setup 2FA et logout
 */
class MfaRequired
{
    private const ROLES_REQUIERANT_MFA = ['admin', 'super_admin'];

    private const ROUTES_EXCLUES = [
        'api/v1/auth/logout',
        'api/v1/auth/2fa/enable',
        'api/v1/auth/2fa/verify',
        'api/v1/auth/2fa/setup',
        'api/v1/auth/me',
        'api/health',
    ];

    public function handle(Request $request, Closure $next)
    {
        $user = auth('api')->user();
        if (!$user) return $next($request);

        // Vérifier si le rôle nécessite la MFA
        if (!in_array($user->role, self::ROLES_REQUIERANT_MFA)) {
            return $next($request);
        }

        // Routes exclues (setup 2FA, logout)
        foreach (self::ROUTES_EXCLUES as $route) {
            if ($request->is($route)) return $next($request);
        }

        // Vérifier si la 2FA est activée
        $mfaActive = $user->two_factor_secret !== null
            || $user->google2fa_secret !== null
            || ($user->mfa_actif ?? false);

        if (!$mfaActive) {
            Log::warning('MFA_REQUIRED: admin sans 2FA tente d\'accéder à l\'API', [
                'user_id' => $user->id,
                'email'   => $user->email,
                'role'    => $user->role,
                'ip'      => $request->ip(),
                'path'    => $request->path(),
            ]);

            return response()->json([
                'success'      => false,
                'message'      => 'La double authentification (2FA) est obligatoire pour les administrateurs.',
                'code'         => 'MFA_REQUIRED',
                'instructions' => 'Activez la 2FA depuis Paramètres → Sécurité → Activer 2FA.',
                'setup_url'    => '/api/v1/auth/2fa/setup',
            ], 403);
        }

        return $next($request);
    }
}
```

---

## ÉTAPE 6 — Appliquer MfaRequired sur les routes admin

**Modifier :**
`edugestdz/backend/bootstrap/app.php`

```php
$middleware->alias([
    // ... aliases existants ...
    'mfa'           => \App\Http\Middleware\MfaRequired::class,
    'mfa.required'  => \App\Http\Middleware\MfaRequired::class,
]);
```

**Modifier :**
`edugestdz/backend/routes/api.php`

Ajouter `'mfa'` sur les groupes admin/super-admin :

```php
// Super-Admin
Route::middleware(['auth:api', 'tenant', 'mfa'])->prefix('v1/super-admin')->group(function () {
    // ... routes existantes ...
});

// Routes admin sensibles (budget, paie, personnel)
Route::middleware(['auth:api', 'tenant', 'mfa'])->group(function () {
    // Routes à forte sensibilité (paie, budget, personnel)
    Route::prefix('v1/budget')->group(function () { /* ... */ });
    Route::prefix('v1/personnel')->group(function () { /* ... */ });
});
```

---

## PARTIE C — DÉTECTION D'ANOMALIES + ALERTES
## ═══════════════════════════════════════════

## ÉTAPE 7 — Migration : table security_events

**Créer :**
`edugestdz/backend/database/migrations/2026_07_07_410000_create_security_events_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_events', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('type');
            // login_failed | brute_force | cross_tenant | mfa_bypass_attempt
            // unusual_volume | after_hours_access | token_reuse | jwt_manipulation
            $table->string('severite')->default('warning');
            // info | warning | critical | emergency
            $table->string('ip')->nullable();
            $table->string('user_agent')->nullable();
            $table->uuid('user_id')->nullable();
            $table->uuid('tenant_id')->nullable();
            $table->string('path')->nullable();
            $table->jsonb('details')->default('{}');
            $table->boolean('alerte_envoyee')->default(false);
            $table->timestamp('survenu_le')->useCurrent();
            $table->timestamps();

            $table->index(['type', 'survenu_le'],    'idx_sec_type_date');
            $table->index(['ip', 'survenu_le'],      'idx_sec_ip_date');
            $table->index(['user_id', 'survenu_le'], 'idx_sec_user_date');
            $table->index(['severite'],              'idx_sec_severite');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_events');
    }
};
```

---

## ÉTAPE 8 — SecurityMonitorService

**Créer :**
`edugestdz/backend/app/Services/SecurityMonitorService.php`

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

/**
 * Service de détection d'anomalies de sécurité.
 *
 * Détecte et alerte sur :
 * - Brute force login (> 5 tentatives / 15 min)
 * - Accès depuis nouvelle IP (admin)
 * - Volume anormal de données (dump potentiel)
 * - Accès hors horaires (après 22h)
 * - Tentatives de manipulation tenant
 */
class SecurityMonitorService
{
    // Seuils configurables
    private const SEUIL_BRUTE_FORCE       = 5;    // tentatives max avant alerte
    private const FENETRE_BRUTE_FORCE     = 900;  // 15 minutes en secondes
    private const SEUIL_VOLUME_ELEVES     = 100;  // > 100 élèves/min = suspect
    private const HEURE_DEBUT_NORMALE     = 6;    // 6h du matin
    private const HEURE_FIN_NORMALE       = 22;   // 22h du soir

    /**
     * Enregistrer une tentative de login échouée.
     * Déclencher une alerte si brute force détecté.
     */
    public function loginEchoue(string $email, string $ip): void
    {
        $cacheKey = "login_failed:{$ip}:{$email}";
        $tentatives = Cache::increment($cacheKey, 1);

        if ($tentatives === 1) {
            Cache::expire($cacheKey, self::FENETRE_BRUTE_FORCE);
        }

        $this->enregistrerEvenement('login_failed', 'info', [
            'email'      => $email,
            'ip'         => $ip,
            'tentatives' => $tentatives,
        ]);

        if ($tentatives >= self::SEUIL_BRUTE_FORCE) {
            $this->alerter('brute_force', 'critical',
                "🚨 Brute force détecté sur {$email} depuis {$ip} — {$tentatives} tentatives",
                ['email' => $email, 'ip' => $ip, 'tentatives' => $tentatives]
            );
        }
    }

    /**
     * Vérifier si une IP est en brute force.
     */
    public function estEnBruteForce(string $email, string $ip): bool
    {
        $cacheKey   = "login_failed:{$ip}:{$email}";
        $tentatives = (int) Cache::get($cacheKey, 0);
        return $tentatives >= self::SEUIL_BRUTE_FORCE;
    }

    /**
     * Détecter un accès hors horaires normaux pour un admin.
     */
    public function verifierAccesHorsHoraires(string $userId, string $role, string $ip): void
    {
        if (!in_array($role, ['admin', 'super_admin'])) return;

        $heure = (int) now()->format('H');

        if ($heure < self::HEURE_DEBUT_NORMALE || $heure >= self::HEURE_FIN_NORMALE) {
            $this->alerter('after_hours_access', 'warning',
                "⚠️ Accès hors horaires : admin {$userId} à {$heure}h depuis {$ip}",
                ['user_id' => $userId, 'heure' => $heure, 'ip' => $ip]
            );
        }
    }

    /**
     * Détecter un volume anormal de données (dump potentiel).
     */
    public function verifierVolumeRequete(string $userId, string $path, int $nbResultats): void
    {
        if ($nbResultats < self::SEUIL_VOLUME_ELEVES) return;

        $cacheKey   = "volume_check:{$userId}:" . now()->format('Y-m-d-H-i');
        $totalMin   = Cache::increment($cacheKey, $nbResultats);

        if ($totalMin === $nbResultats) {
            Cache::expire($cacheKey, 60);
        }

        if ($totalMin > self::SEUIL_VOLUME_ELEVES * 10) {
            $this->alerter('unusual_volume', 'critical',
                "🚨 Volume de données anormal : {$userId} a récupéré {$totalMin} enregistrements en 1 min",
                ['user_id' => $userId, 'path' => $path, 'volume' => $totalMin]
            );
        }
    }

    /**
     * Enregistrer un événement de sécurité en BDD.
     */
    public function enregistrerEvenement(
        string  $type,
        string  $severite,
        array   $details = [],
        ?string $userId   = null,
        ?string $tenantId = null
    ): void {
        try {
            DB::table('security_events')->insert([
                'id'          => \Illuminate\Support\Str::uuid(),
                'type'        => $type,
                'severite'    => $severite,
                'ip'          => request()->ip(),
                'user_agent'  => substr(request()->userAgent() ?? '', 0, 200),
                'user_id'     => $userId ?? auth('api')->id(),
                'tenant_id'   => $tenantId ?? config('tenant.current_id'),
                'path'        => request()->path(),
                'details'     => json_encode($details),
                'survenu_le'  => now(),
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('SecurityMonitor: impossible d\'enregistrer: ' . $e->getMessage());
        }
    }

    /**
     * Envoyer une alerte (Telegram ou email selon config).
     */
    public function alerter(string $type, string $severite, string $message, array $details = []): void
    {
        $this->enregistrerEvenement($type, $severite, $details);

        Log::channel('stack')->log(
            $severite === 'critical' ? 'critical' : 'warning',
            "[SECURITY] {$message}",
            $details
        );

        // Alerte Telegram si configuré
        $telegramToken = config('services.telegram.bot_token');
        $telegramChat  = config('services.telegram.chat_id');

        if ($telegramToken && $telegramChat) {
            try {
                $emoji  = match ($severite) {
                    'emergency', 'critical' => '🚨',
                    'warning'              => '⚠️',
                    default                => 'ℹ️',
                };

                Http::timeout(5)->post(
                    "https://api.telegram.org/bot{$telegramToken}/sendMessage",
                    [
                        'chat_id'    => $telegramChat,
                        'text'       => "{$emoji} *EduGest DZ — Alerte Sécurité*\n\n{$message}\n\n"
                                      . "IP: " . request()->ip() . "\n"
                                      . "Heure: " . now()->format('d/m/Y H:i:s'),
                        'parse_mode' => 'Markdown',
                    ]
                );
            } catch (\Throwable $e) {
                Log::warning('SecurityMonitor: Telegram alert failed: ' . $e->getMessage());
            }
        }

        // Alerte par email si critique
        if (in_array($severite, ['critical', 'emergency'])) {
            $adminEmail = config('app.security_alert_email');
            if ($adminEmail) {
                try {
                    \Illuminate\Support\Facades\Mail::raw(
                        "[SÉCURITÉ CRITIQUE] EduGest DZ\n\n{$message}\n\nDétails: " . json_encode($details, JSON_PRETTY_PRINT),
                        fn($m) => $m->to($adminEmail)->subject("[CRITIQUE] Alerte sécurité EduGest DZ")
                    );
                } catch (\Throwable) {}
            }
        }
    }
}
```

---

## ÉTAPE 9 — Intégrer SecurityMonitor dans AuthController

**Modifier :**
`edugestdz/backend/app/Http/Controllers/Api/V1/AuthController.php`

Dans la méthode `login()` :

```php
public function login(Request $request): JsonResponse
{
    $monitor = app(\App\Services\SecurityMonitorService::class);

    // Vérifier brute force avant de tenter le login
    if ($monitor->estEnBruteForce($request->email ?? '', $request->ip())) {
        return response()->json([
            'success' => false,
            'message' => 'Trop de tentatives. Réessayez dans 15 minutes.',
            'code'    => 'BRUTE_FORCE_BLOCKED',
        ], 429);
    }

    // ... code login existant ...

    // Si login échoue :
    // $monitor->loginEchoue($request->email, $request->ip());
    // return response()->json([...], 401);

    // Si login réussit :
    // $monitor->verifierAccesHorsHoraires($user->id, $user->role, $request->ip());
    // return response()->json([...], 200);
}
```

---

## PARTIE D — HEADERS DE SÉCURITÉ HTTP COMPLETS
## ════════════════════════════════════════════

## ÉTAPE 10 — SecurityHeaders middleware amélioré

**Modifier :**
`edugestdz/backend/app/Http/Middleware/SecurityHeaders.php`

Remplacer complètement :

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Headers de sécurité HTTP complets — OWASP recommandés.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // ── Prévention XSS ────────────────────────────────────────────────
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // ── Prévention MIME Sniffing ──────────────────────────────────────
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // ── Prévention Clickjacking ───────────────────────────────────────
        $response->headers->set('X-Frame-Options', 'DENY');

        // ── Referrer Policy ───────────────────────────────────────────────
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // ── Permissions Policy (désactiver APIs inutilisées) ─────────────
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), payment=(), usb=(), bluetooth=()'
        );

        // ── Content Security Policy ───────────────────────────────────────
        // Adapté pour une API JSON — pas de scripts inline acceptés
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'none'; " .
            "script-src 'none'; " .
            "style-src 'none'; " .
            "img-src 'self' data:; " .
            "font-src 'none'; " .
            "connect-src 'self'; " .
            "frame-ancestors 'none'; " .
            "base-uri 'self'; " .
            "form-action 'self'"
        );

        // ── HSTS — uniquement en production ──────────────────────────────
        if (app()->environment('production')) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        }

        // ── Supprimer les headers qui révèlent la stack ───────────────────
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');
        $response->headers->remove('X-Generator');

        // ── Cache-Control pour les réponses API ───────────────────────────
        if (str_starts_with($request->path(), 'api/')) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
        }

        return $response;
    }
}
```

---

## ÉTAPE 11 — Configuration Telegram alertes

**Modifier :**
`edugestdz/backend/config/services.php`

```php
'telegram' => [
    'bot_token' => env('TELEGRAM_BOT_TOKEN', ''),
    'chat_id'   => env('TELEGRAM_SECURITY_CHAT_ID', ''),
],
```

**Modifier :**
`edugestdz/backend/.env.example`

```dotenv
# ── Alertes sécurité ──────────────────────────────────────────────
# Créer un bot Telegram : https://t.me/BotFather → /newbot
# Obtenir le chat_id : https://t.me/userinfobot
TELEGRAM_BOT_TOKEN=
TELEGRAM_SECURITY_CHAT_ID=

# Email pour alertes critiques
SECURITY_ALERT_EMAIL=
```

**Modifier :**
`edugestdz/backend/config/app.php`

```php
'security_alert_email' => env('SECURITY_ALERT_EMAIL', ''),
```

---

## ÉTAPE 12 — Dashboard sécurité (endpoint)

**Créer :**
`edugestdz/backend/app/Http/Controllers/Api/V1/SecurityDashboardController.php`

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SecurityDashboardController extends Controller
{
    /**
     * Dashboard de surveillance sécurité — admin uniquement.
     */
    public function index(): JsonResponse
    {
        $depuis24h = now()->subHours(24);
        $depuis7j  = now()->subDays(7);

        return response()->json([
            'success' => true,
            'data'    => [
                'evenements_24h'    => DB::table('security_events')
                    ->where('survenu_le', '>=', $depuis24h)
                    ->selectRaw('type, severite, COUNT(*) as total')
                    ->groupBy('type', 'severite')
                    ->get(),
                'critiques_24h'     => DB::table('security_events')
                    ->where('survenu_le', '>=', $depuis24h)
                    ->where('severite', 'critical')
                    ->count(),
                'brute_force_7j'    => DB::table('security_events')
                    ->where('survenu_le', '>=', $depuis7j)
                    ->where('type', 'brute_force')
                    ->count(),
                'ips_suspectes'     => DB::table('security_events')
                    ->where('survenu_le', '>=', $depuis7j)
                    ->whereIn('severite', ['critical', 'emergency'])
                    ->selectRaw('ip, COUNT(*) as total')
                    ->groupBy('ip')
                    ->orderByDesc('total')
                    ->limit(10)
                    ->get(),
                'derniers_evenements'=> DB::table('security_events')
                    ->orderByDesc('survenu_le')
                    ->limit(20)
                    ->get(),
                'jwt_blacklist_total'=> DB::table('jwt_blacklist')->count(),
                'admins_sans_mfa'   => \App\Models\User::whereIn('role', ['admin','super_admin'])
                    ->whereNull('two_factor_secret')
                    ->whereNull('google2fa_secret')
                    ->count(),
            ],
        ]);
    }
}
```

**Modifier :** `routes/api.php`

```php
use App\Http\Controllers\Api\V1\SecurityDashboardController;

Route::middleware(['auth:api', 'tenant', 'mfa'])->prefix('v1/security')->group(function () {
    Route::get('/dashboard', [SecurityDashboardController::class, 'index']);
});
```

---

## ÉTAPE 13 — Tests sécurité Niveau 2

**Créer :**
`edugestdz/backend/tests/Feature/Security/SecurityNiveau2Test.php`

```php
<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Services\SecurityMonitorService;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SecurityNiveau2Test extends TestCase
{
    use RefreshDatabase;

    private SecurityMonitorService $monitor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->monitor = app(SecurityMonitorService::class);
    }

    // ── Brute Force ────────────────────────────────────────────────────

    public function test_brute_force_bloque_apres_5_tentatives(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->monitor->loginEchoue('test@test.com', '1.2.3.4');
        }

        $this->assertTrue($this->monitor->estEnBruteForce('test@test.com', '1.2.3.4'));
    }

    public function test_brute_force_retourne_429(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->monitor->loginEchoue('victim@test.com', '9.8.7.6');
        }

        $this->postJson('/api/v1/auth/login', [
            'email'    => 'victim@test.com',
            'password' => 'anypassword',
        ])->assertStatus(429)
          ->assertJsonPath('code', 'BRUTE_FORCE_BLOCKED');
    }

    public function test_ips_differentes_compteurs_independants(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->monitor->loginEchoue('user@test.com', '1.1.1.1');
        }
        $this->assertFalse($this->monitor->estEnBruteForce('user@test.com', '2.2.2.2'));
        $this->assertFalse($this->monitor->estEnBruteForce('user@test.com', '1.1.1.1'));
    }

    // ── MFA Obligatoire ────────────────────────────────────────────────

    public function test_admin_sans_mfa_bloque_routes_sensibles(): void
    {
        $admin = User::factory()->create([
            'role'              => 'admin',
            'two_factor_secret' => null,
            'google2fa_secret'  => null,
        ]);

        $token = auth('api')->login($admin);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/super-admin/tenants')
            ->assertStatus(403)
            ->assertJsonPath('code', 'MFA_REQUIRED');
    }

    public function test_admin_avec_mfa_accede_normalement(): void
    {
        $admin = User::factory()->create([
            'role'              => 'admin',
            'two_factor_secret' => 'JBSWY3DPEHPK3PXP', // Secret 2FA
        ]);

        $token = auth('api')->login($admin);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/eleves') // Route non protégée par MFA
            ->assertStatus(200);
    }

    public function test_parent_sans_mfa_non_bloque(): void
    {
        $parent = User::factory()->create(['role' => 'parent', 'two_factor_secret' => null]);
        $token  = auth('api')->login($parent);

        // Les parents n'ont pas besoin de MFA
        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/eleves')
            ->assertNotEquals(403);
    }

    // ── Security Events ────────────────────────────────────────────────

    public function test_evenement_securite_enregistre_en_bdd(): void
    {
        $this->monitor->enregistrerEvenement('test_event', 'info', ['detail' => 'test']);

        $this->assertDatabaseHas('security_events', [
            'type'     => 'test_event',
            'severite' => 'info',
        ]);
    }

    public function test_dashboard_securite_accessible_admin(): void
    {
        $admin = User::factory()->create([
            'role'              => 'admin',
            'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
        ]);
        $token = auth('api')->login($admin);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/security/dashboard')
            ->assertStatus(200)
            ->assertJsonStructure(['success','data' => ['critiques_24h','admins_sans_mfa']]);
    }

    // ── Chiffrement ────────────────────────────────────────────────────

    public function test_encrypted_cast_chiffre_et_dechiffre(): void
    {
        $valeur = 'SECRET_TOKEN_12345';
        $cast   = new \App\Casts\EncryptedString();

        $chiffre  = $cast->set(null, 'test', $valeur, []);
        $this->assertNotEquals($valeur, $chiffre); // Chiffré ≠ original

        $dechiffre = $cast->get(null, 'test', $chiffre, []);
        $this->assertEquals($valeur, $dechiffre); // Déchiffré = original
    }

    public function test_acces_sans_auth_refuse(): void
    {
        $this->getJson('/api/v1/security/dashboard')->assertStatus(401);
    }
}
```

---

## ÉTAPE 14 — Exécution

```bash
cd edugestdz/backend

php artisan migrate
php artisan edugest:chiffrer-donnees  # Chiffrer les données existantes
composer dump-autoload -o
php artisan test --parallel
# → 0 régression + 10 nouveaux tests verts

git add .
git commit -m "security(niveau2): Chiffrement colonnes sensibles + MFA obligatoire admins + Détection anomalies + Alertes Telegram + Headers HTTP OWASP complets + 10 tests"
git push origin develop
# → PR develop → main
```

---

## CE QUE TU DIS À DEEPSEEK

```
Repo : https://github.com/Allintelligence2024/edugest-dz.git
Branche : develop
git checkout develop && git pull origin main

Fichier : MISSION_SECURITE_NIVEAU2.md — 14 étapes dans l'ordre.

RÈGLES CRITIQUES :
1. PostgreSQL uniquement — jamais SQLite. 0 régression.
2. EncryptedCast : si une valeur n'est pas chiffrée (DecryptException) → retourner la valeur brute.
   Ne jamais faire planter l'app sur des données existantes non chiffrées.
3. MfaRequired : vérifier le champ two_factor_secret OU google2fa_secret OU mfa_actif.
   Adapter selon les champs réels qui existent sur le model User.
4. SecurityMonitorService : si Telegram non configuré → logger seulement, ne pas crasher.
5. La commande 'edugest:chiffrer-donnees' → lancer APRÈS php artisan migrate.
6. Le SecurityDashboardController → admin uniquement (rôle admin ou super_admin).
7. Ne pas modifier AuthController de manière agressive —
   ajouter seulement les appels monitor au début de login() avec try/catch autour.

php artisan migrate
php artisan edugest:chiffrer-donnees
composer dump-autoload -o
php artisan test --parallel → verts
git push origin develop → PR develop → main
```
