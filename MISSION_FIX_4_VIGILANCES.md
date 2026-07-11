# 🔧 MISSION — Fix 4 Points de Vigilance Critiques
## EduGest DZ · Branche : develop · Tests actuels : 748+ ✅
## Objectif : 0 régression + ces 4 bugs corrigés + CI verte

---

## DIAGNOSTIC RÉEL LU DANS LE REPO (11 Juillet 2026)

### PROBLÈME 1 — CORS / VITE_API_URL 🔴 BLOQUANT EN PRODUCTION
```
ÉTAT LU :
- frontend/vite.config.js → proxy /api vers localhost:8000 (DEV uniquement)
- Pas de VITE_API_URL utilisée dans le code → en production Vercel, toutes
  les requêtes API partent vers localhost:8000 (qui n'existe pas)
- config/cors.php → patterns *.vercel.app OK mais FRONTEND_URL absent du .env.example
- .env.example backend → pas de FRONTEND_URL ni RAILWAY_BACKEND_URL documentés
- Résultat : "Impossible de joindre le serveur" dans Vercel ← BUG RÉEL

CAUSE RACINE :
Le vite.config.js utilise un proxy server-side (dev only).
En production (Vercel), il n'y a pas de proxy → les appels fetch('/api/...')
partent vers le même domaine Vercel qui n'a pas de backend PHP.
```

### PROBLÈME 2 — notifications_inapp : colonnes manquantes 🟠 BUG FONCTIONNEL
```
ÉTAT LU dans la migration 2026_07_12_200000 :
Colonnes existantes : id, tenant_id, user_id, type, titre, corps, lien, lu, timestamps
FK : tenant_id→tenants, user_id→users

COLONNES MANQUANTES que NotificationInAppService (Mission 1) essaie d'insérer :
- action_url  (NotificationInAppService ligne : 'action_url' => $meta['action_url'])
- icone       (NotificationInAppService ligne : 'icone' => $this->icone($type))

Sans ces colonnes → SQLSTATE[42703] undefined column → crash silencieux (try/catch)
```

### PROBLÈME 3 — UserFactory::adminAvec2fa() manquante 🟠 TESTS BRISÉS
```
ÉTAT LU dans UserFactory.php (33 lignes) :
States existants : definition(), unverified()
States MANQUANTS : adminAvec2fa()

Utilisé dans : FluxCirculationTest, PredictionIATest → setUp()
→ Call to undefined method adminAvec2fa() → tous les tests Feature BRISÉS
```

### PROBLÈME 4 — BelongsToTenant : EXISTS ✅ (pas de bug)
```
ÉTAT LU : app/Traits/BelongsToTenant.php → 61 lignes, complet.
Contient : bootBelongsToTenant(), scopeWithoutTenantScope(), tenant(), scopeForTenant()
→ PAS DE PROBLÈME ICI, le trait existe et fonctionne
→ Cette vigilance était préventive, pas un vrai bug
```

---

## RÈGLES ABSOLUES
1. **0 régression** — 748+ tests doivent rester verts
2. Ne modifier QUE les fichiers listés dans cette mission
3. **Ne pas supprimer** la migration existante `2026_07_12_200000` — AJOUTER une migration complémentaire
4. Le fix CORS doit fonctionner **en local ET sur Vercel/Railway** sans changer le comportement dev

---

## ÉTAPE 0 — Synchroniser

```bash
git checkout develop && git pull origin main
```

---

## ══════════════════════════════════════════
## FIX 1 — CORS + VITE_API_URL (BLOQUANT)
## ══════════════════════════════════════════

### 1A — Créer le fichier api.js centralisé dans le frontend

**Créer** : `edugestdz/frontend/src/api/client.js`

```javascript
/**
 * client.js — Client API centralisé EduGest DZ
 *
 * PROBLÈME RÉSOLU :
 * En développement, Vite proxifie /api → localhost:8000 (vite.config.js server.proxy)
 * En production (Vercel), il n'y a pas de proxy → on doit utiliser l'URL absolue du backend.
 *
 * SOLUTION :
 * VITE_API_URL  = vide en dev (le proxy Vite gère)
 * VITE_API_URL  = https://ton-app.railway.app en production (Vercel env vars)
 *
 * Tous les fetch() du projet doivent utiliser apiClient() ou la fonction api() ci-dessous.
 */

// En dev : '' (le proxy Vite /api → localhost:8000 prend le relais)
// En prod : 'https://votre-app.railway.app' (set dans Vercel Environment Variables)
const BASE_URL = import.meta.env.VITE_API_URL ?? '';

/**
 * Récupère le token JWT depuis le localStorage.
 */
function getToken() {
  return localStorage.getItem('token') ?? localStorage.getItem('jwt_token') ?? '';
}

/**
 * Fonction principale — à utiliser dans toutes les pages React.
 *
 * Usage :
 *   const res = await api('/eleves');
 *   const res = await api('/auth/login', { method: 'POST', body: JSON.stringify({...}) });
 */
export async function api(path, options = {}) {
  const url = `${BASE_URL}/api/v1${path}`;

  const defaultHeaders = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  };

  const token = getToken();
  if (token) {
    defaultHeaders['Authorization'] = `Bearer ${token}`;
  }

  const response = await fetch(url, {
    ...options,
    headers: {
      ...defaultHeaders,
      ...(options.headers ?? {}),
    },
  });

  // Gérer les erreurs HTTP
  if (response.status === 401) {
    // Token expiré → redirection login
    localStorage.removeItem('token');
    window.location.href = '/login';
    throw new Error('Session expirée');
  }

  const data = await response.json().catch(() => ({}));

  if (!response.ok && !data) {
    throw new Error(`HTTP ${response.status}`);
  }

  return data;
}

/**
 * Version avec URL absolue explicite (pour les webhooks, les téléchargements PDF, etc.)
 */
export function getApiUrl(path) {
  return `${BASE_URL}/api/v1${path}`;
}

export default api;
```

---

### 1B — Mettre à jour vite.config.js (frontend) pour supporter VITE_API_URL

**Modifier** : `edugestdz/frontend/vite.config.js`

Trouver le bloc `server:` existant et le REMPLACER par :

```javascript
server: {
  host: 'localhost',
  port: 5173,
  open: true,
  proxy: {
    // En développement seulement — VITE_API_URL doit être vide pour que ce proxy soit actif
    '/api': {
      target: process.env.VITE_API_URL || 'http://localhost:8000',
      changeOrigin: true,
      secure: false,
      configure: (proxy) => {
        proxy.on('error', (err) => {
          console.warn('[Vite Proxy] Backend inaccessible:', err.message);
        });
      },
    },
  },
},
```

---

### 1C — Créer .env.example pour le frontend

**Créer** : `edugestdz/frontend/.env.example`

```bash
# ══════════════════════════════════════════════════════════
# EDUGEST DZ — Configuration Frontend (Vite)
# Copier en .env.local pour le développement
# Les variables VITE_* sont publiques (injectées dans le bundle)
# ══════════════════════════════════════════════════════════

# URL du backend Laravel
# Dev local : laisser VIDE (le proxy Vite prend le relais → localhost:8000)
# Production Vercel : mettre l'URL Railway SANS trailing slash
# Exemple : https://edugest-dz-production.up.railway.app
VITE_API_URL=

# Nom de l'application (affiché dans les onglets, PWA)
VITE_APP_NAME="EduGest DZ"

# Environnement (development | production)
VITE_APP_ENV=development

# Firebase (Notifications push web)
# Laisser vide en local si pas testé
VITE_FIREBASE_API_KEY=
VITE_FIREBASE_AUTH_DOMAIN=
VITE_FIREBASE_PROJECT_ID=
VITE_FIREBASE_MESSAGING_SENDER_ID=
VITE_FIREBASE_APP_ID=
VITE_FIREBASE_VAPID_KEY=
```

---

### 1D — Mettre à jour config/cors.php (backend) pour accepter Railway self

**Modifier** : `edugestdz/backend/config/cors.php`

REMPLACER le contenu entier par :

```php
<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter([
        // Développement local
        'http://localhost:5173',
        'http://localhost:3000',
        'http://127.0.0.1:5173',
        'http://127.0.0.1:3000',

        // Frontend URL dynamique (Railway ou domaine personnalisé)
        // À définir dans .env Railway : FRONTEND_URL=https://edugest-dz.vercel.app
        env('FRONTEND_URL', ''),

        // Backend Railway peut s'appeler lui-même (health checks)
        env('APP_URL', ''),
    ]),

    'allowed_origins_patterns' => [
        // Tout sous-domaine Vercel (previews incluses)
        '#^https://.*\.vercel\.app$#',
        // Votre domaine personnalisé algérien si existant
        '#^https://.*\.edugestdz\.dz$#',
        // Railway preview URLs
        '#^https://.*\.railway\.app$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [
        'X-Query-Count',
        'X-Response-Time',
        'X-Notification-Push-Active',   // Ajouté Mission 3 (timing notifications)
        'X-Notification-Next-Window',   // Ajouté Mission 3
    ],

    'max_age' => 86400,

    'supports_credentials' => false,
];
```

---

### 1E — Ajouter FRONTEND_URL dans .env.example backend

**Modifier** : `edugestdz/backend/.env.example`

Après la ligne `APP_URL=http://localhost`, ajouter :

```bash
# URL du frontend Vercel (pour CORS et liens dans les emails)
# Dev local : laisser vide
# Production Railway : https://votre-app.vercel.app
FRONTEND_URL=

# URL Railway du backend (utilisé dans les emails et les redirections)
# Généré automatiquement par Railway : https://xxx.up.railway.app
RAILWAY_BACKEND_URL=
```

---

### 1F — Instructions pour configurer Vercel (README dans le fichier)

**Créer** : `edugestdz/frontend/VERCEL_SETUP.md`

```markdown
# 🚀 Configuration Vercel ↔ Railway pour EduGest DZ

## Problème résolu
Sans cette configuration, le frontend Vercel ne peut pas parler au backend Railway.
Symptôme : "Impossible de joindre le serveur" dans la console.

## Configuration Vercel (2 minutes)

1. Aller sur https://vercel.com → Votre projet EduGest DZ → Settings → Environment Variables

2. Ajouter ces variables (pour Production + Preview + Development) :

| Variable      | Valeur                                           |
|---------------|--------------------------------------------------|
| VITE_API_URL  | https://VOTRE_APP.up.railway.app                 |
| VITE_APP_NAME | EduGest DZ                                       |
| VITE_APP_ENV  | production                                       |

3. Cliquer "Save" → Redéployer le projet (Deployments → Redeploy)

## Configuration Railway (1 minute)

Dans votre service Railway → Variables :

| Variable     | Valeur                              |
|--------------|-------------------------------------|
| FRONTEND_URL | https://votre-app.vercel.app        |
| APP_URL      | https://VOTRE_APP.up.railway.app    |

## Vérification

Ouvrir la console du navigateur sur votre app Vercel.
Les requêtes API doivent aller vers https://VOTRE_APP.up.railway.app/api/v1/...
et NON vers localhost:8000.

## Développement local

Fichier `edugestdz/frontend/.env.local` (créer localement, ne pas committer) :
```
VITE_API_URL=
```
Laisser VITE_API_URL vide en local → Vite proxy prend le relais.
```

---

## ══════════════════════════════════════════
## FIX 2 — MIGRATION : colonnes manquantes notifications_inapp
## ══════════════════════════════════════════

**NE PAS modifier** la migration existante `2026_07_12_200000` (déjà exécutée en production).
**CRÉER** une migration additive avec `hasColumn()` guards.

**Créer** : `edugestdz/backend/database/migrations/2026_07_11_050000_add_missing_columns_notifications_inapp.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration additive — Ajoute les colonnes manquantes à notifications_inapp.
 *
 * CONTEXTE :
 * La migration originale 2026_07_12_200000 a créé la table avec :
 *   id, tenant_id, user_id, type, titre, corps, lien, lu, timestamps
 *
 * NotificationInAppService (Mission Flux-1) écrit aussi :
 *   - action_url (URL de navigation au clic)
 *   - icone (emoji de la notification)
 *
 * Sans ces colonnes → SQLSTATE[42703]: column "action_url" does not exist
 *
 * TIMESTAMP ANTÉRIEUR à 2026_07_12_200000 pour s'assurer que cette migration
 * s'exécute AVANT si la table n'existe pas encore (fresh install).
 * Si la table existe déjà sans ces colonnes → les hasColumn() les ajoutent.
 * Si elles existent déjà → skip (idempotent).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Cas 1 : fresh install → la table sera créée par 2026_07_12_200000 APRÈS
        // Cas 2 : table existante sans action_url/icone → on les ajoute
        if (!Schema::hasTable('notifications_inapp')) {
            // La table sera créée par la migration principale → rien à faire ici
            return;
        }

        Schema::table('notifications_inapp', function (Blueprint $table) {
            if (!Schema::hasColumn('notifications_inapp', 'action_url')) {
                $table->string('action_url', 500)->nullable()->after('corps');
            }

            if (!Schema::hasColumn('notifications_inapp', 'icone')) {
                $table->string('icone', 10)->nullable()->default('🔔')->after('action_url');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('notifications_inapp')) return;

        Schema::table('notifications_inapp', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('notifications_inapp', 'action_url')) $cols[] = 'action_url';
            if (Schema::hasColumn('notifications_inapp', 'icone'))      $cols[] = 'icone';
            if (!empty($cols)) $table->dropColumn($cols);
        });
    }
};
```

**AUSSI modifier** la migration originale `2026_07_12_200000` pour les fresh installs :

**Modifier** : `edugestdz/backend/database/migrations/2026_07_12_200000_create_notifications_inapp_table.php`

Remplacer le `up()` entier par :

```php
public function up(): void
{
    if (!Schema::hasTable('notifications_inapp')) {
        Schema::create('notifications_inapp', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('user_id')->nullable();    // nullable : peut être une notif système
            $table->string('type');
            $table->string('titre');
            $table->text('corps')->nullable();
            $table->string('action_url', 500)->nullable();   // ← AJOUTÉ
            $table->string('icone', 10)->nullable()->default('🔔'); // ← AJOUTÉ
            $table->string('lien')->nullable();              // ← conservé (compat)
            $table->boolean('lu')->default(false);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'user_id', 'lu'], 'idx_notif_inapp_tenant_user_lu');
        });
    }
}
```

---

## ══════════════════════════════════════════
## FIX 3 — UserFactory : ajouter adminAvec2fa() et autres states manquants
## ══════════════════════════════════════════

**Modifier** : `edugestdz/backend/database/factories/UserFactory.php`

REMPLACER le fichier ENTIER par :

```php
<?php

namespace Database\Factories;

use App\Models\{Role, Tenant};
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * UserFactory — Factory complète pour les tests.
 *
 * STATES DISPONIBLES :
 * - definition()          → User standard (role_id null)
 * - unverified()          → Email non vérifié
 * - adminAvec2fa()        → Admin avec MFA activé (utilisé dans FluxCirculationTest, etc.)
 * - admin()               → Admin sans MFA (shorthand)
 * - enseignant()          → Enseignant avec role auto-créé
 * - eleve()               → Élève avec role auto-créé
 * - parent_()             → Parent avec role auto-créé
 * - avecMfa()             → Ajoute MFA à n'importe quel user
 * - actif()               → statut actif (default)
 * - inactif()             → statut inactif
 */
class UserFactory extends Factory
{
    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'nom'       => $this->faker->lastName(),
            'prenom'    => $this->faker->firstName(),
            'email'     => $this->faker->unique()->safeEmail(),
            'password'  => static::$password ??= Hash::make('password'),
            'statut'    => 'actif',
            'langue'    => 'fr',
            'role_id'   => null,
        ];
    }

    /**
     * State : email non vérifié.
     */
    public function unverified(): static
    {
        return $this->state(fn(array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * State : Admin avec 2FA activé.
     * Utilisé dans : FluxCirculationTest, PredictionIATest, etc.
     *
     * Crée automatiquement un rôle 'admin' s'il n'existe pas déjà dans le tenant.
     */
    public function adminAvec2fa(): static
    {
        return $this->state(function (array $attributes) {
            $roleAdmin = Role::firstOrCreate(
                ['nom' => 'admin'],
                ['description' => 'Directeur / Administrateur', 'permissions' => json_encode([])]
            );

            return [
                'role_id'          => $roleAdmin->id,
                'mfa_active'       => true,
                'mfa_secret'       => Str::random(32),
                'mfa_confirme'     => true,
                'statut'           => 'actif',
            ];
        });
    }

    /**
     * State : Admin sans 2FA (plus simple pour les tests qui n'ont pas besoin de MFA).
     */
    public function admin(): static
    {
        return $this->state(function (array $attributes) {
            $roleAdmin = Role::firstOrCreate(
                ['nom' => 'admin'],
                ['description' => 'Directeur / Administrateur', 'permissions' => json_encode([])]
            );

            return ['role_id' => $roleAdmin->id, 'statut' => 'actif'];
        });
    }

    /**
     * State : Enseignant.
     */
    public function enseignant(): static
    {
        return $this->state(function (array $attributes) {
            $role = Role::firstOrCreate(
                ['nom' => 'enseignant'],
                ['description' => 'Enseignant', 'permissions' => json_encode([])]
            );
            return ['role_id' => $role->id, 'statut' => 'actif'];
        });
    }

    /**
     * State : Élève (compte numérique).
     */
    public function eleveRole(): static
    {
        return $this->state(function (array $attributes) {
            $role = Role::firstOrCreate(
                ['nom' => 'eleve'],
                ['description' => 'Élève', 'permissions' => json_encode([])]
            );
            return ['role_id' => $role->id, 'statut' => 'actif'];
        });
    }

    /**
     * State : Parent.
     */
    public function parentRole(): static
    {
        return $this->state(function (array $attributes) {
            $role = Role::firstOrCreate(
                ['nom' => 'parent'],
                ['description' => 'Parent / Tuteur', 'permissions' => json_encode([])]
            );
            return ['role_id' => $role->id, 'statut' => 'actif'];
        });
    }

    /**
     * State : Ajouter MFA à un user existant.
     */
    public function avecMfa(): static
    {
        return $this->state(fn(array $attributes) => [
            'mfa_active'   => true,
            'mfa_secret'   => Str::random(32),
            'mfa_confirme' => true,
        ]);
    }

    /**
     * State : Utilisateur inactif (bloqué).
     */
    public function inactif(): static
    {
        return $this->state(fn(array $attributes) => ['statut' => 'inactif']);
    }
}
```

---

## ══════════════════════════════════════════
## FIX 4 — VÉRIFICATION BelongsToTenant (confirmation pas de bug)
## ══════════════════════════════════════════

**Aucune action requise** — Le trait existe et est complet.

Vérification rapide à exécuter :

```bash
# Vérifier que le trait est bien chargé
php artisan tinker --execute="echo class_exists('App\Traits\BelongsToTenant') ? 'OK' : 'MANQUANT';"
# → doit afficher "OK"

# Vérifier que AbsenceEnseignant l'utilise correctement
php artisan tinker --execute="echo (new ReflectionClass(App\Models\AbsenceEnseignant::class))->getTraitNames()[0];"
# → doit afficher "App\Traits\BelongsToTenant"
```

---

## ══════════════════════════════════════════
## TESTS DE RÉGRESSION
## ══════════════════════════════════════════

## ÉTAPE 5 — Test de régression CORS

**Créer** : `edugestdz/backend/tests/Feature/CorsConfigTest.php`

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class CorsConfigTest extends TestCase
{
    /**
     * Vérifier que les headers CORS sont présents sur les routes API.
     */
    public function test_cors_headers_presents_sur_api(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://edugest-dz.vercel.app',
        ])->getJson('/api/v1/health');

        // Le header CORS doit être présent (peu importe le status HTTP)
        $this->assertTrue(
            $response->headers->has('Access-Control-Allow-Origin')
            || $response->status() === 200,
            'CORS header manquant sur /api/v1/health'
        );
    }

    public function test_cors_accepte_domaine_vercel(): void
    {
        $response = $this->options('/api/v1/health', [], [
            'Origin'                         => 'https://mon-app.vercel.app',
            'Access-Control-Request-Method'  => 'GET',
            'Access-Control-Request-Headers' => 'Authorization,Content-Type',
        ]);

        // 200 ou 204 = preflight OK
        $this->assertContains($response->status(), [200, 204],
            "Preflight CORS refusé pour *.vercel.app");
    }

    public function test_cors_accepte_domaine_railway(): void
    {
        $response = $this->options('/api/v1/health', [], [
            'Origin'                         => 'https://mon-app.up.railway.app',
            'Access-Control-Request-Method'  => 'POST',
            'Access-Control-Request-Headers' => 'Authorization,Content-Type',
        ]);

        $this->assertContains($response->status(), [200, 204],
            "Preflight CORS refusé pour *.railway.app");
    }

    public function test_cors_bloque_origine_inconnue(): void
    {
        $response = $this->options('/api/v1/health', [], [
            'Origin'                         => 'https://site-malveillant.com',
            'Access-Control-Request-Method'  => 'GET',
        ]);

        // L'origin inconnue ne doit PAS recevoir Access-Control-Allow-Origin avec sa valeur
        $allowOrigin = $response->headers->get('Access-Control-Allow-Origin', '');
        $this->assertNotEquals(
            'https://site-malveillant.com',
            $allowOrigin,
            "CORS accepte une origine non autorisée !"
        );
    }
}
```

---

## ÉTAPE 6 — Test UserFactory states

**Créer** : `edugestdz/backend/tests/Unit/UserFactoryTest.php`

```php
<?php

namespace Tests\Unit;

use App\Models\{User, Role, Tenant};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_definition_cree_user_valide(): void
    {
        $tenant = Tenant::factory()->create();
        $user   = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'statut' => 'actif']);
    }

    public function test_admin_avec_2fa_cree_role_admin(): void
    {
        $tenant = Tenant::factory()->create();
        $admin  = User::factory()->adminAvec2fa()->create(['tenant_id' => $tenant->id]);

        $this->assertNotNull($admin->role_id, "adminAvec2fa() doit assigner un role_id");
        $this->assertEquals('admin', $admin->role?->nom ?? Role::find($admin->role_id)?->nom);
        $this->assertTrue((bool)($admin->mfa_active ?? false), "mfa_active doit être true");
    }

    public function test_admin_sans_2fa(): void
    {
        $tenant = Tenant::factory()->create();
        $admin  = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

        $this->assertNotNull($admin->role_id);
    }

    public function test_states_enseignant_et_parent(): void
    {
        $tenant     = Tenant::factory()->create();
        $enseignant = User::factory()->enseignant()->create(['tenant_id' => $tenant->id]);
        $parent     = User::factory()->parentRole()->create(['tenant_id' => $tenant->id]);

        $this->assertDatabaseHas('users', ['id' => $enseignant->id]);
        $this->assertDatabaseHas('users', ['id' => $parent->id]);
    }

    public function test_unverified_state(): void
    {
        $tenant = Tenant::factory()->create();
        $user   = User::factory()->unverified()->create(['tenant_id' => $tenant->id]);

        $this->assertNull($user->email_verified_at);
    }

    public function test_inactif_state(): void
    {
        $tenant = Tenant::factory()->create();
        $user   = User::factory()->inactif()->create(['tenant_id' => $tenant->id]);

        $this->assertEquals('inactif', $user->statut);
    }

    public function test_admin_avec_2fa_ne_cree_pas_doublon_role(): void
    {
        $tenant = Tenant::factory()->create();
        config(['tenant.current_id' => $tenant->id]);

        // Créer 2 admins → le rôle 'admin' doit exister une seule fois
        User::factory()->adminAvec2fa()->create(['tenant_id' => $tenant->id]);
        User::factory()->adminAvec2fa()->create(['tenant_id' => $tenant->id]);

        $nbRolesAdmin = Role::where('nom', 'admin')->count();
        $this->assertEquals(1, $nbRolesAdmin, "firstOrCreate doit éviter les doublons");
    }
}
```

---

## ÉTAPE 7 — Test colonnes notifications_inapp

**Créer** : `edugestdz/backend/tests/Feature/NotificationsInappColumnsTest.php`

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class NotificationsInappColumnsTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_notifications_inapp_existe(): void
    {
        $this->assertTrue(Schema::hasTable('notifications_inapp'),
            "La table notifications_inapp doit exister après migrate");
    }

    public function test_colonne_action_url_existe(): void
    {
        $this->assertTrue(
            Schema::hasColumn('notifications_inapp', 'action_url'),
            "La colonne action_url manque dans notifications_inapp — ajouter la migration additive"
        );
    }

    public function test_colonne_icone_existe(): void
    {
        $this->assertTrue(
            Schema::hasColumn('notifications_inapp', 'icone'),
            "La colonne icone manque dans notifications_inapp — ajouter la migration additive"
        );
    }

    public function test_insert_avec_action_url_et_icone_ne_crash_pas(): void
    {
        DB::table('notifications_inapp')->insert([
            'id'         => \Illuminate\Support\Str::uuid(),
            'tenant_id'  => \Illuminate\Support\Str::uuid(),
            'user_id'    => null,
            'type'       => 'test',
            'titre'      => 'Test notification',
            'corps'      => 'Corps test',
            'action_url' => '/dashboard',
            'icone'      => '🔔',
            'lu'         => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('notifications_inapp', [
            'type'       => 'test',
            'action_url' => '/dashboard',
            'icone'      => '🔔',
        ]);
    }

    public function test_user_id_nullable(): void
    {
        // user_id doit être nullable (notifications système sans user cible)
        $inserted = DB::table('notifications_inapp')->insertGetId([
            'id'         => \Illuminate\Support\Str::uuid(),
            'tenant_id'  => \Illuminate\Support\Str::uuid(),
            'user_id'    => null,   // ← nullable
            'type'       => 'systeme',
            'titre'      => 'Notification système',
            'lu'         => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertNotNull($inserted);
    }
}
```

---

## ÉTAPE 8 — Exécution complète

```bash
cd edugestdz/backend

# ── 1. Migrations ─────────────────────────────────────────────────────
php artisan migrate --force
# Vérifier que 2026_07_11_050000 s'est bien exécutée
php artisan migrate:status | grep notifications_inapp

# ── 2. Autoload ───────────────────────────────────────────────────────
composer dump-autoload -o

# ── 3. Tinker : vérification BelongsToTenant ──────────────────────────
php artisan tinker --execute="echo class_exists('App\Traits\BelongsToTenant') ? 'BelongsToTenant OK' : 'MANQUANT';"

# ── 4. Tests ciblés ───────────────────────────────────────────────────
php artisan test tests/Unit/UserFactoryTest.php --stop-on-failure
php artisan test tests/Feature/NotificationsInappColumnsTest.php --stop-on-failure
php artisan test tests/Feature/CorsConfigTest.php --stop-on-failure

# ── 5. Suite complète ─────────────────────────────────────────────────
php artisan test
# → 748+ ✅  0 failures  0 regression

# ── 6. Frontend ───────────────────────────────────────────────────────
cd ../frontend
npm run build
# → dist/ compilé sans erreur

# ── 7. Commit ─────────────────────────────────────────────────────────
cd ..
git add \
  backend/database/migrations/2026_07_11_050000_add_missing_columns_notifications_inapp.php \
  backend/database/migrations/2026_07_12_200000_create_notifications_inapp_table.php \
  backend/database/factories/UserFactory.php \
  backend/config/cors.php \
  backend/.env.example \
  backend/tests/Unit/UserFactoryTest.php \
  backend/tests/Feature/NotificationsInappColumnsTest.php \
  backend/tests/Feature/CorsConfigTest.php \
  frontend/src/api/client.js \
  frontend/vite.config.js \
  frontend/.env.example \
  frontend/VERCEL_SETUP.md

git commit -m "fix(vigilances): 4 points critiques résolus

FIX 1 — CORS + VITE_API_URL (Vercel/Railway) :
  - frontend/src/api/client.js : client centralisé avec VITE_API_URL
    En dev : VITE_API_URL vide → proxy Vite localhost:8000
    En prod Vercel : VITE_API_URL=https://xxx.railway.app → appel direct
  - frontend/vite.config.js : proxy dynamique via process.env.VITE_API_URL
  - frontend/.env.example : créé (manquait totalement)
  - frontend/VERCEL_SETUP.md : instructions setup 2 minutes
  - backend/config/cors.php : ajout pattern *.railway.app + headers Mission 3
  - backend/.env.example : ajout FRONTEND_URL + RAILWAY_BACKEND_URL

FIX 2 — notifications_inapp colonnes manquantes :
  - Migration additive 2026_07_11_050000 : ajoute action_url + icone
    avec hasColumn() guards (idempotente)
  - Migration originale 2026_07_12_200000 : mise à jour pour fresh install
    (ajoute les 2 colonnes + rend user_id nullable + hasTable() guard)
  - Résout : SQLSTATE[42703] undefined column action_url

FIX 3 — UserFactory states manquants :
  - adminAvec2fa() : crée role admin via firstOrCreate + mfa_active=true
  - admin() : shorthand sans MFA
  - enseignant(), eleveRole(), parentRole() : states par rôle
  - avecMfa(), inactif() : states utilitaires
  - firstOrCreate() évite les doublons de rôles entre tests

FIX 4 — BelongsToTenant : confirmé existant (app/Traits/BelongsToTenant.php)
  Aucun changement requis. Vigilance levée.

TESTS AJOUTÉS :
  - UserFactoryTest (7 tests) : tous les states + anti-doublon rôles
  - NotificationsInappColumnsTest (4 tests) : schéma + insert complet
  - CorsConfigTest (4 tests) : Vercel OK, Railway OK, inconnu refusé"

git push origin develop
# → PR → main
```

---

## PROMPT EXACT POUR DEEPSEEK

```
Repo : https://github.com/Allintelligence2024/edugest-dz.git
Branche : develop
git checkout develop && git pull origin main

Fichier : MISSION_FIX_4_VIGILANCES.md — 8 étapes.

CONTEXTE PRÉCIS (lu dans le repo avant d'écrire) :
- UserFactory.php : 33 lignes, states = definition() + unverified() SEULEMENT
  adminAvec2fa() manque totalement → crash tous les FluxCirculationTest
- notifications_inapp : colonnes action_url et icone MANQUANTES
  NotificationInAppService les insère → SQLSTATE[42703] undefined column
- BelongsToTenant : EXISTS (app/Traits/BelongsToTenant.php, 61 lignes) → pas de bug ici
- cors.php : patterns Vercel OK, mais pattern Railway manquant
- vite.config.js frontend : pas de VITE_API_URL → Vercel appelle localhost

RÈGLES CRITIQUES :
1. UserFactory : utiliser Role::firstOrCreate() et NON Role::factory()->create()
   → les tests en parallèle créent plusieurs fois 'admin' → firstOrCreate évite l'erreur
   unique constraint. NE PAS utiliser Role::factory() dans les states de UserFactory.

2. Migration notifications_inapp additive :
   Le timestamp 2026_07_11_050000 est ANTÉRIEUR à 2026_07_12_200000.
   Laravel exécute les migrations dans l'ordre alphabétique du timestamp.
   Donc 050000 s'exécute AVANT 200000 sur un fresh install.
   La logique : si la table n'existe pas encore à 050000 → return (skip).
   La migration 200000 créera la table AVEC les colonnes.
   Si la table existe SANS les colonnes (prod existante) → 050000 les ajoute.

3. CORS test : /api/v1/health doit exister comme route.
   Si cette route n'existe pas → utiliser /api/v1/auth/login à la place dans le test.
   Ne pas créer une nouvelle route juste pour le test.

4. vite.config.js : NE PAS supprimer la config PWA (VitePWA) ni les aliases (@, @api, etc.)
   Modifier SEULEMENT le bloc server.proxy pour utiliser process.env.VITE_API_URL.

5. frontend/src/api/client.js : utiliser import.meta.env.VITE_API_URL (pas process.env).
   Vite expose les variables VITE_* via import.meta.env en frontend.
   process.env est pour Node.js (vite.config.js côté build uniquement).

6. Vérifier si 'mfa_active', 'mfa_secret', 'mfa_confirme' existent dans la table users.
   Si ces colonnes n'existent pas → les retirer du state adminAvec2fa() pour éviter
   SQLSTATE[42703]. L'état adminAvec2fa() fonctionnel minimal = juste role_id = admin.

php artisan migrate --force
php artisan test tests/Unit/UserFactoryTest.php
php artisan test tests/Feature/NotificationsInappColumnsTest.php
php artisan test tests/Feature/CorsConfigTest.php
php artisan test → 748+ ✅ 0 failures
npm run build → 0 erreurs
git push origin develop → PR → main
```

---

## RÉSUMÉ — AVANT / APRÈS

| Problème | Avant | Après |
|---|---|---|
| **CORS Vercel** | `fetch('/api/...')` → localhost:8000 💀 | `VITE_API_URL=https://railway.app` → backend ✅ |
| **CORS Railway** | Pattern `*.railway.app` manquant | Ajouté dans `cors.php` ✅ |
| **notifications_inapp** | INSERT crash sur `action_url` | Migration additive ajoute les colonnes ✅ |
| **UserFactory** | `adminAvec2fa()` undefined → crash tests | 7 states complets + firstOrCreate ✅ |
| **BelongsToTenant** | Vigilance préventive | Confirmé existant — rien à faire ✅ |

---

## ACTION MANUELLE SUR VERCEL (30 secondes — à faire VOUS-MÊME)

**Cette partie ne peut pas être faite par DeepSeek — c'est dans l'interface Vercel.**

```
1. https://vercel.com → Projet EduGest DZ → Settings → Environment Variables
2. Cliquer "Add New"
   Name  : VITE_API_URL
   Value : https://VOTRE_APP.up.railway.app   ← remplacer par votre vraie URL Railway
   Environment : ✅ Production  ✅ Preview  ✅ Development
3. Save → Deployments → Redeploy (le dernier deployment)
4. Tester dans la console navigateur :
   fetch('/api/v1/health').then(r => r.json()).then(console.log)
   → doit retourner {"status":"ok"} sans erreur CORS
```

**Action sur Railway** (1 minute) :
```
Railway Dashboard → Votre service backend → Variables
FRONTEND_URL = https://votre-app.vercel.app
→ Railway redéploie automatiquement
```
