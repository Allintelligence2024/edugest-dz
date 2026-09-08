# 🤖 MISSION DEEPSEEK — Priorité 6 : Paiement CIB/Dahabia (Satim)
## EduGest DZ · Branche : develop · 1er Juillet 2026
## Tests actuels : 301+ ✅ · Objectif : 315+ ✅

---

## CONTEXTE EXACT

### Ce qui EXISTE déjà (ne pas recréer)
- `app/Services/Paiement/SatimGateway.php` ✅ complet
  - `registerOrder()` → initie un paiement, retourne `form_url` + `order_id`
  - `getOrderStatus()` → vérifie le statut (2 = payé)
  - `confirmOrder()` → confirme la commande
  - Mode **sandbox** automatique si `SATIM_SANDBOX=true` en env
- `app/Http/Controllers/Api/V1/PaiementEnLigneController.php` ✅ complet
  - `initier()` → `POST /api/v1/paiements/online/initier`
  - `retour()` → `GET /api/v1/paiements/online/retour` (redirect navigateur)
  - `callback()` → `POST /api/v1/paiements/online/callback` (IPN serveur)
- `app/Models/Paiement.php` → champs `reference_trans`, `order_id`, `statut`, `raw_payload`
- `app/Models/Facture.php` → `statut` géré (payée/partiellement_payée)
- Routes `/api/v1/paiements/online/*` ✅ déclarées

### Ce qui MANQUE — à créer
```
1. config/satim.php                 ← config Satim (terminal, merchant, password, url)
2. .env.example                     ← variables Satim à documenter
3. Migration                        ← colonnes manquantes sur table paiements
4. PaiementEnLigneController.php    ← ajouter : dashboard + remboursement + statut manuel
5. Tests                            ← PaiementEnLigneTest.php
6. Notification SMS/email           ← confirmation paiement au parent
```

### Situation réelle Satim en Algérie
- **Sandbox** : disponible sur `https://test.satim.dz` — pas besoin de contrat
- **Production** : nécessite contrat commercial avec Satim + agrément Banque d'Algérie
- **Stratégie** : tout développer en mode sandbox maintenant, basculer en prod quand le contrat est signé

---

## ÉTAPE 1 — Fichier de configuration Satim

**Créer :** `edugestdz/backend/config/satim.php`

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Configuration Satim (CIB / Dahabia)
    | Banque d'Algérie — Système de Télépaiement Interbancaire Monétique
    |--------------------------------------------------------------------------
    */

    // Mode sandbox (test sans vrai paiement)
    'sandbox' => (bool) env('SATIM_SANDBOX', true),

    // URL de la passerelle
    'url' => env('SATIM_URL', 'https://test.satim.dz/payment/rest'),

    // Identifiants fournis par Satim après signature du contrat
    'terminal_id'  => env('SATIM_TERMINAL_ID', ''),
    'merchant_id'  => env('SATIM_MERCHANT_ID', ''),
    'password'     => env('SATIM_PASSWORD', ''),

    // Devise (toujours DZD)
    'currency' => 'DZD',

    // Langue de la page de paiement
    'language' => env('SATIM_LANGUAGE', 'fr'),

    // URLs de callback (automatiquement construites depuis APP_URL)
    'retour_url'   => env('SATIM_RETOUR_URL',   env('APP_URL', 'http://localhost') . '/api/v1/paiements/online/retour'),
    'callback_url' => env('SATIM_CALLBACK_URL', env('APP_URL', 'http://localhost') . '/api/v1/paiements/online/callback'),

    // Délai d'expiration d'un order en minutes
    'order_expiry_minutes' => (int) env('SATIM_ORDER_EXPIRY', 30),
];
```

---

## ÉTAPE 2 — Documenter les variables dans .env.example

**Modifier :** `edugestdz/backend/.env.example`

Ajouter à la fin du fichier :

```env
# ─────────────────────────────────────────────────
# SATIM — Paiement CIB / Dahabia (Banque d'Algérie)
# ─────────────────────────────────────────────────
# Mode sandbox = true pour les tests, false pour la production
SATIM_SANDBOX=true

# URL passerelle Satim
# Sandbox : https://test.satim.dz/payment/rest
# Production : https://satim.dz/payment/rest
SATIM_URL=https://test.satim.dz/payment/rest

# Identifiants fournis par Satim après signature du contrat
SATIM_TERMINAL_ID=
SATIM_MERCHANT_ID=
SATIM_PASSWORD=

# Optionnel — URLs de callback (auto-construites depuis APP_URL si vides)
SATIM_RETOUR_URL=
SATIM_CALLBACK_URL=

# Langue page paiement : fr | ar | en
SATIM_LANGUAGE=fr

# Délai d'expiration d'un paiement en minutes
SATIM_ORDER_EXPIRY=30
```

---

## ÉTAPE 3 — Migration colonnes manquantes sur paiements

**Créer :** `edugestdz/backend/database/migrations/2026_07_01_200000_add_online_payment_columns_to_paiements.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            // Colonnes paiement en ligne (ajout si elles n'existent pas déjà)
            if (!Schema::hasColumn('paiements', 'reference_trans')) {
                $table->string('reference_trans', 50)->nullable()->after('statut');
            }
            if (!Schema::hasColumn('paiements', 'order_id')) {
                $table->string('order_id', 100)->nullable()->after('reference_trans');
            }
            if (!Schema::hasColumn('paiements', 'raw_payload')) {
                $table->jsonb('raw_payload')->nullable()->after('order_id');
            }
            if (!Schema::hasColumn('paiements', 'mode')) {
                $table->enum('mode', ['cash', 'en_ligne', 'virement', 'cheque'])
                      ->default('cash')->after('raw_payload');
            }
            if (!Schema::hasColumn('paiements', 'type_paiement')) {
                $table->enum('type_paiement', ['cib', 'dahabia', 'baridimob', 'cash', 'virement', 'cheque'])
                      ->nullable()->after('mode');
            }
            if (!Schema::hasColumn('paiements', 'recu_par')) {
                $table->uuid('recu_par')->nullable()->after('type_paiement');
            }
            if (!Schema::hasColumn('paiements', 'rembourse_le')) {
                $table->timestamp('rembourse_le')->nullable()->after('recu_par');
            }
            if (!Schema::hasColumn('paiements', 'motif_remboursement')) {
                $table->string('motif_remboursement', 300)->nullable()->after('rembourse_le');
            }
        });
    }

    public function down(): void
    {
        Schema::table('paiements', function (Blueprint $table) {
            $cols = ['reference_trans', 'order_id', 'raw_payload', 'mode', 'type_paiement', 'recu_par', 'rembourse_le', 'motif_remboursement'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('paiements', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
```

---

## ÉTAPE 4 — Enrichir le modèle Paiement

**Modifier :** `edugestdz/backend/app/Models/Paiement.php`

Remplacer le contenu entier par :

```php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Paiement extends BaseModel
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'facture_id', 'eleve_id',
        'montant', 'mode_paiement', 'date_paiement', 'notes',
        'statut', 'recu_par',
        // Paiement en ligne
        'reference_trans', 'order_id', 'raw_payload',
        'mode', 'type_paiement',
        // Remboursement
        'rembourse_le', 'motif_remboursement',
    ];

    protected $casts = [
        'date_paiement'  => 'date',
        'montant'        => 'decimal:2',
        'raw_payload'    => 'array',
        'rembourse_le'   => 'datetime',
    ];

    public function facture(): BelongsTo
    {
        return $this->belongsTo(Facture::class);
    }

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    public function recuPar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recu_par');
    }

    public function getEstEnLigneAttribute(): bool
    {
        return $this->mode === 'en_ligne';
    }

    public function getEstRembourseAttribute(): bool
    {
        return $this->statut === 'remboursé';
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type_paiement ?? $this->mode_paiement) {
            'cib'       => 'CIB (Carte Interbancaire)',
            'dahabia'   => 'Dahabia (Algérie Poste)',
            'baridimob' => 'BaridiMob',
            'cash'      => 'Espèces',
            'virement'  => 'Virement bancaire',
            'cheque'    => 'Chèque',
            default     => ucfirst($this->mode_paiement ?? ''),
        };
    }

    public function scopeEnLigne($query)
    {
        return $query->where('mode', 'en_ligne');
    }

    public function scopeConfirmes($query)
    {
        return $query->where('statut', 'confirmé');
    }
}
```

---

## ÉTAPE 5 — Ajouter méthodes au PaiementEnLigneController

**Modifier :** `edugestdz/backend/app/Http/Controllers/Api/V1/PaiementEnLigneController.php`

Ajouter ces 3 méthodes à la fin de la classe (avant la dernière `}`) :

```php
/**
 * GET /api/v1/paiements/online/dashboard
 * Tableau de bord des paiements en ligne.
 */
public function dashboard(Request $request): JsonResponse
{
    $mois  = (int) ($request->mois  ?? now()->month);
    $annee = (int) ($request->annee ?? now()->year);

    $paiementsEnLigne = Paiement::enLigne()
        ->whereMonth('date_paiement', $mois)
        ->whereYear('date_paiement', $annee)
        ->get();

    $stats = [
        'total_transactions' => $paiementsEnLigne->count(),
        'confirmes'         => $paiementsEnLigne->where('statut', 'confirmé')->count(),
        'en_attente'        => $paiementsEnLigne->where('statut', 'en_attente')->count(),
        'annules'           => $paiementsEnLigne->where('statut', 'annulé')->count(),
        'rembourses'        => $paiementsEnLigne->where('statut', 'remboursé')->count(),
        'montant_total'     => (float) $paiementsEnLigne->where('statut', 'confirmé')->sum('montant'),
        'par_type'          => $paiementsEnLigne->where('statut', 'confirmé')
            ->groupBy('type_paiement')
            ->map(fn($g) => ['count' => $g->count(), 'montant' => (float) $g->sum('montant')]),
        'sandbox_actif'     => $this->satim->isSandbox(),
    ];

    $derniersPayments = Paiement::enLigne()
        ->with('facture:id,numero_facture', 'eleve:id,nom,prenom')
        ->orderByDesc('created_at')
        ->limit(10)
        ->get();

    return response()->json([
        'success' => true,
        'data'    => [
            'periode'          => compact('mois', 'annee'),
            'stats'            => $stats,
            'derniers_paiements'=> $derniersPayments,
        ],
    ]);
}

/**
 * GET /api/v1/paiements/online/{id}/statut
 * Vérifier manuellement le statut d'un paiement en ligne.
 */
public function verifierStatut(string $id): JsonResponse
{
    $paiement = Paiement::with(['facture:id,numero_facture,total_ttc', 'eleve:id,nom,prenom'])
        ->findOrFail($id);

    if (!$paiement->order_id) {
        return response()->json([
            'success' => false,
            'error'   => ['code' => 'NO_ORDER_ID', 'message' => 'Ce paiement ne possède pas d\'order_id Satim'],
        ], 422);
    }

    $statut = $this->satim->getOrderStatus($paiement->order_id);

    // Si confirmé côté Satim mais pas encore dans notre BDD
    if ($statut['success'] && ($statut['order_status'] ?? null) === 2 && $paiement->statut !== 'confirmé') {
        $paiement->update([
            'statut'      => 'confirmé',
            'raw_payload' => array_merge($paiement->raw_payload ?? [], $statut),
        ]);
        $this->finaliserFacture($paiement);
    }

    return response()->json([
        'success' => true,
        'data'    => [
            'paiement'        => $paiement->fresh(),
            'satim_response'  => $statut,
            'statut_satim'    => match ($statut['order_status'] ?? null) {
                0       => 'Enregistré (non payé)',
                1       => 'Pré-autorisé',
                2       => 'Payé et confirmé',
                3       => 'Autorisé',
                4       => 'Remboursé',
                5       => 'ACS demandé',
                6       => 'Refusé',
                default => 'Inconnu',
            },
        ],
    ]);
}

/**
 * POST /api/v1/paiements/online/{id}/rembourser
 * Initier un remboursement (admin uniquement).
 */
public function rembourser(Request $request, string $id): JsonResponse
{
    $validated = $request->validate([
        'motif'   => 'required|string|max:300',
        'montant' => 'nullable|numeric|min:1', // remboursement partiel possible
    ]);

    $paiement = Paiement::with('facture')->findOrFail($id);

    if ($paiement->statut !== 'confirmé') {
        return response()->json([
            'success' => false,
            'error'   => ['code' => 'NOT_CONFIRMED', 'message' => 'Seuls les paiements confirmés peuvent être remboursés'],
        ], 422);
    }

    // En sandbox : simuler le remboursement
    // En production : appeler l'API Satim de remboursement (reversalOrder.do)
    if (!$this->satim->isSandbox() && $paiement->order_id) {
        $result = \Illuminate\Support\Facades\Http::timeout(15)->post(
            config('satim.url') . '/reversalOrder.do',
            [
                'userName' => config('satim.merchant_id'),
                'password' => config('satim.password'),
                'orderId'  => $paiement->order_id,
                'language' => 'fr',
            ]
        );
        if (!$result->successful() || ($result->json()['errorCode'] ?? '1') !== '0') {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'REFUND_FAILED', 'message' => 'Remboursement refusé par Satim'],
            ], 502);
        }
    }

    $paiement->update([
        'statut'              => 'remboursé',
        'rembourse_le'        => now(),
        'motif_remboursement' => $validated['motif'],
    ]);

    // Remettre la facture en statut "émise"
    if ($paiement->facture) {
        $paiement->facture->update(['statut' => 'émise']);
    }

    \Illuminate\Support\Facades\Log::info('[Satim] Remboursement effectué', [
        'paiement_id' => $paiement->id,
        'motif'       => $validated['motif'],
        'sandbox'     => $this->satim->isSandbox(),
    ]);

    return response()->json([
        'success' => true,
        'data'    => $paiement->fresh('facture'),
        'message' => 'Remboursement effectué avec succès',
    ]);
}
```

---

## ÉTAPE 6 — Ajouter routes paiement en ligne

**Modifier :** `edugestdz/backend/routes/api.php`

Dans le groupe `auth:api`, ajouter après le bloc paiements existant :

```php
// ── Paiement en ligne — Dashboard & Gestion (auth requis) ──
Route::prefix('paiements/online')->group(function () {
    Route::get('dashboard',        [\App\Http\Controllers\Api\V1\PaiementEnLigneController::class, 'dashboard']);
    Route::get('{id}/statut',      [\App\Http\Controllers\Api\V1\PaiementEnLigneController::class, 'verifierStatut']);
    Route::post('{id}/rembourser', [\App\Http\Controllers\Api\V1\PaiementEnLigneController::class, 'rembourser']);
});
```

---

## ÉTAPE 7 — Notification SMS confirmation paiement

**Modifier :** `edugestdz/backend/app/Http/Controllers/Api/V1/PaiementEnLigneController.php`

Dans la méthode privée `finaliserFacture()`, ajouter la notification SMS après la mise à jour du statut :

```php
private function finaliserFacture(Paiement $paiement): void
{
    $facture = $paiement->facture;
    if (!$facture) return;

    $totalPaye = $facture->paiements()
        ->where('statut', 'confirmé')
        ->sum('montant');

    if ($totalPaye >= $facture->total_ttc) {
        $facture->update(['statut' => 'payée']);
    } elseif ($totalPaye > 0) {
        $facture->update(['statut' => 'partiellement_payée']);
    }

    // ── Notification SMS au parent ──
    try {
        $eleve = $paiement->eleve?->load('parents');
        if ($eleve) {
            $parent = $eleve->parents->first();
            if ($parent?->telephone_1) {
                $montantFormate = number_format($paiement->montant, 2, ',', ' ');
                $typeLabel      = $paiement->type_label;
                $message = "EduGest DZ : Paiement {$typeLabel} de {$montantFormate} DA "
                         . "reçu pour {$eleve->prenom} {$eleve->nom}. "
                         . "Facture N° {$facture->numero_facture}. Merci.";

                app(\App\Services\Sms\SmsService::class)->send($parent->telephone_1, $message);
            }
        }
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::error('[Satim] SMS confirmation échoué', [
            'paiement_id' => $paiement->id,
            'error'       => $e->getMessage(),
        ]);
    }
}
```

---

## ÉTAPE 8 — Tests

**Créer :** `edugestdz/backend/tests/Feature/Api/PaiementEnLigneTest.php`

```php
<?php

namespace Tests\Feature\Api;

use App\Models\Eleve;
use App\Models\Facture;
use App\Models\Paiement;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class PaiementEnLigneTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private string $token;
    private Eleve  $eleve;
    private Facture $facture;

    protected function setUp(): void
    {
        parent::setUp();

        $role         = Role::factory()->create(['nom' => 'admin']);
        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);
        $admin        = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id'   => $role->id,
            'statut'    => 'actif',
        ]);
        $this->token = auth('api')->login($admin);
        config(['tenant.current_id' => $this->tenant->id]);

        $this->eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->facture = Facture::factory()->create([
            'tenant_id' => $this->tenant->id,
            'eleve_id'  => $this->eleve->id,
            'total_ttc' => 5000,
            'statut'    => 'émise',
        ]);

        // Forcer le mode sandbox pour les tests
        Config::set('satim.sandbox', true);
    }

    // ─── INITIER PAIEMENT ────────────────────────────────

    public function test_initier_paiement_cib_sandbox(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/paiements/online/initier', [
                'facture_id'   => $this->facture->id,
                'type_paiement'=> 'cib',
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['paiement', 'redirect_url', 'order_id']]);

        // En sandbox, redirect_url contient l'URL de retour
        $this->assertStringContainsString('retour', $response->json('data.redirect_url'));

        // Paiement créé en BDD
        $this->assertDatabaseHas('paiements', [
            'facture_id'   => $this->facture->id,
            'statut'       => 'en_attente',
            'mode'         => 'en_ligne',
            'type_paiement'=> 'cib',
        ]);
    }

    public function test_initier_paiement_dahabia_sandbox(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/paiements/online/initier', [
                'facture_id'   => $this->facture->id,
                'type_paiement'=> 'dahabia',
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_initier_paiement_baridimob_retourne_reference(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/paiements/online/initier', [
                'facture_id'   => $this->facture->id,
                'type_paiement'=> 'baridimob',
            ])
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['paiement', 'reference', 'montant', 'instructions']]);

        $this->assertNotEmpty($response->json('data.reference'));
    }

    public function test_initier_facture_deja_payee_bloque(): void
    {
        $this->facture->update(['statut' => 'payée']);

        $this->withToken($this->token)
            ->postJson('/api/v1/paiements/online/initier', [
                'facture_id'   => $this->facture->id,
                'type_paiement'=> 'cib',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'E004');
    }

    public function test_initier_facture_autre_tenant_bloque(): void
    {
        $autreTenant  = Tenant::factory()->create();
        $autreEleve   = Eleve::factory()->create(['tenant_id' => $autreTenant->id]);
        $autreFacture = Facture::factory()->create([
            'tenant_id' => $autreTenant->id,
            'eleve_id'  => $autreEleve->id,
            'total_ttc' => 3000,
            'statut'    => 'émise',
        ]);

        $this->withToken($this->token)
            ->postJson('/api/v1/paiements/online/initier', [
                'facture_id'   => $autreFacture->id,
                'type_paiement'=> 'cib',
            ])
            ->assertStatus(422); // validation échoue : facture_id n'existe pas dans ce tenant
    }

    // ─── RETOUR SATIM ─────────────────────────────────────

    public function test_retour_sandbox_confirme_paiement(): void
    {
        // Créer un paiement en_attente avec un order_id
        $paiement = Paiement::create([
            'tenant_id'      => $this->tenant->id,
            'facture_id'     => $this->facture->id,
            'eleve_id'       => $this->eleve->id,
            'montant'        => 5000,
            'mode_paiement'  => 'cib',
            'mode'           => 'en_ligne',
            'type_paiement'  => 'cib',
            'reference_trans'=> 'PAY-TEST123456',
            'order_id'       => 'SANDBOX_TEST001',
            'statut'         => 'en_attente',
            'date_paiement'  => now(),
        ]);

        // Simuler le retour Satim sandbox (order_status=2)
        $this->getJson('/api/v1/paiements/online/retour?reference=PAY-TEST123456&satim_order_id=SANDBOX_TEST001')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        // Vérifier que le paiement est confirmé
        $this->assertDatabaseHas('paiements', [
            'id'     => $paiement->id,
            'statut' => 'confirmé',
        ]);

        // Vérifier que la facture est mise à jour
        $this->assertDatabaseHas('factures', [
            'id'     => $this->facture->id,
            'statut' => 'payée',
        ]);
    }

    public function test_retour_echec_annule_paiement(): void
    {
        $paiement = Paiement::create([
            'tenant_id'      => $this->tenant->id,
            'facture_id'     => $this->facture->id,
            'eleve_id'       => $this->eleve->id,
            'montant'        => 5000,
            'mode_paiement'  => 'cib',
            'mode'           => 'en_ligne',
            'type_paiement'  => 'cib',
            'reference_trans'=> 'PAY-ECHEC001',
            'order_id'       => null,
            'statut'         => 'en_attente',
            'date_paiement'  => now(),
        ]);

        $this->getJson('/api/v1/paiements/online/retour?reference=PAY-ECHEC001&echec=1')
            ->assertStatus(200)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'PAYMENT_CANCELLED');

        $this->assertDatabaseHas('paiements', [
            'id'     => $paiement->id,
            'statut' => 'annulé',
        ]);
    }

    // ─── DASHBOARD ───────────────────────────────────────

    public function test_dashboard_paiements_en_ligne(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/paiements/online/dashboard')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['periode', 'stats' => ['total_transactions', 'confirmes', 'montant_total', 'sandbox_actif']],
            ]);
    }

    public function test_dashboard_affiche_sandbox_actif(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/paiements/online/dashboard')
            ->assertStatus(200);

        // En mode test, sandbox doit être true
        $this->assertTrue($response->json('data.stats.sandbox_actif'));
    }

    // ─── REMBOURSEMENT ───────────────────────────────────

    public function test_rembourser_paiement_confirme(): void
    {
        $paiement = Paiement::create([
            'tenant_id'      => $this->tenant->id,
            'facture_id'     => $this->facture->id,
            'eleve_id'       => $this->eleve->id,
            'montant'        => 5000,
            'mode_paiement'  => 'cib',
            'mode'           => 'en_ligne',
            'type_paiement'  => 'cib',
            'reference_trans'=> 'PAY-REMB001',
            'order_id'       => 'SANDBOX_REMB001',
            'statut'         => 'confirmé',
            'date_paiement'  => now(),
        ]);

        $this->withToken($this->token)
            ->postJson("/api/v1/paiements/online/{$paiement->id}/rembourser", [
                'motif' => 'Erreur de saisie — doublon de paiement',
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('paiements', [
            'id'     => $paiement->id,
            'statut' => 'remboursé',
        ]);
    }

    public function test_rembourser_paiement_non_confirme_bloque(): void
    {
        $paiement = Paiement::create([
            'tenant_id'      => $this->tenant->id,
            'facture_id'     => $this->facture->id,
            'eleve_id'       => $this->eleve->id,
            'montant'        => 5000,
            'mode_paiement'  => 'cib',
            'mode'           => 'en_ligne',
            'type_paiement'  => 'cib',
            'reference_trans'=> 'PAY-ATT001',
            'statut'         => 'en_attente',
            'date_paiement'  => now(),
        ]);

        $this->withToken($this->token)
            ->postJson("/api/v1/paiements/online/{$paiement->id}/rembourser", [
                'motif' => 'Test remboursement paiement non confirmé',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'NOT_CONFIRMED');
    }

    // ─── VÉRIFICATION STATUT ─────────────────────────────

    public function test_verifier_statut_paiement_sandbox(): void
    {
        $paiement = Paiement::create([
            'tenant_id'      => $this->tenant->id,
            'facture_id'     => $this->facture->id,
            'eleve_id'       => $this->eleve->id,
            'montant'        => 5000,
            'mode_paiement'  => 'cib',
            'mode'           => 'en_ligne',
            'type_paiement'  => 'cib',
            'reference_trans'=> 'PAY-STAT001',
            'order_id'       => 'SANDBOX_STAT001',
            'statut'         => 'en_attente',
            'date_paiement'  => now(),
        ]);

        $this->withToken($this->token)
            ->getJson("/api/v1/paiements/online/{$paiement->id}/statut")
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['paiement', 'satim_response', 'statut_satim']]);
    }
}
```

---

## ÉTAPE 9 — Ajouter FakerFactory pour Facture (si manquante)

Vérifier que `FactureFactory.php` existe dans `database/factories/`.
Si elle n'existe pas, créer :

**Créer :** `edugestdz/backend/database/factories/FactureFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\Facture;
use Illuminate\Database\Eloquent\Factories\Factory;

class FactureFactory extends Factory
{
    protected $model = Facture::class;

    public function definition(): array
    {
        $mois  = now()->month;
        $annee = now()->year;
        $total = $this->faker->numberBetween(2000, 15000);

        return [
            'numero_facture' => 'FAC-' . $annee . str_pad($mois, 2, '0', STR_PAD_LEFT) . '-' . str_pad($this->faker->numberBetween(1, 999), 4, '0', STR_PAD_LEFT),
            'mois'           => $mois,
            'annee'          => $annee,
            'date_emission'  => today()->toDateString(),
            'date_echeance'  => today()->addDays(15)->toDateString(),
            'sous_total'     => $total,
            'remise_pct'     => 0,
            'remise_montant' => 0,
            'total_ttc'      => $total,
            'statut'         => 'émise',
        ];
    }

    public function payee(): static
    {
        return $this->state(['statut' => 'payée']);
    }

    public function enRetard(): static
    {
        return $this->state([
            'statut'         => 'en_retard',
            'date_echeance'  => today()->subDays(5)->toDateString(),
        ]);
    }
}
```

---

## ORDRE D'EXÉCUTION DEEPSEEK

```bash
# 0. Attendre merge PR #9 (Mobile P5) dans main, puis synchroniser
git checkout develop
git pull origin main

# 1. Fichier de config Satim
create: edugestdz/backend/config/satim.php

# 2. Documenter .env.example
modify: edugestdz/backend/.env.example
# → Ajouter les variables SATIM_* à la fin

# 3. Migration colonnes paiements
create: edugestdz/backend/database/migrations/2026_07_01_200000_add_online_payment_columns_to_paiements.php

# 4. Enrichir le modèle Paiement
replace: edugestdz/backend/app/Models/Paiement.php

# 5. Ajouter méthodes au controller (dashboard + verifierStatut + rembourser)
modify: edugestdz/backend/app/Http/Controllers/Api/V1/PaiementEnLigneController.php

# 6. Ajouter notification SMS dans finaliserFacture()
modify: edugestdz/backend/app/Http/Controllers/Api/V1/PaiementEnLigneController.php
# → Mettre à jour la méthode finaliserFacture()

# 7. Ajouter routes
modify: edugestdz/backend/routes/api.php
# → Ajouter bloc Route::prefix('paiements/online') avec dashboard + statut + rembourser

# 8. Vérifier/créer FactureFactory
# → Si database/factories/FactureFactory.php n'existe pas, le créer

# 9. Créer les tests
create: edugestdz/backend/tests/Feature/Api/PaiementEnLigneTest.php

# 10. Migration
php artisan migrate

# 11. Tests
php artisan test --parallel
# → Attendu : 301+ précédents + 12 nouveaux = 313+ tests verts

# 12. Si tout est vert
git add .
git commit -m "feat: Paiement CIB/Dahabia Satim — Dashboard + Remboursement + SMS confirmation + 12 tests"
git push origin develop

# 13. Ouvrir PR develop → main
```

---

## CE QUE TU DIS À DEEPSEEK

```
Repo : https://github.com/Allintelligence2024/edugest-dz.git
Branche : develop
Attends merge PR #9 (Mobile P5) dans main, puis :
git checkout develop && git pull origin main

Fichier : MISSION_P6_PAIEMENT_CIB_DAHABIA.md — 13 étapes dans l'ordre.

Points clés :
- SatimGateway.php EXISTE déjà et fonctionne en sandbox
- PaiementEnLigneController.php EXISTE déjà (initier/retour/callback)
- Ajouter UNIQUEMENT : config + migration + 3 nouvelles méthodes + SMS + tests
- Config::set('satim.sandbox', true) dans setUp() des tests → les tests passent sans Satim réel
- Ne pas modifier SatimGateway.php

php artisan test --parallel → 313+ tests verts requis.
0 régression tolérée.
PR develop → main à la fin.
```
