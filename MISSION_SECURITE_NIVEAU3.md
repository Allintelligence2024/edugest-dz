# 🔐 MISSION DEEPSEEK — Sécurité Niveau 3 (IMPORTANT — Ce trimestre)
## EduGest DZ · Branche : develop · 7 Juillet 2026
## Tests actuels : 418+ ✅ · Objectif : ≥ 428 ✅ · 0 régression

---

## CONTEXTE — Ce qui est ciblé dans cette mission

```
1. Audit logs immuables
   → Les logs Spatie actuels peuvent être modifiés si accès BDD
   → Export vers système séparé + signature cryptographique

2. Plan de réponse aux incidents
   → Pas de procédure documentée → panique en cas d'attaque
   → Guide étape par étape + API de notification breach

3. Politique de mots de passe renforcée
   → Pas de règles de complexité actuellement
   → Vérification contre les mots de passe les plus courants

4. Limite d'accès par IP (allowlist pour super-admin)
   → Le super-admin accessible depuis n'importe quelle IP du monde
   → Restriction aux IPs connues

5. Rotation automatique des secrets
   → JWT_SECRET fixe → si volé → tous les tokens compromis indéfiniment
   → Rotation programmée + invalidation des anciens tokens
```

### RÈGLES ABSOLUES
1. 0 régression — tests existants restent verts
2. PostgreSQL uniquement — jamais SQLite
3. Ne pas casser l'auth existante
4. Tous les mécanismes dégradent gracieusement si non configurés

---

## ÉTAPE 0 — Synchroniser

```bash
git checkout develop && git pull origin main
```

---

## PARTIE A — AUDIT LOGS IMMUABLES
## ══════════════════════════════════

## ÉTAPE 1 — Migration : audit_log_exports (logs signés)

**Créer :**
`edugestdz/backend/database/migrations/2026_07_07_500000_create_immutable_audit_tables.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Table d'export de logs immuables (signés cryptographiquement)
        Schema::create('audit_log_exports', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->nullable();
            $table->string('periode');          // ex: "2026-07-07" ou "2026-07-semaine-28"
            $table->string('type_export');      // daily | weekly | breach | manual
            $table->integer('nb_entrees')->default(0);
            $table->text('hash_sha256');        // hash SHA256 du contenu pour vérification
            $table->text('signature');          // signature HMAC du hash
            $table->string('fichier_chemin')->nullable(); // chemin du fichier exporté
            $table->boolean('integrite_ok')->default(true);
            $table->timestamp('exporte_le')->useCurrent();
            $table->timestamps();

            $table->index(['tenant_id', 'exporte_le'], 'idx_audit_export_tenant');
            $table->index(['type_export', 'exporte_le'], 'idx_audit_export_type');
        });

        // Table pour les déclarations de breach (loi 18-07 : 72h pour notifier)
        Schema::create('breach_declarations', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->nullable();
            $table->string('type_incident');
            // data_leak | unauthorized_access | ransomware | insider_threat | other
            $table->string('severite');         // low | medium | high | critical
            $table->text('description');
            $table->jsonb('donnees_affectees')->default('[]');
            // Ex: ["emails","notes","données_parents"]
            $table->integer('nb_personnes_affectees')->default(0);
            $table->timestamp('detecte_le');
            $table->timestamp('contenu_le')->nullable();
            $table->timestamp('notifie_clients_le')->nullable();
            $table->timestamp('notifie_anpdp_le')->nullable();  // Délai légal 72h loi 18-07
            $table->string('statut')->default('ouvert');
            // ouvert | en_cours | resolu | clos
            $table->text('actions_prises')->nullable();
            $table->text('lecons_apprises')->nullable();
            $table->uuid('declare_par');
            $table->timestamps();

            $table->index(['statut', 'detecte_le'], 'idx_breach_statut');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('breach_declarations');
        Schema::dropIfExists('audit_log_exports');
    }
};
```

---

## ÉTAPE 2 — ImmutableAuditService

**Créer :**
`edugestdz/backend/app/Services/ImmutableAuditService.php`

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Service d'audit immuable.
 *
 * Les logs sont signés cryptographiquement avec HMAC-SHA256.
 * Toute modification ultérieure du fichier est détectable.
 *
 * Principe :
 * 1. Récupérer tous les logs de la période
 * 2. Sérialiser en JSON
 * 3. Calculer SHA256 du contenu
 * 4. Signer avec HMAC (clé = APP_KEY)
 * 5. Stocker le hash + signature en BDD (table audit_log_exports)
 * 6. Sauvegarder le fichier JSON (optionnel)
 */
class ImmutableAuditService
{
    /**
     * Exporter et signer les logs d'audit de la journée.
     * Appelé chaque nuit par le scheduler.
     */
    public function exporterJournalier(?string $tenantId = null, ?string $date = null): array
    {
        $date = $date ?? now()->subDay()->format('Y-m-d');

        // Récupérer les logs de la journée (Spatie ActivityLog)
        $logs = DB::table('activity_log')
            ->where('created_at', '>=', $date . ' 00:00:00')
            ->where('created_at', '<=', $date . ' 23:59:59')
            ->when($tenantId, fn($q) => $q->where('properties->tenant_id', $tenantId))
            ->orderBy('created_at')
            ->get()
            ->toArray();

        if (empty($logs)) {
            return ['exportes' => 0, 'hash' => null];
        }

        // Sérialiser + signer
        $contenu   = json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $hash      = hash('sha256', $contenu);
        $signature = hash_hmac('sha256', $hash, config('app.key'));

        // Sauvegarder le fichier
        $chemin    = "audit/{$date}/audit-{$date}" . ($tenantId ? "-{$tenantId}" : '') . ".json";
        Storage::disk('local')->put($chemin, $contenu);

        // Enregistrer en BDD
        DB::table('audit_log_exports')->insert([
            'id'           => \Illuminate\Support\Str::uuid(),
            'tenant_id'    => $tenantId,
            'periode'      => $date,
            'type_export'  => 'daily',
            'nb_entrees'   => count($logs),
            'hash_sha256'  => $hash,
            'signature'    => $signature,
            'fichier_chemin'=> $chemin,
            'integrite_ok' => true,
            'exporte_le'   => now(),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        Log::info("Audit exporté et signé: {$date} — {$count} entrées, hash: {$hash}");

        return [
            'exportes'  => count($logs),
            'hash'      => $hash,
            'chemin'    => $chemin,
            'date'      => $date,
        ];
    }

    /**
     * Vérifier l'intégrité d'un export existant.
     * Retourne true si le fichier n'a pas été modifié.
     */
    public function verifierIntegrite(string $exportId): array
    {
        $export = DB::table('audit_log_exports')->where('id', $exportId)->first();

        if (!$export) {
            return ['ok' => false, 'raison' => 'Export non trouvé'];
        }

        if (!Storage::disk('local')->exists($export->fichier_chemin)) {
            return ['ok' => false, 'raison' => 'Fichier manquant — possible suppression'];
        }

        $contenuActuel   = Storage::disk('local')->get($export->fichier_chemin);
        $hashActuel      = hash('sha256', $contenuActuel);
        $signatureActuelle = hash_hmac('sha256', $hashActuel, config('app.key'));

        $hashOk      = hash_equals($export->hash_sha256, $hashActuel);
        $signatureOk = hash_equals($export->signature, $signatureActuelle);

        if (!$hashOk || !$signatureOk) {
            DB::table('audit_log_exports')
                ->where('id', $exportId)
                ->update(['integrite_ok' => false, 'updated_at' => now()]);

            Log::critical('AUDIT INTEGRITY FAILURE', [
                'export_id'    => $exportId,
                'periode'      => $export->periode,
                'hash_attendu' => $export->hash_sha256,
                'hash_actuel'  => $hashActuel,
            ]);

            return [
                'ok'     => false,
                'raison' => 'Intégrité compromise — le fichier a été modifié après signature',
            ];
        }

        return ['ok' => true, 'hash' => $hashActuel, 'periode' => $export->periode];
    }
}
```

---

## ÉTAPE 3 — Commande export audit journalier

**Créer :**
`edugestdz/backend/app/Console/Commands/ExporterAuditJournalierCommand.php`

```php
<?php

namespace App\Console\Commands;

use App\Services\ImmutableAuditService;
use Illuminate\Console\Command;

class ExporterAuditJournalierCommand extends Command
{
    protected $signature   = 'edugest:audit-export {--date= : Date YYYY-MM-DD}';
    protected $description = 'Exporter et signer les logs d\'audit de la journée';

    public function handle(ImmutableAuditService $service): int
    {
        $date   = $this->option('date') ?? now()->subDay()->format('Y-m-d');
        $result = $service->exporterJournalier(null, $date);

        $this->info("✅ Audit exporté: {$result['exportes']} entrées · Hash: {$result['hash']}");
        return Command::SUCCESS;
    }
}
```

**Modifier :** `edugestdz/backend/app/Console/Kernel.php`

```php
// Export audit signé — chaque nuit à 2h
$schedule->command('edugest:audit-export')
    ->dailyAt('02:00')
    ->withoutOverlapping();
```

---

## PARTIE B — POLITIQUE MOTS DE PASSE RENFORCÉE
## ════════════════════════════════════════════

## ÉTAPE 4 — PasswordPolicyService

**Créer :**
`edugestdz/backend/app/Services/PasswordPolicyService.php`

```php
<?php

namespace App\Services;

/**
 * Service de politique de mots de passe.
 *
 * Règles appliquées :
 * - Minimum 12 caractères (NIST 2025 recommande 8+ mais 12 est mieux)
 * - Au moins 1 majuscule
 * - Au moins 1 chiffre
 * - Au moins 1 caractère spécial
 * - Pas dans la liste des 1000 mots de passe les plus courants
 * - Pas identique à l'email de l'utilisateur
 * - Pas le nom de l'école
 */
class PasswordPolicyService
{
    // Top 50 mots de passe les plus courants adaptés au contexte algérien
    private const MOTS_DE_PASSE_INTERDITS = [
        'password', 'password1', '123456', '12345678', '123456789',
        '1234567890', 'qwerty', 'abc123', 'Password1', 'password123',
        'admin', 'admin123', 'Admin123', 'azerty', 'azerty123',
        'Algeria2024', 'Algeria2025', 'Algeria2026', 'Algerie123',
        'EduGest', 'EduGest123', 'edugest', 'ecole123', 'ecole2026',
        'directeur', 'Directeur1', 'enseignant', 'parent123',
        'motdepasse', 'motdepasse1', 'bonjour123', 'salam123',
        'welcome', 'Welcome1', 'changeme', 'changeit',
        '111111', '111111111', '000000', '000000000',
        'iloveyou', 'sunshine', 'princess', 'dragon',
        'aaaaaa', 'aaaaaaaa', 'zzzzzz', '1q2w3e4r',
        'qazwsx', 'qwerty123', 'Qwerty123', 'Pass@word1',
    ];

    /**
     * Valider un mot de passe selon la politique.
     * Retourne un tableau de violations (vide = valide).
     */
    public function valider(string $password, ?string $email = null, ?string $nomEcole = null): array
    {
        $violations = [];

        // Longueur minimale
        if (strlen($password) < 12) {
            $violations[] = 'Le mot de passe doit contenir au moins 12 caractères.';
        }

        // Majuscule
        if (!preg_match('/[A-Z]/', $password)) {
            $violations[] = 'Au moins une lettre majuscule est requise.';
        }

        // Minuscule
        if (!preg_match('/[a-z]/', $password)) {
            $violations[] = 'Au moins une lettre minuscule est requise.';
        }

        // Chiffre
        if (!preg_match('/[0-9]/', $password)) {
            $violations[] = 'Au moins un chiffre est requis.';
        }

        // Caractère spécial
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $violations[] = 'Au moins un caractère spécial est requis (@, #, !, ...)';
        }

        // Mots de passe courants interdits
        if (in_array($password, self::MOTS_DE_PASSE_INTERDITS)) {
            $violations[] = 'Ce mot de passe est trop courant et ne peut pas être utilisé.';
        }

        // Pas identique à l'email
        if ($email && strtolower($password) === strtolower($email)) {
            $violations[] = 'Le mot de passe ne peut pas être identique à l\'email.';
        }

        // Pas le nom de l'école
        if ($nomEcole && mb_stripos($password, $nomEcole) !== false) {
            $violations[] = 'Le mot de passe ne peut pas contenir le nom de l\'établissement.';
        }

        // Pas de séquences répétées (aaaa, 1111, etc.)
        if (preg_match('/(.)\1{3,}/', $password)) {
            $violations[] = 'Le mot de passe ne peut pas contenir 4 caractères identiques consécutifs.';
        }

        return $violations;
    }

    /**
     * Vérifier si un mot de passe est conforme à la politique.
     */
    public function estConforme(string $password, ?string $email = null): bool
    {
        return empty($this->valider($password, $email));
    }

    /**
     * Calculer la force d'un mot de passe (0-100).
     */
    public function calculerForce(string $password): array
    {
        $score = 0;

        if (strlen($password) >= 8)  $score += 20;
        if (strlen($password) >= 12) $score += 10;
        if (strlen($password) >= 16) $score += 10;
        if (preg_match('/[A-Z]/', $password)) $score += 15;
        if (preg_match('/[a-z]/', $password)) $score += 10;
        if (preg_match('/[0-9]/', $password)) $score += 15;
        if (preg_match('/[^A-Za-z0-9]/', $password)) $score += 20;

        $niveau = match(true) {
            $score >= 80 => 'Très fort',
            $score >= 60 => 'Fort',
            $score >= 40 => 'Moyen',
            $score >= 20 => 'Faible',
            default      => 'Très faible',
        };

        return ['score' => $score, 'niveau' => $niveau];
    }
}
```

---

## ÉTAPE 5 — Ajouter validation dans AuthController

**Modifier :**
`edugestdz/backend/app/Http/Controllers/Api/V1/AuthController.php`

Dans la méthode `register()` ou `updatePassword()` :

```php
// Ajouter la validation du mot de passe
$policyService = app(\App\Services\PasswordPolicyService::class);
$violations    = $policyService->valider($request->password, $request->email);

if (!empty($violations)) {
    return response()->json([
        'success'    => false,
        'message'    => 'Mot de passe non conforme à la politique de sécurité.',
        'violations' => $violations,
    ], 422);
}
```

---

## PARTIE C — RESTRICTION IP POUR SUPER-ADMIN
## ════════════════════════════════════════════

## ÉTAPE 6 — Middleware IpAllowlist pour Super-Admin

**Créer :**
`edugestdz/backend/app/Http/Middleware/SuperAdminIpAllowlist.php`

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Middleware de restriction IP pour le Super-Admin.
 *
 * Si SUPER_ADMIN_ALLOWED_IPS est défini dans .env :
 * → Seules les IPs listées peuvent accéder aux routes super-admin
 *
 * Si non défini : pas de restriction (pour le développement)
 *
 * Format .env : SUPER_ADMIN_ALLOWED_IPS=1.2.3.4,5.6.7.8
 *
 * Supports CIDR notation partielle (ex: 192.168.1.*)
 */
class SuperAdminIpAllowlist
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth('api')->user();

        // Appliquer seulement aux super-admins
        if (!$user || $user->role !== 'super_admin') {
            return $next($request);
        }

        $allowedIpsEnv = config('app.super_admin_allowed_ips', '');

        // Si non configuré → pas de restriction (environnement dev)
        if (empty($allowedIpsEnv)) {
            return $next($request);
        }

        $allowedIps = array_map('trim', explode(',', $allowedIpsEnv));
        $clientIp   = $request->ip();

        $autorise = false;
        foreach ($allowedIps as $allowedIp) {
            if ($this->ipCorrespond($clientIp, $allowedIp)) {
                $autorise = true;
                break;
            }
        }

        if (!$autorise) {
            Log::critical('SUPER_ADMIN IP BLOCKED', [
                'user_id'     => $user->id,
                'client_ip'   => $clientIp,
                'allowed_ips' => $allowedIps,
                'path'        => $request->path(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Accès Super-Admin refusé depuis cette adresse IP.',
                'code'    => 'IP_NOT_ALLOWED',
                'ip'      => $clientIp,
            ], 403);
        }

        return $next($request);
    }

    /**
     * Vérifier si l'IP correspond (support partiel CIDR avec *)
     */
    private function ipCorrespond(string $ip, string $pattern): bool
    {
        if ($ip === $pattern) return true;
        if ($pattern === '*') return true;

        // Support wildcard : 192.168.1.*
        if (str_contains($pattern, '*')) {
            $regex = '/^' . str_replace('.', '\.', str_replace('*', '\d+', $pattern)) . '$/';
            return (bool) preg_match($regex, $ip);
        }

        // Support CIDR : 192.168.1.0/24
        if (str_contains($pattern, '/')) {
            return $this->ipInCidr($ip, $pattern);
        }

        return false;
    }

    private function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $mask] = explode('/', $cidr);
        $ipLong     = ip2long($ip);
        $subnetLong = ip2long($subnet);
        $maskLong   = ~((1 << (32 - (int) $mask)) - 1);
        return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
    }
}
```

**Modifier :** `edugestdz/backend/bootstrap/app.php`

```php
$middleware->alias([
    // ... aliases existants ...
    'ip.allowlist' => \App\Http\Middleware\SuperAdminIpAllowlist::class,
]);
```

**Modifier :** `routes/api.php`

```php
Route::middleware(['auth:api', 'ip.allowlist', 'mfa'])->prefix('v1/super-admin')->group(function () {
    // ... routes super-admin existantes ...
});
```

**Modifier :** `.env.example`

```dotenv
# ── Super-Admin IP Allowlist ─────────────────────────────────────
# IPs autorisées pour le Super-Admin (séparées par virgule)
# Laisser vide pour aucune restriction (développement)
# Production : mettre ton IP fixe ou VPN
SUPER_ADMIN_ALLOWED_IPS=
# Exemple : SUPER_ADMIN_ALLOWED_IPS=41.109.xx.xx,192.168.1.100
```

**Modifier :** `config/app.php`

```php
'super_admin_allowed_ips' => env('SUPER_ADMIN_ALLOWED_IPS', ''),
```

---

## PARTIE D — ROTATION JWT SECRET
## ════════════════════════════════

## ÉTAPE 7 — JwtSecretRotationService

**Créer :**
`edugestdz/backend/app/Services/JwtSecretRotationService.php`

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Service de rotation du secret JWT.
 *
 * Principe :
 * - On maintient 2 secrets valides simultanément (courant + précédent)
 * - Les tokens signés avec l'ancien secret restent valides pendant la période de grâce
 * - Après la période de grâce, l'ancien secret est invalide → tous les anciens tokens expirent
 *
 * Avantage : même si le JWT_SECRET est compromis, il expire automatiquement.
 */
class JwtSecretRotationService
{
    private const CURRENT_KEY  = 'jwt_secret_current';
    private const PREVIOUS_KEY = 'jwt_secret_previous';
    private const ROTATION_LOG = 'jwt_rotation_log';
    private const GRACE_PERIOD = 3600 * 24; // 24h de grâce après rotation

    /**
     * Effectuer une rotation du secret JWT.
     * L'ancien secret reste valide 24h (période de grâce).
     */
    public function effectuerRotation(): array
    {
        $nouveauSecret = bin2hex(random_bytes(32)); // 64 caractères hex = 256 bits
        $ancienSecret  = config('jwt.secret');

        // Stocker l'ancien secret en cache (période de grâce 24h)
        Cache::put(self::PREVIOUS_KEY, $ancienSecret, self::GRACE_PERIOD);

        // Enregistrer le log de rotation
        $this->enregistrerRotation($ancienSecret, $nouveauSecret);

        Log::info('JWT Secret rotation effectuée', [
            'nouveau_hash' => hash('sha256', $nouveauSecret),
            'ancien_hash'  => hash('sha256', $ancienSecret),
            'grace_period' => self::GRACE_PERIOD . 's',
        ]);

        return [
            'nouveau_secret' => $nouveauSecret,
            'instruction'    => 'Mettre à jour JWT_SECRET dans les variables d\'environnement',
            'grace_until'    => now()->addSeconds(self::GRACE_PERIOD)->toDateTimeString(),
        ];
    }

    /**
     * Vérifier si un token est signé avec un secret valide
     * (courant OU précédent si dans la période de grâce).
     */
    public function secretPrecedentValide(): ?string
    {
        return Cache::get(self::PREVIOUS_KEY);
    }

    private function enregistrerRotation(string $ancien, string $nouveau): void
    {
        $log = Cache::get(self::ROTATION_LOG, []);
        $log[] = [
            'date'         => now()->toIso8601String(),
            'ancien_hash'  => hash('sha256', $ancien),
            'nouveau_hash' => hash('sha256', $nouveau),
        ];

        // Garder les 10 dernières rotations
        $log = array_slice($log, -10);
        Cache::put(self::ROTATION_LOG, $log, 3600 * 24 * 90); // 90 jours
    }

    /**
     * Obtenir l'historique des rotations.
     */
    public function getHistoriqueRotations(): array
    {
        return Cache::get(self::ROTATION_LOG, []);
    }
}
```

---

## ÉTAPE 8 — Commande rotation JWT

**Créer :**
`edugestdz/backend/app/Console/Commands/RoterJwtSecretCommand.php`

```php
<?php

namespace App\Console\Commands;

use App\Services\JwtSecretRotationService;
use Illuminate\Console\Command;

class RoterJwtSecretCommand extends Command
{
    protected $signature   = 'edugest:jwt-rotate';
    protected $description = 'Effectuer une rotation du secret JWT (sécurité)';

    public function handle(JwtSecretRotationService $service): int
    {
        $this->warn('⚠️  Cette commande va générer un nouveau JWT_SECRET.');
        $this->warn('   Les utilisateurs connectés resteront connectés 24h (période de grâce).');

        if (!$this->confirm('Continuer ?')) {
            $this->info('Annulé.');
            return Command::SUCCESS;
        }

        $result = $service->effectuerRotation();

        $this->info('✅ Rotation effectuée !');
        $this->line('');
        $this->line('<fg=yellow>Nouveau JWT_SECRET (mettre dans les variables d\'environnement) :</>');
        $this->line("<fg=green>{$result['nouveau_secret']}</>");
        $this->line('');
        $this->line("Période de grâce jusqu'à : {$result['grace_until']}");
        $this->line('');
        $this->warn('ACTION REQUISE : Redéployer l\'application avec le nouveau JWT_SECRET !');

        return Command::SUCCESS;
    }
}
```

---

## PARTIE E — PLAN DE RÉPONSE AUX INCIDENTS
## ═════════════════════════════════════════

## ÉTAPE 9 — BreachResponseController

**Créer :**
`edugestdz/backend/app/Http/Controllers/Api/V1/BreachResponseController.php`

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\JwtBlacklistService;
use App\Services\SecurityMonitorService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Contrôleur de réponse aux incidents de sécurité.
 * Accessible UNIQUEMENT au super_admin.
 *
 * Actions disponibles :
 * - Déclarer un incident
 * - Verrouillage d'urgence (invalider TOUS les tokens)
 * - Isoler un tenant (bloquer tous accès)
 * - Exporter les preuves (logs)
 * - Notifier l'ANPDP
 */
class BreachResponseController extends Controller
{
    public function __construct(
        private JwtBlacklistService    $jwtBlacklist,
        private SecurityMonitorService $monitor
    ) {}

    /**
     * Déclencher le verrouillage d'urgence global.
     * Invalide TOUS les tokens JWT actifs de TOUS les tenants.
     * À utiliser seulement en cas de compromission avérée.
     */
    public function verrouillageUrgence(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'raison'         => 'required|string|max:500',
            'confirmer_avec' => 'required|string', // Mot de confirmation
        ]);

        if ($validated['confirmer_avec'] !== 'VERROUILLAGE_URGENCE_CONFIRME') {
            return response()->json([
                'success' => false,
                'message' => 'Confirmation invalide. Tapez exactement : VERROUILLAGE_URGENCE_CONFIRME',
            ], 422);
        }

        // Invalider tous les tokens en stockant un timestamp global
        $timestamp = now()->timestamp;
        Cache::put('global_tokens_invalidated_at', $timestamp, now()->addDays(30));

        $this->monitor->alerter(
            'emergency_lockdown',
            'emergency',
            "🔴 VERROUILLAGE D'URGENCE ACTIVÉ par " . auth('api')->user()->email,
            ['raison' => $validated['raison'], 'timestamp' => $timestamp]
        );

        Log::emergency('EMERGENCY LOCKDOWN ACTIVATED', [
            'admin'     => auth('api')->id(),
            'raison'    => $validated['raison'],
            'timestamp' => $timestamp,
        ]);

        return response()->json([
            'success'  => true,
            'message'  => '🔴 Verrouillage d\'urgence activé. Tous les tokens sont invalides.',
            'raison'   => $validated['raison'],
            'timestamp'=> $timestamp,
            'action'   => 'Tous les utilisateurs devront se reconnecter.',
        ]);
    }

    /**
     * Déclarer un incident de sécurité (breach declaration - loi 18-07).
     */
    public function declarerIncident(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type_incident'          => 'required|in:data_leak,unauthorized_access,ransomware,insider_threat,other',
            'severite'               => 'required|in:low,medium,high,critical',
            'description'            => 'required|string|max:2000',
            'donnees_affectees'      => 'array',
            'nb_personnes_affectees' => 'required|integer|min:0',
            'detecte_le'             => 'required|date',
        ]);

        $incident = DB::table('breach_declarations')->insert(array_merge($validated, [
            'id'                => \Illuminate\Support\Str::uuid(),
            'tenant_id'         => $request->tenant_id ?? null,
            'donnees_affectees' => json_encode($validated['donnees_affectees'] ?? []),
            'statut'            => 'ouvert',
            'declare_par'       => auth('api')->id(),
            'created_at'        => now(),
            'updated_at'        => now(),
        ]));

        $this->monitor->alerter(
            'breach_declared',
            $validated['severite'],
            "📋 Incident déclaré ({$validated['type_incident']}) — {$validated['nb_personnes_affectees']} personnes affectées",
            $validated
        );

        // Rappel délai légal 72h ANPDP
        $delai72h = now()->addHours(72)->format('d/m/Y H:i');

        return response()->json([
            'success'      => true,
            'message'      => 'Incident enregistré.',
            'alerte_legal' => "⚠️ LOI 18-07 : Vous devez notifier l'ANPDP avant le {$delai72h} (délai légal 72h).",
            'contact_anpdp'=> 'www.anpdp.dz',
        ], 201);
    }

    /**
     * Obtenir les incidents ouverts.
     */
    public function indexIncidents(): JsonResponse
    {
        $incidents = DB::table('breach_declarations')
            ->orderByDesc('detecte_le')
            ->get();

        $nonNotifiesAnpdp = $incidents->whereNull('notifie_anpdp_le')
            ->where('detecte_le', '<', now()->subHours(72)->toDateTimeString())
            ->count();

        return response()->json([
            'success' => true,
            'data'    => [
                'incidents'          => $incidents,
                'en_retard_anpdp'    => $nonNotifiesAnpdp,
                'alerte'             => $nonNotifiesAnpdp > 0
                    ? "⚠️ {$nonNotifiesAnpdp} incident(s) non notifiés à l'ANPDP — délai légal dépassé"
                    : null,
            ],
        ]);
    }

    /**
     * Lever le verrouillage d'urgence.
     */
    public function leverVerrouillage(): JsonResponse
    {
        Cache::forget('global_tokens_invalidated_at');

        Log::info('Emergency lockdown lifted by ' . auth('api')->id());

        return response()->json([
            'success' => true,
            'message' => '✅ Verrouillage levé. Les nouveaux tokens seront valides.',
        ]);
    }
}
```

**Modifier :** `routes/api.php`

```php
use App\Http\Controllers\Api\V1\BreachResponseController;

Route::middleware(['auth:api', 'ip.allowlist'])->prefix('v1/security/breach')->group(function () {
    Route::post('/verrouillage-urgence', [BreachResponseController::class, 'verrouillageUrgence']);
    Route::post('/incidents',            [BreachResponseController::class, 'declarerIncident']);
    Route::get('/incidents',             [BreachResponseController::class, 'indexIncidents']);
    Route::delete('/verrouillage',       [BreachResponseController::class, 'leverVerrouillage']);
});
```

---

## ÉTAPE 10 — Middleware GlobalTokenCheck (verrouillage d'urgence)

**Modifier :**
`edugestdz/backend/app/Http/Middleware/JwtBlacklistCheck.php`

Ajouter dans la méthode `handle()` AVANT la vérification individuelle :

```php
// Vérification du verrouillage d'urgence global
$globalLockTimestamp = Cache::get('global_tokens_invalidated_at');
if ($globalLockTimestamp) {
    // Vérifier si le token a été émis AVANT le verrouillage
    $issuedAt = $payload->get('iat') ?? 0;
    if ($issuedAt < $globalLockTimestamp) {
        return response()->json([
            'success' => false,
            'message' => 'Session invalide suite à un incident de sécurité. Reconnectez-vous.',
            'code'    => 'GLOBAL_LOCKDOWN',
        ], 401);
    }
}
```

---

## ÉTAPE 11 — Tests sécurité Niveau 3

**Créer :**
`edugestdz/backend/tests/Feature/Security/SecurityNiveau3Test.php`

```php
<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Services\PasswordPolicyService;
use App\Services\ImmutableAuditService;
use App\Services\JwtSecretRotationService;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SecurityNiveau3Test extends TestCase
{
    use RefreshDatabase;

    // ── Politique mots de passe ────────────────────────────────────────

    public function test_mot_de_passe_court_refuse(): void
    {
        $policy     = app(PasswordPolicyService::class);
        $violations = $policy->valider('Pass1!');
        $this->assertNotEmpty($violations);
        $this->assertStringContainsString('12 caractères', $violations[0]);
    }

    public function test_mot_de_passe_sans_majuscule_refuse(): void
    {
        $policy     = app(PasswordPolicyService::class);
        $violations = $policy->valider('password123!');
        $this->assertNotEmpty($violations);
    }

    public function test_mot_de_passe_courant_refuse(): void
    {
        $policy     = app(PasswordPolicyService::class);
        $violations = $policy->valider('Password1');
        $this->assertNotEmpty($violations);
    }

    public function test_mot_de_passe_fort_accepte(): void
    {
        $policy     = app(PasswordPolicyService::class);
        $violations = $policy->valider('EduGest@2026!Secure#42');
        $this->assertEmpty($violations);
    }

    public function test_mot_de_passe_identique_email_refuse(): void
    {
        $policy     = app(PasswordPolicyService::class);
        $violations = $policy->valider('admin@edugest.dz', 'admin@edugest.dz');
        $this->assertNotEmpty($violations);
    }

    public function test_calcul_force_mot_de_passe(): void
    {
        $policy  = app(PasswordPolicyService::class);
        $faible  = $policy->calculerForce('abc');
        $fort    = $policy->calculerForce('EduGest@2026!Secure');

        $this->assertLessThan($fort['score'], $faible['score']);
        $this->assertEquals('Très faible', $faible['niveau']);
    }

    // ── Restriction IP Super-Admin ─────────────────────────────────────

    public function test_superadmin_ip_autorisee_passe(): void
    {
        config(['app.super_admin_allowed_ips' => '']);
        // Sans restriction → passe
        $admin = User::factory()->create(['role' => 'super_admin']);
        $token = auth('api')->login($admin);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/super-admin/tenants')
            ->assertNotEquals(403);
    }

    // ── Rotation JWT ───────────────────────────────────────────────────

    public function test_rotation_jwt_genere_nouveau_secret(): void
    {
        $service = app(JwtSecretRotationService::class);
        $result  = $service->effectuerRotation();

        $this->assertArrayHasKey('nouveau_secret', $result);
        $this->assertEquals(64, strlen($result['nouveau_secret']));
        $this->assertNotEquals(config('jwt.secret'), $result['nouveau_secret']);
    }

    public function test_historique_rotation_enregistre(): void
    {
        $service = app(JwtSecretRotationService::class);
        $service->effectuerRotation();

        $historique = $service->getHistoriqueRotations();
        $this->assertNotEmpty($historique);
        $this->assertArrayHasKey('date', $historique[0]);
    }

    // ── Audit immuable ─────────────────────────────────────────────────

    public function test_export_audit_signe_avec_hash(): void
    {
        $service = app(ImmutableAuditService::class);
        $result  = $service->exporterJournalier(null, now()->format('Y-m-d'));

        // L'export peut être vide (pas de logs) mais ne doit pas crasher
        $this->assertArrayHasKey('exportes', $result);
        $this->assertArrayHasKey('hash', $result);
    }

    // ── Incident de sécurité ───────────────────────────────────────────

    public function test_declarer_incident_requiert_super_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']); // pas super_admin
        $token = auth('api')->login($admin);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson('/api/v1/security/breach/incidents', [
                'type_incident'          => 'data_leak',
                'severite'               => 'high',
                'description'            => 'Test',
                'nb_personnes_affectees' => 0,
                'detecte_le'             => now()->format('Y-m-d'),
            ])
            ->assertStatus(403);
    }

    public function test_verrouillage_urgence_sans_confirmation_refuse(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $token = auth('api')->login($admin);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson('/api/v1/security/breach/verrouillage-urgence', [
                'raison'         => 'Test',
                'confirmer_avec' => 'mauvaise_confirmation',
            ])
            ->assertStatus(422);
    }
}
```

---

## ÉTAPE 12 — Documenter le plan de réponse aux incidents

**Créer :**
`edugestdz/INCIDENT_RESPONSE_PLAN.md`

```markdown
# 🚨 Plan de Réponse aux Incidents — EduGest DZ
## Procédure officielle · Loi 18-07 · Mise à jour : Juillet 2026

---

## CONTACTS D'URGENCE

| Rôle | Contact | Disponibilité |
|---|---|---|
| Responsable technique | [Ton email] | 24/7 |
| ANPDP (Algérie) | www.anpdp.dz | Heures ouvrables |
| Hostarts DZ (hébergeur) | support@hostarts.dz | 24/7 |

---

## ÉTAPES EN CAS D'INCIDENT

### Étape 1 — DÉTECTER (0-15 minutes)
```
Dashboard sécurité : GET /api/v1/security/dashboard
Vérifier les alertes Telegram
Consulter les logs : docker compose logs app --tail=200
```

### Étape 2 — CONTENIR (15-60 minutes)
```
Si compromission avérée → Verrouillage d'urgence :
POST /api/v1/security/breach/verrouillage-urgence
{ "raison": "...", "confirmer_avec": "VERROUILLAGE_URGENCE_CONFIRME" }

Si 1 seul tenant compromis → Désactiver uniquement ce tenant :
POST /api/v1/super-admin/tenants/{id}/suspendre
```

### Étape 3 — DOCUMENTER (immédiatement)
```
Déclarer l'incident :
POST /api/v1/security/breach/incidents
{
  "type_incident": "data_leak|unauthorized_access|ransomware|...",
  "severite": "low|medium|high|critical",
  "description": "Description détaillée",
  "nb_personnes_affectees": 0,
  "detecte_le": "2026-07-07"
}
```

### Étape 4 — NOTIFIER (dans les 72h — loi 18-07)
```
Délai légal : 72h après détection pour notifier l'ANPDP
Contact : www.anpdp.dz
Informations à fournir :
  - Nature de la violation
  - Données concernées
  - Nombre de personnes affectées
  - Mesures prises
```

### Étape 5 — CORRIGER ET RESTAURER
```
Identifier la cause racine
Appliquer le patch
Vérifier les logs d'audit (intégrité)
Lever le verrouillage si activé :
DELETE /api/v1/security/breach/verrouillage
Rotation JWT secret :
php artisan edugest:jwt-rotate
```

### Étape 6 — INFORMER LES CLIENTS
```
Email aux directeurs d'école concernés avec :
  - Ce qui s'est passé
  - Les données concernées
  - Les mesures prises
  - Ce qu'ils doivent faire
```

---

## DÉLAIS LÉGAUX (loi 18-07)
- Notification ANPDP : **72h** après détection
- Notification des personnes affectées : **sans délai déraisonnable**
- Sanctions si non-respect : 500 000 DA → 4 000 000 DA
```

---

## ÉTAPE 13 — Exécution

```bash
cd edugestdz/backend

php artisan migrate
composer dump-autoload -o
php artisan test --parallel
# → 0 régression + 11 nouveaux tests verts

git add .
git commit -m "security(niveau3): Audit immuable signé HMAC + Politique mots de passe + IP Allowlist super-admin + Rotation JWT + Plan réponse incidents + Verrouillage urgence + 11 tests"
git push origin develop
# → PR develop → main
```

---

## CE QUE TU DIS À DEEPSEEK

```
Repo : https://github.com/Allintelligence2024/edugest-dz.git
Branche : develop
git checkout develop && git pull origin main

Fichier : MISSION_SECURITE_NIVEAU3.md — 13 étapes dans l'ordre.

RÈGLES CRITIQUES :
1. PostgreSQL uniquement — jamais SQLite. 0 régression.
2. ImmutableAuditService : le disk 'local' (pas 'public') pour les exports d'audit.
3. PasswordPolicyService : la liste des mots de passe interdits est fixe dans le code.
   Ne pas charger depuis un fichier externe (risque de ne pas trouver le fichier).
4. SuperAdminIpAllowlist : si SUPER_ADMIN_ALLOWED_IPS est vide → ne pas bloquer.
   Ne jamais bloquer en développement (pas de config = pas de restriction).
5. JwtSecretRotationService : la commande affiche le nouveau secret dans le terminal
   mais ne le sauvegarde PAS automatiquement dans .env — c'est intentionnel.
   L'admin doit le copier manuellement.
6. BreachResponseController : accessible UNIQUEMENT avec ip.allowlist middleware.
   Le middleware 'ip.allowlist' doit être dans les aliases de bootstrap/app.php.
7. Le fichier INCIDENT_RESPONSE_PLAN.md va à la RACINE du repo (pas dans /docs/).
8. JwtBlacklistCheck middleware : ajouter la vérification du verrouillage global
   AVANT la vérification individuelle du token.

php artisan migrate
composer dump-autoload -o
php artisan test --parallel → verts
git push origin develop → PR develop → main
```
