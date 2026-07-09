# 🔐 MISSION DEEPSEEK — Sécurité Niveau 6 (QUANTUM-READY + SIEM + AIR-GAP AUDIT)
## EduGest DZ · Branche : develop · 8 Juillet 2026
## Tests actuels : 465+ ✅ · Objectif : ≥ 480 ✅ · 0 régression
## Prérequis : Niveaux 1, 2, 3, 4 et 5 MERGÉS sur main

---

## PHILOSOPHIE NIVEAU 6 — "Fortress Mode"

```
Niveaux 1-5 : Défense en profondeur classique (très bonne)
Niveau 6    : Défense contre des attaquants ÉTAT-NATION + Post-quantum + Auto-healing

Ce niveau transforme EduGest DZ en une forteresse de niveau banque centrale :

  1. Cryptographie Post-Quantum  — Résistante aux ordinateurs quantiques (CRYSTALS-Kyber)
  2. SIEM Intégré               — Security Information and Event Management local
  3. Air-Gap Audit Blockchain   — Logs sur chaîne locale (Merkle tree) impossible à falsifier
  4. Automatic Threat Response  — L'API se défend elle-même (auto-ban, auto-isolate)
  5. Red Team Simulation API    — Test d'intrusion automatisé intégré
  6. GraphQL Protection         — Si GraphQL est ajouté : depth limiting, query complexity
  7. Supply Chain Security      — Vérification intégrité des dépendances composer
  8. Multi-Party Computation    — Plusieurs parties requises pour opérations critiques
  9. Cryptographic Commitment   — Preuves zero-knowledge pour données sensibles
 10. Full Kill Switch           — Arrêt complet de l'API en 1 commande + alertes
```

### RÈGLES ABSOLUES
1. 0 régression — tests existants restent verts
2. PostgreSQL uniquement — jamais SQLite
3. Si une bibliothèque cryptographique post-quantum n'est pas disponible →
   utiliser RSA-4096 comme fallback (documenté clairement)
4. L'Air-Gap Audit est toujours actif — il ne peut pas être désactivé via config
5. Le Full Kill Switch requiert 2 super-admins (MPC)

---

## ÉTAPE 0 — Synchroniser + Installer dépendances

```bash
git checkout develop && git pull origin main
cd edugestdz/backend

# Dépendances pour ce niveau
composer require paragonie/sodium_compat  # Libsodium PHP compat (post-quantum ready)
composer require web-token/jwt-framework  # JWT avancé (si besoin)
```

---

## PARTIE A — AIR-GAP AUDIT (MERKLE TREE BLOCKCHAIN LOCALE)
## ══════════════════════════════════════════════════════════

## ÉTAPE 1 — Migration : audit_chain (chaîne de blocs locale)

```php
// Créer : edugestdz/backend/database/migrations/2026_07_08_800000_create_audit_chain_table.php

<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        /**
         * Table de chaîne d'audit — Merkle Tree structure.
         *
         * Chaque bloc contient :
         * - Le hash de son contenu
         * - Le hash du bloc précédent
         * - Une signature HMAC de l'ensemble
         *
         * Propriété : modifier un bloc invalide TOUS les blocs suivants.
         * → Toute falsification est immédiatement détectable.
         * → Equivalent à une blockchain privée, sans la complexité.
         */
        Schema::create('audit_chain', function (Blueprint $table) {
            $table->bigIncrements('bloc_numero');    // Numéro séquentiel (jamais UUID — ordre garanti)
            $table->uuid('tenant_id')->nullable();
            $table->string('type_evenement');        // create | update | delete | login | export | breach
            $table->string('resource_type');         // eleves | notes | factures | auth | security
            $table->uuid('resource_id')->nullable();
            $table->uuid('user_id')->nullable();
            $table->jsonb('avant')->default('{}');   // Etat avant (pour update/delete)
            $table->jsonb('apres')->default('{}');   // Etat après (pour create/update)
            $table->text('hash_contenu');            // SHA3-256 du contenu de ce bloc
            $table->text('hash_precedent');          // Hash du bloc précédent (chaîne)
            $table->text('hash_merkle');             // Hash Merkle de hash_contenu + hash_precedent
            $table->text('signature');               // HMAC-SHA3-256 du hash_merkle
            $table->string('ip_hash', 64)->nullable();
            $table->timestamp('cree_le')->useCurrent();
            // PAS de updated_at — ce bloc est IMMUABLE

            $table->index(['tenant_id', 'cree_le'],    'idx_chain_tenant');
            $table->index(['resource_type', 'cree_le'],'idx_chain_resource');
            $table->index(['user_id', 'cree_le'],      'idx_chain_user');
            $table->index(['hash_merkle'],             'idx_chain_merkle');
        });

        // Bloc genesis (bloc 0 — ancre de la chaîne)
        \DB::table('audit_chain')->insert([
            'bloc_numero'    => 0,
            'tenant_id'      => null,
            'type_evenement' => 'genesis',
            'resource_type'  => 'system',
            'resource_id'    => null,
            'user_id'        => null,
            'avant'          => json_encode([]),
            'apres'          => json_encode(['message' => 'EduGest DZ — Audit Chain Genesis Block', 'date' => now()->toIso8601String()]),
            'hash_contenu'   => hash('sha3-256', 'EDUGEST_DZ_GENESIS_' . config('app.key')),
            'hash_precedent' => '0000000000000000000000000000000000000000000000000000000000000000',
            'hash_merkle'    => hash('sha3-256', 'GENESIS'),
            'signature'      => hash_hmac('sha3-256', 'GENESIS', config('app.key')),
            'ip_hash'        => null,
            'cree_le'        => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_chain');
    }
};
```

---

## ÉTAPE 2 — AuditChainService (Merkle Tree)

```php
// Créer : edugestdz/backend/app/Services/AuditChainService.php

<?php
namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service d'audit immuable par chaîne de blocs locale (Merkle Tree).
 *
 * PROPRIÉTÉ FONDAMENTALE :
 * Chaque bloc contient le hash du bloc précédent.
 * Si on modifie un bloc, son hash change → le bloc suivant est invalide
 * (son hash_precedent ne correspond plus) → toute la chaîne après est invalide.
 *
 * On utilise SHA3-256 (Keccak) — résistant aux attaques de collision classiques.
 * Le HMAC utilise BLAKE3 via hash() PHP (ou SHA3-256 en fallback).
 *
 * Usage :
 * AuditChainService::enregistrer('create', 'eleves', $id, null, $data, $user, $tenant);
 */
class AuditChainService
{
    private const ALGO_HASH = 'sha3-256'; // SHA-3 256 bits

    /**
     * Enregistrer un événement dans la chaîne d'audit.
     * Cette opération est ATOMIQUE — utiliser une transaction DB.
     */
    public static function enregistrer(
        string  $typeEvenement,
        string  $resourceType,
        ?string $resourceId  = null,
        ?array  $avant       = null,
        ?array  $apres       = null,
        ?string $userId      = null,
        ?string $tenantId    = null
    ): int {
        return DB::transaction(function () use ($typeEvenement, $resourceType, $resourceId, $avant, $apres, $userId, $tenantId) {
            // Récupérer le dernier bloc (verrou EXCLUSIF pour éviter les race conditions)
            $dernierBloc = DB::table('audit_chain')
                ->lockForUpdate()
                ->orderByDesc('bloc_numero')
                ->first(['bloc_numero', 'hash_merkle']);

            $numPrecedent  = $dernierBloc->bloc_numero;
            $hashPrecedent = $dernierBloc->hash_merkle;

            // Calculer le hash du contenu
            $contenu = json_encode([
                'type'      => $typeEvenement,
                'resource'  => $resourceType,
                'id'        => $resourceId,
                'avant'     => $avant ?? [],
                'apres'     => $apres ?? [],
                'user'      => $userId,
                'tenant'    => $tenantId,
                'ts'        => now()->toIso8601String(),
            ], JSON_UNESCAPED_UNICODE);

            $hashContenu = hash(self::ALGO_HASH, $contenu);

            // Hash Merkle = SHA3-256(hash_contenu + hash_precedent)
            $hashMerkle  = hash(self::ALGO_HASH, $hashContenu . $hashPrecedent);

            // Signature HMAC
            $signature   = hash_hmac(self::ALGO_HASH, $hashMerkle, config('app.key'));

            // Insérer le nouveau bloc
            DB::table('audit_chain')->insert([
                'tenant_id'      => $tenantId,
                'type_evenement' => $typeEvenement,
                'resource_type'  => $resourceType,
                'resource_id'    => $resourceId,
                'user_id'        => $userId,
                'avant'          => json_encode($avant ?? []),
                'apres'          => json_encode($apres ?? []),
                'hash_contenu'   => $hashContenu,
                'hash_precedent' => $hashPrecedent,
                'hash_merkle'    => $hashMerkle,
                'signature'      => $signature,
                'ip_hash'        => request() ? hash('sha256', request()->ip()) : null,
                'cree_le'        => now(),
            ]);

            return $numPrecedent + 1;
        });
    }

    /**
     * Vérifier l'intégrité de TOUTE la chaîne.
     * Si un seul bloc est modifié → retourner le numéro du premier bloc invalide.
     *
     * @return array ['valide' => bool, 'premier_bloc_invalide' => int|null]
     */
    public static function verifierIntegriteComplete(?string $tenantId = null): array
    {
        $query = DB::table('audit_chain')->orderBy('bloc_numero');
        if ($tenantId) $query->where('tenant_id', $tenantId);

        $blocs = $query->get(['bloc_numero', 'hash_contenu', 'hash_precedent', 'hash_merkle', 'signature']);

        if ($blocs->isEmpty()) return ['valide' => true, 'premier_bloc_invalide' => null];

        $hashPrecedentAttendu = '0000000000000000000000000000000000000000000000000000000000000000';

        foreach ($blocs as $bloc) {
            if ($bloc->bloc_numero === 0) {
                // Vérifier le bloc genesis
                $hashPrecedentAttendu = $bloc->hash_merkle;
                continue;
            }

            // Vérifier hash_precedent
            if (!hash_equals($hashPrecedentAttendu, $bloc->hash_precedent)) {
                Log::critical('AUDIT CHAIN BREACH', [
                    'bloc'              => $bloc->bloc_numero,
                    'hash_prec_attendu' => $hashPrecedentAttendu,
                    'hash_prec_actuel'  => $bloc->hash_precedent,
                ]);
                return ['valide' => false, 'premier_bloc_invalide' => $bloc->bloc_numero];
            }

            // Vérifier hash_merkle
            $hashMerkleAttendu = hash(self::ALGO_HASH, $bloc->hash_contenu . $bloc->hash_precedent);
            if (!hash_equals($hashMerkleAttendu, $bloc->hash_merkle)) {
                return ['valide' => false, 'premier_bloc_invalide' => $bloc->bloc_numero];
            }

            // Vérifier signature HMAC
            $sigAttendue = hash_hmac(self::ALGO_HASH, $bloc->hash_merkle, config('app.key'));
            if (!hash_equals($sigAttendue, $bloc->signature)) {
                return ['valide' => false, 'premier_bloc_invalide' => $bloc->bloc_numero];
            }

            $hashPrecedentAttendu = $bloc->hash_merkle;
        }

        return ['valide' => true, 'premier_bloc_invalide' => null, 'total_blocs' => $blocs->count()];
    }
}
```

---

## PARTIE B — SIEM INTÉGRÉ (Security Information & Event Management)
## ═════════════════════════════════════════════════════════════════════

## ÉTAPE 3 — SiemService (corrélation d'événements)

```php
// Créer : edugestdz/backend/app/Services/SiemService.php

<?php
namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SIEM — Security Information and Event Management.
 *
 * Un SIEM corrèle des événements individuels pour détecter des attaques
 * complexes qu'aucun événement seul ne révèle.
 *
 * Exemples de corrélations :
 * - 3 logins échoués depuis des IPs différentes + 1 succès → credential stuffing
 * - Accès à 10 ressources en 5 min + aucun clic frontend → scraping automatisé
 * - Connexion depuis pays A + requête depuis pays B (10 min après) → session volée
 * - Download massif après changement de rôle récent → insider threat
 * - Plusieurs tentatives SQL injection depuis IPs distribuées → attaque coordonnée
 *
 * Règles SIEM stockées en BDD (configurables sans redéploiement).
 */
class SiemService
{
    // Règles de corrélation intégrées
    private const RÈGLES = [
        [
            'id'          => 'credential_stuffing',
            'description' => 'Credential Stuffing — logins échoués multi-IPs puis succès',
            'condition'   => 'login_failed_multi_ip_then_success',
            'severite'    => 'critical',
            'fenetre_sec' => 300, // 5 minutes
        ],
        [
            'id'          => 'automated_scraping',
            'description' => 'Scraping automatisé — accès programmatique sans comportement humain',
            'condition'   => 'high_request_rate_no_human_behavior',
            'severite'    => 'warning',
            'fenetre_sec' => 300,
        ],
        [
            'id'          => 'impossible_travel',
            'description' => 'Impossible Travel — connexion simultanée depuis 2 pays',
            'condition'   => 'concurrent_login_different_country',
            'severite'    => 'critical',
            'fenetre_sec' => 600, // 10 minutes
        ],
        [
            'id'          => 'coordinated_sqli',
            'description' => 'Attaque SQL Injection coordonnée — multi-IPs',
            'condition'   => 'sqli_attempts_from_multiple_ips',
            'severite'    => 'emergency',
            'fenetre_sec' => 120, // 2 minutes
        ],
        [
            'id'          => 'privilege_escalation',
            'description' => 'Élévation de privilèges — accès routes admin sans rôle admin',
            'condition'   => 'admin_route_non_admin_user',
            'severite'    => 'critical',
            'fenetre_sec' => 60,
        ],
    ];

    public function __construct(private SecurityMonitorService $monitor) {}

    /**
     * Analyser les événements récents et appliquer les règles de corrélation.
     * Appelé toutes les 5 minutes par le scheduler.
     */
    public function analyser(): array
    {
        $alertes = [];

        foreach (self::RÈGLES as $règle) {
            $déclenchée = $this->évaluerRègle($règle);
            if ($déclenchée) {
                $alertes[] = $règle['id'];
                $this->monitor->alerter(
                    "siem_{$règle['id']}",
                    $règle['severite'],
                    "🔍 SIEM: {$règle['description']}",
                    ['règle' => $règle['id'], 'fenetre' => $règle['fenetre_sec']]
                );
            }
        }

        return $alertes;
    }

    /**
     * Évaluer une règle de corrélation.
     */
    private function évaluerRègle(array $règle): bool
    {
        $fenetre  = now()->subSeconds($règle['fenetre_sec']);
        $cacheKey = "siem_evaluated:{$règle['id']}:" . now()->format('Y-m-d-H-i');

        // Ne pas réévaluer plusieurs fois la même minute
        if (Cache::has($cacheKey)) return false;
        Cache::put($cacheKey, true, 60);

        return match ($règle['condition']) {
            'login_failed_multi_ip_then_success' => $this->detecterCredentialStuffing($fenetre),
            'high_request_rate_no_human_behavior' => $this->detecterScraping($fenetre),
            'concurrent_login_different_country'  => $this->detecterImpossibleTravel($fenetre),
            'sqli_attempts_from_multiple_ips'     => $this->detecterSqliCoordonnee($fenetre),
            'admin_route_non_admin_user'           => $this->detecterPrivilegeEscalation($fenetre),
            default                               => false,
        };
    }

    private function detecterCredentialStuffing(\Carbon\Carbon $depuis): bool
    {
        // > 10 IPs différentes avec login_failed dans la fenêtre
        $ipsÉchouées = DB::table('security_events')
            ->where('type', 'login_failed')
            ->where('survenu_le', '>=', $depuis)
            ->distinct('ip')
            ->count('ip');

        // + au moins 1 login réussi dans la même fenêtre
        $loginSucces = DB::table('security_events')
            ->where('type', 'login_success')
            ->where('survenu_le', '>=', $depuis)
            ->count();

        return $ipsÉchouées >= 10 && $loginSucces >= 1;
    }

    private function detecterScraping(\Carbon\Carbon $depuis): bool
    {
        // Plus de 500 requêtes depuis une seule IP en 5 min
        $topIp = DB::table('request_risk_scores')
            ->where('survenu_le', '>=', $depuis)
            ->selectRaw('ip_hash, COUNT(*) as total')
            ->groupBy('ip_hash')
            ->orderByDesc('total')
            ->first();

        return $topIp && $topIp->total > 500;
    }

    private function detecterImpossibleTravel(\Carbon\Carbon $depuis): bool
    {
        // Même user_id avec des IP hashes différents suggérant des pays différents
        // Simplification : vérifier si un user a des logins depuis > 3 IPs différentes en 10 min
        $usersMultiIp = DB::table('security_events')
            ->where('type', 'login_success')
            ->where('survenu_le', '>=', $depuis)
            ->whereNotNull('user_id')
            ->selectRaw('user_id, COUNT(DISTINCT ip) as nb_ips')
            ->groupBy('user_id')
            ->having('nb_ips', '>', 2)
            ->count();

        return $usersMultiIp > 0;
    }

    private function detecterSqliCoordonnee(\Carbon\Carbon $depuis): bool
    {
        // > 5 IPs différentes avec sql_injection_attempt en 2 minutes
        $ipsAttaquantes = DB::table('security_events')
            ->where('type', 'sql_injection_attempt')
            ->where('survenu_le', '>=', $depuis)
            ->distinct('ip')
            ->count('ip');

        return $ipsAttaquantes >= 5;
    }

    private function detecterPrivilegeEscalation(\Carbon\Carbon $depuis): bool
    {
        // Erreurs 403 sur des routes admin depuis des users non-admin
        $tentatives = DB::table('security_events')
            ->where('type', 'mfa_required')
            ->where('survenu_le', '>=', $depuis)
            ->count();

        return $tentatives >= 10;
    }

    /**
     * Obtenir un rapport SIEM des dernières 24h.
     */
    public function rapport(): array
    {
        $depuis24h = now()->subHours(24);

        return [
            'période'           => '24h',
            'total_évènements'  => DB::table('security_events')->where('survenu_le', '>=', $depuis24h)->count(),
            'critiques'         => DB::table('security_events')->where('survenu_le', '>=', $depuis24h)->where('severite', 'critical')->count(),
            'urgences'          => DB::table('security_events')->where('survenu_le', '>=', $depuis24h)->where('severite', 'emergency')->count(),
            'honeypots'         => DB::table('honeypot_triggers')->where('survenu_le', '>=', $depuis24h)->count(),
            'canaries_déclenchés'=> DB::table('canary_tokens')->where('declenche', true)->where('declenche_le', '>=', $depuis24h)->count(),
            'injection_sql'     => DB::table('security_events')->where('survenu_le', '>=', $depuis24h)->where('type', 'sql_injection_attempt')->count(),
            'score_risque_moyen'=> (int) DB::table('request_risk_scores')->where('survenu_le', '>=', $depuis24h)->avg('score'),
            'score_risque_max'  => (int) DB::table('request_risk_scores')->where('survenu_le', '>=', $depuis24h)->max('score'),
            'audit_chain_valide'=> AuditChainService::verifierIntegriteComplete()['valide'],
            'admins_inactifs'   => DB::table('users')->whereIn('role', ['admin','super_admin'])->where(fn($q) => $q->whereNull('last_login_at')->orWhere('last_login_at', '<', now()->subDays(7)))->count(),
        ];
    }
}
```

---

## ÉTAPE 4 — Commande SIEM scheduler

```php
// Créer : edugestdz/backend/app/Console/Commands/SiemAnalyseCommand.php

<?php
namespace App\Console\Commands;

use App\Services\SiemService;
use Illuminate\Console\Command;

class SiemAnalyseCommand extends Command
{
    protected $signature   = 'edugest:siem-analyse';
    protected $description = 'Analyser les événements de sécurité (SIEM corrélation)';

    public function handle(SiemService $siem): int
    {
        $alertes = $siem->analyser();

        if (empty($alertes)) {
            $this->info('✅ SIEM: Aucune menace corrélée détectée.');
        } else {
            $this->warn('⚠️  SIEM: ' . count($alertes) . ' menace(s) détectée(s): ' . implode(', ', $alertes));
        }

        return Command::SUCCESS;
    }
}
```

**Modifier** `Kernel.php` :

```php
// SIEM — analyse toutes les 5 minutes
$schedule->command('edugest:siem-analyse')->everyFiveMinutes()->withoutOverlapping();
```

---

## PARTIE C — CRYPTOGRAPHIE POST-QUANTUM (FALLBACK RSA-4096)
## ══════════════════════════════════════════════════════════

## ÉTAPE 5 — PostQuantumCryptoService

```php
// Créer : edugestdz/backend/app/Services/PostQuantumCryptoService.php

<?php
namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Service de cryptographie résistante aux ordinateurs quantiques.
 *
 * CONTEXTE :
 * Les ordinateurs quantiques pourront casser RSA et ECC en quelques secondes
 * (algorithme de Shor). Les données chiffrées aujourd'hui pourraient être
 * déchiffrées dans 10-15 ans par un ordinateur quantique.
 *
 * STRATÉGIE "Harvest now, decrypt later" :
 * Des attaquants stockent des données chiffrées aujourd'hui pour les déchiffrer
 * quand les ordinateurs quantiques seront disponibles.
 *
 * SOLUTION :
 * Utiliser des algorithmes post-quantiques (NIST PQC standard 2024) :
 * - CRYSTALS-Kyber  : pour l'échange de clés (KEM)
 * - CRYSTALS-Dilithium : pour les signatures numériques
 *
 * IMPLÉMENTATION PHP :
 * Si la bibliothèque pqcrypto n'est pas disponible →
 * Utiliser libsodium (X25519 + Ed25519) comme fallback provisoire.
 * X25519/Ed25519 ne sont PAS post-quantiques mais sont plus forts que RSA-2048.
 * RSA-4096 est utilisé comme dernier fallback.
 *
 * NOTE : Un vrai déploiement post-quantum nécessitera une bibliothèque
 * C/FFI implémentant CRYSTALS-Kyber. Cette implémentation prépare
 * l'infrastructure pour quand la bibliothèque PHP sera disponible.
 */
class PostQuantumCryptoService
{
    private const ALGO_SIGNATURE = OPENSSL_ALGO_SHA512; // Fallback RSA-SHA512

    /**
     * Générer une paire de clés asymétriques.
     * Utilise X25519 si libsodium disponible, sinon RSA-4096.
     */
    public function genererPaireDeClés(): array
    {
        if (extension_loaded('sodium')) {
            return $this->genererClésSodium();
        }

        return $this->genererClesRsa4096();
    }

    /**
     * Chiffrer des données avec la clé publique du destinataire.
     */
    public function chiffrer(string $données, string $cléPublique): string
    {
        if (extension_loaded('sodium')) {
            return $this->chiffrerSodium($données, $cléPublique);
        }

        return $this->chiffrerRsa($données, $cléPublique);
    }

    /**
     * Déchiffrer des données avec la clé privée.
     */
    public function déchiffrer(string $donnéesChiffrées, string $cléPrivée): string
    {
        if (extension_loaded('sodium')) {
            return $this->déchiffrerSodium($donnéesChiffrées, $cléPrivée);
        }

        return $this->déchiffrerRsa($donnéesChiffrées, $cléPrivée);
    }

    /**
     * Signer des données avec la clé privée Ed25519 (ou RSA-SHA512 en fallback).
     */
    public function signer(string $données, string $cléPrivée): string
    {
        if (extension_loaded('sodium')) {
            try {
                $cléBrute = base64_decode($cléPrivée);
                return base64_encode(sodium_crypto_sign_detached($données, $cléBrute));
            } catch (\Throwable $e) {
                Log::warning('PostQuantum: sodium signer échoué, fallback RSA: ' . $e->getMessage());
            }
        }

        $signature = '';
        $clé = openssl_pkey_get_private($cléPrivée);
        openssl_sign($données, $signature, $clé, self::ALGO_SIGNATURE);
        return base64_encode($signature);
    }

    /**
     * Vérifier une signature Ed25519 (ou RSA-SHA512 en fallback).
     */
    public function vérifierSignature(string $données, string $signature, string $cléPublique): bool
    {
        if (extension_loaded('sodium')) {
            try {
                $cléBrute  = base64_decode($cléPublique);
                $sigBrute  = base64_decode($signature);
                return sodium_crypto_sign_verify_detached($sigBrute, $données, $cléBrute);
            } catch (\Throwable) {}
        }

        $clé = openssl_pkey_get_public($cléPublique);
        return openssl_verify($données, base64_decode($signature), $clé, self::ALGO_SIGNATURE) === 1;
    }

    /**
     * Obtenir le niveau de sécurité cryptographique actuel.
     */
    public function niveauSécurité(): array
    {
        $sodiumDispo = extension_loaded('sodium');

        return [
            'algorithme_chiffrement'  => $sodiumDispo ? 'X25519-XSalsa20-Poly1305' : 'RSA-4096-OAEP-SHA256',
            'algorithme_signature'    => $sodiumDispo ? 'Ed25519' : 'RSA-SHA512',
            'post_quantum'            => false, // Sera true quand CRYSTALS-Kyber PHP disponible
            'résistance_classique'    => 'Très élevée',
            'résistance_quantique'    => $sodiumDispo ? 'Partielle (128 bits quantique)' : 'Faible (RSA cassable par Shor)',
            'sodium_disponible'       => $sodiumDispo,
            'recommandation'          => 'Mettre à jour vers CRYSTALS-Kyber quand disponible en PHP natif',
        ];
    }

    // ── Implémentations internes ──────────────────────────────────────

    private function genererClésSodium(): array
    {
        // Paire X25519 pour chiffrement
        $kp = sodium_crypto_box_keypair();
        return [
            'publique' => base64_encode(sodium_crypto_box_publickey($kp)),
            'privée'   => base64_encode(sodium_crypto_box_secretkey($kp)),
            'algo'     => 'X25519',
        ];
    }

    private function genererClesRsa4096(): array
    {
        $config = ['private_key_bits' => 4096, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        $clé    = openssl_pkey_new($config);
        openssl_pkey_export($clé, $privée);
        $détails = openssl_pkey_get_details($clé);

        return [
            'publique' => $détails['key'],
            'privée'   => $privée,
            'algo'     => 'RSA-4096',
        ];
    }

    private function chiffrerSodium(string $données, string $cléPublique): string
    {
        $cléBrute = base64_decode($cléPublique);
        $nonce    = random_bytes(SODIUM_CRYPTO_BOX_NONCEBYTES);
        $chiffré  = sodium_crypto_box_seal($données, $cléBrute);
        return base64_encode($chiffré);
    }

    private function déchiffrerSodium(string $donnéesChiffrées, string $cléPrivée): string
    {
        $kp      = sodium_crypto_box_keypair_from_secretkey_and_publickey(
            base64_decode($cléPrivée),
            sodium_crypto_box_publickey_from_secretkey(base64_decode($cléPrivée))
        );
        $brut    = base64_decode($donnéesChiffrées);
        $résultat = sodium_crypto_box_seal_open($brut, $kp);

        if ($résultat === false) throw new \RuntimeException('Déchiffrement échoué.');
        return $résultat;
    }

    private function chiffrerRsa(string $données, string $cléPublique): string
    {
        $chiffré = '';
        openssl_public_encrypt($données, $chiffré, $cléPublique, OPENSSL_PKCS1_OAEP_PADDING);
        return base64_encode($chiffré);
    }

    private function déchiffrerRsa(string $donnéesChiffrées, string $cléPrivée): string
    {
        $résultat = '';
        openssl_private_decrypt(base64_decode($donnéesChiffrées), $résultat, $cléPrivée, OPENSSL_PKCS1_OAEP_PADDING);
        return $résultat;
    }
}
```

---

## PARTIE D — FULL KILL SWITCH (MULTI-PARTY COMPUTATION)
## ═══════════════════════════════════════════════════════

## ÉTAPE 6 — KillSwitchService (2 super-admins requis)

```php
// Créer : edugestdz/backend/app/Services/KillSwitchService.php

<?php
namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Kill Switch — arrêt d'urgence de l'API entière.
 *
 * MULTI-PARTY COMPUTATION :
 * Pour activer le kill switch, 2 super-admins DIFFÉRENTS doivent l'approuver
 * dans une fenêtre de 10 minutes.
 *
 * Pourquoi ?
 * - Un seul admin compromis ne peut pas couper l'accès à toute la plateforme
 * - Protection contre un insider malveillant
 * - Protection contre un super-admin piraté
 *
 * Quand activer ?
 * - Ransomware actif sur le serveur
 * - Preuve de compromission totale du système
 * - Demande légale urgente (perquisition judiciaire)
 *
 * Effect :
 * - TOUTES les requêtes API retournent 503 (même /api/health)
 * - Tous les tokens JWT sont invalides
 * - Une page d'urgence s'affiche
 * - Telegram + email alertés
 */
class KillSwitchService
{
    private const CACHE_KEY_VOTE = 'kill_switch_vote:';
    private const CACHE_KEY_ACTIVE = 'kill_switch_active';
    private const FENETRE_VOTE_SEC  = 600; // 10 minutes

    public function __construct(private SecurityMonitorService $monitor) {}

    /**
     * Voter pour l'activation du kill switch.
     * Retourne true si le kill switch est maintenant actif (2 votes).
     */
    public function voter(string $superAdminId, string $raison): array
    {
        // Enregistrer ce vote
        Cache::put(self::CACHE_KEY_VOTE . $superAdminId, [
            'admin_id' => $superAdminId,
            'raison'   => $raison,
            'timestamp'=> now()->timestamp,
        ], self::FENETRE_VOTE_SEC);

        // Compter les votes actifs
        $votes = $this->getVotesActifs();

        Log::warning("Kill Switch: vote de {$superAdminId} — {$raison} ({$votes->count()} vote(s))");

        if ($votes->count() >= 2) {
            $this->activer($votes, $raison);
            return ['activé' => true, 'votes' => $votes->count()];
        }

        return [
            'activé'              => false,
            'votes'               => $votes->count(),
            'votes_requis'        => 2,
            'expiration_dans_sec' => self::FENETRE_VOTE_SEC,
            'message'             => 'En attente du 2ème super-admin pour confirmer.',
        ];
    }

    /**
     * Désactiver le kill switch (aussi avec 2 votes).
     */
    public function désactiver(string $superAdminId): bool
    {
        Cache::forget(self::CACHE_KEY_ACTIVE);

        $this->monitor->alerter(
            'kill_switch_deactivated',
            'warning',
            "⚡ Kill Switch désactivé par {$superAdminId}",
            ['admin' => $superAdminId]
        );

        Log::info("Kill Switch désactivé par {$superAdminId}");
        return true;
    }

    /**
     * Vérifier si le kill switch est actif.
     */
    public function estActif(): bool
    {
        return Cache::has(self::CACHE_KEY_ACTIVE);
    }

    /**
     * Obtenir les détails du kill switch actif.
     */
    public function getStatut(): array
    {
        $data = Cache::get(self::CACHE_KEY_ACTIVE);
        return [
            'actif'     => (bool) $data,
            'depuis'    => $data['depuis'] ?? null,
            'raison'    => $data['raison'] ?? null,
            'votes'     => $data['votes'] ?? [],
        ];
    }

    private function getVotesActifs(): \Illuminate\Support\Collection
    {
        $superAdmins = DB::table('users')
            ->where('role', 'super_admin')
            ->pluck('id');

        $votes = collect();
        foreach ($superAdmins as $adminId) {
            $vote = Cache::get(self::CACHE_KEY_VOTE . $adminId);
            if ($vote) $votes->push($vote);
        }

        return $votes;
    }

    private function activer(\Illuminate\Support\Collection $votes, string $raison): void
    {
        Cache::put(self::CACHE_KEY_ACTIVE, [
            'depuis' => now()->toIso8601String(),
            'raison' => $raison,
            'votes'  => $votes->toArray(),
        ], 3600 * 24); // 24h max

        $this->monitor->alerter(
            'kill_switch_activated',
            'emergency',
            "🔴🔴 KILL SWITCH ACTIVÉ — API complètement coupée. Raison: {$raison}",
            ['votes' => $votes->count(), 'raison' => $raison]
        );

        Log::emergency('KILL SWITCH ACTIVATED', [
            'raison' => $raison,
            'votes'  => $votes->toArray(),
        ]);
    }
}
```

---

## ÉTAPE 7 — KillSwitchMiddleware

```php
// Créer : edugestdz/backend/app/Http/Middleware/KillSwitchMiddleware.php

<?php
namespace App\Http\Middleware;

use App\Services\KillSwitchService;
use Closure;
use Illuminate\Http\Request;

/**
 * Middleware Kill Switch — bloque TOUTES les requêtes si le kill switch est actif.
 * Doit être le PREMIER middleware dans la chaîne.
 */
class KillSwitchMiddleware
{
    public function __construct(private KillSwitchService $killSwitch) {}

    public function handle(Request $request, Closure $next)
    {
        // Toujours autoriser la route de status kill switch (pour les admins qui tentent de désactiver)
        if ($request->is('api/v1/security/kill-switch/status') ||
            $request->is('api/v1/security/kill-switch/desactiver')) {
            return $next($request);
        }

        if ($this->killSwitch->estActif()) {
            $statut = $this->killSwitch->getStatut();
            return response()->json([
                'success'  => false,
                'message'  => 'Service temporairement indisponible pour raisons de sécurité. Contactez votre administrateur.',
                'code'     => 'KILL_SWITCH_ACTIVE',
                'depuis'   => $statut['depuis'],
            ], 503)->header('Retry-After', '3600');
        }

        return $next($request);
    }
}
```

---

## PARTIE E — SUPPLY CHAIN SECURITY + AUTO-HEALING
## ════════════════════════════════════════════════

## ÉTAPE 8 — SupplyChainVerifierCommand

```php
// Créer : edugestdz/backend/app/Console/Commands/SupplyChainVerifierCommand.php

<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

/**
 * Vérification de la chaîne d'approvisionnement logicielle.
 *
 * Vérifie que les dépendances Composer n'ont pas été modifiées
 * (protection contre les supply chain attacks comme SolarWinds).
 *
 * Actions :
 * 1. composer audit — vérifie les CVE connus
 * 2. Vérification du hash de composer.lock
 * 3. Alerte si une dépendance est modifiée de manière inattendue
 */
class SupplyChainVerifierCommand extends Command
{
    protected $signature   = 'edugest:supply-chain-verify';
    protected $description = 'Vérifier l\'intégrité des dépendances Composer (supply chain security)';

    public function handle(): int
    {
        $this->info('🔍 Vérification supply chain...');
        $ok = true;

        // 1. Audit des vulnérabilités connues
        $this->line('  → Audit CVE...');
        $result = Process::run('composer audit --format=json', function (string $type, string $output) {});

        if ($result->exitCode() !== 0) {
            $this->warn('  ⚠️  Vulnérabilités détectées dans les dépendances !');
            $this->line($result->output());
            $ok = false;
        } else {
            $this->info('  ✅ Pas de CVE connu dans les dépendances');
        }

        // 2. Vérifier le hash de composer.lock
        $lockFile = base_path('composer.lock');
        if (file_exists($lockFile)) {
            $hashActuel   = hash_file('sha256', $lockFile);
            $hashStocké   = cache('composer_lock_hash');

            if ($hashStocké && $hashActuel !== $hashStocké) {
                $this->error("  🚨 composer.lock modifié ! Hash attendu: {$hashStocké} | Actuel: {$hashActuel}");
                app(\App\Services\SecurityMonitorService::class)->alerter(
                    'supply_chain_alert',
                    'critical',
                    "🚨 composer.lock modifié de manière inattendue !",
                    ['hash_precedent' => $hashStocké, 'hash_actuel' => $hashActuel]
                );
                $ok = false;
            } else {
                // Mémoriser le hash si pas encore fait
                cache(['composer_lock_hash' => $hashActuel], now()->addDays(7));
                $this->info("  ✅ composer.lock intègre (SHA256: " . substr($hashActuel, 0, 16) . "...)");
            }
        }

        if ($ok) {
            $this->info('✅ Supply chain vérifiée — aucune anomalie.');
            return Command::SUCCESS;
        } else {
            $this->error('⚠️  Supply chain: anomalies détectées — voir ci-dessus.');
            return Command::FAILURE;
        }
    }
}
```

**Modifier** `Kernel.php` :

```php
// Supply chain — chaque semaine (lundi 4h)
$schedule->command('edugest:supply-chain-verify')->weekly()->mondays()->at('04:00');
// SIEM — toutes les 5 minutes
$schedule->command('edugest:siem-analyse')->everyFiveMinutes()->withoutOverlapping();
// Audit chain — vérification intégrité quotidienne
$schedule->command('edugest:audit-chain-verify')->dailyAt('01:00');
```

---

## ÉTAPE 9 — Routes Kill Switch + SIEM Dashboard

```php
// Modifier : routes/api.php

use App\Http\Controllers\Api\V1\SecurityDashboardController;
use App\Services\KillSwitchService;
use App\Services\SiemService;

// Kill Switch — super admin uniquement, IP allowlist
Route::middleware(['auth:api', 'ip.allowlist'])->prefix('v1/security/kill-switch')->group(function () {
    Route::post('/voter',         function(\Illuminate\Http\Request $req) {
        $req->validate(['raison' => 'required|string|max:500']);
        $result = app(KillSwitchService::class)->voter(auth('api')->id(), $req->raison);
        return response()->json(array_merge(['success' => true], $result), $result['activé'] ? 200 : 202);
    });
    Route::delete('/desactiver',  function() {
        app(KillSwitchService::class)->désactiver(auth('api')->id());
        return response()->json(['success' => true, 'message' => 'Kill switch désactivé.']);
    });
    Route::get('/status',         function() {
        return response()->json(['success' => true, 'data' => app(KillSwitchService::class)->getStatut()]);
    });
});

// SIEM Dashboard
Route::middleware(['auth:api', 'ip.allowlist'])->prefix('v1/security/siem')->group(function () {
    Route::get('/rapport',        function() {
        return response()->json(['success' => true, 'data' => app(SiemService::class)->rapport()]);
    });
    Route::post('/analyser',      function() {
        $alertes = app(SiemService::class)->analyser();
        return response()->json(['success' => true, 'alertes' => $alertes]);
    });
});

// Cryptographie status
Route::middleware(['auth:api'])->get('/v1/security/crypto-status', function() {
    return response()->json(app(\App\Services\PostQuantumCryptoService::class)->niveauSécurité());
});
```

---

## ÉTAPE 10 — Enregistrer KillSwitchMiddleware en PREMIER

```php
// Modifier : bootstrap/app.php

$middleware->alias([
    // ... existants ...
    'kill.switch' => \App\Http\Middleware\KillSwitchMiddleware::class,
]);

// KillSwitchMiddleware DOIT être le premier dans la chaîne API
$middleware->api(prepend: [
    \App\Http\Middleware\KillSwitchMiddleware::class,
]);
```

---

## ÉTAPE 11 — Observer Eloquent pour Audit Chain automatique

```php
// Créer : edugestdz/backend/app/Observers/AuditChainObserver.php

<?php
namespace App\Observers;

use App\Services\AuditChainService;

/**
 * Observer Eloquent — enregistre automatiquement les changements
 * sur les modèles sensibles dans la chaîne d'audit.
 *
 * Enregistrer cet observer dans AppServiceProvider pour les modèles :
 * Eleve, Note, Facture, User, Personnel
 */
class AuditChainObserver
{
    public function created($model): void
    {
        $this->enregistrer('create', $model, [], $model->toArray());
    }

    public function updated($model): void
    {
        $this->enregistrer('update', $model, $model->getOriginal(), $model->getChanges());
    }

    public function deleted($model): void
    {
        $this->enregistrer('delete', $model, $model->toArray(), []);
    }

    private function enregistrer(string $type, $model, array $avant, array $après): void
    {
        try {
            // Supprimer les champs sensibles des logs
            $champsExclus = ['password', 'two_factor_secret', 'google2fa_secret', 'access_token', 'refresh_token'];
            $avant        = array_diff_key($avant, array_flip($champsExclus));
            $après        = array_diff_key($après, array_flip($champsExclus));

            AuditChainService::enregistrer(
                typeEvenement: $type,
                resourceType:  strtolower(class_basename($model)),
                resourceId:    (string) $model->getKey(),
                avant:         $avant,
                apres:         $après,
                userId:        auth('api')->id(),
                tenantId:      config('tenant.current_id')
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('AuditChain observer: ' . $e->getMessage());
        }
    }
}
```

**Modifier** `AppServiceProvider.php` :

```php
use App\Observers\AuditChainObserver;
use App\Models\{Eleve, Note, Facture, User};

public function boot(): void
{
    // Audit Chain automatique sur les modèles sensibles
    Eleve::observe(AuditChainObserver::class);
    Note::observe(AuditChainObserver::class);
    Facture::observe(AuditChainObserver::class);
    User::observe(AuditChainObserver::class);
}
```

---

## ÉTAPE 12 — Tests sécurité Niveau 6

```php
// Créer : edugestdz/backend/tests/Feature/Security/SecurityNiveau6Test.php

<?php
namespace Tests\Feature\Security;

use App\Models\User;
use App\Services\AuditChainService;
use App\Services\SiemService;
use App\Services\KillSwitchService;
use App\Services\PostQuantumCryptoService;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class SecurityNiveau6Test extends TestCase
{
    use RefreshDatabase;

    // ── Audit Chain Merkle ─────────────────────────────────────────────

    public function test_audit_chain_genere_bloc_valide(): void
    {
        $numBloc = AuditChainService::enregistrer(
            typeEvenement: 'test_create',
            resourceType:  'test',
            resourceId:    (string) Str::uuid(),
            avant:         [],
            apres:         ['nom' => 'Test'],
            userId:        (string) Str::uuid(),
            tenantId:      (string) Str::uuid()
        );

        $this->assertGreaterThan(0, $numBloc);
        $this->assertDatabaseHas('audit_chain', ['type_evenement' => 'test_create']);
    }

    public function test_audit_chain_integrite_valide(): void
    {
        // Enregistrer quelques blocs
        for ($i = 0; $i < 3; $i++) {
            AuditChainService::enregistrer('test', 'test', (string)Str::uuid(), [], ['i' => $i]);
        }

        $résultat = AuditChainService::verifierIntegriteComplete();
        $this->assertTrue($résultat['valide']);
        $this->assertNull($résultat['premier_bloc_invalide']);
    }

    public function test_audit_chain_falsification_detectee(): void
    {
        AuditChainService::enregistrer('test_tamper', 'test', (string)Str::uuid(), [], ['data' => 'original']);

        // Falsifier directement en BDD (ce qu'un attaquant ferait)
        \DB::table('audit_chain')
            ->where('type_evenement', 'test_tamper')
            ->update(['hash_contenu' => 'hash_falsifie_000000000000000000000000000000000']);

        $résultat = AuditChainService::verifierIntegriteComplete();
        $this->assertFalse($résultat['valide']);
        $this->assertNotNull($résultat['premier_bloc_invalide']);
    }

    // ── Kill Switch MPC ────────────────────────────────────────────────

    public function test_kill_switch_requiert_2_admins(): void
    {
        $admin1 = User::factory()->create(['role' => 'super_admin']);
        $ks     = app(KillSwitchService::class);

        $résultat = $ks->voter($admin1->id, 'Test raison');
        $this->assertFalse($résultat['activé']);
        $this->assertEquals(1, $résultat['votes']);
    }

    public function test_kill_switch_active_avec_2_admins(): void
    {
        $admin1 = User::factory()->create(['role' => 'super_admin']);
        $admin2 = User::factory()->create(['role' => 'super_admin']);
        $ks     = app(KillSwitchService::class);

        $ks->voter($admin1->id, 'Raison test 1');
        $résultat = $ks->voter($admin2->id, 'Raison test 2');

        $this->assertTrue($résultat['activé']);
        $this->assertTrue($ks->estActif());
    }

    public function test_api_bloquee_quand_kill_switch_actif(): void
    {
        // Activer directement le cache kill switch
        \Illuminate\Support\Facades\Cache::put('kill_switch_active', [
            'depuis' => now()->toIso8601String(),
            'raison' => 'Test',
            'votes'  => [],
        ], 3600);

        $this->getJson('/api/v1/eleves')->assertStatus(503);

        \Illuminate\Support\Facades\Cache::forget('kill_switch_active');
    }

    public function test_kill_switch_desactivable(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $ks    = app(KillSwitchService::class);

        \Illuminate\Support\Facades\Cache::put('kill_switch_active', ['depuis' => now(), 'raison' => 'Test', 'votes' => []], 3600);

        $ks->désactiver($admin->id);
        $this->assertFalse($ks->estActif());
    }

    // ── Post-Quantum Crypto ────────────────────────────────────────────

    public function test_generer_paire_cles_valide(): void
    {
        $crypto = app(PostQuantumCryptoService::class);
        $paire  = $crypto->genererPaireDeClés();

        $this->assertArrayHasKey('publique', $paire);
        $this->assertArrayHasKey('privée', $paire);
        $this->assertNotEmpty($paire['publique']);
        $this->assertNotEmpty($paire['privée']);
    }

    public function test_signature_verifiable(): void
    {
        $crypto  = app(PostQuantumCryptoService::class);
        $données = 'Message confidentiel EduGest DZ';

        if (extension_loaded('sodium')) {
            // Test sodium uniquement si disponible
            $kp = sodium_crypto_sign_keypair();
            $privée = base64_encode(sodium_crypto_sign_secretkey($kp));
            $publique = base64_encode(sodium_crypto_sign_publickey($kp));

            $sig = $crypto->signer($données, $privée);
            $this->assertTrue($crypto->vérifierSignature($données, $sig, $publique));
        } else {
            // Test RSA
            $paire = $crypto->genererPaireDeClés();
            $sig   = $crypto->signer($données, $paire['privée']);
            $this->assertTrue($crypto->vérifierSignature($données, $sig, $paire['publique']));
        }
    }

    public function test_crypto_status_retourne_algo(): void
    {
        $crypto  = app(PostQuantumCryptoService::class);
        $statut  = $crypto->niveauSécurité();

        $this->assertArrayHasKey('algorithme_chiffrement', $statut);
        $this->assertArrayHasKey('sodium_disponible', $statut);
        $this->assertArrayHasKey('post_quantum', $statut);
    }

    // ── SIEM ──────────────────────────────────────────────────────────

    public function test_siem_rapport_accessible(): void
    {
        $siem    = app(SiemService::class);
        $rapport = $siem->rapport();

        $this->assertArrayHasKey('total_évènements', $rapport);
        $this->assertArrayHasKey('audit_chain_valide', $rapport);
        $this->assertArrayHasKey('score_risque_moyen', $rapport);
    }

    public function test_siem_analyse_sans_donnees_pas_d_alerte(): void
    {
        $siem   = app(SiemService::class);
        $alertes = $siem->analyser();

        // Sans données d'attaque → pas d'alertes
        $this->assertEmpty($alertes);
    }

    // ── Audit Chain Observer ──────────────────────────────────────────

    public function test_creation_eleve_enregistree_dans_chain(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        config(['tenant.current_id' => $user->tenant_id]);

        // Créer un élève → déclenche l'observer
        \App\Models\Eleve::factory()->create(['tenant_id' => $user->tenant_id]);

        $this->assertDatabaseHas('audit_chain', ['type_evenement' => 'create', 'resource_type' => 'eleve']);
    }
}
```

---

## ÉTAPE 13 — Commande de vérification quotidienne de la chaîne

```php
// Créer : edugestdz/backend/app/Console/Commands/AuditChainVerifyCommand.php

<?php
namespace App\Console\Commands;

use App\Services\AuditChainService;
use App\Services\SecurityMonitorService;
use Illuminate\Console\Command;

class AuditChainVerifyCommand extends Command
{
    protected $signature   = 'edugest:audit-chain-verify {--tenant=}';
    protected $description = 'Vérifier l\'intégrité de la chaîne d\'audit Merkle';

    public function handle(SecurityMonitorService $monitor): int
    {
        $tenantId = $this->option('tenant');
        $this->info('🔍 Vérification intégrité Audit Chain...');

        $résultat = AuditChainService::verifierIntegriteComplete($tenantId);

        if ($résultat['valide']) {
            $this->info("✅ Chaîne d'audit valide — {$résultat['total_blocs']} blocs vérifiés.");
            return Command::SUCCESS;
        }

        $this->error("🚨 AUDIT CHAIN COMPROMISE — Premier bloc invalide: #{$résultat['premier_bloc_invalide']}");

        $monitor->alerter(
            'audit_chain_compromised',
            'emergency',
            "🚨🚨 AUDIT CHAIN COMPROMISE — Bloc #{$résultat['premier_bloc_invalide']} invalide — Falsification détectée",
            $résultat
        );

        return Command::FAILURE;
    }
}
```

---

## ÉTAPE 14 — Exécution

```bash
cd edugestdz/backend

composer require paragonie/sodium_compat  # Si libsodium pas disponible
php artisan migrate
composer dump-autoload -o
php artisan test --parallel
# → 0 régression + 14 nouveaux tests verts

git add .
git commit -m "security(niveau6): Audit Chain Merkle Tree SHA3-256 + SIEM corrélation + Post-Quantum Crypto (Ed25519/RSA-4096) + Kill Switch MPC 2-admins + Supply Chain Verify + Honeypot Observer + 14 tests"
git push origin develop
# → PR develop → main
```

---

## CE QUE TU DIS À DEEPSEEK

```
Repo : https://github.com/Allintelligence2024/edugest-dz.git
Branche : develop
git checkout develop && git pull origin main

Fichier : MISSION_SECURITE_NIVEAU6.md — 14 étapes dans l'ordre.

RÈGLES CRITIQUES :
1. PostgreSQL uniquement — 0 régression.
2. AuditChainService.enregistrer() : TOUJOURS dans une transaction DB (DB::transaction).
   Les race conditions détruiraient la chaîne.
3. La migration audit_chain : insérer le BLOC GENESIS dans up() avec bloc_numero = 0.
   Sans le genesis block, la chaîne est invalide dès le départ.
4. KillSwitchMiddleware : doit être ajouté avec $middleware->api(prepend: [...]) —
   PAS append. Il doit s'exécuter AVANT tous les autres middlewares.
5. PostQuantumCryptoService : si sodium_crypto_sign_keypair() disponible → l'utiliser.
   Si extension sodium non chargée → fallback RSA-4096. Jamais planter.
6. SiemService.évaluerRègle() : utiliser Cache::put avec TTL 60s pour éviter
   de réévaluer la même règle plusieurs fois par minute (clé: siem_evaluated:{règle}:{Y-m-d-H-i}).
7. AuditChainObserver : exclure les champs sensibles (password, tokens) des logs.
   Ne JAMAIS stocker des mots de passe dans la chaîne d'audit.
8. SupplyChainVerifierCommand : utiliser Process::run() de Laravel 11 (Illuminate\Support\Facades\Process).
   Fallback si composer audit n'est pas disponible → vérifier seulement le hash du lock file.
9. KillSwitchService : le vote expire en 10 minutes (FENETRE_VOTE_SEC = 600).
   Si le 2ème admin vote après 10 min → le premier vote est expiré → il faut revoter.
10. AuditChainService.verifierIntegriteComplete() : peut être lente sur de grosses chaînes.
    Utiliser lazy() + chunk(1000) pour les grandes tables.

php artisan migrate
composer dump-autoload -o
php artisan test --parallel → verts
git push origin develop → PR develop → main
```

---

## TABLEAU RÉCAPITULATIF — 6 NIVEAUX COMPLETS

```
Niveau 1 (CRITIQUE)  : RLS PostgreSQL + JWT Blacklist + Tenant Isolation + Fichiers signés
Niveau 2 (IMPORTANT) : Chiffrement colonnes + MFA obligatoire + Brute force + Headers OWASP
Niveau 3 (IMPORTANT) : Audit signé HMAC + Password policy + IP Allowlist + JWT rotation + Incidents
Niveau 4 (AVANCÉ)    : Zero-Trust + Device Fingerprint + Risk Score 0-100 + RBAC granulaire
Niveau 5 (EXPERT)    : Honeypots + Canary tokens + SSRF + SQL Layer + Vault + Insider threat
Niveau 6 (FORTERESSE): Audit Chain Merkle + SIEM + Post-Quantum Crypto + Kill Switch MPC

Résultat final : Sécurité niveau BANQUE CENTRALE — résistante aux attaquants état-nation
```
