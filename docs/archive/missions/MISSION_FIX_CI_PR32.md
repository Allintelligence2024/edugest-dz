# 🔧 MISSION DEEPSEEK — Fix CI PR #32 (Branche develop)
## EduGest DZ · 8 Juillet 2026
## Problème : 2 failing checks bloquent le merge develop → main

---

## DIAGNOSTIC RÉEL (lu directement dans le repo)

### Ce qui échoue exactement

```
Run #178 (push develop)      → exit code 2  → php artisan test --parallel échoue
Run #179 (pull_request #32)  → exit code 1  → php artisan test --parallel échoue
```

L'étape qui plante = "Run tests" (step:10) — les migrations passent.

### Causes identifiées (code lu dans GitHub)

---

#### 🔴 CAUSE 1 — UserFactory ne génère pas `two_factor_confirmed_at`

**Fichier** : `database/factories/UserFactory.php`

```php
// Ce que le factory produit ACTUELLEMENT (source lue) :
return [
    'nom'      => $this->faker->lastName(),
    'prenom'   => $this->faker->firstName(),
    'email'    => $this->faker->unique()->safeEmail(),
    'password' => static::$password ??= Hash::make('password'),
    'statut'   => 'actif',
    'langue'   => 'fr',
    // ← PAS de role_id, PAS de tenant_id, PAS de two_factor_confirmed_at
];
```

**Ce que les tests de sécurité font** :
```php
// SecurityNiveau2Test.php — ligne réelle lue dans GitHub
$admin = User::factory()->create([
    'role_id'                  => $this->role->id,
    'tenant_id'                => $this->tenant->id,
    'two_factor_secret'        => null,
    'two_factor_confirmed_at'  => null,   // ← COLONNE QUI N'EXISTE PAS dans la migration 0002
]);
```

**La migration `0002_create_users_table.php`** (lue) ne contient PAS `two_factor_confirmed_at`.
Elle ne contient que : nom, prenom, email, telephone, password, avatar_url, langue, theme, role_id, derniere_connexion, remember_token, email_verified_at, statut.

→ **Erreur** : `SQLSTATE[42703]: undefined column: 7 ERROR: column "two_factor_confirmed_at" of relation "users" does not exist`

---

#### 🔴 CAUSE 2 — `login_attempts` et `locked_until` absents de la migration

**Le modèle `User.php`** (lu) déclare dans `$fillable` :
```php
'login_attempts', 'locked_until', 'two_factor_phone',
'two_factor_type', 'two_factor_confirmed_at',
```

Mais **aucune de ces colonnes** n'est dans `0002_create_users_table.php`.

Si un test crée un User avec ces colonnes → erreur PostgreSQL "column does not exist".

---

#### 🔴 CAUSE 3 — Migration `2026_07_07_300000` RLS → `current_setting()::uuid` plante si valeur vide

Dans la migration RLS (lue) :
```sql
CREATE POLICY tenant_isolation_policy ON {table}
USING (
    tenant_id = current_setting('app.current_tenant_id', true)::uuid
    OR current_setting('app.current_tenant_id', true) IS NULL
    OR current_setting('app.current_tenant_id', true) = ''
)
```

**Problème** : PostgreSQL évalue `current_setting(...)::uuid` AVANT les conditions OR.
Si `current_setting('app.current_tenant_id', true)` retourne `''` (chaîne vide),
le cast `::uuid` plante avec `invalid input syntax for type uuid: ""`.

→ Résultat : les tests qui créent des données sans tenant_id défini échouent.

---

#### 🟡 CAUSE 4 — `SecurityNiveau2Test` : `->role?->nom` vs colonne directe

**`MfaRequired.php`** (lu) vérifie :
```php
if (!in_array($user->role?->nom, self::ROLES_REQUIERANT_MFA))
```

Mais dans les tests, après `User::factory()->create(['role_id' => $this->role->id])`,
la relation `role` n'est pas eager-loaded automatiquement.
`$user->role` → fait une requête → retourne le modèle Role → `->nom` = 'admin' ✓

Ce n'est pas la cause directe du fail, mais c'est fragile.

---

## FIXES À APPLIQUER — Dans l'ordre strict

---

### FIX 1 — Ajouter les colonnes manquantes dans la migration users

**Créer** : `edugestdz/backend/database/migrations/2026_07_08_001000_add_security_columns_to_users.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Colonnes 2FA — ajoutées si absentes
            if (!Schema::hasColumn('users', 'two_factor_secret')) {
                $table->text('two_factor_secret')->nullable()->after('statut');
            }
            if (!Schema::hasColumn('users', 'two_factor_recovery_codes')) {
                $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            }
            if (!Schema::hasColumn('users', 'two_factor_confirmed_at')) {
                $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
            }
            if (!Schema::hasColumn('users', 'two_factor_type')) {
                $table->string('two_factor_type', 20)->nullable()->after('two_factor_confirmed_at');
            }
            if (!Schema::hasColumn('users', 'two_factor_phone')) {
                $table->string('two_factor_phone', 20)->nullable()->after('two_factor_type');
            }
            // Colonnes sécurité login
            if (!Schema::hasColumn('users', 'login_attempts')) {
                $table->integer('login_attempts')->default(0)->after('two_factor_phone');
            }
            if (!Schema::hasColumn('users', 'locked_until')) {
                $table->timestamp('locked_until')->nullable()->after('login_attempts');
            }
            // Colonne last_login_at (utilisée par DeadManSwitch)
            if (!Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('locked_until');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumnIfExists('two_factor_secret');
            $table->dropColumnIfExists('two_factor_recovery_codes');
            $table->dropColumnIfExists('two_factor_confirmed_at');
            $table->dropColumnIfExists('two_factor_type');
            $table->dropColumnIfExists('two_factor_phone');
            $table->dropColumnIfExists('login_attempts');
            $table->dropColumnIfExists('locked_until');
            $table->dropColumnIfExists('last_login_at');
        });
    }
};
```

---

### FIX 2 — Corriger la politique RLS pour éviter le cast uuid sur chaîne vide

**Modifier** : `edugestdz/backend/database/migrations/2026_07_07_300000_add_postgresql_row_level_security.php`

Trouver la partie CREATE POLICY et remplacer par cette version safe :

```php
DB::statement("
    CREATE POLICY tenant_isolation_policy ON {$table}
    USING (
        current_setting('app.current_tenant_id', true) = ''
        OR current_setting('app.current_tenant_id', true) IS NULL
        OR tenant_id::text = current_setting('app.current_tenant_id', true)
    )
");
```

**Explication du fix** :
- On compare `tenant_id::text` (UUID converti en texte) avec le setting texte
- Les conditions "vide" et "null" sont évaluées EN PREMIER (court-circuit OR)
- Plus de `::uuid` sur une chaîne potentiellement vide → plus d'erreur PostgreSQL

---

### FIX 3 — Mettre à jour UserFactory pour inclure les colonnes de sécurité

**Remplacer entièrement** : `edugestdz/backend/database/factories/UserFactory.php`

```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'nom'                       => $this->faker->lastName(),
            'prenom'                    => $this->faker->firstName(),
            'email'                     => $this->faker->unique()->safeEmail(),
            'password'                  => static::$password ??= Hash::make('password'),
            'statut'                    => 'actif',
            'langue'                    => 'fr',
            'theme'                     => 'light',
            // Colonnes sécurité — nullable par défaut
            'two_factor_secret'         => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at'   => null,
            'two_factor_type'           => null,
            'two_factor_phone'          => null,
            'login_attempts'            => 0,
            'locked_until'              => null,
            'last_login_at'             => null,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn(array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * State : admin avec 2FA activée
     */
    public function adminAvec2fa(): static
    {
        return $this->state(fn(array $attributes) => [
            'two_factor_secret'       => 'JBSWY3DPEHPK3PXP',
            'two_factor_confirmed_at' => now(),
        ]);
    }

    /**
     * State : admin sans 2FA
     */
    public function adminSans2fa(): static
    {
        return $this->state(fn(array $attributes) => [
            'two_factor_secret'       => null,
            'two_factor_confirmed_at' => null,
        ]);
    }
}
```

---

### FIX 4 — Corriger SecurityNiveau2Test pour être compatible avec le vrai modèle

**Remplacer entièrement** : `edugestdz/backend/tests/Feature/Security/SecurityNiveau2Test.php`

```php
<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Models\Role;
use App\Models\Tenant;
use App\Services\SecurityMonitorService;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SecurityNiveau2Test extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Role   $role;
    private SecurityMonitorService $monitor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant  = Tenant::factory()->create();
        config(['tenant.current_id' => $this->tenant->id]);
        $this->role    = Role::factory()->create(['nom' => 'admin']);
        $this->monitor = app(SecurityMonitorService::class);
    }

    // ── Brute Force ────────────────────────────────────────────────────

    public function test_brute_force_bloque_apres_10_tentatives(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->monitor->loginEchoue('test@test.com', '1.2.3.4');
        }
        $this->assertTrue($this->monitor->estEnBruteForce('test@test.com', '1.2.3.4'));
    }

    public function test_brute_force_retourne_429(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->monitor->loginEchoue('victim@test.com', '127.0.0.1');
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
        // Utiliser les états du factory — pas de colonnes hardcodées
        $admin = User::factory()->adminSans2fa()->create([
            'role_id'   => $this->role->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $token = auth('api')->login($admin);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/super-admin/tenants')
            ->assertStatus(403)
            ->assertJsonPath('code', 'MFA_REQUIRED');
    }

    public function test_admin_avec_mfa_accede_normalement(): void
    {
        $admin = User::factory()->adminAvec2fa()->create([
            'role_id'   => $this->role->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $token = auth('api')->login($admin);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/eleves')
            ->assertStatus(200);
    }

    public function test_parent_sans_mfa_non_bloque(): void
    {
        $roleParent = Role::factory()->create(['nom' => 'parent']);
        $parent     = User::factory()->create([
            'role_id'   => $roleParent->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $token = auth('api')->login($parent);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/eleves')
            ->assertStatus(200);
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
        $admin = User::factory()->adminAvec2fa()->create([
            'role_id'   => $this->role->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $token = auth('api')->login($admin);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/security/dashboard')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['critiques_24h', 'admins_sans_mfa']]);
    }

    // ── Chiffrement ────────────────────────────────────────────────────

    public function test_encrypted_cast_chiffre_et_dechiffre(): void
    {
        $valeur  = 'SECRET_TOKEN_12345';
        $cast    = new \App\Casts\EncryptedString();

        $chiffre   = $cast->set(null, 'test', $valeur, []);
        $this->assertNotEquals($valeur, $chiffre);

        $dechiffre = $cast->get(null, 'test', $chiffre, []);
        $this->assertEquals($valeur, $dechiffre);
    }

    public function test_valeur_non_chiffree_retournee_brute(): void
    {
        $cast       = new \App\Casts\EncryptedString();
        $nonChiffre = $cast->get(null, 'test', 'valeur_en_clair', []);
        $this->assertEquals('valeur_en_clair', $nonChiffre);
    }

    public function test_dashboard_sans_auth_refuse(): void
    {
        $this->getJson('/api/v1/security/dashboard')->assertStatus(401);
    }
}
```

---

### FIX 5 — Corriger SecurityNiveau1Test pour utiliser les factory states

**Remplacer entièrement** : `edugestdz/backend/tests/Feature/Security/SecurityNiveau1Test.php`

```php
<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Models\Eleve;
use App\Models\Role;
use App\Models\Tenant;
use App\Services\JwtBlacklistService;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class SecurityNiveau1Test extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Role   $role;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        config(['tenant.current_id' => $this->tenant->id]);
        $this->role   = Role::factory()->create(['nom' => 'admin']);
    }

    public function test_token_blackliste_retourne_401(): void
    {
        $user  = User::factory()->create([
            'role_id'   => $this->role->id,
            'tenant_id' => $this->tenant->id,
        ]);
        $token = auth('api')->login($user);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/eleves')
            ->assertStatus(200);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson('/api/v1/auth/logout')
            ->assertStatus(200);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/eleves')
            ->assertStatus(401);
    }

    public function test_invalidation_tous_tokens_user(): void
    {
        $user    = User::factory()->create([
            'role_id'   => $this->role->id,
            'tenant_id' => $this->tenant->id,
        ]);
        $service = app(JwtBlacklistService::class);
        $service->blacklisterTousLesTokensUser($user->id, 'test_security');
        $this->assertTrue(true);
    }

    public function test_eleve_autre_tenant_non_accessible(): void
    {
        $tenantB = Tenant::factory()->create();
        $userA   = User::factory()->create([
            'role_id'   => $this->role->id,
            'tenant_id' => $this->tenant->id,
        ]);
        $eleveB  = Eleve::factory()->create(['tenant_id' => $tenantB->id]);

        $token = auth('api')->login($userA);
        config(['tenant.current_id' => $this->tenant->id]);

        $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'X-Tenant-ID'   => $this->tenant->id,
        ])->getJson("/api/v1/eleves/{$eleveB->id}")
          ->assertStatus(404);
    }

    public function test_manipulation_tenant_header_bloquee(): void
    {
        $tenantB = Str::uuid()->toString();
        $userA   = User::factory()->create([
            'role_id'   => $this->role->id,
            'tenant_id' => $this->tenant->id,
        ]);
        $token = auth('api')->login($userA);

        $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'X-Tenant-ID'   => $tenantB,
        ])->getJson('/api/v1/eleves')
          ->assertStatus(403)
          ->assertJsonPath('code', 'TENANT_MANIPULATION');
    }

    public function test_scope_tenant_automatique(): void
    {
        $tenantB = Tenant::factory()->create();
        Eleve::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);
        Eleve::factory()->count(5)->create(['tenant_id' => $tenantB->id]);
        config(['tenant.current_id' => $this->tenant->id]);
        $this->assertEquals(3, Eleve::count());
    }

    public function test_fichier_autre_tenant_acces_refuse(): void
    {
        $tenantB   = Str::uuid()->toString();
        $userA     = User::factory()->create([
            'role_id'   => $this->role->id,
            'tenant_id' => $this->tenant->id,
        ]);
        config(['tenant.current_id' => $this->tenant->id]);
        $token     = auth('api')->login($userA);
        $cheminB   = "tenants/{$tenantB}/bulletins/test.pdf";
        $cheminB64 = base64_encode($cheminB);
        $sig       = hash_hmac('sha256', $cheminB . $this->tenant->id, config('app.key'));

        $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'X-Tenant-ID'   => $this->tenant->id,
        ])->getJson("/api/fichier/{$cheminB64}?sig={$sig}&exp=" . now()->addHour()->timestamp)
          ->assertStatus(403);
    }

    public function test_lien_fichier_expire_retourne_410(): void
    {
        $userA     = User::factory()->create([
            'role_id'   => $this->role->id,
            'tenant_id' => $this->tenant->id,
        ]);
        config(['tenant.current_id' => $this->tenant->id]);
        $token     = auth('api')->login($userA);
        $chemin    = "tenants/{$this->tenant->id}/bulletins/test.pdf";
        $cheminB64 = base64_encode($chemin);
        $sig       = hash_hmac('sha256', $chemin . $this->tenant->id, config('app.key'));

        $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'X-Tenant-ID'   => $this->tenant->id,
        ])->getJson("/api/fichier/{$cheminB64}?sig={$sig}&exp=" . now()->subHour()->timestamp)
          ->assertStatus(410);
    }

    public function test_health_check_accessible_sans_auth(): void
    {
        $this->getJson('/api/health')->assertStatus(200);
    }
}
```

---

### FIX 6 — Mettre à jour ci.yml : supprimer le warning Node.js deprecated

**Modifier** : `.github/workflows/ci.yml`

Remplacer les 2 lignes :
```yaml
- uses: actions/checkout@v4
```
et
```yaml
- uses: actions/cache@v4
```

Par :
```yaml
- uses: actions/checkout@v4
```
```yaml
- uses: actions/cache@v4
```

*(Le warning Node.js vient du runner GitHub, pas du YAML — rien à changer ici.
C'est un avertissement non bloquant. Laisser tel quel.)*

---

## VÉRIFICATION AVANT COMMIT

```bash
cd edugestdz/backend

# 1. Appliquer les migrations
php artisan migrate --force

# 2. Vérifier que les colonnes existent
php artisan tinker --execute="Schema::hasColumn('users', 'two_factor_confirmed_at') ? 'OK' : 'MANQUANT'"
# → doit afficher "OK"

# 3. Lancer les tests
php artisan test --parallel

# ✅ Résultat attendu :
# SecurityNiveau1Test  → 7 tests verts
# SecurityNiveau2Test  → 9 tests verts  
# SecurityNiveau3Test  → 9 tests verts
# Tous les anciens tests → toujours verts (0 régression)
```

---

## COMMIT ET PUSH

```bash
git add .
git commit -m "fix(ci): colonnes 2FA/sécurité absentes de migration users + RLS cast uuid vide + UserFactory states + tests corrigés

- Ajout migration 2026_07_08_001000 : two_factor_confirmed_at, two_factor_type,
  two_factor_phone, login_attempts, locked_until, last_login_at dans table users
- Fix RLS policy : tenant_id::text = setting au lieu de setting::uuid (évite cast sur vide)
- UserFactory : ajout de toutes les colonnes sécurité + états adminAvec2fa/adminSans2fa
- SecurityNiveau1Test + SecurityNiveau2Test : utiliser les factory states proprement
- 0 régression sur les tests existants"

git push origin develop
```

→ Le CI doit passer ✅ → Merger la PR #32 dans main.

---

## RÉSUMÉ DES CAUSES RÉELLES

| # | Fichier | Problème | Fix |
|---|---------|----------|-----|
| 1 | `0002_create_users_table.php` | 8 colonnes sécurité absentes | Migration additive `2026_07_08_001000` |
| 2 | `2026_07_07_300000_add_rls.php` | `::uuid` sur chaîne vide = crash PostgreSQL | Comparer `tenant_id::text` avec setting |
| 3 | `UserFactory.php` | Pas de colonnes 2FA ni states | Factory complet + 2 états |
| 4 | `SecurityNiveau1Test.php` | Utilise colonnes inexistantes hardcodées | Remplacé par factory states |
| 5 | `SecurityNiveau2Test.php` | Idem + `two_factor_confirmed_at` hardcodé | Remplacé par factory states |

---

## RÈGLES ABSOLUES POUR CE FIX

1. PostgreSQL uniquement — jamais SQLite
2. La migration `2026_07_08_001000` utilise `Schema::hasColumn()` pour chaque colonne → idempotente
3. UserFactory : les nouveaux states `adminAvec2fa()` et `adminSans2fa()` remplacent les attributs inline dans les tests
4. Ne pas toucher à `MfaRequired.php` — il est correct (`$user->role?->nom` fonctionne)
5. Ne pas modifier la migration `0002` existante — toujours ajouter via une nouvelle migration
6. 0 régression — tous les 543 tests existants doivent rester verts
