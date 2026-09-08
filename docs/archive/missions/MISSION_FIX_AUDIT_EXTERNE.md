# 🔧 MISSION DEEPSEEK — Fix Audit Externe (4 problèmes réels identifiés)
## EduGest DZ · Branche : develop · 9 Juillet 2026
## Tests actuels : 724 ✅ · Objectif : ≥ 740 ✅ · 0 régression
## Prérequis : main à jour · branch develop synchronisée

---

## CONTEXTE — Ce que l'audit externe a trouvé (lu dans le code réel)

```
PROBLÈME 1 — Mensonge technique : PostQuantumCryptoService mal nommé
  → Ed25519 (Sodium) et RSA-4096 ne sont PAS post-quantiques
  → Le vrai post-quantique = CRYSTALS-Kyber/Dilithium (NIST PQC 2024)
  → Ed25519 est cassable par l'algorithme de Shor (ordinateur quantique)
  → Fichier lu : app/Services/PostQuantumCryptoService.php (103 lignes)
  → La logique est bonne — seul le nom est faux et trompeur

PROBLÈME 2 — phpunit.xml : CACHE_STORE=array mais pas de guard explicite
  → Lu dans phpunit.xml actuel (69 lignes) : DB_CONNECTION=pgsql ✅
  → Mais pas de TestCase.php avec guard qui CRASH si SQLite détecté
  → Danger : un dev local sans PostgreSQL lance les tests sur SQLite
    sans le savoir → fausses passes

PROBLÈME 3 — KillSwitch : risque de verrouillage accidentel en production
  → Lu dans KillSwitchService.php (141 lignes)
  → Le vote expire en 600s (10 min) → OK
  → Mais : si Redis tombe → Cache::put() échoue silencieusement
  → Et : pas de mécanisme de déverrouillage d'urgence sans Redis
  → Risque réel : Redis down → KillSwitch reste actif → école bloquée

PROBLÈME 4 — DEPLOIEMENT.md : Self-Hosted "1 commande" trompeuse
  → Lu dans docs/DEPLOIEMENT.md : "sudo bash install.sh (~10 minutes)"
  → Réalité : Redis + PostgreSQL 16 + Meilisearch + Nginx + Docker
    + 60+ migrations → impossible en 10 min sur un vieux serveur d'école
  → La doc ne précise pas les prérequis OS (Ubuntu 22.04 obligatoire)
  → Ni les ressources minimales (RAM, CPU, espace disque)
```

### RÈGLES ABSOLUES
1. **0 régression** — 724 tests restent verts
2. **Renommage PostQuantumCryptoService → AsymmetricCryptoService** :
   - Créer le nouveau fichier avec le nouveau nom
   - Mettre à jour TOUS les endroits qui l'utilisent
   - Supprimer l'ancien fichier
   - Mettre à jour les tests
3. **Ne pas casser la logique existante** — seulement renommer et documenter honnêtement
4. **PostgreSQL uniquement** — jamais SQLite

---

## ÉTAPE 0 — Synchroniser

```bash
git checkout develop && git pull origin main
```

---

## ══════════════════════════════════════════════
## FIX 1 — Renommer PostQuantumCryptoService → AsymmetricCryptoService
## Corriger le mensonge technique Ed25519 = "post-quantique"
## ══════════════════════════════════════════════

### Pourquoi c'est important

```
Ed25519 (Sodium) utilise les courbes elliptiques Curve25519.
L'algorithme de Shor (ordinateur quantique) peut factoriser les groupes
de courbes elliptiques → Ed25519 N'EST PAS résistant aux QC.

Le vrai post-quantique (NIST PQC Standard 2024) :
- CRYSTALS-Kyber    → Échange de clés (KEM) — résistant aux QC
- CRYSTALS-Dilithium → Signature numérique — résistant aux QC
- SPHINCS+           → Signature basée sur les hash trees

Ce que nous avons réellement :
- Ed25519   → Très bon (128 bits de sécurité classique) mais cassable par QC
- RSA-4096  → Bon (fallback) mais encore plus cassable par QC
- L'infra est PRÊTE pour accueillir CRYSTALS-Dilithium quand PHP l'implémentera
```

### FIX : Créer le nouveau service honnêtement nommé

**Créer** : `edugestdz/backend/app/Services/AsymmetricCryptoService.php`

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * AsymmetricCryptoService — Service de cryptographie asymétrique.
 *
 * HONNÊTETÉ TECHNIQUE (corrigé suite audit externe Juillet 2026) :
 *
 * Ce service utilise :
 *   1. Ed25519 (libsodium) si disponible — courbes elliptiques Curve25519
 *   2. RSA-4096 (OpenSSL) en fallback
 *
 * CE N'EST PAS du "post-quantique" au sens NIST PQC 2024.
 * Ed25519 et RSA sont tous deux cassables par l'algorithme de Shor
 * sur un ordinateur quantique suffisamment puissant.
 *
 * RÉSISTANCE RÉELLE :
 *   - Ed25519  : 128 bits classique | NON résistant quantique
 *   - RSA-4096 : ~140 bits classique | NON résistant quantique
 *
 * VRAI POST-QUANTIQUE (NIST PQC 2024, pas encore en PHP natif) :
 *   - CRYSTALS-Kyber    : échange de clés (KEM)
 *   - CRYSTALS-Dilithium : signature numérique
 *   - SPHINCS+           : signature hash-based
 *
 * FEUILLE DE ROUTE :
 *   Quand une bibliothèque PHP stable implémente CRYSTALS-Dilithium
 *   (ex: openssl 3.x avec liboqs), ce service sera mis à jour.
 *   L'interface publique (signer/verifier/genererPaireDeClés) restera identique.
 *
 * POURQUOI ED25519 EST QUAND MÊME UN BON CHOIX AUJOURD'HUI :
 *   - Plus sûr que RSA-2048 contre les attaques classiques
 *   - Signatures plus petites (64 bytes vs 512 bytes RSA)
 *   - Calculs plus rapides
 *   - Recommandé par ANSSI, BSI, NIST pour les usages actuels
 *   - Les ordinateurs quantiques suffisamment puissants n'existent pas encore (2026)
 */
class AsymmetricCryptoService
{
    private ?string $publicKey  = null;
    private ?string $privateKey = null;
    private bool    $useSodium  = false;
    private string  $algorithme = 'unknown';

    public function __construct()
    {
        $this->useSodium = extension_loaded('sodium')
            && function_exists('sodium_crypto_sign_keypair');

        if ($this->useSodium) {
            try {
                $keypair          = sodium_crypto_sign_keypair();
                $this->publicKey  = sodium_crypto_sign_publickey($keypair);
                $this->privateKey = sodium_crypto_sign_secretkey($keypair);
                $this->algorithme = 'Ed25519'; // Courbes elliptiques Curve25519
            } catch (\Throwable $e) {
                Log::warning('AsymmetricCrypto: sodium indisponible, fallback RSA-4096', [
                    'error' => $e->getMessage(),
                ]);
                $this->useSodium = false;
                $this->genererRsa();
            }
        } else {
            Log::info('AsymmetricCrypto: sodium non chargé, utilisation RSA-4096');
            $this->genererRsa();
        }
    }

    /**
     * Signer des données.
     * Retourne la signature encodée en base64.
     */
    public function signer(string $data): string
    {
        if ($this->useSodium && $this->privateKey !== null) {
            return base64_encode(sodium_crypto_sign_detached($data, $this->privateKey));
        }

        if (!str_starts_with($this->privateKey ?? '', '-----BEGIN')) {
            // Fallback HMAC si ni sodium ni OpenSSL disponible
            return base64_encode(hash_hmac('sha512', $data, $this->privateKey ?? '', true));
        }

        openssl_sign($data, $signature, $this->privateKey, OPENSSL_ALGO_SHA512);
        return base64_encode($signature);
    }

    /**
     * Vérifier une signature.
     */
    public function verifier(string $data, string $signature): bool
    {
        $decoded = base64_decode($signature, true);
        if ($decoded === false) return false;

        if ($this->useSodium && $this->publicKey !== null) {
            return sodium_crypto_sign_verify_detached($decoded, $data, $this->publicKey);
        }

        if (!str_starts_with($this->privateKey ?? '', '-----BEGIN')) {
            $expected = hash_hmac('sha512', $data, $this->privateKey ?? '', true);
            return hash_equals($expected, $decoded);
        }

        $result = openssl_verify($data, $decoded, $this->publicKey, OPENSSL_ALGO_SHA512);
        return $result === 1;
    }

    /**
     * Obtenir la clé publique encodée en base64.
     */
    public function getPublicKey(): string
    {
        return base64_encode($this->publicKey ?? '');
    }

    /**
     * Obtenir le niveau de sécurité réel (honnête).
     */
    public function niveauSecuriteReel(): array
    {
        return [
            'algorithme'               => $this->algorithme,
            'bits_securite_classique'  => match ($this->algorithme) {
                'Ed25519'  => 128,
                'RSA-4096' => 140,
                default    => 64,
            },
            'resistant_quantique'      => false, // HONNÊTE — ni Ed25519 ni RSA ne le sont
            'resistant_classique'      => true,
            'sodium_disponible'        => $this->useSodium,
            'recommande_pour_aujourdhui' => true, // Ed25519 est le best practice actuel
            'note_honnete'             => 'Ed25519 est excellent contre les attaques classiques '
                . 'actuelles. Il sera remplacé par CRYSTALS-Dilithium quand PHP l\'implémentera nativement.',
            'reference_nist'           => 'NIST PQC 2024 : FIPS 203 (Kyber), FIPS 204 (Dilithium), FIPS 205 (SPHINCS+)',
        ];
    }

    /**
     * Générer une paire de clés RSA-4096 (fallback si sodium absent).
     */
    private function genererRsa(): void
    {
        $this->algorithme = 'RSA-4096';

        if (!extension_loaded('openssl') || !function_exists('openssl_pkey_new')) {
            // Double fallback HMAC si OpenSSL aussi absent (environnement très limité)
            $this->publicKey  = hash('sha256', 'fallback-public-'  . config('app.key'));
            $this->privateKey = hash('sha256', 'fallback-private-' . config('app.key'));
            $this->algorithme = 'HMAC-SHA512-fallback';
            Log::error('AsymmetricCrypto: ni sodium ni openssl disponibles — fallback HMAC (non recommandé)');
            return;
        }

        $resource = @openssl_pkey_new([
            'private_key_bits' => 4096,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($resource === false) {
            $this->publicKey  = hash('sha256', 'fallback-public-'  . config('app.key'));
            $this->privateKey = hash('sha256', 'fallback-private-' . config('app.key'));
            $this->algorithme = 'HMAC-SHA512-fallback';
            return;
        }

        openssl_pkey_export($resource, $this->privateKey);
        $details        = openssl_pkey_get_details($resource);
        $this->publicKey= $details['key'];
    }
}
```

---

### Mettre à jour tous les endroits qui utilisent PostQuantumCryptoService

**Rechercher dans le repo** tous les fichiers qui contiennent `PostQuantumCryptoService` :

```bash
cd edugestdz/backend
grep -r "PostQuantumCryptoService" --include="*.php" -l
```

**Pour chaque fichier trouvé**, remplacer :
- `use App\Services\PostQuantumCryptoService;` → `use App\Services\AsymmetricCryptoService;`
- `PostQuantumCryptoService` → `AsymmetricCryptoService`
- `new PostQuantumCryptoService()` → `new AsymmetricCryptoService()`
- `app(PostQuantumCryptoService::class)` → `app(AsymmetricCryptoService::class)`

**Typiquement dans** :
- `tests/Feature/Security/SecurityNiveau6Test.php`
- Tout autre service ou controller qui l'injecte
- `bootstrap/app.php` si enregistré

**Supprimer l'ancien fichier** :
```bash
rm edugestdz/backend/app/Services/PostQuantumCryptoService.php
```

**Mettre à jour le test SecurityNiveau6Test.php** :

Trouver toutes les références à `PostQuantumCryptoService` et remplacer par `AsymmetricCryptoService`.
Trouver les tests qui vérifient `'post_quantum' => true` et corriger en `'resistant_quantique' => false` (la vérité).

Exemple de correction dans le test :
```php
// AVANT (mensonge) :
$this->assertTrue($statut['post_quantum']);

// APRÈS (honnête) :
$statut = $crypto->niveauSecuriteReel();
$this->assertFalse($statut['resistant_quantique']); // Ed25519 n'est pas QC-résistant
$this->assertTrue($statut['resistant_classique']);   // Mais excellent en classique
$this->assertContains($statut['algorithme'], ['Ed25519', 'RSA-4096', 'HMAC-SHA512-fallback']);
```

---

## ══════════════════════════════════════════════
## FIX 2 — TestCase.php : Guard PostgreSQL + Protection SQLite
## ══════════════════════════════════════════════

### Problème exact

`phpunit.xml` force `DB_CONNECTION=pgsql` — c'est bien.
Mais si un dev lance les tests sans PostgreSQL disponible, Laravel peut
basculer silencieusement sur une connexion de secours ou crasher avec
un message peu clair. Il faut un guard explicite dans TestCase.php.

**Vérifier si `tests/TestCase.php` existe déjà** :
```bash
ls edugestdz/backend/tests/TestCase.php
```

**Si non existant, créer. Si existant, modifier en ajoutant setUp()** :

**Fichier** : `edugestdz/backend/tests/TestCase.php`

```php
<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

/**
 * Classe de base pour tous les tests EduGest DZ.
 *
 * Contient des guards de sécurité :
 * 1. Refuse de tourner sur SQLite (PostgreSQL obligatoire)
 * 2. Vérifie la connexion PostgreSQL avant chaque test
 * 3. Réinitialise le tenant context entre les tests
 */
abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // ── GUARD 1 : Bloquer SQLite ────────────────────────────────────
        // EduGest DZ utilise des features PostgreSQL-spécifiques :
        //   RLS (Row-Level Security), jsonb, gen_random_uuid(), SAVEPOINT
        // Ces features n'existent pas dans SQLite → fausses passes de tests.
        $driver = config('database.default');
        if ($driver === 'sqlite') {
            $this->fail(
                "\n\n" .
                "❌ STOP : Les tests tournent sur SQLite — INTERDIT pour EduGest DZ\n\n" .
                "EduGest DZ utilise des fonctionnalités PostgreSQL exclusives :\n" .
                "  • RLS (Row-Level Security) sur 40+ tables\n" .
                "  • jsonb (stockage JSON binaire)\n" .
                "  • gen_random_uuid() (UUID natif)\n" .
                "  • SAVEPOINT (transactions imbriquées)\n\n" .
                "Solution :\n" .
                "  1. Installer PostgreSQL 16 localement\n" .
                "  2. Créer la base : createdb edugestdz_test\n" .
                "  3. Ou utiliser Docker : docker compose up -d\n" .
                "  4. Relancer : php artisan test --parallel\n\n" .
                "Ne JAMAIS ajouter de compat SQLite aux migrations.\n"
            );
        }

        // ── GUARD 2 : Vérifier la connexion PostgreSQL ──────────────────
        // Si PostgreSQL n'est pas démarré → message clair (pas une stacktrace cryptique)
        try {
            DB::connection()->getPdo();
        } catch (\Exception $e) {
            $this->fail(
                "\n\n" .
                "❌ Connexion PostgreSQL impossible\n\n" .
                "  Host : " . config('database.connections.pgsql.host', '127.0.0.1') . "\n" .
                "  Port : " . config('database.connections.pgsql.port', '5432') . "\n" .
                "  Base : " . config('database.connections.pgsql.database', 'edugestdz_test') . "\n" .
                "  Erreur : " . $e->getMessage() . "\n\n" .
                "Solution : démarrer PostgreSQL ou Docker\n"
            );
        }

        // ── GUARD 3 : Réinitialiser le contexte tenant entre les tests ──
        // Évite les fuites de contexte entre tests parallèles
        config(['tenant.current_id' => null]);
    }
}
```

---

## ══════════════════════════════════════════════
## FIX 3 — KillSwitch : Protection contre le verrouillage accidentel
## ══════════════════════════════════════════════

### Problème exact (lu dans KillSwitchService.php)

```php
// Ligne dans executerAction() :
Cache::put('kill_switch:active', true, now()->addHour());
```

**Risque 1** : Si Redis est down, `Cache::put()` échoue silencieusement
→ Le KillSwitch pense être actif mais Redis ne le stocke pas
→ Comportement imprévisible

**Risque 2** : Si Redis redémarre, `kill_switch:active` disparaît
→ Le KillSwitch se désactive tout seul sans action humaine (bien ou mal selon le cas)

**Risque 3** : Pas de mécanisme de déverrouillage d'urgence sans Redis

### FIX : Ajouter la persistance DB comme fallback + mécanisme d'urgence

**Créer migration** : `edugestdz/backend/database/migrations/2026_07_09_100000_add_kill_switch_fallback_to_kill_switch_votes.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ajouter une colonne de fallback BDD au cas où Redis est down
        Schema::table('kill_switch_votes', function (Blueprint $table) {
            if (!Schema::hasColumn('kill_switch_votes', 'active_since')) {
                $table->timestamp('active_since')->nullable()->after('status');
                // Si non null → le KillSwitch est actif même sans Redis
            }
            if (!Schema::hasColumn('kill_switch_votes', 'deactivated_at')) {
                $table->timestamp('deactivated_at')->nullable()->after('active_since');
            }
        });

        // Table d'état global du KillSwitch (séparée des votes)
        if (!Schema::hasTable('kill_switch_state')) {
            Schema::create('kill_switch_state', function (Blueprint $table) {
                $table->id();
                $table->boolean('is_active')->default(false);
                $table->string('reason')->nullable();
                $table->string('activated_by')->nullable();  // super_admin email
                $table->string('approved_by')->nullable();   // 2ème super_admin
                $table->timestamp('activated_at')->nullable();
                $table->timestamp('deactivated_at')->nullable();
                $table->timestamps();
            });

            // Insérer l'état initial (inactif)
            \DB::table('kill_switch_state')->insert([
                'is_active'    => false,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kill_switch_state');
        Schema::table('kill_switch_votes', function (Blueprint $table) {
            $table->dropColumnIfExists('active_since');
            $table->dropColumnIfExists('deactivated_at');
        });
    }
};
```

**Modifier** `edugestdz/backend/app/Services/KillSwitchService.php`

Remplacer la méthode `executerAction()` et ajouter `estActif()` avec fallback BDD :

```php
/**
 * Exécuter l'action du KillSwitch avec persistance double (Redis + BDD).
 * Si Redis est down → la BDD prend le relais (fail-safe).
 */
private function executerAction(KillSwitchVote $vote): void
{
    try {
        // ── Persistance Redis (principal) ─────────────────────────────
        Cache::put('kill_switch:active', [
            'reason'       => $vote->action,
            'activated_at' => now()->toIso8601String(),
            'vote_id'      => $vote->id,
        ], now()->addHours(24)); // 24h max — doit être désactivé manuellement

        // ── Persistance BDD (fallback si Redis down) ──────────────────
        \DB::table('kill_switch_state')
            ->update([
                'is_active'    => true,
                'reason'       => $vote->action,
                'activated_by' => $vote->initiator_id,
                'approved_by'  => $vote->approver_id,
                'activated_at' => now(),
                'deactivated_at' => null,
                'updated_at'   => now(),
            ]);

        Log::critical('KillSwitch: action exécutée — persistance Redis + BDD', [
            'action'  => $vote->action,
            'vote_id' => $vote->id,
        ]);

    } catch (\Throwable $e) {
        Log::error('KillSwitch: échec activation', [
            'action' => $vote->action,
            'error'  => $e->getMessage(),
        ]);
        // Re-lancer pour que l'appelant sache que ça a échoué
        throw $e;
    }
}

/**
 * Vérifier si le KillSwitch est actif.
 * Vérifie Redis EN PREMIER (rapide), puis BDD (fallback si Redis down).
 */
public function estActif(): bool
{
    // ── Vérification Redis (principale, rapide) ──────────────────────
    try {
        if (Cache::has('kill_switch:active')) {
            return true;
        }
    } catch (\Throwable) {
        // Redis down → continuer vers BDD
        Log::warning('KillSwitch: Redis indisponible — vérification BDD');
    }

    // ── Vérification BDD (fallback) ───────────────────────────────────
    try {
        return (bool) \DB::table('kill_switch_state')
            ->where('is_active', true)
            ->whereNull('deactivated_at')
            ->exists();
    } catch (\Throwable $e) {
        // Si ni Redis ni BDD ne répondent → comportement sécurisé (LAISSER PASSER)
        // Un KillSwitch qui bloque quand l'infra est down = problème opérationnel
        Log::error('KillSwitch: impossible de vérifier (Redis + BDD down) — LAISSER PASSER', [
            'error' => $e->getMessage(),
        ]);
        return false; // fail-open ici car bloquer = service down = pire que la menace
    }
}

/**
 * Désactiver le KillSwitch (Redis + BDD).
 */
public function desactiver(string $adminId): void
{
    // ── Désactiver dans Redis ──────────────────────────────────────────
    try {
        Cache::forget('kill_switch:active');
    } catch (\Throwable) {}

    // ── Désactiver dans BDD ───────────────────────────────────────────
    try {
        \DB::table('kill_switch_state')
            ->update([
                'is_active'      => false,
                'deactivated_at' => now(),
                'updated_at'     => now(),
            ]);
    } catch (\Throwable $e) {
        Log::error('KillSwitch: impossible de désactiver en BDD', ['error' => $e->getMessage()]);
    }

    Log::warning('KillSwitch: désactivé', ['admin' => $adminId]);
}
```

**Mettre à jour KillSwitchMiddleware** pour utiliser la méthode `estActif()` :

```php
// Dans app/Http/Middleware/KillSwitchMiddleware.php
// Remplacer :
if (Cache::has('kill_switch:active')) {

// Par :
if (app(\App\Services\KillSwitchService::class)->estActif()) {
```

---

## ══════════════════════════════════════════════
## FIX 4 — DEPLOIEMENT.md : Corriger le Self-Hosted "impossible en 10 min"
## ══════════════════════════════════════════════

### Problème exact

Le guide actuel dit "~10 minutes" pour l'installation Self-Hosted.
C'est faux. Réalité : Docker seul prend 15-30 min sur une connexion algérienne.
Si le serveur est un vieux Windows ou Ubuntu 18 → impossible.

### FIX : Réécrire la section NIVEAU 3 honnêtement

**Modifier** : `edugestdz/docs/DEPLOIEMENT.md`

Remplacer la section NIVEAU 3 — Self-Hosted par ce contenu corrigé :

```markdown
## NIVEAU 3 — Self-Hosted (Installation chez le client)

**Pour :** Zones avec connexion instable · Groupes d'écoles · Établissements avec IT interne
**Conformité :** Loi 18-07 ✅ · Données 100% sur site ✅

> ⚠️ **AVERTISSEMENT HONNÊTE** : Ce mode n'est PAS "plug and play".
> Il nécessite un responsable IT compétent et un serveur dédié sous Ubuntu 22.04.
> La durée d'installation réelle est **45-90 minutes** (pas "10 minutes").
> Sur un vieux PC Windows ou Ubuntu < 20.04 → non supporté.

### Prérequis OBLIGATOIRES (vérifier avant d'acheter)

| Prérequis | Minimum | Recommandé | Obligatoire |
|-----------|---------|------------|-------------|
| OS | Ubuntu 22.04 LTS 64-bit | Ubuntu 22.04 LTS | **Oui** |
| RAM | 6 GB | 8 GB | **Oui — 4 GB insuffisant** |
| CPU | 2 cœurs | 4 cœurs | Min. 2 |
| Stockage | 60 GB SSD | 120 GB SSD NVMe | SSD obligatoire |
| Connexion | 10 Mbps | 20 Mbps+ | Pour l'installation |
| Docker | 24+ | 24+ | **Oui** |
| Connaissance IT | Niveau admin Linux | — | **Obligatoire** |

**❌ NON SUPPORTÉ :**
- Windows (toutes versions)
- Ubuntu 18.04, 20.04
- CentOS, Fedora, Alpine
- Machines avec < 4 GB RAM
- Serveurs partagés (hébergement mutualisé)

### Services installés automatiquement

L'installation déploie les services suivants via Docker Compose :

```
┌────────────────────────────────────────────────────────┐
│  nginx (reverse proxy + SSL)        Port 80/443        │
│  php-fpm (Laravel 11 app)          Port 9000 interne   │
│  postgresql:16 (base de données)   Port 5432 interne   │
│  redis:7 (cache + sessions)        Port 6379 interne   │
│  meilisearch:1.8 (recherche)       Port 7700 interne   │
│  worker (queue jobs Laravel)       Supervisord          │
└────────────────────────────────────────────────────────┘
Total : ~3-4 GB RAM utilisés en fonctionnement normal
```

### Installation (durée réelle : 45-90 minutes)

```bash
# ── Étape 1 : Préparer le serveur (15-20 min) ──────────────
# Sur Ubuntu 22.04 fraîchement installé en root :

# Mettre à jour le système
apt update && apt upgrade -y

# Installer Docker
curl -fsSL https://get.docker.com | bash
usermod -aG docker $USER

# Installer Git
apt install -y git curl nano

# ── Étape 2 : Cloner le projet (5-10 min selon connexion) ──
git clone https://github.com/Allintelligence2024/edugest-dz.git /opt/edugestdz
cd /opt/edugestdz/edugestdz

# ── Étape 3 : Configurer (10-15 min) ───────────────────────
cp backend/.env.level3.example backend/.env
nano backend/.env
# Remplir OBLIGATOIREMENT :
#   APP_URL=https://votre-ecole.dz  (ou http://IP-SERVEUR)
#   DB_PASSWORD=MotDePasseComplexe16Chars
#   JWT_SECRET=(généré automatiquement à l'étape suivante)

# ── Étape 4 : Déployer (15-30 min — téléchargement images) ─
bash install.sh
# → Télécharge les images Docker (~2-3 GB selon vitesse)
# → Génère les clés (APP_KEY, JWT_SECRET)
# → Lance les migrations (60+ tables)
# → Importe le curriculum algérien
# → Configure Nginx + SSL (Let's Encrypt si domaine configuré)

# ── Étape 5 : Créer le premier compte (5 min) ─────────────
docker compose exec app php artisan db:seed --class=InitialProductionSeeder
# → Affiche le mot de passe temporaire du super_admin
# → CHANGER IMMÉDIATEMENT après la première connexion
```

### Vérification post-installation

```bash
# Test smoke — doit retourner "healthy"
curl http://localhost/api/health | python3 -m json.tool

# Vérifier tous les services
docker compose ps
# Tous doivent être "Up"

# Voir les logs si problème
docker compose logs app --tail=50
```

### Mise à jour (5-15 minutes)

```bash
cd /opt/edugestdz/edugestdz
bash update.sh
# → Sauvegarde automatique BDD → Pull nouvelles images → Migrations → Redémarrage
```

### Support à distance

```bash
# Partager l'accès SSH temporaire pour le support :
bash setup-vpn.sh
# → Génère une config WireGuard
# → Partager le fichier généré avec support@edugestdz.dz
```

### Matériel recommandé (prix Algérie Juillet 2026)

| Option | Matériel | Prix estimé | Pour |
|--------|----------|-------------|------|
| **Minimum** | PC reconditionné i5 · 8GB RAM · 256GB SSD | ~45 000-60 000 DA | < 100 élèves |
| **Standard** | Intel NUC Gen 12 · 16GB RAM · 512GB SSD | ~90 000-120 000 DA | < 300 élèves |
| **Groupe** | Mini-tour Xeon · 32GB RAM · 1TB SSD NVMe | ~160 000-200 000 DA | < 1000 élèves |

### Prix d'installation et support

- Installation + configuration + formation admin : **80 000 DA** (une fois)
- Abonnement support mensuel : **5 000 DA/mois** (mises à jour + assistance à distance)
- Formation directeur (1 journée sur site) : **15 000 DA** (optionnel)

> 💡 **Conseil** : Pour la majorité des écoles algériennes, le **Niveau 1 (SaaS Cloud DZ)**
> est beaucoup plus simple et moins cher sur le long terme. Le Self-Hosted est recommandé
> uniquement si vous avez un IT interne compétent et une raison impérative d'héberger localement.
```

---

## ÉTAPE FINALE — Tests + Vérification + Commit

### Tests à ajouter pour valider les fixes

**Modifier** : `edugestdz/backend/tests/Feature/Security/SecurityNiveau6Test.php`

Trouver le test `test_generer_paire_cles_valide()` et `test_crypto_status_retourne_algo()` et adapter :

```php
// Changer l'import :
use App\Services\AsymmetricCryptoService;  // ← nouveau nom

// Adapter les tests :
public function test_generer_paire_cles_valide(): void
{
    $crypto = app(AsymmetricCryptoService::class);
    $statut = $crypto->niveauSecuriteReel();

    $this->assertArrayHasKey('algorithme', $statut);
    $this->assertArrayHasKey('resistant_quantique', $statut);
    $this->assertArrayHasKey('resistant_classique', $statut);

    // HONNÊTETÉ : Ed25519 n'est PAS post-quantique
    $this->assertFalse($statut['resistant_quantique'],
        'Ed25519 n\'est pas post-quantique (cassable par algorithme de Shor)');

    // Mais excellent contre les attaques classiques
    $this->assertTrue($statut['resistant_classique']);

    // Algorithme doit être dans la liste connue
    $this->assertContains($statut['algorithme'],
        ['Ed25519', 'RSA-4096', 'HMAC-SHA512-fallback']);
}

public function test_signature_verifiable(): void
{
    $crypto  = app(AsymmetricCryptoService::class);
    $donnees = 'Message confidentiel EduGest DZ — Test signature';

    $signature = $crypto->signer($donnees);
    $this->assertNotEmpty($signature);
    $this->assertTrue($crypto->verifier($donnees, $signature));
}

public function test_signature_differentes_donnees_invalide(): void
{
    $crypto = app(AsymmetricCryptoService::class);

    $sig = $crypto->signer('données originales');

    // Une signature valide pour des données A ne doit pas valider des données B
    $this->assertFalse($crypto->verifier('données modifiées', $sig));
}
```

**Ajouter** un test KillSwitch avec persistance BDD :

```php
public function test_kill_switch_persiste_en_bdd(): void
{
    $ks = app(\App\Services\KillSwitchService::class);

    // Vider Redis simulé (ou utiliser array cache)
    \Illuminate\Support\Facades\Cache::flush();

    // Vérifier que le KillSwitch est inactif par défaut
    $this->assertFalse($ks->estActif());
}
```

### Exécution

```bash
cd edugestdz/backend

# Migrations (nouvelle table kill_switch_state)
php artisan migrate --force

# Vérifier que l'ancien fichier est bien supprimé
ls app/Services/PostQuantumCryptoService.php 2>/dev/null && echo "EXISTE ENCORE" || echo "OK - Supprimé"

# Vérifier que le nouveau existe
ls app/Services/AsymmetricCryptoService.php && echo "OK - Présent"

# Autoload
composer dump-autoload -o

# Tests complets
php artisan test --parallel

# Résultat attendu :
# ✅ 724 tests existants → toujours verts (0 régression)
# ✅ Tests AsymmetricCrypto → honnêtes (resistant_quantique = false)
# ✅ Tests KillSwitch → vérifient la persistance BDD
# Total : ≥ 728 tests

git add .
git commit -m "fix(audit-externe): 4 corrections suite audit indépendant Juillet 2026

Fix 1 — PostQuantumCryptoService renommé en AsymmetricCryptoService
  → Ed25519/RSA-4096 ne sont PAS post-quantiques (cassables par Shor)
  → Nouveau nom honnête + documentation claire sur la résistance réelle
  → Interface publique identique (signer/verifier/getPublicKey)
  → Tests mis à jour : resistant_quantique = false (vérité technique)

Fix 2 — TestCase.php : guard PostgreSQL anti-SQLite
  → Crash immédiat avec message clair si SQLite détecté
  → Message explicatif + instructions de correction
  → Réinitialisation tenant.current_id entre les tests

Fix 3 — KillSwitchService : persistance double Redis + BDD
  → Si Redis down : BDD prend le relais via kill_switch_state table
  → Migration additive avec hasColumn guards
  → estActif() vérifie Redis puis BDD (fallback)
  → desactiver() nettoie Redis ET BDD
  → fail-open si les deux sont down (service > sécurité dans ce cas)

Fix 4 — DEPLOIEMENT.md Self-Hosted : réécriture honnête
  → Durée réelle : 45-90 min (pas '10 minutes')
  → Prérequis explicites : Ubuntu 22.04, 6GB RAM, SSD, Docker
  → Liste ce qui est NON SUPPORTÉ (Windows, vieux Ubuntu, < 4GB)
  → Détail des 6 services Docker installés + RAM estimée
  → Prix et matériel à jour (Juillet 2026, DA)"

git push origin develop
# → PR develop → main
```

---

## RÉSUMÉ — 4 PROBLÈMES, 4 FIXES PRÉCIS

| # | Problème identifié | Sévérité | Fix appliqué |
|---|---|---|---|
| **1** | `PostQuantumCryptoService` → Ed25519 n'est PAS post-quantique | 🟠 Honnêteté | Renommé `AsymmetricCryptoService` + doc complète + tests corrigés |
| **2** | Pas de guard TestCase pour bloquer SQLite silencieux | 🟠 Fiabilité | `TestCase.php` avec fail explicite + message clair si SQLite |
| **3** | KillSwitch perd son état si Redis redémarre | 🔴 Fiabilité prod | Persistance double Redis+BDD + table `kill_switch_state` + `estActif()` avec fallback |
| **4** | Self-Hosted "10 minutes" → irréaliste et trompeur | 🟡 Honnêteté | DEPLOIEMENT.md réécrit honnêtement : 45-90 min, prérequis Ubuntu 22.04, 6GB RAM |

---

## CE QUE TU DIS À DEEPSEEK

```
Repo : https://github.com/Allintelligence2024/edugest-dz.git
Branche : develop
git checkout develop && git pull origin main

Fichier : MISSION_FIX_AUDIT_EXTERNE.md — 4 fixes dans l'ordre.

RÈGLES CRITIQUES :
1. PostgreSQL uniquement — 0 régression sur 724 tests.

2. Fix 1 (renommage) :
   AVANT de créer AsymmetricCryptoService.php, faire :
     grep -r "PostQuantumCryptoService" --include="*.php" -l
   pour lister TOUS les fichiers à modifier.
   Remplacer dans chaque fichier trouvé.
   Supprimer l'ancien fichier à la fin.
   Ne pas laisser les deux coexister.

3. Fix 2 (TestCase.php) :
   Si tests/TestCase.php existe déjà avec du contenu important →
   AJOUTER seulement le setUp() avec les guards.
   Ne pas écraser si le fichier a des méthodes utilisées par les tests.

4. Fix 3 (KillSwitch) :
   La migration utilise Schema::hasColumn() et Schema::hasTable().
   Si kill_switch_votes n'existe pas encore → ne pas faire l'ALTER TABLE.
   Si kill_switch_state existe déjà → ignorer la création.
   Le fail-open si Redis+BDD sont down est intentionnel :
     un KillSwitch qui bloque quand l'infra est down = école coupée
     = problème opérationnel pire que la menace de sécurité.

5. Fix 4 (DEPLOIEMENT.md) :
   Remplacer UNIQUEMENT la section NIVEAU 3.
   Ne pas toucher aux sections NIVEAU 1 et NIVEAU 2.

php artisan migrate --force
composer dump-autoload -o
php artisan test --parallel → ≥ 728 ✅ 0 failures
git push origin develop → PR → main
```
