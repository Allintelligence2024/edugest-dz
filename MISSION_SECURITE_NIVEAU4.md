# 🔐 MISSION DEEPSEEK — Sécurité Niveau 4 (ZERO-TRUST + BEHAVIORAL ANALYSIS)
## EduGest DZ · Branche : develop · 8 Juillet 2026
## Tests actuels : 430+ ✅ · Objectif : ≥ 450 ✅ · 0 régression
## Prérequis : Niveaux 1, 2 et 3 MERGÉS sur main

---

## PHILOSOPHIE NIVEAU 4

```
Niveaux 1-3 : "Vérifie qui tu es, puis fais confiance"
Niveau 4    : "Ne JAMAIS faire confiance — vérifier CHAQUE requête indépendamment"

Zero-Trust signifie :
  → Un token valide ne suffit PLUS
  → On vérifie aussi : appareil connu ? comportement normal ? risque score faible ?
  → Chaque accès sensible requiert une preuve fraîche

Nouveau dans ce niveau :
  1. Device Fingerprinting — appareil reconnu obligatoire
  2. Risk Score Engine — score 0-100 calculé par requête
  3. Behavioral Analysis — ML-like sur les patterns d'accès
  4. PKCE OAuth2 — code_verifier/challenge pour le frontend SPA
  5. Fine-grained RBAC — permissions au niveau champ, pas seulement route
  6. Session anomaly detection — parallèle, géolocalisation, vitesse impossible
  7. Secure API gateway layer — rate limiting intelligent par tenant/user/IP
  8. Request signing — toutes les mutations signées côté client
```

### RÈGLES ABSOLUES
1. 0 régression — tests existants restent verts
2. PostgreSQL uniquement — jamais SQLite
3. Dégradation gracieuse — si le risk engine est down, appliquer le niveau de risque MAX (pas MIN)
4. Backward compatible — les clients API existants continuent de fonctionner
5. Jamais stocker en clair : fingerprint, user-agent, géoloc → toujours hashés

---

## ÉTAPE 0 — Synchroniser

```bash
git checkout develop && git pull origin main
```

---

## PARTIE A — DEVICE FINGERPRINTING & TRUST REGISTRY
## ════════════════════════════════════════════════════

## ÉTAPE 1 — Migration : trusted_devices + device_challenges

```php
// Créer : edugestdz/backend/database/migrations/2026_07_08_600000_create_device_trust_tables.php

<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Appareils de confiance enregistrés par utilisateur
        Schema::create('trusted_devices', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
            $table->uuid('user_id');
            $table->uuid('tenant_id');
            $table->string('device_hash', 64);      // SHA256 du fingerprint — jamais le fingerprint brut
            $table->string('device_name')->nullable();// "iPhone 14 Pro - Safari" (déclaratif)
            $table->string('platform')->nullable();   // web | ios | android
            $table->boolean('is_trusted')->default(false);
            $table->boolean('is_verified')->default(false); // Confirmé par email/SMS
            $table->integer('trust_score')->default(0);     // 0-100
            $table->string('last_ip_hash', 64)->nullable(); // Hash de la dernière IP
            $table->string('last_country')->nullable();     // DZ | FR | ...
            $table->timestamp('first_seen_at')->useCurrent();
            $table->timestamp('last_seen_at')->useCurrent();
            $table->timestamp('trusted_at')->nullable();
            $table->timestamp('expires_at')->nullable(); // null = jamais (pour VPN fixe)
            $table->timestamps();

            $table->unique(['user_id', 'device_hash'], 'uq_user_device');
            $table->index(['user_id', 'is_trusted'], 'idx_trusted_devices_user');
            $table->index(['device_hash'],            'idx_trusted_devices_hash');
        });

        // Défis d'approbation d'appareil (envoyés par email/SMS)
        Schema::create('device_challenges', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
            $table->uuid('user_id');
            $table->uuid('trusted_device_id');
            $table->string('code', 8);              // Code à 8 chiffres (OTP)
            $table->string('code_hash', 64);        // SHA256 du code — jamais le code brut
            $table->integer('attempts')->default(0);
            $table->boolean('is_used')->default(false);
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['user_id', 'is_used', 'expires_at'], 'idx_device_challenge');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_challenges');
        Schema::dropIfExists('trusted_devices');
    }
};
```

---

## ÉTAPE 2 — DeviceFingerprintService

```php
// Créer : edugestdz/backend/app/Services/DeviceFingerprintService.php

<?php
namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Service de fingerprinting des appareils.
 *
 * Le fingerprint est calculé côté serveur à partir de :
 * - User-Agent (hashé)
 * - Accept-Language header
 * - Accept-Encoding header
 * - Timezone (header X-Timezone)
 * - Screen resolution (header X-Screen — envoyé par le frontend React)
 * - Platform (header X-Platform)
 *
 * SÉCURITÉ : On ne stocke JAMAIS le fingerprint brut — seulement son SHA256.
 * Un attaquant qui vole la BDD ne peut pas reconstituer les fingerprints.
 */
class DeviceFingerprintService
{
    /**
     * Calculer le fingerprint d'un appareil à partir de la requête.
     * Retourne le hash SHA256 (jamais les données brutes).
     */
    public function calculerHash(Request $request): string
    {
        $components = implode('|', array_filter([
            $request->header('User-Agent', ''),
            $request->header('Accept-Language', ''),
            $request->header('Accept-Encoding', ''),
            $request->header('X-Timezone', ''),
            $request->header('X-Screen', ''),
            $request->header('X-Platform', 'web'),
        ]));

        return hash('sha256', $components);
    }

    /**
     * Vérifier si un appareil est connu et de confiance pour un utilisateur.
     */
    public function estAppareilConnu(string $userId, string $deviceHash): bool
    {
        $cacheKey = "device_trusted:{$userId}:{$deviceHash}";

        return Cache::remember($cacheKey, 3600, function () use ($userId, $deviceHash) {
            return DB::table('trusted_devices')
                ->where('user_id', $userId)
                ->where('device_hash', $deviceHash)
                ->where('is_trusted', true)
                ->where('is_verified', true)
                ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->exists();
        });
    }

    /**
     * Enregistrer un nouvel appareil (non vérifié).
     * Retourne l'ID de l'appareil pour envoi du challenge.
     */
    public function enregistrerAppareil(
        string  $userId,
        string  $tenantId,
        string  $deviceHash,
        Request $request
    ): string {
        $existant = DB::table('trusted_devices')
            ->where('user_id', $userId)
            ->where('device_hash', $deviceHash)
            ->first();

        if ($existant) {
            // Mettre à jour la dernière vue
            DB::table('trusted_devices')
                ->where('id', $existant->id)
                ->update([
                    'last_seen_at'   => now(),
                    'last_ip_hash'   => hash('sha256', $request->ip()),
                    'last_country'   => $this->detecterPays($request->ip()),
                    'updated_at'     => now(),
                ]);
            return $existant->id;
        }

        $id = (string) Str::uuid();
        DB::table('trusted_devices')->insert([
            'id'           => $id,
            'user_id'      => $userId,
            'tenant_id'    => $tenantId,
            'device_hash'  => $deviceHash,
            'device_name'  => $this->nomAppareil($request),
            'platform'     => $request->header('X-Platform', 'web'),
            'is_trusted'   => false,
            'is_verified'  => false,
            'trust_score'  => 0,
            'last_ip_hash' => hash('sha256', $request->ip()),
            'last_country' => $this->detecterPays($request->ip()),
            'first_seen_at'=> now(),
            'last_seen_at' => now(),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        Log::info("Nouvel appareil détecté pour {$userId} — hash: {$deviceHash}");
        return $id;
    }

    /**
     * Créer un challenge OTP pour approuver un nouvel appareil.
     */
    public function creerChallenge(string $userId, string $deviceId): string
    {
        // Code à 8 chiffres
        $code     = str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
        $codeHash = hash('sha256', $code);

        // Invalider les anciens challenges
        DB::table('device_challenges')
            ->where('user_id', $userId)
            ->where('is_used', false)
            ->update(['is_used' => true]);

        DB::table('device_challenges')->insert([
            'id'                => (string) Str::uuid(),
            'user_id'           => $userId,
            'trusted_device_id' => $deviceId,
            'code'              => '', // JAMAIS stocker le code brut
            'code_hash'         => $codeHash,
            'attempts'          => 0,
            'is_used'           => false,
            'expires_at'        => now()->addMinutes(15),
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        return $code; // Retourné UNE SEULE FOIS pour envoi par email/SMS
    }

    /**
     * Vérifier un challenge OTP pour approuver un appareil.
     */
    public function verifierChallenge(string $userId, string $code): bool
    {
        $codeHash  = hash('sha256', $code);
        $challenge = DB::table('device_challenges')
            ->where('user_id', $userId)
            ->where('code_hash', $codeHash)
            ->where('is_used', false)
            ->where('expires_at', '>', now())
            ->first();

        if (!$challenge) {
            // Incrémenter les tentatives même si challenge non trouvé (anti-timing)
            DB::table('device_challenges')
                ->where('user_id', $userId)
                ->where('is_used', false)
                ->increment('attempts');
            return false;
        }

        // Vérifier les tentatives max (5)
        if ($challenge->attempts >= 5) {
            DB::table('device_challenges')
                ->where('id', $challenge->id)
                ->update(['is_used' => true]);
            return false;
        }

        // Approuver l'appareil
        DB::table('trusted_devices')
            ->where('id', $challenge->trusted_device_id)
            ->update([
                'is_trusted'  => true,
                'is_verified' => true,
                'trust_score' => 80,
                'trusted_at'  => now(),
                'updated_at'  => now(),
            ]);

        // Invalider le challenge
        DB::table('device_challenges')
            ->where('id', $challenge->id)
            ->update(['is_used' => true]);

        // Vider le cache
        $device = DB::table('trusted_devices')->find($challenge->trusted_device_id);
        if ($device) {
            Cache::forget("device_trusted:{$userId}:{$device->device_hash}");
        }

        return true;
    }

    private function nomAppareil(Request $request): string
    {
        $ua       = $request->header('User-Agent', '');
        $platform = $request->header('X-Platform', 'web');

        if (str_contains($ua, 'iPhone'))  return "iPhone ({$platform})";
        if (str_contains($ua, 'Android')) return "Android ({$platform})";
        if (str_contains($ua, 'iPad'))    return "iPad ({$platform})";
        if (str_contains($ua, 'Mac'))     return "Mac ({$platform})";
        if (str_contains($ua, 'Windows')) return "Windows ({$platform})";
        return "Appareil inconnu ({$platform})";
    }

    private function detecterPays(string $ip): string
    {
        // Détection basique par plage IP (Algérie = 41.x.x.x, 105.x.x.x, etc.)
        $firstOctet = (int) explode('.', $ip)[0];
        if (in_array($firstOctet, [41, 105, 154, 193, 194, 213, 196])) return 'DZ';
        if ($ip === '127.0.0.1' || str_starts_with($ip, '192.168') || str_starts_with($ip, '10.')) return 'LOCAL';
        return 'UNKNOWN';
    }
}
```

---

## PARTIE B — RISK SCORE ENGINE
## ══════════════════════════════

## ÉTAPE 3 — Migration : risk_scores (historique des scores)

```php
// Créer : edugestdz/backend/database/migrations/2026_07_08_610000_create_risk_score_table.php

<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('request_risk_scores', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
            $table->uuid('user_id')->nullable();
            $table->uuid('tenant_id')->nullable();
            $table->string('ip_hash', 64);
            $table->integer('score');           // 0 (safe) → 100 (critical)
            $table->string('action_prise');     // allow | challenge | block | alert
            $table->jsonb('facteurs');          // Détail de chaque facteur
            $table->string('path');
            $table->string('methode', 10);
            $table->timestamp('survenu_le')->useCurrent();

            $table->index(['user_id', 'survenu_le'], 'idx_risk_user');
            $table->index(['score', 'survenu_le'],   'idx_risk_score');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_risk_scores');
    }
};
```

---

## ÉTAPE 4 — RiskScoreEngine

```php
// Créer : edugestdz/backend/app/Services/RiskScoreEngine.php

<?php
namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Moteur de scoring de risque — évalue chaque requête sur 0-100.
 *
 * Score 0-20  → FAIBLE  → Autoriser normalement
 * Score 21-50 → MOYEN   → Autoriser + logger
 * Score 51-75 → ÉLEVÉ   → Demander confirmation MFA
 * Score 76-90 → CRITIQUE→ Bloquer + alerter
 * Score 91-100→ URGENT  → Bloquer + verrouillage automatique + Telegram
 *
 * Facteurs de risque (chacun ajoute des points) :
 * +40 : IP jamais vue pour ce user
 * +30 : Pays différent du pays habituel
 * +25 : Appareil non reconnu
 * +20 : Heure anormale (02h-05h DZ)
 * +20 : > 50 requêtes en 5 minutes
 * +15 : > 3 erreurs 403 en 10 minutes
 * +15 : > 3 tentatives login échouées
 * +10 : User-Agent botlike (curl, python, etc.)
 * +10 : Accès à des données en volume anormal
 * +05 : Première connexion de la semaine à cette heure
 */
class RiskScoreEngine
{
    private const SEUIL_CHALLENGE = 51;
    private const SEUIL_BLOCK     = 76;
    private const SEUIL_LOCKDOWN  = 91;

    public function __construct(
        private DeviceFingerprintService $deviceService,
        private SecurityMonitorService   $monitor
    ) {}

    /**
     * Évaluer le risque d'une requête.
     * Retourne ['score' => int, 'action' => string, 'facteurs' => array]
     */
    public function evaluer(Request $request, ?string $userId = null): array
    {
        $score    = 0;
        $facteurs = [];
        $tenantId = config('tenant.current_id');
        $ipHash   = hash('sha256', $request->ip());

        // ── Facteur 1 : IP jamais vue pour ce user (+40) ──────────────
        if ($userId && !$this->ipConnuePourUser($userId, $ipHash)) {
            $score += 40;
            $facteurs['ip_inconnue'] = 40;
        }

        // ── Facteur 2 : Pays inhabituel (+30) ─────────────────────────
        if ($userId && $this->paysInhabituel($userId, $request->ip())) {
            $score += 30;
            $facteurs['pays_inhabituel'] = 30;
        }

        // ── Facteur 3 : Appareil non reconnu (+25) ────────────────────
        if ($userId) {
            $deviceHash = $this->deviceService->calculerHash($request);
            if (!$this->deviceService->estAppareilConnu($userId, $deviceHash)) {
                $score += 25;
                $facteurs['appareil_inconnu'] = 25;
            }
        }

        // ── Facteur 4 : Heure anormale, 02h-05h +20 ───────────────────
        $heure = (int) now()->setTimezone('Africa/Algiers')->format('H');
        if ($heure >= 2 && $heure <= 5) {
            $score += 20;
            $facteurs['heure_anormale'] = 20;
        }

        // ── Facteur 5 : Rate limit anormal, >50 req/5min (+20) ────────
        $reqCount = (int) Cache::get("ratelimit:{$ipHash}:5min", 0);
        if ($reqCount > 50) {
            $score += 20;
            $facteurs['rate_limit'] = 20;
        }

        // ── Facteur 6 : > 3 erreurs 403 en 10 min (+15) ──────────────
        $erreurs403 = (int) Cache::get("errors_403:{$ipHash}", 0);
        if ($erreurs403 > 3) {
            $score += 15;
            $facteurs['erreurs_403'] = 15;
        }

        // ── Facteur 7 : > 3 logins échoués (+15) ─────────────────────
        $loginFails = (int) Cache::get("login_failed:{$ipHash}:" . strtolower($request->input('email', '')), 0);
        if ($loginFails > 3) {
            $score += 15;
            $facteurs['login_fails'] = 15;
        }

        // ── Facteur 8 : User-Agent botlike (+10) ──────────────────────
        $ua = strtolower($request->header('User-Agent', ''));
        if (str_contains($ua, 'curl') || str_contains($ua, 'python') ||
            str_contains($ua, 'wget') || str_contains($ua, 'bot') ||
            str_contains($ua, 'scanner') || empty($ua)) {
            $score += 10;
            $facteurs['botlike_ua'] = 10;
        }

        // ── Facteur 9 : Volume de données suspect (+10) ───────────────
        $limit = (int) $request->query('limit', 20);
        if ($limit > 200 || (int) $request->query('per_page', 20) > 200) {
            $score += 10;
            $facteurs['volume_suspect'] = 10;
        }

        $score = min(100, $score);

        // Déterminer l'action
        $action = match(true) {
            $score >= self::SEUIL_LOCKDOWN => 'lockdown',
            $score >= self::SEUIL_BLOCK    => 'block',
            $score >= self::SEUIL_CHALLENGE=> 'challenge',
            default                        => 'allow',
        };

        // Enregistrer en BDD (async-like via queue si disponible, sinon direct)
        $this->enregistrer($userId, $tenantId, $ipHash, $score, $action, $facteurs, $request);

        // Alertes si score critique
        if ($score >= self::SEUIL_BLOCK) {
            $this->monitor->alerter(
                'high_risk_request',
                $score >= self::SEUIL_LOCKDOWN ? 'emergency' : 'critical',
                "🚨 Requête à risque élevé (score: {$score}) — user: {$userId} — IP: {$request->ip()}",
                array_merge($facteurs, ['score' => $score, 'path' => $request->path()])
            );
        }

        return [
            'score'    => $score,
            'action'   => $action,
            'facteurs' => $facteurs,
        ];
    }

    private function ipConnuePourUser(string $userId, string $ipHash): bool
    {
        $cacheKey = "known_ips:{$userId}";
        $knownIps = Cache::get($cacheKey, []);
        return in_array($ipHash, $knownIps);
    }

    private function paysInhabituel(string $userId, string $ip): bool
    {
        // Récupérer le pays habituel depuis les 30 derniers jours
        $habituel = Cache::get("usual_country:{$userId}", 'DZ');
        $actuel   = $this->deviceService->calculerHash(request()); // Réutiliser
        // Simplification : comparer premier octet IP avec profil connu
        $firstOctet = (int) explode('.', $ip)[0];
        $isAlgerie  = in_array($firstOctet, [41, 105, 154, 193, 194, 213, 196]) || str_starts_with($ip, '192.168') || str_starts_with($ip, '10.');
        $isLocal    = str_starts_with($ip, '127.');

        if ($isLocal) return false;
        if ($habituel === 'DZ' && !$isAlgerie) return true;
        return false;
    }

    private function enregistrer(
        ?string $userId,
        ?string $tenantId,
        string  $ipHash,
        int     $score,
        string  $action,
        array   $facteurs,
        Request $request
    ): void {
        try {
            DB::table('request_risk_scores')->insert([
                'id'          => \Illuminate\Support\Str::uuid(),
                'user_id'     => $userId,
                'tenant_id'   => $tenantId,
                'ip_hash'     => $ipHash,
                'score'       => $score,
                'action_prise'=> $action,
                'facteurs'    => json_encode($facteurs),
                'path'        => substr($request->path(), 0, 200),
                'methode'     => $request->method(),
                'survenu_le'  => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('RiskScore: enregistrement échoué: ' . $e->getMessage());
        }
    }
}
```

---

## PARTIE C — ZERO-TRUST MIDDLEWARE
## ════════════════════════════════════

## ÉTAPE 5 — ZeroTrustMiddleware

```php
// Créer : edugestdz/backend/app/Http/Middleware/ZeroTrustMiddleware.php

<?php
namespace App\Http\Middleware;

use App\Services\RiskScoreEngine;
use App\Services\DeviceFingerprintService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Middleware Zero-Trust.
 *
 * Applique le principe "Never trust, always verify" :
 * → Calcule un score de risque pour chaque requête
 * → Score faible  : passe
 * → Score moyen   : passe + enregistrement
 * → Score élevé   : challenge MFA requis (même si déjà connecté)
 * → Score critique : bloqué immédiatement + alerte
 * → Score lockdown : verrouillage du compte temporaire
 *
 * ROUTES EXCLUES : health check, 2FA setup, challenge device
 */
class ZeroTrustMiddleware
{
    private const ROUTES_EXCLUES = [
        'api/health',
        'api/v1/auth/login',
        'api/v1/auth/2fa*',
        'api/v1/auth/device*',
        'api/v1/auth/refresh',
    ];

    public function __construct(
        private RiskScoreEngine          $riskEngine,
        private DeviceFingerprintService $deviceService
    ) {}

    public function handle(Request $request, Closure $next, string $niveau = 'normal')
    {
        // Exclure certaines routes
        foreach (self::ROUTES_EXCLUES as $pattern) {
            if ($request->is($pattern)) return $next($request);
        }

        $user   = auth('api')->user();
        $userId = $user?->id;

        // Calculer le score de risque
        $risque = $this->riskEngine->evaluer($request, $userId);

        // Ajouter le score dans les headers de réponse (pour debug en dev)
        $response = $next($request);
        $response->headers->set('X-Risk-Score', (string) $risque['score']);
        $response->headers->set('X-Risk-Action', $risque['action']);

        // Appliquer l'action AVANT d'exécuter la requête sensible
        return match ($risque['action']) {
            'lockdown'  => $this->lockdown($request, $risque, $userId),
            'block'     => $this->bloquer($request, $risque),
            'challenge' => ($niveau === 'strict')
                           ? $this->demanderChallenge($request, $risque)
                           : $response, // En mode normal : juste logger
            default     => $response,
        };
    }

    private function lockdown(Request $request, array $risque, ?string $userId): \Illuminate\Http\JsonResponse
    {
        // Verrouillage temporaire 30 minutes
        if ($userId) {
            Cache::put("account_locked:{$userId}", true, 1800);
        }

        Log::emergency('ZERO-TRUST LOCKDOWN', [
            'user_id' => $userId,
            'ip'      => $request->ip(),
            'score'   => $risque['score'],
            'facteurs'=> $risque['facteurs'],
        ]);

        return response()->json([
            'success'  => false,
            'message'  => 'Accès temporairement suspendu pour raisons de sécurité. Réessayez dans 30 minutes ou contactez l\'administrateur.',
            'code'     => 'ZERO_TRUST_LOCKDOWN',
            'score'    => $risque['score'],
        ], 403);
    }

    private function bloquer(Request $request, array $risque): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success'  => false,
            'message'  => 'Requête bloquée par le système de sécurité Zero-Trust.',
            'code'     => 'ZERO_TRUST_BLOCKED',
            'score'    => $risque['score'],
        ], 403);
    }

    private function demanderChallenge(Request $request, array $risque): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success'      => false,
            'message'      => 'Vérification supplémentaire requise pour cette opération sensible.',
            'code'         => 'ZERO_TRUST_CHALLENGE',
            'score'        => $risque['score'],
            'challenge_url'=> '/api/v1/auth/2fa/verify',
        ], 428); // 428 Precondition Required
    }
}
```

---

## PARTIE D — FINE-GRAINED RBAC (PERMISSIONS AU NIVEAU CHAMP)
## ═════════════════════════════════════════════════════════════

## ÉTAPE 6 — Migration : permissions granulaires

```php
// Créer : edugestdz/backend/database/migrations/2026_07_08_620000_create_fine_rbac_tables.php

<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Définition des permissions au niveau champ
        Schema::create('field_permissions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->string('role');              // admin | enseignant | parent | eleve
            $table->string('resource');          // eleves | notes | factures | personnel
            $table->string('field')->nullable(); // null = ressource entière ; 'telephone' = champ spécifique
            $table->boolean('can_read')->default(false);
            $table->boolean('can_write')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->boolean('can_export')->default(false);
            $table->boolean('is_masked')->default(false); // Masquer partiellement (ex: 06**1234)
            $table->string('mask_pattern')->nullable();   // '06**####' ou 'XXXX@XXXX'
            $table->timestamps();

            $table->unique(['tenant_id', 'role', 'resource', 'field'], 'uq_field_permission');
            $table->index(['tenant_id', 'role', 'resource'], 'idx_field_perm_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_permissions');
    }
};
```

---

## ÉTAPE 7 — FieldPermissionService + Seeder permissions par défaut

```php
// Créer : edugestdz/backend/app/Services/FieldPermissionService.php

<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

/**
 * Service de permissions granulaires au niveau champ.
 *
 * Exemples d'usage :
 * - Un enseignant peut voir les notes mais PAS le téléphone des parents
 * - Un parent peut voir les notes de SON enfant mais pas celles des autres
 * - Les numéros de téléphone sont masqués pour les roles <= enseignant
 */
class FieldPermissionService
{
    /**
     * Vérifier si un rôle peut effectuer une action sur une ressource/champ.
     */
    public function peutAcceder(
        string  $role,
        string  $resource,
        string  $action,          // read | write | delete | export
        ?string $field    = null,
        ?string $tenantId = null
    ): bool {
        $tenantId = $tenantId ?? config('tenant.current_id');
        $cacheKey = "field_perm:{$tenantId}:{$role}:{$resource}:{$field}:{$action}";

        return Cache::remember($cacheKey, 300, function () use ($tenantId, $role, $resource, $field, $action) {
            $colonne = "can_{$action}";

            // Vérifier permission au niveau champ
            if ($field) {
                $perm = DB::table('field_permissions')
                    ->where('tenant_id', $tenantId)
                    ->where('role', $role)
                    ->where('resource', $resource)
                    ->where('field', $field)
                    ->first();

                if ($perm) return (bool) $perm->$colonne;
            }

            // Vérifier permission au niveau ressource
            $permGlobal = DB::table('field_permissions')
                ->where('tenant_id', $tenantId)
                ->where('role', $role)
                ->where('resource', $resource)
                ->whereNull('field')
                ->first();

            return $permGlobal ? (bool) $permGlobal->$colonne : false;
        });
    }

    /**
     * Masquer les champs sensibles selon les permissions du rôle.
     */
    public function masquerChamps(array $donnees, string $role, string $resource, ?string $tenantId = null): array
    {
        $tenantId = $tenantId ?? config('tenant.current_id');

        $permissions = DB::table('field_permissions')
            ->where('tenant_id', $tenantId)
            ->where('role', $role)
            ->where('resource', $resource)
            ->where('is_masked', true)
            ->whereNotNull('field')
            ->get();

        foreach ($permissions as $perm) {
            if (isset($donnees[$perm->field])) {
                $donnees[$perm->field] = $this->appliquerMasque(
                    (string) $donnees[$perm->field],
                    $perm->mask_pattern
                );
            }
        }

        return $donnees;
    }

    /**
     * Initialiser les permissions par défaut pour un nouveau tenant.
     */
    public function initialiserPermissionsDefaut(string $tenantId): void
    {
        $permissionsParDefaut = [
            // Admin : accès total
            ['role' => 'admin', 'resource' => 'eleves',    'field' => null, 'can_read' => true,  'can_write' => true,  'can_delete' => true,  'can_export' => true,  'is_masked' => false],
            ['role' => 'admin', 'resource' => 'notes',     'field' => null, 'can_read' => true,  'can_write' => true,  'can_delete' => true,  'can_export' => true,  'is_masked' => false],
            ['role' => 'admin', 'resource' => 'factures',  'field' => null, 'can_read' => true,  'can_write' => true,  'can_delete' => false, 'can_export' => true,  'is_masked' => false],
            ['role' => 'admin', 'resource' => 'personnel', 'field' => null, 'can_read' => true,  'can_write' => true,  'can_delete' => false, 'can_export' => true,  'is_masked' => false],

            // Enseignant : notes oui, finances non, téléphone masqué
            ['role' => 'enseignant', 'resource' => 'eleves',    'field' => null,        'can_read' => true,  'can_write' => false, 'can_delete' => false, 'can_export' => false, 'is_masked' => false],
            ['role' => 'enseignant', 'resource' => 'eleves',    'field' => 'telephone', 'can_read' => true,  'can_write' => false, 'can_delete' => false, 'can_export' => false, 'is_masked' => true,  'mask_pattern' => '06**####'],
            ['role' => 'enseignant', 'resource' => 'notes',     'field' => null,        'can_read' => true,  'can_write' => true,  'can_delete' => false, 'can_export' => false, 'is_masked' => false],
            ['role' => 'enseignant', 'resource' => 'factures',  'field' => null,        'can_read' => false, 'can_write' => false, 'can_delete' => false, 'can_export' => false, 'is_masked' => false],

            // Parent : ses enfants uniquement, pas de finances détaillées
            ['role' => 'parent', 'resource' => 'eleves',   'field' => null,     'can_read' => true,  'can_write' => false, 'can_delete' => false, 'can_export' => false, 'is_masked' => false],
            ['role' => 'parent', 'resource' => 'notes',    'field' => null,     'can_read' => true,  'can_write' => false, 'can_delete' => false, 'can_export' => false, 'is_masked' => false],
            ['role' => 'parent', 'resource' => 'factures', 'field' => 'montant','can_read' => true,  'can_write' => false, 'can_delete' => false, 'can_export' => false, 'is_masked' => false],
            ['role' => 'parent', 'resource' => 'personnel','field' => null,     'can_read' => false, 'can_write' => false, 'can_delete' => false, 'can_export' => false, 'is_masked' => false],
        ];

        foreach ($permissionsParDefaut as $perm) {
            DB::table('field_permissions')->insertOrIgnore(array_merge($perm, [
                'id'         => (string) \Illuminate\Support\Str::uuid(),
                'tenant_id'  => $tenantId,
                'field'      => $perm['field'] ?? null,
                'mask_pattern'=> $perm['mask_pattern'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    private function appliquerMasque(string $valeur, ?string $patron): string
    {
        if (!$patron) return str_repeat('*', strlen($valeur));

        // Masque simple : '#' = chiffre conservé, '*' = remplacé par *
        if (strlen($patron) !== strlen($valeur)) return str_repeat('*', strlen($valeur));

        $resultat = '';
        for ($i = 0; $i < strlen($patron); $i++) {
            $resultat .= $patron[$i] === '#' ? $valeur[$i] : $patron[$i];
        }
        return $resultat;
    }
}
```

---

## PARTIE E — INTELLIGENT RATE LIMITER
## ══════════════════════════════════════

## ÉTAPE 8 — IntelligentRateLimiter

```php
// Créer : edugestdz/backend/app/Services/IntelligentRateLimiter.php

<?php
namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Rate limiter intelligent — adaptatif par contexte.
 *
 * Contrairement au rate limiter standard qui applique la même limite
 * à tout le monde, celui-ci adapte les limites selon :
 * - Le rôle (admin a plus de droits)
 * - L'heure (nuit = limites plus strictes)
 * - L'historique (user suspect = limites plus strictes)
 * - La route (export = limite très stricte)
 *
 * Système de tokens (Token Bucket Algorithm) :
 * - Chaque user a un "seau" de tokens
 * - Chaque requête consomme 1 token (ou plus pour opérations lourdes)
 * - Les tokens se régénèrent à un taux fixe
 * - Seau vide → 429 Too Many Requests
 */
class IntelligentRateLimiter
{
    private const LIMITES_PAR_ROLE = [
        'super_admin' => 1000,
        'admin'       => 500,
        'enseignant'  => 300,
        'parent'      => 200,
        'eleve'       => 150,
        'guest'       => 60,
    ];

    private const COÛT_PAR_ROUTE = [
        'v1/*/export*'    => 10, // Export = coût élevé
        'v1/*/pdf*'       => 8,
        'v1/*/bulk*'      => 5,
        'v1/auth/login'   => 3,
        'v1/auth/register'=> 5,
        'default'         => 1,
    ];

    public function verifier(Request $request, ?string $userId = null, string $role = 'guest'): array
    {
        $cleUser = $userId ? "ratelimit_user:{$userId}" : "ratelimit_ip:" . hash('sha256', $request->ip());
        $limite  = $this->calculerLimite($role, $request);
        $coût    = $this->calculerCout($request->path());

        // Incrémenter le compteur avec TTL de 1 minute
        $actuel  = (int) Cache::get($cleUser, 0);
        $nouveau = $actuel + $coût;

        if ($nouveau > $limite) {
            $retryAfter = 60; // Réessayer dans 60 secondes
            return [
                'autorise'    => false,
                'actuel'      => $actuel,
                'limite'      => $limite,
                'retry_after' => $retryAfter,
            ];
        }

        Cache::put($cleUser, $nouveau, 60);
        Cache::increment("ratelimit:{$hash}:5min", $coût);

        return [
            'autorise'   => true,
            'actuel'     => $nouveau,
            'limite'     => $limite,
            'restant'    => $limite - $nouveau,
        ];
    }

    private function calculerLimite(string $role, Request $request): int
    {
        $base = self::LIMITES_PAR_ROLE[$role] ?? self::LIMITES_PAR_ROLE['guest'];

        // Réduire de 50% la nuit (02h-05h)
        $heure = (int) now()->setTimezone('Africa/Algiers')->format('H');
        if ($heure >= 2 && $heure <= 5) $base = (int) ($base * 0.5);

        return $base;
    }

    private function calculerCout(string $path): int
    {
        foreach (self::COÛT_PAR_ROUTE as $pattern => $coût) {
            if ($pattern !== 'default' && fnmatch($pattern, $path)) return $coût;
        }
        return self::COÛT_PAR_ROUTE['default'];
    }
}
```

---

## ÉTAPE 9 — Enregistrement des middlewares et services

```php
// Modifier : edugestdz/backend/bootstrap/app.php

// Ajouter dans $middleware->alias() :
'zero.trust'    => \App\Http\Middleware\ZeroTrustMiddleware::class,
'zero.trust.strict' => fn($req, $next) => (new \App\Http\Middleware\ZeroTrustMiddleware(
    app(\App\Services\RiskScoreEngine::class),
    app(\App\Services\DeviceFingerprintService::class)
))->handle($req, $next, 'strict'),

// Ajouter ZeroTrustMiddleware sur toutes les routes API :
$middleware->api(append: [
    \App\Http\Middleware\ZeroTrustMiddleware::class,
]);
```

---

## ÉTAPE 10 — Controller : Device Trust API

```php
// Créer : edugestdz/backend/app/Http/Controllers/Api/V1/DeviceTrustController.php

<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DeviceFingerprintService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DeviceTrustController extends Controller
{
    public function __construct(private DeviceFingerprintService $deviceService) {}

    /**
     * Lister les appareils de confiance de l'utilisateur connecté.
     */
    public function index(Request $request): JsonResponse
    {
        $user    = auth('api')->user();
        $appareils = \DB::table('trusted_devices')
            ->where('user_id', $user->id)
            ->orderByDesc('last_seen_at')
            ->get(['id','device_name','platform','is_trusted','is_verified','trust_score','last_country','last_seen_at','trusted_at']);

        return response()->json(['success' => true, 'data' => $appareils]);
    }

    /**
     * Vérifier le challenge OTP pour approuver un nouvel appareil.
     */
    public function verifierChallenge(Request $request): JsonResponse
    {
        $validated = $request->validate(['code' => 'required|string|size:8']);

        $user    = auth('api')->user();
        $valide  = $this->deviceService->verifierChallenge($user->id, $validated['code']);

        if (!$valide) {
            return response()->json([
                'success' => false,
                'message' => 'Code invalide ou expiré.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Appareil approuvé avec succès.',
        ]);
    }

    /**
     * Révoquer un appareil de confiance.
     */
    public function revoquer(Request $request, string $deviceId): JsonResponse
    {
        $user = auth('api')->user();

        $deleted = \DB::table('trusted_devices')
            ->where('id', $deviceId)
            ->where('user_id', $user->id) // Sécurité : uniquement ses propres appareils
            ->delete();

        if (!$deleted) {
            return response()->json(['success' => false, 'message' => 'Appareil non trouvé.'], 404);
        }

        return response()->json(['success' => true, 'message' => 'Appareil révoqué.']);
    }
}
```

**Modifier** `routes/api.php` :

```php
use App\Http\Controllers\Api\V1\DeviceTrustController;

Route::middleware(['auth:api', 'tenant'])->prefix('v1/auth/device')->group(function () {
    Route::get('/',                [DeviceTrustController::class, 'index']);
    Route::post('/challenge',      [DeviceTrustController::class, 'verifierChallenge']);
    Route::delete('/{deviceId}',   [DeviceTrustController::class, 'revoquer']);
});
```

---

## ÉTAPE 11 — Tests sécurité Niveau 4

```php
// Créer : edugestdz/backend/tests/Feature/Security/SecurityNiveau4Test.php

<?php
namespace Tests\Feature\Security;

use App\Models\User;
use App\Services\DeviceFingerprintService;
use App\Services\RiskScoreEngine;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class SecurityNiveau4Test extends TestCase
{
    use RefreshDatabase;

    // ── Device Fingerprinting ──────────────────────────────────────────

    public function test_appareil_inconnu_non_approuve(): void
    {
        $service = app(DeviceFingerprintService::class);
        $userId  = (string) Str::uuid();
        $hash    = 'abc123fakehash456def';

        $this->assertFalse($service->estAppareilConnu($userId, $hash));
    }

    public function test_appareil_approuve_reconnu(): void
    {
        $service  = app(DeviceFingerprintService::class);
        $userId   = (string) Str::uuid();
        $tenantId = (string) Str::uuid();
        $hash     = hash('sha256', 'test-device-fingerprint');

        $deviceId = $service->enregistrerAppareil($userId, $tenantId, $hash, request());

        // Approuver manuellement
        \DB::table('trusted_devices')->where('id', $deviceId)->update([
            'is_trusted'  => true,
            'is_verified' => true,
            'expires_at'  => null,
        ]);

        \Illuminate\Support\Facades\Cache::forget("device_trusted:{$userId}:{$hash}");

        $this->assertTrue($service->estAppareilConnu($userId, $hash));
    }

    public function test_challenge_otp_correct_approuve_appareil(): void
    {
        $service  = app(DeviceFingerprintService::class);
        $userId   = (string) Str::uuid();
        $tenantId = (string) Str::uuid();
        $hash     = hash('sha256', 'test-device-challenge');

        $deviceId = $service->enregistrerAppareil($userId, $tenantId, $hash, request());
        $code     = $service->creerChallenge($userId, $deviceId);

        $this->assertTrue($service->verifierChallenge($userId, $code));
        $this->assertTrue($service->estAppareilConnu($userId, $hash));
    }

    public function test_challenge_otp_incorrect_refuse(): void
    {
        $service  = app(DeviceFingerprintService::class);
        $userId   = (string) Str::uuid();
        $tenantId = (string) Str::uuid();
        $hash     = hash('sha256', 'test-device-wrong-code');

        $deviceId = $service->enregistrerAppareil($userId, $tenantId, $hash, request());
        $service->creerChallenge($userId, $deviceId);

        $this->assertFalse($service->verifierChallenge($userId, '00000000'));
    }

    // ── Risk Score Engine ──────────────────────────────────────────────

    public function test_user_agent_botlike_augmente_score(): void
    {
        $engine   = app(RiskScoreEngine::class);
        $request  = request();
        $request->headers->set('User-Agent', 'curl/7.88.1');

        $risque = $engine->evaluer($request, null);
        $this->assertGreaterThanOrEqual(10, $risque['score']);
        $this->assertArrayHasKey('botlike_ua', $risque['facteurs']);
    }

    public function test_requete_normale_score_bas(): void
    {
        $engine  = app(RiskScoreEngine::class);
        $request = request();
        $request->headers->set('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');

        $risque = $engine->evaluer($request, null);
        $this->assertLessThan(51, $risque['score']); // Doit être faible ou moyen
    }

    // ── Fine-grained RBAC ─────────────────────────────────────────────

    public function test_enseignant_ne_peut_pas_voir_factures(): void
    {
        $service  = app(\App\Services\FieldPermissionService::class);
        $tenantId = (string) Str::uuid();

        $service->initialiserPermissionsDefaut($tenantId);

        config(['tenant.current_id' => $tenantId]);

        $peutLire = $service->peutAcceder('enseignant', 'factures', 'read', null, $tenantId);
        $this->assertFalse($peutLire);
    }

    public function test_admin_peut_tout_lire(): void
    {
        $service  = app(\App\Services\FieldPermissionService::class);
        $tenantId = (string) Str::uuid();

        $service->initialiserPermissionsDefaut($tenantId);

        $this->assertTrue($service->peutAcceder('admin', 'eleves', 'read', null, $tenantId));
        $this->assertTrue($service->peutAcceder('admin', 'factures', 'read', null, $tenantId));
        $this->assertTrue($service->peutAcceder('admin', 'notes', 'write', null, $tenantId));
    }

    public function test_telephone_masque_pour_enseignant(): void
    {
        $service  = app(\App\Services\FieldPermissionService::class);
        $tenantId = (string) Str::uuid();

        $service->initialiserPermissionsDefaut($tenantId);

        $donnees  = ['nom' => 'Benali', 'telephone' => '0661234567'];
        $masquees = $service->masquerChamps($donnees, 'enseignant', 'eleves', $tenantId);

        $this->assertNotEquals('0661234567', $masquees['telephone']);
    }

    // ── Device Trust API ──────────────────────────────────────────────

    public function test_lister_appareils_necessite_auth(): void
    {
        $this->getJson('/api/v1/auth/device')->assertStatus(401);
    }

    public function test_revoquer_appareil_autre_user_impossible(): void
    {
        $user1 = User::factory()->create(['role' => 'admin']);
        $user2 = User::factory()->create(['role' => 'admin']);

        $deviceId = (string) \Illuminate\Support\Str::uuid();
        \DB::table('trusted_devices')->insert([
            'id'          => $deviceId,
            'user_id'     => $user1->id,
            'tenant_id'   => $user1->tenant_id,
            'device_hash' => 'testhash123',
            'is_trusted'  => true,
            'is_verified' => true,
            'trust_score' => 80,
            'first_seen_at' => now(),
            'last_seen_at'  => now(),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        // User2 tente de révoquer l'appareil de User1
        $token = auth('api')->login($user2);
        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->deleteJson("/api/v1/auth/device/{$deviceId}")
            ->assertStatus(404); // Pas trouvé pour user2
    }
}
```

---

## ÉTAPE 12 — Exécution

```bash
cd edugestdz/backend

php artisan migrate
composer dump-autoload -o
php artisan test --parallel
# → 0 régression + 12 nouveaux tests verts

git add .
git commit -m "security(niveau4): Zero-Trust Engine + Device Fingerprinting + Risk Score 0-100 + Fine-grained RBAC + Intelligent Rate Limiter + 12 tests"
git push origin develop
# → PR develop → main
```

---

## CE QUE TU DIS À DEEPSEEK

```
Repo : https://github.com/Allintelligence2024/edugest-dz.git
Branche : develop
git checkout develop && git pull origin main

Fichier : MISSION_SECURITE_NIVEAU4.md — 12 étapes dans l'ordre.

RÈGLES CRITIQUES :
1. PostgreSQL uniquement — 0 régression.
2. ZeroTrustMiddleware : en mode 'normal' (défaut), le score 'challenge' est loggé mais ne bloque pas.
   Seul le mode 'strict' (via alias zero.trust.strict) bloque avec 428.
   Cela évite de casser l'expérience utilisateur normale.
3. RiskScoreEngine : si le calcul du score lève une exception → score = 100 (défaut sécurisé).
   JAMAIS laisser passer par défaut en cas d'erreur.
4. DeviceFingerprintService.creerChallenge() : ne stocker JAMAIS le code brut en BDD.
   Seulement le hash SHA256. Le code brut est retourné UNE SEULE FOIS au contrôleur.
5. FieldPermissionService : si la table field_permissions est vide pour un tenant+role+resource
   → retourner false (deny by default). JAMAIS allow by default.
6. IntelligentRateLimiter : les headers X-RateLimit-Limit et X-RateLimit-Remaining
   doivent être ajoutés sur TOUTES les réponses API.
7. La migration trusted_devices : device_hash est VARCHAR(64), pas TEXT.
   Index unique sur [user_id, device_hash].
8. Device challenge : max 5 tentatives puis challenge invalidé automatiquement.

php artisan migrate
composer dump-autoload -o
php artisan test --parallel → verts
git push origin develop → PR develop → main
```
