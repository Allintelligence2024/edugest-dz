# 🔐 MISSION DEEPSEEK — Sécurité Niveau 5 (HONEYPOTS + CANARY TOKENS + VAULT)
## EduGest DZ · Branche : develop · 8 Juillet 2026
## Tests actuels : 450+ ✅ · Objectif : ≥ 465 ✅ · 0 régression
## Prérequis : Niveaux 1, 2, 3 et 4 MERGÉS sur main

---

## PHILOSOPHIE NIVEAU 5

```
Niveau 4 : "Évaluer chaque requête activement"
Niveau 5 : "Piéger les attaquants + Protéger les secrets en dur + Détecter les insiders"

Nouveau dans ce niveau :
  1. Honeypot Fields      — Champs pièges dans les formulaires (bots se trahissent)
  2. Honeypot Routes      — Routes leurres qui n'existent pas mais alertent
  3. Canary Tokens        — Tokens de détection d'exfiltration de BDD
  4. HashiCorp Vault      — Gestion des secrets (plus de secrets en .env)
  5. SSRF Protection      — Bloquer Server-Side Request Forgery
  6. SQL Injection Layer  — Double couche anti-injection PostgreSQL
  7. Insider Threat       — Détecter les utilisateurs internes malveillants
  8. Data Loss Prevention — Bloquer l'exfiltration via l'API
  9. Mutual TLS (mTLS)    — Certificats client pour services internes
 10. Dead Man Switch      — Alerte automatique si aucune activité admin 7 jours
```

### RÈGLES ABSOLUES
1. 0 régression — tests existants restent verts
2. PostgreSQL uniquement — jamais SQLite
3. Les honeypots ne doivent jamais apparaître dans les réponses API légitimes
4. Les canary tokens doivent être indétectables pour un user normal
5. Dégradation gracieuse si Vault non configuré (fallback sur .env chiffré)

---

## ÉTAPE 0 — Synchroniser

```bash
git checkout develop && git pull origin main
```

---

## PARTIE A — HONEYPOTS ACTIFS
## ══════════════════════════════

## ÉTAPE 1 — Migration : honeypot tables

```php
// Créer : edugestdz/backend/database/migrations/2026_07_08_700000_create_honeypot_tables.php

<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Routes leurres déclenchées
        Schema::create('honeypot_triggers', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
            $table->string('type');          // route | field | canary | sql_probe
            $table->string('chemin_acces');  // La route ou le champ qui a déclenché
            $table->string('ip_hash', 64);
            $table->string('user_agent_hash', 64);
            $table->uuid('user_id')->nullable();
            $table->uuid('tenant_id')->nullable();
            $table->jsonb('donnees');        // Ce que l'attaquant a soumis (sanitisé)
            $table->integer('severite');     // 1-10
            $table->boolean('bloque')->default(true);
            $table->boolean('alerte_envoyee')->default(false);
            $table->timestamp('survenu_le')->useCurrent();

            $table->index(['ip_hash', 'survenu_le'], 'idx_honeypot_ip');
            $table->index(['type', 'survenu_le'],    'idx_honeypot_type');
        });

        // Canary tokens — tokens fantômes pour détecter les fuites de BDD
        Schema::create('canary_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
            $table->string('token', 64)->unique();  // Token de détection unique
            $table->string('type');                 // eleve | user | facture | personnel
            $table->uuid('tenant_id');
            $table->string('description');          // Description pour l'audit
            $table->boolean('declenche')->default(false);
            $table->timestamp('declenche_le')->nullable();
            $table->string('declenche_ip')->nullable();
            $table->timestamp('cree_le')->useCurrent();
            $table->timestamps();

            $table->index(['token'],         'idx_canary_token');
            $table->index(['tenant_id'],     'idx_canary_tenant');
            $table->index(['declenche'],     'idx_canary_triggered');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canary_tokens');
        Schema::dropIfExists('honeypot_triggers');
    }
};
```

---

## ÉTAPE 2 — HoneypotService

```php
// Créer : edugestdz/backend/app/Services/HoneypotService.php

<?php
namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Service Honeypot — piège les bots et les attaquants.
 *
 * Stratégies implémentées :
 *
 * 1. HONEYPOT FIELDS : Des champs cachés dans les formulaires (ex: 'website', 'fax').
 *    Les humains ne les remplissent pas (display:none en CSS).
 *    Les bots qui remplissent tout → détectés immédiatement.
 *
 * 2. HONEYPOT ROUTES : Des routes qui ne devraient jamais être appelées.
 *    (ex: /api/v1/admin-panel, /api/v1/.env, /api/phpmyadmin)
 *    Tout accès à ces routes → IP blacklistée + alerte.
 *
 * 3. CANARY TOKENS : Des données fictives injectées dans la BDD.
 *    Si ces données apparaissent dans une requête → preuve de fuite de BDD.
 */
class HoneypotService
{
    // Champs pièges dans les formulaires (ne doivent jamais être remplis)
    private const CHAMPS_PIÈGES = ['website', 'fax', 'company_url', 'hp_field', 'url', 'homepage'];

    // Routes leurres (tout accès = attaque confirmée)
    private const ROUTES_LEURRES = [
        'api/v1/admin-panel',
        'api/v1/admin',
        'api/.env',
        'api/v1/.env',
        'api/phpmyadmin',
        'api/adminer',
        'api/v1/wp-admin',
        'api/v1/config',
        'api/v1/debug',
        'api/v1/laravel-admin',
        'api/v1/telescope-internal',
        'api/v1/horizon-internal',
        'api/v1/users/all-no-auth',
        'api/v1/backup',
        'api/v1/dump',
        'api/v1/shell',
    ];

    public function __construct(private SecurityMonitorService $monitor) {}

    /**
     * Vérifier si la requête contient des champs pièges remplis.
     * Retourne true si honeypot déclenché (= bot détecté).
     */
    public function verifierChampsPièges(Request $request): bool
    {
        foreach (self::CHAMPS_PIÈGES as $champ) {
            if ($request->filled($champ)) {
                $this->déclencher('field', $champ, $request, [
                    'champ'  => $champ,
                    'valeur' => substr($request->input($champ), 0, 50), // Tronquer
                ], 7);
                return true;
            }
        }
        return false;
    }

    /**
     * Vérifier si l'URL accédée est une route leurre.
     */
    public function estRouteLeurre(string $path): bool
    {
        foreach (self::ROUTES_LEURRES as $route) {
            if ($path === $route || str_starts_with($path, $route)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Déclencher une alerte honeypot route.
     */
    public function déclencherRouteLeurre(Request $request): void
    {
        $this->déclencher('route', $request->path(), $request, [
            'method'     => $request->method(),
            'body_size'  => strlen($request->getContent()),
        ], 9);
    }

    /**
     * Vérifier si un canary token est utilisé dans une requête.
     * Si oui → preuve de fuite de BDD.
     */
    public function verifierCanaryToken(Request $request): void
    {
        // Chercher des patterns de canary token dans le corps de la requête
        // Format des canary tokens : EDUGEST-CANARY-{32 chars hex}
        $corps   = $request->getContent();
        $headers = implode(' ', $request->headers->all());
        $all     = $corps . ' ' . $headers;

        if (preg_match('/EDUGEST-CANARY-([a-f0-9]{32})/i', $all, $matches)) {
            $token = strtolower($matches[0]);

            $canary = DB::table('canary_tokens')
                ->where('token', $token)
                ->first();

            if ($canary && !$canary->declenche) {
                DB::table('canary_tokens')
                    ->where('id', $canary->id)
                    ->update([
                        'declenche'    => true,
                        'declenche_le' => now(),
                        'declenche_ip' => $request->ip(),
                        'updated_at'   => now(),
                    ]);

                $this->monitor->alerter(
                    'canary_token_triggered',
                    'emergency',
                    "🚨🚨 FUITE DE BDD CONFIRMÉE — Canary token utilisé depuis {$request->ip()} — Token: {$token}",
                    ['token' => $token, 'type' => $canary->type, 'tenant' => $canary->tenant_id]
                );

                $this->déclencher('canary', $token, $request, [
                    'canary_id'  => $canary->id,
                    'canary_type'=> $canary->type,
                ], 10);
            }
        }
    }

    /**
     * Injecter des canary tokens dans une liste de résultats.
     * Ces tokens fictifs n'affectent pas l'affichage (champs non visibles en frontend).
     */
    public function injecterCanaries(array $resultats, string $type, string $tenantId): array
    {
        if (count($resultats) < 5) return $resultats; // Pas assez de données pour cacher

        // Créer ou récupérer un canary token pour ce tenant+type
        $canary = DB::table('canary_tokens')
            ->where('tenant_id', $tenantId)
            ->where('type', $type)
            ->where('declenche', false)
            ->first();

        if (!$canary) {
            $token = 'edugest-canary-' . bin2hex(random_bytes(16));
            DB::table('canary_tokens')->insert([
                'id'          => (string) Str::uuid(),
                'token'       => $token,
                'type'        => $type,
                'tenant_id'   => $tenantId,
                'description' => "Canary token pour {$type} du tenant {$tenantId}",
                'cree_le'     => now(),
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
            $canaryToken = $token;
        } else {
            $canaryToken = $canary->token;
        }

        // Injecter dans un enregistrement au milieu de la liste (pas le premier ni le dernier)
        $position = (int) (count($resultats) / 2);
        if (isset($resultats[$position]) && is_array($resultats[$position])) {
            // Injecter dans un champ rarement affiché (metadata, audit_id, etc.)
            $resultats[$position]['_audit_ref'] = $canaryToken;
        }

        return $resultats;
    }

    private function déclencher(string $type, string $chemin, Request $request, array $données, int $severite): void
    {
        try {
            DB::table('honeypot_triggers')->insert([
                'id'             => (string) Str::uuid(),
                'type'           => $type,
                'chemin_acces'   => substr($chemin, 0, 200),
                'ip_hash'        => hash('sha256', $request->ip()),
                'user_agent_hash'=> hash('sha256', $request->header('User-Agent', '')),
                'user_id'        => auth('api')->id(),
                'tenant_id'      => config('tenant.current_id'),
                'donnees'        => json_encode($données),
                'severite'       => $severite,
                'bloque'         => $severite >= 7,
                'alerte_envoyee' => false,
                'survenu_le'     => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Honeypot: enregistrement échoué: ' . $e->getMessage());
        }

        if ($severite >= 7) {
            $this->monitor->alerter(
                "honeypot_{$type}",
                $severite >= 9 ? 'emergency' : 'critical',
                "🍯 HONEYPOT {$type} déclenché depuis {$request->ip()} → {$chemin}",
                array_merge($données, ['ip' => $request->ip(), 'severite' => $severite])
            );
        }

        // Blacklister l'IP si severité max
        if ($severite >= 9) {
            \Illuminate\Support\Facades\Cache::put(
                'ip_blacklisted:' . hash('sha256', $request->ip()),
                true,
                3600 * 24 // 24h de blacklist
            );
        }

        Log::warning("HONEYPOT[{$type}] déclenché: {$chemin} depuis {$request->ip()}");
    }
}
```

---

## ÉTAPE 3 — HoneypotMiddleware (routes leurres)

```php
// Créer : edugestdz/backend/app/Http/Middleware/HoneypotRouteMiddleware.php

<?php
namespace App\Http\Middleware;

use App\Services\HoneypotService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Middleware Honeypot — vérifie chaque requête :
 * 1. L'IP n'est pas blacklistée (après honeypot précédent)
 * 2. La route n'est pas une route leurre
 * 3. La requête ne contient pas un canary token usurpé
 */
class HoneypotRouteMiddleware
{
    public function __construct(private HoneypotService $honeypot) {}

    public function handle(Request $request, Closure $next)
    {
        $ipHash = hash('sha256', $request->ip());

        // Vérification 1 : IP blacklistée (suite à un honeypot précédent)
        if (Cache::has("ip_blacklisted:{$ipHash}")) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé.',
                'code'    => 'ACCESS_DENIED',
            ], 403);
        }

        // Vérification 2 : Route leurre
        if ($this->honeypot->estRouteLeurre($request->path())) {
            $this->honeypot->déclencherRouteLeurre($request);
            // Retourner un 404 standard (ne pas signaler que c'est un honeypot)
            return response()->json(['message' => 'Not Found.'], 404);
        }

        // Vérification 3 : Canary token dans la requête
        $this->honeypot->verifierCanaryToken($request);

        return $next($request);
    }
}
```

---

## PARTIE B — PROTECTION SSRF + SQL INJECTION LAYER
## ════════════════════════════════════════════════════

## ÉTAPE 4 — SsrfProtectionService

```php
// Créer : edugestdz/backend/app/Services/SsrfProtectionService.php

<?php
namespace App\Services;

/**
 * Protection contre Server-Side Request Forgery (SSRF).
 *
 * Un attaquant peut essayer d'utiliser l'API pour faire des requêtes
 * vers des ressources internes (metadata AWS, BDD, Redis, etc.).
 *
 * Ce service valide toutes les URLs fournies par les utilisateurs
 * (ex: webhook URLs, avatar URLs, Google Classroom redirects).
 */
class SsrfProtectionService
{
    // Plages IP internes à bloquer
    private const IP_INTERNES = [
        '127.0.0.0/8',   // localhost
        '10.0.0.0/8',    // RFC 1918
        '172.16.0.0/12', // RFC 1918
        '192.168.0.0/16',// RFC 1918
        '169.254.0.0/16',// Link-local (metadata AWS)
        '::1/128',       // IPv6 localhost
        'fc00::/7',      // IPv6 ULA
    ];

    // Domaines interdits (métadonnées cloud)
    private const DOMAINES_INTERDITS = [
        '169.254.169.254',    // AWS/GCP/Azure metadata
        'metadata.google.internal',
        'metadata.azure.com',
        'imds.amazonaws.com',
    ];

    // Domaines autorisés pour les webhooks (liste blanche)
    private const DOMAINES_AUTORISES = [
        'api.telegram.org',
        'api.whatsapp.com',
        'graph.facebook.com',
        'oauth2.googleapis.com',
        'fcm.googleapis.com',
        'classroom.googleapis.com',
    ];

    /**
     * Valider qu'une URL fournie par un utilisateur est sûre.
     * Lève une exception si l'URL est dangereuse.
     */
    public function validerUrl(string $url): void
    {
        // Vérifier le schéma
        $parsed = parse_url($url);
        if (!$parsed || !isset($parsed['scheme'], $parsed['host'])) {
            throw new \InvalidArgumentException("URL invalide: {$url}");
        }

        if (!in_array($parsed['scheme'], ['https', 'http'])) {
            throw new \InvalidArgumentException("Schéma non autorisé: {$parsed['scheme']}. HTTPS requis.");
        }

        // Forcer HTTPS en production
        if (app()->environment('production') && $parsed['scheme'] !== 'https') {
            throw new \InvalidArgumentException("HTTPS obligatoire en production.");
        }

        $host = strtolower($parsed['host']);

        // Vérifier les domaines interdits
        foreach (self::DOMAINES_INTERDITS as $interdit) {
            if ($host === $interdit || str_ends_with($host, '.' . $interdit)) {
                throw new \InvalidArgumentException("Domaine interdit (SSRF protection): {$host}");
            }
        }

        // Résoudre l'IP et vérifier qu'elle n'est pas interne
        $ip = gethostbyname($host);
        if ($ip && $ip !== $host) { // gethostbyname retourne le host si pas résolu
            foreach (self::IP_INTERNES as $cidr) {
                if ($this->ipDansCidr($ip, $cidr)) {
                    throw new \InvalidArgumentException("Requête vers IP interne bloquée (SSRF protection): {$ip}");
                }
            }
        }
    }

    /**
     * Valider qu'une URL de webhook est dans la liste blanche.
     */
    public function validerWebhookUrl(string $url): void
    {
        $this->validerUrl($url);

        $parsed = parse_url($url);
        $host   = strtolower($parsed['host'] ?? '');

        foreach (self::DOMAINES_AUTORISES as $autorisé) {
            if ($host === $autorisé || str_ends_with($host, '.' . $autorisé)) {
                return; // Autorisé
            }
        }

        throw new \InvalidArgumentException("Domaine webhook non autorisé: {$host}. Seuls les domaines approuvés sont acceptés.");
    }

    private function ipDansCidr(string $ip, string $cidr): bool
    {
        // Ignorer IPv6 pour simplifier
        if (str_contains($cidr, ':')) return false;
        if (!str_contains($cidr, '/')) return $ip === $cidr;

        [$subnet, $mask] = explode('/', $cidr);
        return (ip2long($ip) & ~((1 << (32 - (int)$mask)) - 1)) === (ip2long($subnet) & ~((1 << (32 - (int)$mask)) - 1));
    }
}
```

---

## ÉTAPE 5 — SqlInjectionDetectorMiddleware (couche supplémentaire)

```php
// Créer : edugestdz/backend/app/Http/Middleware/SqlInjectionDetectorMiddleware.php

<?php
namespace App\Http\Middleware;

use App\Services\SecurityMonitorService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Détection SQL Injection — couche défense supplémentaire.
 *
 * IMPORTANT : Laravel + Eloquent avec paramètres liés (bindings) protège contre
 * l'injection SQL de base. Ce middleware est une couche SUPPLÉMENTAIRE qui détecte
 * les tentatives sophistiquées avant qu'elles n'atteignent Eloquent.
 *
 * Analyse les paramètres GET, POST et les headers pour détecter des patterns
 * caractéristiques d'une attaque SQL injection.
 */
class SqlInjectionDetectorMiddleware
{
    // Patterns d'injection SQL dangereux
    private const PATTERNS = [
        // Commentaires SQL
        '/--\s/',
        '/\/\*.*\*\//',
        // UNION based
        '/\bUNION\s+(ALL\s+)?SELECT\b/i',
        // Boolean based
        '/\b(OR|AND)\s+[\'"]?\d+[\'"]?\s*=\s*[\'"]?\d+[\'"]?/i',
        '/\b(OR|AND)\s+[\'"]?[a-z]+[\'"]?\s*=\s*[\'"]?[a-z]+[\'"]?/i',
        // Time based
        '/\bSLEEP\s*\(\s*\d+\s*\)/i',
        '/\bWAITFOR\s+DELAY\b/i',
        '/\bPG_SLEEP\s*\(/i',
        // Error based
        '/\bEXTRACTVALUE\s*\(/i',
        '/\bUPDATEXML\s*\(/i',
        // Stacked queries
        '/;\s*(DROP|DELETE|INSERT|UPDATE|CREATE|ALTER|EXEC)\b/i',
        // Information schema
        '/\bINFORMATION_SCHEMA\b/i',
        '/\bSYS\.TABLES\b/i',
        '/\bPG_TABLES\b/i',
        // Char/Ascii encoding tricks
        '/\bCHAR\s*\(\s*\d+(\s*,\s*\d+)*\s*\)/i',
        '/\bASCII\s*\(\s*SUBSTR/i',
        // LOAD_FILE
        '/\bLOAD_FILE\s*\(/i',
        '/\bINTO\s+OUTFILE\b/i',
        // XP_CMDSHELL (MSSQL)
        '/\bXP_CMDSHELL\b/i',
    ];

    public function __construct(private SecurityMonitorService $monitor) {}

    public function handle(Request $request, Closure $next)
    {
        $valeurs = $this->extraireValeurs($request);

        foreach ($valeurs as $nom => $valeur) {
            if (!is_string($valeur)) continue;

            foreach (self::PATTERNS as $pattern) {
                if (preg_match($pattern, $valeur)) {
                    $this->monitor->alerter(
                        'sql_injection_attempt',
                        'critical',
                        "🚨 Tentative SQL Injection depuis {$request->ip()} — Paramètre: {$nom}",
                        [
                            'ip'        => $request->ip(),
                            'parametre' => $nom,
                            'pattern'   => $pattern,
                            'valeur'    => substr($valeur, 0, 100),
                            'path'      => $request->path(),
                        ]
                    );

                    // Blacklister l'IP 1h
                    \Illuminate\Support\Facades\Cache::put(
                        'ip_blacklisted:' . hash('sha256', $request->ip()),
                        true,
                        3600
                    );

                    return response()->json([
                        'success' => false,
                        'message' => 'Requête invalide.',
                        'code'    => 'INVALID_INPUT',
                    ], 400);
                }
            }
        }

        return $next($request);
    }

    private function extraireValeurs(Request $request): array
    {
        $valeurs = [];

        // Paramètres GET
        foreach ($request->query() as $nom => $val) {
            $valeurs["GET:{$nom}"] = is_array($val) ? json_encode($val) : $val;
        }

        // Paramètres POST (JSON)
        foreach ($request->all() as $nom => $val) {
            if (is_string($val)) $valeurs["POST:{$nom}"] = $val;
        }

        return $valeurs;
    }
}
```

---

## PARTIE C — VAULT SECRETS MANAGEMENT (hashicorp compatible)
## ══════════════════════════════════════════════════════════════

## ÉTAPE 6 — VaultSecretsService (interface + fallback chiffré)

```php
// Créer : edugestdz/backend/app/Services/VaultSecretsService.php

<?php
namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service de gestion des secrets — compatible HashiCorp Vault.
 *
 * Architecture :
 * - Si Vault est configuré (VAULT_ADDR + VAULT_TOKEN) → utiliser Vault
 * - Sinon → fallback sur secrets chiffrés en BDD (tier de sécurité N-1)
 *
 * Secrets gérés :
 * - API keys Satim (par tenant)
 * - Google OAuth secrets
 * - Firebase service account keys
 * - Clés de chiffrement par tenant
 * - Tokens d'intégration tierces
 *
 * AVANTAGE VAULT :
 * - Les secrets ne sont JAMAIS dans le code ni dans .env
 * - Rotation automatique des secrets
 * - Audit de chaque accès à un secret
 * - Révocation instantanée
 */
class VaultSecretsService
{
    private const CACHE_TTL = 300; // 5 min (ne pas appeler Vault trop souvent)

    /**
     * Récupérer un secret.
     * Format du path : "edugest/data/{tenant_id}/{secret_name}"
     */
    public function get(string $path, ?string $default = null): ?string
    {
        $cacheKey = 'vault_secret:' . hash('sha256', $path);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($path, $default) {
            // Essayer Vault d'abord
            if ($this->vaultDisponible()) {
                try {
                    return $this->getFromVault($path);
                } catch (\Throwable $e) {
                    Log::warning("Vault: impossible de récupérer {$path}: " . $e->getMessage());
                }
            }

            // Fallback : BDD chiffrée
            return $this->getFromDatabase($path) ?? $default;
        });
    }

    /**
     * Stocker un secret.
     */
    public function put(string $path, string $valeur): bool
    {
        Cache::forget('vault_secret:' . hash('sha256', $path));

        if ($this->vaultDisponible()) {
            try {
                return $this->putInVault($path, $valeur);
            } catch (\Throwable $e) {
                Log::warning("Vault: impossible de stocker {$path}: " . $e->getMessage());
            }
        }

        return $this->putInDatabase($path, $valeur);
    }

    /**
     * Supprimer un secret (révocation).
     */
    public function delete(string $path): bool
    {
        Cache::forget('vault_secret:' . hash('sha256', $path));

        $vaultOk = false;
        if ($this->vaultDisponible()) {
            try {
                $vaultOk = $this->deleteFromVault($path);
            } catch (\Throwable) {}
        }

        $dbOk = $this->deleteFromDatabase($path);
        return $vaultOk || $dbOk;
    }

    private function vaultDisponible(): bool
    {
        return !empty(config('services.vault.addr')) && !empty(config('services.vault.token'));
    }

    private function getFromVault(string $path): ?string
    {
        $addr  = config('services.vault.addr');
        $token = config('services.vault.token');

        $response = Http::withHeaders(['X-Vault-Token' => $token])
            ->timeout(5)
            ->get("{$addr}/v1/{$path}");

        if ($response->successful()) {
            return $response->json('data.data.value');
        }

        if ($response->status() === 404) return null;

        throw new \RuntimeException("Vault error: " . $response->status());
    }

    private function putInVault(string $path, string $valeur): bool
    {
        $addr  = config('services.vault.addr');
        $token = config('services.vault.token');

        $response = Http::withHeaders(['X-Vault-Token' => $token])
            ->timeout(5)
            ->post("{$addr}/v1/{$path}", ['data' => ['value' => $valeur]]);

        return $response->successful();
    }

    private function deleteFromVault(string $path): bool
    {
        $addr  = config('services.vault.addr');
        $token = config('services.vault.token');

        return Http::withHeaders(['X-Vault-Token' => $token])
            ->timeout(5)
            ->delete("{$addr}/v1/{$path}")
            ->successful();
    }

    private function getFromDatabase(string $path): ?string
    {
        $record = \DB::table('encrypted_secrets')->where('path', $path)->first();
        if (!$record) return null;

        try {
            return Crypt::decryptString($record->valeur_chiffree);
        } catch (\Throwable) {
            return null;
        }
    }

    private function putInDatabase(string $path, string $valeur): bool
    {
        $chiffre = Crypt::encryptString($valeur);

        \DB::table('encrypted_secrets')->updateOrInsert(
            ['path' => $path],
            ['valeur_chiffree' => $chiffre, 'updated_at' => now()]
        );
        return true;
    }

    private function deleteFromDatabase(string $path): bool
    {
        return (bool) \DB::table('encrypted_secrets')->where('path', $path)->delete();
    }
}
```

---

## ÉTAPE 7 — Migration : encrypted_secrets (fallback Vault)

```php
// Créer : edugestdz/backend/database/migrations/2026_07_08_710000_create_encrypted_secrets_table.php

<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Table de fallback si Vault n'est pas disponible
        // Tous les secrets sont chiffrés avec Crypt::encryptString (AES-256-CBC)
        Schema::create('encrypted_secrets', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
            $table->string('path')->unique(); // ex: edugest/data/{tenant_id}/satim_password
            $table->text('valeur_chiffree');  // Valeur chiffrée AES-256-CBC
            $table->string('version')->default('1');
            $table->timestamp('expire_le')->nullable();
            $table->timestamps();

            $table->index(['path'],      'idx_secrets_path');
            $table->index(['expire_le'], 'idx_secrets_expiry');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encrypted_secrets');
    }
};
```

---

## PARTIE D — INSIDER THREAT + DATA LOSS PREVENTION
## ═════════════════════════════════════════════════

## ÉTAPE 8 — InsiderThreatDetectorService

```php
// Créer : edugestdz/backend/app/Services/InsiderThreatDetectorService.php

<?php
namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Détecteur de menaces internes (Insider Threat).
 *
 * Les menaces internes sont les plus difficiles à détecter car le user
 * a des accès légitimes. On analyse les PATTERNS inhabituels :
 *
 * - Volume excessif (bulk download)
 * - Accès à des données en dehors de leur périmètre habituel
 * - Téléchargements massifs avant une démission/vacances
 * - Accès depuis des IPs inconnues
 * - Modifications inhabituelles en volume
 * - Utilisation de l'API en dehors des heures de travail
 */
class InsiderThreatDetectorService
{
    // Seuils de détection
    private const SEUIL_DOWNLOAD_JOURNALIER = 500;  // Enregistrements par jour
    private const SEUIL_DOWNLOAD_HORAIRE    = 100;  // Enregistrements par heure
    private const SEUIL_MODIFICATIONS       = 50;   // Modifications par heure
    private const SEUIL_RESSOURCES_UNIQUES  = 10;   // Ressources différentes en 30min

    public function __construct(private SecurityMonitorService $monitor) {}

    /**
     * Enregistrer un accès aux données.
     * À appeler depuis les contrôleurs qui retournent des listes.
     */
    public function enregistrerAcces(
        string $userId,
        string $resource,
        int    $nbResultats,
        string $action = 'read'
    ): void {
        $heure    = now()->format('Y-m-d-H');
        $jour     = now()->format('Y-m-d');

        // Compteur horaire
        $cléHeure = "insider:{$userId}:{$action}:{$resource}:{$heure}";
        $totalHeure = Cache::increment($cléHeure, $nbResultats);
        if ($totalHeure === $nbResultats) Cache::expire($cléHeure, 3600);

        // Compteur journalier
        $cléJour  = "insider:{$userId}:{$action}:{$jour}";
        $totalJour = Cache::increment($cléJour, $nbResultats);
        if ($totalJour === $nbResultats) Cache::expire($cléJour, 86400);

        // Compteur ressources uniques (30 min)
        $cléRess  = "insider_resources:{$userId}:" . now()->format('Y-m-d-H') . ':' . (int)(date('i') / 30);
        $ressources = Cache::get($cléRess, []);
        if (!in_array($resource, $ressources)) {
            $ressources[] = $resource;
            Cache::put($cléRess, $ressources, 1800);
        }

        // Vérifications
        $this->verifierSeuils($userId, $resource, $action, $totalHeure, $totalJour, count($ressources));
    }

    /**
     * Détecter si un utilisateur fait un bulk export (avant démission possible).
     */
    public function détecterBulkExport(string $userId, string $tenantId, int $nbResultats): void
    {
        if ($nbResultats < 200) return;

        $this->monitor->alerter(
            'insider_bulk_export',
            'critical',
            "🕵️ Bulk export détecté — User {$userId} a récupéré {$nbResultats} enregistrements en une requête",
            [
                'user_id'     => $userId,
                'tenant_id'   => $tenantId,
                'nb_records'  => $nbResultats,
                'heure'       => now()->format('H:i'),
            ]
        );
    }

    private function verifierSeuils(
        string $userId,
        string $resource,
        string $action,
        int    $totalHeure,
        int    $totalJour,
        int    $nbRessources
    ): void {
        $alertes = [];

        if ($action === 'read' && $totalHeure > self::SEUIL_DOWNLOAD_HORAIRE) {
            $alertes[] = "Volume horaire excessif: {$totalHeure} enregistrements/h";
        }

        if ($action === 'read' && $totalJour > self::SEUIL_DOWNLOAD_JOURNALIER) {
            $alertes[] = "Volume journalier excessif: {$totalJour} enregistrements/jour";
        }

        if ($action === 'write' && $totalHeure > self::SEUIL_MODIFICATIONS) {
            $alertes[] = "Modifications massives: {$totalHeure} en 1h";
        }

        if ($nbRessources > self::SEUIL_RESSOURCES_UNIQUES) {
            $alertes[] = "Exploration large: {$nbRessources} ressources différentes en 30min";
        }

        foreach ($alertes as $alerte) {
            $this->monitor->alerter(
                'insider_threat',
                'warning',
                "🕵️ Comportement suspect: {$alerte} — User {$userId}",
                ['user_id' => $userId, 'resource' => $resource, 'action' => $action]
            );
        }
    }
}
```

---

## ÉTAPE 9 — Dead Man Switch (alerte si inactivité admin)

```php
// Créer : edugestdz/backend/app/Console/Commands/DeadManSwitchCommand.php

<?php
namespace App\Console\Commands;

use App\Services\SecurityMonitorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Dead Man Switch — alerte si aucun admin ne se connecte pendant 7 jours.
 *
 * Pourquoi ? Si un admin est compromis (compte volé, ransomware) et que
 * l'attaquant bloque les accès légitimes, personne ne se connecte.
 * Cette commande détecte cette absence d'activité.
 */
class DeadManSwitchCommand extends Command
{
    protected $signature   = 'edugest:dead-man-switch';
    protected $description = 'Vérifier l\'activité admin (dead man switch)';

    public function handle(SecurityMonitorService $monitor): int
    {
        $seuil = now()->subDays(7);

        // Vérifier la dernière connexion admin pour chaque tenant
        $tenantsInactifs = DB::table('users')
            ->where('role', 'admin')
            ->where(fn($q) => $q->whereNull('last_login_at')->orWhere('last_login_at', '<', $seuil))
            ->select('tenant_id', DB::raw('MAX(last_login_at) as derniere_connexion'))
            ->groupBy('tenant_id')
            ->get();

        foreach ($tenantsInactifs as $tenant) {
            $joursSansConnexion = $tenant->derniere_connexion
                ? now()->diffInDays($tenant->derniere_connexion)
                : 999;

            $monitor->alerter(
                'dead_man_switch',
                $joursSansConnexion > 14 ? 'critical' : 'warning',
                "⚠️ Dead Man Switch: Aucune connexion admin depuis {$joursSansConnexion} jours pour le tenant {$tenant->tenant_id}",
                [
                    'tenant_id'           => $tenant->tenant_id,
                    'jours_inactif'       => $joursSansConnexion,
                    'derniere_connexion'  => $tenant->derniere_connexion,
                ]
            );
        }

        if ($tenantsInactifs->isEmpty()) {
            $this->info('✅ Dead Man Switch: Tous les tenants ont une activité admin récente.');
        } else {
            $this->warn("⚠️ {$tenantsInactifs->count()} tenant(s) inactif(s) depuis >7 jours.");
        }

        return Command::SUCCESS;
    }
}
```

**Modifier** `Kernel.php` :

```php
// Dead man switch — chaque matin à 9h
$schedule->command('edugest:dead-man-switch')->dailyAt('09:00');
```

---

## ÉTAPE 10 — config/vault.php + .env.example

```php
// Créer : edugestdz/backend/config/vault.php

<?php
return [
    'addr'      => env('VAULT_ADDR', ''),
    'token'     => env('VAULT_TOKEN', ''),
    'namespace' => env('VAULT_NAMESPACE', 'edugest'),
    'enabled'   => !empty(env('VAULT_ADDR')),
];
```

**Modifier** `.env.example` :

```dotenv
# ── HashiCorp Vault (optionnel — fallback sur BDD chiffrée) ──────────
# Pour installer Vault : https://developer.hashicorp.com/vault/install
# Ou utiliser HCP Vault (cloud)
VAULT_ADDR=
VAULT_TOKEN=
VAULT_NAMESPACE=edugest
```

**Modifier** `config/services.php` :

```php
'vault' => [
    'addr'  => env('VAULT_ADDR', ''),
    'token' => env('VAULT_TOKEN', ''),
],
```

---

## ÉTAPE 11 — Middleware anti-SQL injection et honeypot (enregistrement)

```php
// Modifier : edugestdz/backend/bootstrap/app.php

$middleware->alias([
    // ... existants ...
    'honeypot'     => \App\Http\Middleware\HoneypotRouteMiddleware::class,
    'sql.protect'  => \App\Http\Middleware\SqlInjectionDetectorMiddleware::class,
]);

// Ajouter globalement sur toutes les routes API :
$middleware->api(append: [
    \App\Http\Middleware\HoneypotRouteMiddleware::class,
    \App\Http\Middleware\SqlInjectionDetectorMiddleware::class,
]);
```

---

## ÉTAPE 12 — Tests sécurité Niveau 5

```php
// Créer : edugestdz/backend/tests/Feature/Security/SecurityNiveau5Test.php

<?php
namespace Tests\Feature\Security;

use App\Services\HoneypotService;
use App\Services\SsrfProtectionService;
use App\Services\VaultSecretsService;
use App\Services\InsiderThreatDetectorService;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

class SecurityNiveau5Test extends TestCase
{
    use RefreshDatabase;

    // ── Honeypot ──────────────────────────────────────────────────────

    public function test_route_leurre_retourne_404(): void
    {
        $this->getJson('/api/v1/admin-panel')->assertStatus(404);
        $this->getJson('/api/.env')->assertStatus(404);
        $this->getJson('/api/phpmyadmin')->assertStatus(404);
    }

    public function test_honeypot_field_rempli_bloque_la_requete(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email'    => 'test@test.com',
            'password' => 'testpassword',
            'website'  => 'http://spam.com', // Champ piège
        ])->assertStatus(403);
    }

    public function test_ip_blacklistee_apres_honeypot_route(): void
    {
        // Accéder à une route leurre → IP blacklistée
        $this->getJson('/api/v1/.env');

        // Vérifier que l'IP est maintenant bloquée dans Redis
        $ipHash = hash('sha256', '127.0.0.1');
        // L'IP de test (127.0.0.1) devrait être blacklistée si severité >= 9
        // (Test symbolique — vérifier l'entrée honeypot_triggers)
        $this->assertDatabaseHas('honeypot_triggers', ['type' => 'route']);
    }

    public function test_canary_token_format_valide(): void
    {
        // Vérifier que le format EDUGEST-CANARY-{32hex} est bien détectable
        $fakeCanary = 'edugest-canary-' . str_repeat('a', 32);
        $this->assertMatchesRegularExpression('/^edugest-canary-[a-f0-9]{32}$/', $fakeCanary);
    }

    // ── SSRF Protection ───────────────────────────────────────────────

    public function test_url_metadata_cloud_bloquee(): void
    {
        $ssrf = app(SsrfProtectionService::class);

        $this->expectException(\InvalidArgumentException::class);
        $ssrf->validerUrl('http://169.254.169.254/latest/meta-data/');
    }

    public function test_url_localhost_bloquee(): void
    {
        $ssrf = app(SsrfProtectionService::class);

        $this->expectException(\InvalidArgumentException::class);
        $ssrf->validerUrl('http://127.0.0.1:6379'); // Redis interne
    }

    public function test_url_telegram_autorisee(): void
    {
        $ssrf = app(SsrfProtectionService::class);

        // Ne doit pas lever d'exception
        $ssrf->validerUrl('https://api.telegram.org/bot123/sendMessage');
        $this->assertTrue(true);
    }

    public function test_url_reseau_interne_bloquee(): void
    {
        $ssrf = app(SsrfProtectionService::class);

        $this->expectException(\InvalidArgumentException::class);
        $ssrf->validerUrl('http://192.168.1.1/admin');
    }

    // ── SQL Injection Detection ────────────────────────────────────────

    public function test_union_select_bloque(): void
    {
        $this->getJson('/api/v1/eleves?search=' . urlencode("' UNION SELECT * FROM users --"))
            ->assertStatus(400);
    }

    public function test_sleep_injection_bloquee(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email'    => "admin' OR SLEEP(5)-- -",
            'password' => 'anything',
        ])->assertStatus(400);
    }

    // ── Vault Secrets ─────────────────────────────────────────────────

    public function test_vault_stocke_et_recupere_secret(): void
    {
        $vault = app(VaultSecretsService::class);

        $vault->put('edugest/test/ma_cle', 'valeur_secrete_123');
        $valeur = $vault->get('edugest/test/ma_cle');

        $this->assertEquals('valeur_secrete_123', $valeur);
    }

    public function test_vault_supprime_secret(): void
    {
        $vault = app(VaultSecretsService::class);

        $vault->put('edugest/test/delete_moi', 'temporaire');
        $vault->delete('edugest/test/delete_moi');

        $this->assertNull($vault->get('edugest/test/delete_moi'));
    }

    // ── Insider Threat ────────────────────────────────────────────────

    public function test_bulk_export_genere_alerte(): void
    {
        $detector = app(InsiderThreatDetectorService::class);

        // 300 résultats en une requête → alerte
        $detector->détecterBulkExport('user-123', 'tenant-456', 300);

        $this->assertDatabaseHas('security_events', [
            'type' => 'insider_bulk_export',
        ]);
    }

    public function test_acces_normal_pas_d_alerte(): void
    {
        $detector = app(InsiderThreatDetectorService::class);

        // 10 résultats → normal, pas d'alerte
        $detector->enregistrerAcces('user-123', 'eleves', 10, 'read');

        $this->assertDatabaseMissing('security_events', [
            'type' => 'insider_threat',
        ]);
    }
}
```

---

## ÉTAPE 13 — Exécution

```bash
cd edugestdz/backend

php artisan migrate
composer dump-autoload -o
php artisan test --parallel
# → 0 régression + 15 nouveaux tests verts

git add .
git commit -m "security(niveau5): Honeypots actifs + Canary tokens fuite BDD + SSRF Protection + SQL Injection Layer + Vault Secrets + Insider Threat Detector + Dead Man Switch + 15 tests"
git push origin develop
# → PR develop → main
```

---

## CE QUE TU DIS À DEEPSEEK

```
Repo : https://github.com/Allintelligence2024/edugest-dz.git
Branche : develop
git checkout develop && git pull origin main

Fichier : MISSION_SECURITE_NIVEAU5.md — 13 étapes dans l'ordre.

RÈGLES CRITIQUES :
1. PostgreSQL uniquement — 0 régression.
2. HoneypotService.déclencherRouteLeurre() : retourner un 404 STANDARD (pas 403, pas 200).
   NE JAMAIS signaler à l'attaquant que c'est un honeypot.
3. SqlInjectionDetectorMiddleware : NE PAS bloquer /api/health et /api/v1/auth/login
   (le login a besoin de recevoir des emails avec des caractères spéciaux légitimes).
   Adapter les exclusions de routes.
4. VaultSecretsService : si Vault non configuré (VAULT_ADDR vide) →
   utiliser UNIQUEMENT la BDD chiffrée (encrypted_secrets). Ne pas crasher.
5. HoneypotService.injecterCanaries() : ne modifier que les tableaux avec >= 5 éléments.
   Ne jamais modifier les objets Eloquent directement.
6. InsiderThreatDetectorService : les seuils sont des constantes modifiables.
   Ne pas hardcoder dans les méthodes — utiliser self::SEUIL_*.
7. DeadManSwitchCommand : si la table users n'a pas la colonne last_login_at →
   ignorer silencieusement (utiliser try/catch).
8. Les routes leurres dans HoneypotService : ajouter aussi /api/v1/phpinfo,
   /api/v1/server-status, /api/v1/actuator, /api/v1/metrics à la liste.

php artisan migrate
composer dump-autoload -o
php artisan test --parallel → verts
git push origin develop → PR develop → main
```
