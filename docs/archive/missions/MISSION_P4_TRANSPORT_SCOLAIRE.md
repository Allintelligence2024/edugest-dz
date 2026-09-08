# 🤖 MISSION DEEPSEEK — Priorité 4 : M09 Transport Scolaire
## EduGest DZ · Branche : develop · 29 Juin 2026
## Tests actuels : 232+ ✅ (après merge PR #2) · Objectif : 250+ ✅

---

## CONTEXTE EXACT

### Ce qui EXISTE déjà (ne pas recréer)
- `app/Services/Sms/SmsService.php` — SMS opérationnel (Twilio)
- `app/Models/Eleve.php` — modèle élève complet avec `parents()`
- `app/Models/PersonnelNonEnseignant.php` — chauffeurs déjà dans le système (M12)
- `app/Traits/BelongsToTenant.php` — isolation tenant
- `app/Models/BaseModel.php` — UUID auto
- `app/Http/Controllers/Api/BaseApiController.php` — helpers réponse
- Migrations jusqu'à `2026_06_29_500000` → prochaine : `2026_06_29_600000`

### Ce qui MANQUE — M09 Transport complet
```
Migration  : create_transport_scolaire_tables
Models     : CircuitTransport · ArretBus · TransportEleve · PointageBus
Controller : TransportController.php
Factory    : CircuitTransportFactory.php
Tests      : Feature/Api/TransportTest.php
Routes     : bloc transport dans api.php
```

### Logique métier M09
- Un **circuit** a un chauffeur, un véhicule, une capacité, un tarif mensuel
- Un circuit a des **arrêts** ordonnés avec heure de passage matin/soir
- Un **élève** est affecté à un arrêt (abonnement aller/retour/aller seul/retour seul)
- Le **pointage bus** enregistre qui est monté ce jour
- Si un élève ne monte pas → SMS parent automatique
- La facturation transport est ajoutée à la facture mensuelle élève

---

## ÉTAPE 1 — Migration

**Créer :** `edugestdz/backend/database/migrations/2026_06_29_600000_create_transport_scolaire_tables.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Circuits de ramassage ──
        Schema::create('circuits_transport', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();

            $table->string('nom', 100);                    // "Circuit Nord Alger"
            $table->string('description', 300)->nullable();
            $table->uuid('chauffeur_id')->nullable();      // FK → personnel_non_enseignant
            $table->string('vehicule_immat', 30)->nullable();  // immatriculation
            $table->string('vehicule_marque', 50)->nullable();
            $table->unsignedSmallInteger('capacite')->default(20);
            $table->decimal('tarif_mensuel', 10, 2)->default(0);
            $table->enum('type_abonnement', ['mensuel', 'trimestriel', 'annuel'])->default('mensuel');
            $table->boolean('actif')->default(true);
            $table->text('note')->nullable();

            // Maintenance véhicule
            $table->date('date_controle_technique')->nullable();
            $table->date('date_expiration_assurance')->nullable();
            $table->date('date_vidange')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('chauffeur_id')
                ->references('id')->on('personnel_non_enseignant')
                ->onDelete('set null');
        });

        // ── Arrêts de bus ──
        Schema::create('arrets_bus', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();
            $table->uuid('circuit_id');

            $table->string('nom', 100);             // "Arrêt Pharmacie Ben Aknoun"
            $table->string('adresse', 200)->nullable();
            $table->string('wilaya', 50)->nullable();
            $table->unsignedTinyInteger('ordre');   // ordre de passage dans le circuit
            $table->time('heure_matin')->nullable(); // heure de passage matin
            $table->time('heure_soir')->nullable();  // heure retour soir
            $table->boolean('actif')->default(true);

            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('circuit_id')->references('id')->on('circuits_transport')->onDelete('cascade');
            $table->unique(['circuit_id', 'ordre']);
        });

        // ── Affectation élève ↔ arrêt ──
        Schema::create('transport_eleves', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();
            $table->uuid('eleve_id');
            $table->uuid('circuit_id');
            $table->uuid('arret_id');

            $table->enum('abonnement', ['aller_retour', 'aller', 'retour'])->default('aller_retour');
            $table->date('date_debut');
            $table->date('date_fin')->nullable();
            $table->boolean('actif')->default(true);
            $table->decimal('tarif_mensuel_applique', 10, 2)->default(0); // tarif au moment de l'inscription
            $table->text('note')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('eleve_id')->references('id')->on('eleves')->onDelete('cascade');
            $table->foreign('circuit_id')->references('id')->on('circuits_transport')->onDelete('cascade');
            $table->foreign('arret_id')->references('id')->on('arrets_bus')->onDelete('cascade');
            // Un élève ne peut être inscrit qu'une fois sur un circuit actif
            $table->unique(['tenant_id', 'eleve_id', 'circuit_id', 'date_debut']);
        });

        // ── Pointage journalier dans le bus ──
        Schema::create('pointage_bus', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();
            $table->uuid('circuit_id');
            $table->uuid('eleve_id');
            $table->uuid('arret_id');

            $table->date('date');
            $table->enum('trajet', ['matin', 'soir'])->default('matin');
            $table->enum('statut', ['monte', 'absent', 'excuse'])->default('monte');
            $table->time('heure_montee')->nullable();
            $table->boolean('sms_parent_envoye')->default(false);
            $table->timestamp('sms_envoye_at')->nullable();
            $table->string('signale_par', 30)->default('chauffeur'); // chauffeur | admin | parent

            $table->timestamps();

            $table->foreign('circuit_id')->references('id')->on('circuits_transport')->onDelete('cascade');
            $table->foreign('eleve_id')->references('id')->on('eleves')->onDelete('cascade');
            $table->unique(['tenant_id', 'circuit_id', 'eleve_id', 'date', 'trajet']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pointage_bus');
        Schema::dropIfExists('transport_eleves');
        Schema::dropIfExists('arrets_bus');
        Schema::dropIfExists('circuits_transport');
    }
};
```

---

## ÉTAPE 2 — Modèle CircuitTransport

**Créer :** `edugestdz/backend/app/Models/CircuitTransport.php`

```php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CircuitTransport extends BaseModel
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'circuits_transport';

    protected $fillable = [
        'tenant_id', 'nom', 'description', 'chauffeur_id',
        'vehicule_immat', 'vehicule_marque', 'capacite',
        'tarif_mensuel', 'type_abonnement', 'actif', 'note',
        'date_controle_technique', 'date_expiration_assurance', 'date_vidange',
    ];

    protected $casts = [
        'tarif_mensuel'             => 'decimal:2',
        'actif'                     => 'boolean',
        'date_controle_technique'   => 'date',
        'date_expiration_assurance' => 'date',
        'date_vidange'              => 'date',
    ];

    public function chauffeur(): BelongsTo
    {
        return $this->belongsTo(PersonnelNonEnseignant::class, 'chauffeur_id');
    }

    public function arrets(): HasMany
    {
        return $this->hasMany(ArretBus::class, 'circuit_id')->orderBy('ordre');
    }

    public function inscriptions(): HasMany
    {
        return $this->hasMany(TransportEleve::class, 'circuit_id');
    }

    public function inscriptionsActives(): HasMany
    {
        return $this->hasMany(TransportEleve::class, 'circuit_id')
            ->where('actif', true)
            ->where(fn($q) => $q->whereNull('date_fin')->orWhere('date_fin', '>=', today()));
    }

    public function pointages(): HasMany
    {
        return $this->hasMany(PointageBus::class, 'circuit_id');
    }

    // Alertes maintenance
    public function getAlertesMaintenanceAttribute(): array
    {
        $alertes = [];
        if ($this->date_controle_technique && $this->date_controle_technique->isPast()) {
            $alertes[] = "Contrôle technique expiré le {$this->date_controle_technique->format('d/m/Y')}";
        }
        if ($this->date_expiration_assurance && $this->date_expiration_assurance->isPast()) {
            $alertes[] = "Assurance expirée le {$this->date_expiration_assurance->format('d/m/Y')}";
        }
        return $alertes;
    }

    public function getNbElevesActifsAttribute(): int
    {
        return $this->inscriptionsActives()->count();
    }

    public function getTauxRemplissageAttribute(): float
    {
        if ($this->capacite === 0) return 0;
        return round(($this->nb_eleves_actifs / $this->capacite) * 100, 1);
    }

    public function scopeActifs($query)
    {
        return $query->where('actif', true);
    }
}
```

---

## ÉTAPE 3 — Modèle ArretBus

**Créer :** `edugestdz/backend/app/Models/ArretBus.php`

```php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArretBus extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'arrets_bus';

    protected $fillable = [
        'tenant_id', 'circuit_id', 'nom', 'adresse',
        'wilaya', 'ordre', 'heure_matin', 'heure_soir', 'actif',
    ];

    protected $casts = [
        'actif' => 'boolean',
    ];

    public function circuit(): BelongsTo
    {
        return $this->belongsTo(CircuitTransport::class, 'circuit_id');
    }

    public function elevesInscrits(): HasMany
    {
        return $this->hasMany(TransportEleve::class, 'arret_id')->where('actif', true);
    }
}
```

---

## ÉTAPE 4 — Modèle TransportEleve

**Créer :** `edugestdz/backend/app/Models/TransportEleve.php`

```php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransportEleve extends BaseModel
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'transport_eleves';

    protected $fillable = [
        'tenant_id', 'eleve_id', 'circuit_id', 'arret_id',
        'abonnement', 'date_debut', 'date_fin',
        'actif', 'tarif_mensuel_applique', 'note',
    ];

    protected $casts = [
        'date_debut'             => 'date',
        'date_fin'               => 'date',
        'actif'                  => 'boolean',
        'tarif_mensuel_applique' => 'decimal:2',
    ];

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    public function circuit(): BelongsTo
    {
        return $this->belongsTo(CircuitTransport::class, 'circuit_id');
    }

    public function arret(): BelongsTo
    {
        return $this->belongsTo(ArretBus::class, 'arret_id');
    }

    public function isActif(): bool
    {
        if (!$this->actif) return false;
        if ($this->date_fin && $this->date_fin->isPast()) return false;
        return true;
    }
}
```

---

## ÉTAPE 5 — Modèle PointageBus

**Créer :** `edugestdz/backend/app/Models/PointageBus.php`

```php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PointageBus extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'pointage_bus';

    protected $fillable = [
        'tenant_id', 'circuit_id', 'eleve_id', 'arret_id',
        'date', 'trajet', 'statut', 'heure_montee',
        'sms_parent_envoye', 'sms_envoye_at', 'signale_par',
    ];

    protected $casts = [
        'date'               => 'date',
        'sms_parent_envoye'  => 'boolean',
        'sms_envoye_at'      => 'datetime',
    ];

    public function circuit(): BelongsTo
    {
        return $this->belongsTo(CircuitTransport::class, 'circuit_id');
    }

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    public function arret(): BelongsTo
    {
        return $this->belongsTo(ArretBus::class, 'arret_id');
    }
}
```

---

## ÉTAPE 6 — Controller TransportController

**Créer :** `edugestdz/backend/app/Http/Controllers/Api/V1/TransportController.php`

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Jobs\NotifierAbsenceParent;
use App\Models\ArretBus;
use App\Models\CircuitTransport;
use App\Models\Eleve;
use App\Models\PointageBus;
use App\Models\TransportEleve;
use App\Services\Sms\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * M09 — Transport Scolaire
 *
 * Circuits
 * GET    /api/v1/transport/circuits                  → liste circuits
 * POST   /api/v1/transport/circuits                  → créer circuit
 * GET    /api/v1/transport/circuits/{id}             → détail + arrêts + élèves
 * PUT    /api/v1/transport/circuits/{id}             → modifier
 * DELETE /api/v1/transport/circuits/{id}             → supprimer
 *
 * Arrêts
 * GET    /api/v1/transport/circuits/{id}/arrets      → liste arrêts du circuit
 * POST   /api/v1/transport/circuits/{id}/arrets      → ajouter arrêt
 * PUT    /api/v1/transport/arrets/{id}               → modifier arrêt
 * DELETE /api/v1/transport/arrets/{id}               → supprimer arrêt
 *
 * Inscriptions
 * POST   /api/v1/transport/inscrire                  → inscrire élève à un circuit
 * DELETE /api/v1/transport/inscrire/{id}             → désinscrire
 * GET    /api/v1/transport/eleve/{eleveId}           → circuits d'un élève
 *
 * Pointage
 * POST   /api/v1/transport/pointage                  → pointer élèves du bus
 * GET    /api/v1/transport/circuits/{id}/pointage    → pointage du jour d'un circuit
 *
 * Dashboard
 * GET    /api/v1/transport/dashboard                 → vue synthétique
 */
class TransportController extends BaseApiController
{
    public function __construct(private readonly SmsService $sms) {}

    // ═══════════════════════════════════════════
    // CIRCUITS
    // ═══════════════════════════════════════════

    public function indexCircuits(Request $request): JsonResponse
    {
        $circuits = CircuitTransport::with(['chauffeur:id,nom,prenom,telephone', 'arrets'])
            ->when($request->filled('actif'), fn($q) => $q->where('actif', (bool) $request->actif))
            ->orderBy('nom')
            ->get()
            ->map(fn($c) => array_merge($c->toArray(), [
                'nb_eleves'       => $c->nb_eleves_actifs,
                'taux_remplissage'=> $c->taux_remplissage,
                'alertes'         => $c->alertes_maintenance,
            ]));

        return $this->success([
            'circuits' => $circuits,
            'stats'    => [
                'total'         => $circuits->count(),
                'actifs'        => $circuits->where('actif', true)->count(),
                'total_eleves'  => $circuits->sum('nb_eleves'),
            ],
        ], 'Circuits récupérés');
    }

    public function storeCircuit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nom'                       => 'required|string|max:100',
            'description'               => 'nullable|string|max:300',
            'chauffeur_id'              => 'nullable|uuid|exists:personnel_non_enseignant,id',
            'vehicule_immat'            => 'nullable|string|max:30',
            'vehicule_marque'           => 'nullable|string|max:50',
            'capacite'                  => 'required|integer|min:1|max:100',
            'tarif_mensuel'             => 'required|numeric|min:0',
            'type_abonnement'           => 'nullable|in:mensuel,trimestriel,annuel',
            'date_controle_technique'   => 'nullable|date',
            'date_expiration_assurance' => 'nullable|date',
            'date_vidange'              => 'nullable|date',
            'note'                      => 'nullable|string|max:500',
        ]);

        $circuit = CircuitTransport::create($validated);

        return $this->created(
            $circuit->load('chauffeur:id,nom,prenom'),
            "Circuit '{$circuit->nom}' créé"
        );
    }

    public function showCircuit(string $id): JsonResponse
    {
        $circuit = CircuitTransport::with([
            'chauffeur:id,nom,prenom,telephone',
            'arrets',
            'inscriptionsActives.eleve:id,nom,prenom,photo_url',
            'inscriptionsActives.arret:id,nom,ordre',
        ])->findOrFail($id);

        return $this->success([
            'circuit'          => $circuit,
            'nb_eleves'        => $circuit->nb_eleves_actifs,
            'taux_remplissage' => $circuit->taux_remplissage,
            'alertes'          => $circuit->alertes_maintenance,
            'places_restantes' => $circuit->capacite - $circuit->nb_eleves_actifs,
        ]);
    }

    public function updateCircuit(Request $request, string $id): JsonResponse
    {
        $circuit   = CircuitTransport::findOrFail($id);
        $validated = $request->validate([
            'nom'                       => 'sometimes|string|max:100',
            'chauffeur_id'              => 'nullable|uuid|exists:personnel_non_enseignant,id',
            'vehicule_immat'            => 'nullable|string|max:30',
            'vehicule_marque'           => 'nullable|string|max:50',
            'capacite'                  => 'sometimes|integer|min:1|max:100',
            'tarif_mensuel'             => 'sometimes|numeric|min:0',
            'actif'                     => 'sometimes|boolean',
            'date_controle_technique'   => 'nullable|date',
            'date_expiration_assurance' => 'nullable|date',
            'date_vidange'              => 'nullable|date',
            'note'                      => 'nullable|string|max:500',
        ]);

        $circuit->update($validated);
        return $this->success($circuit->fresh('chauffeur'), 'Circuit mis à jour');
    }

    public function destroyCircuit(string $id): JsonResponse
    {
        $circuit = CircuitTransport::findOrFail($id);
        if ($circuit->inscriptionsActives()->exists()) {
            return $this->error(
                'Impossible de supprimer : des élèves sont inscrits sur ce circuit',
                'HAS_INSCRIPTIONS', 422
            );
        }
        $nom = $circuit->nom;
        $circuit->delete();
        return $this->success(null, "Circuit '{$nom}' supprimé");
    }

    // ═══════════════════════════════════════════
    // ARRÊTS
    // ═══════════════════════════════════════════

    public function indexArrets(string $circuitId): JsonResponse
    {
        $circuit = CircuitTransport::findOrFail($circuitId);
        $arrets  = $circuit->arrets()->withCount([
            'elevesInscrits as nb_eleves',
        ])->get();

        return $this->success($arrets, "Arrêts du circuit '{$circuit->nom}'");
    }

    public function storeArret(Request $request, string $circuitId): JsonResponse
    {
        $circuit   = CircuitTransport::findOrFail($circuitId);
        $validated = $request->validate([
            'nom'          => 'required|string|max:100',
            'adresse'      => 'nullable|string|max:200',
            'wilaya'       => 'nullable|string|max:50',
            'ordre'        => 'required|integer|min:1|max:99',
            'heure_matin'  => 'nullable|date_format:H:i',
            'heure_soir'   => 'nullable|date_format:H:i',
        ]);

        $validated['circuit_id'] = $circuit->id;

        $arret = ArretBus::create($validated);
        return $this->created($arret, "Arrêt '{$arret->nom}' ajouté");
    }

    public function updateArret(Request $request, string $id): JsonResponse
    {
        $arret     = ArretBus::findOrFail($id);
        $validated = $request->validate([
            'nom'         => 'sometimes|string|max:100',
            'adresse'     => 'nullable|string|max:200',
            'ordre'       => 'sometimes|integer|min:1',
            'heure_matin' => 'nullable|date_format:H:i',
            'heure_soir'  => 'nullable|date_format:H:i',
            'actif'       => 'sometimes|boolean',
        ]);
        $arret->update($validated);
        return $this->success($arret->fresh(), 'Arrêt mis à jour');
    }

    public function destroyArret(string $id): JsonResponse
    {
        $arret = ArretBus::findOrFail($id);
        if ($arret->elevesInscrits()->exists()) {
            return $this->error(
                'Des élèves sont affectés à cet arrêt',
                'HAS_ELEVES', 422
            );
        }
        $arret->delete();
        return $this->success(null, "Arrêt '{$arret->nom}' supprimé");
    }

    // ═══════════════════════════════════════════
    // INSCRIPTIONS ÉLÈVES
    // ═══════════════════════════════════════════

    public function inscrireEleve(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'eleve_id'   => 'required|uuid|exists:eleves,id',
            'circuit_id' => 'required|uuid|exists:circuits_transport,id',
            'arret_id'   => 'required|uuid|exists:arrets_bus,id',
            'abonnement' => 'required|in:aller_retour,aller,retour',
            'date_debut' => 'required|date',
            'date_fin'   => 'nullable|date|after:date_debut',
        ]);

        $circuit = CircuitTransport::findOrFail($validated['circuit_id']);
        $eleve   = Eleve::findOrFail($validated['eleve_id']);

        // Vérifier capacité
        if ($circuit->nb_eleves_actifs >= $circuit->capacite) {
            return $this->error(
                "Circuit complet ({$circuit->capacite} places)",
                'CIRCUIT_COMPLET', 422
            );
        }

        // Vérifier que l'arrêt appartient au circuit
        if (!$circuit->arrets()->where('id', $validated['arret_id'])->exists()) {
            return $this->error(
                "Cet arrêt n'appartient pas au circuit sélectionné",
                'ARRET_INVALIDE', 422
            );
        }

        // Vérifier inscription en double
        $dejaInscrit = TransportEleve::where('eleve_id', $validated['eleve_id'])
            ->where('circuit_id', $validated['circuit_id'])
            ->where('actif', true)
            ->exists();

        if ($dejaInscrit) {
            return $this->error(
                "{$eleve->prenom} {$eleve->nom} est déjà inscrit sur ce circuit",
                'DEJA_INSCRIT', 409
            );
        }

        $inscription = TransportEleve::create(array_merge($validated, [
            'tarif_mensuel_applique' => $circuit->tarif_mensuel,
            'actif'                  => true,
        ]));

        return $this->created([
            'inscription'    => $inscription->load('arret:id,nom,ordre'),
            'eleve'          => ['nom' => $eleve->nom, 'prenom' => $eleve->prenom],
            'circuit'        => ['nom' => $circuit->nom],
            'tarif_mensuel'  => $circuit->tarif_mensuel,
        ], "{$eleve->prenom} {$eleve->nom} inscrit sur le circuit '{$circuit->nom}'");
    }

    public function desinscrireEleve(string $id): JsonResponse
    {
        $inscription = TransportEleve::with(['eleve', 'circuit'])->findOrFail($id);
        $inscription->update(['actif' => false, 'date_fin' => today()]);

        return $this->success(null,
            "{$inscription->eleve->prenom} {$inscription->eleve->nom} désinscrit du circuit '{$inscription->circuit->nom}'"
        );
    }

    public function circuitsEleve(string $eleveId): JsonResponse
    {
        $eleve = Eleve::findOrFail($eleveId);
        $inscriptions = TransportEleve::with([
            'circuit:id,nom,vehicule_marque,vehicule_immat,tarif_mensuel',
            'arret:id,nom,ordre,heure_matin,heure_soir',
        ])
            ->where('eleve_id', $eleveId)
            ->where('actif', true)
            ->get();

        return $this->success([
            'eleve'       => ['id' => $eleve->id, 'nom' => $eleve->nom, 'prenom' => $eleve->prenom],
            'inscriptions'=> $inscriptions,
        ]);
    }

    // ═══════════════════════════════════════════
    // POINTAGE BUS
    // ═══════════════════════════════════════════

    public function pointer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'circuit_id' => 'required|uuid|exists:circuits_transport,id',
            'date'       => 'nullable|date',
            'trajet'     => 'required|in:matin,soir',
            'pointages'  => 'required|array|min:1',
            'pointages.*.eleve_id' => 'required|uuid|exists:eleves,id',
            'pointages.*.statut'   => 'required|in:monte,absent,excuse',
            'pointages.*.arret_id' => 'required|uuid|exists:arrets_bus,id',
        ]);

        $date    = $validated['date'] ?? today()->toDateString();
        $circuit = CircuitTransport::findOrFail($validated['circuit_id']);
        $enregistres = 0;
        $absentsNonNotifies = [];

        foreach ($validated['pointages'] as $p) {
            $pointage = PointageBus::updateOrCreate(
                [
                    'tenant_id'  => config('tenant.current_id'),
                    'circuit_id' => $circuit->id,
                    'eleve_id'   => $p['eleve_id'],
                    'date'       => $date,
                    'trajet'     => $validated['trajet'],
                ],
                [
                    'arret_id'      => $p['arret_id'],
                    'statut'        => $p['statut'],
                    'heure_montee'  => $p['statut'] === 'monte' ? now()->format('H:i:s') : null,
                    'signale_par'   => 'chauffeur',
                ]
            );

            $enregistres++;

            // SMS parent si absent et pas encore notifié
            if ($p['statut'] === 'absent' && !$pointage->sms_parent_envoye) {
                $absentsNonNotifies[] = ['pointage' => $pointage, 'eleve_id' => $p['eleve_id']];
            }
        }

        // Notifier les parents des absents (hors du loop principal)
        foreach ($absentsNonNotifies as $item) {
            $this->notifierParentAbsentBus($item['pointage'], $item['eleve_id'], $circuit->nom, $date, $validated['trajet']);
        }

        return $this->success([
            'circuit'     => $circuit->nom,
            'date'        => $date,
            'trajet'      => $validated['trajet'],
            'enregistres' => $enregistres,
            'absents_sms' => count($absentsNonNotifies),
        ], "{$enregistres} pointage(s) enregistré(s)");
    }

    public function pointageDuJour(Request $request, string $circuitId): JsonResponse
    {
        $validated = $request->validate([
            'date'   => 'nullable|date',
            'trajet' => 'nullable|in:matin,soir',
        ]);

        $circuit = CircuitTransport::with('inscriptionsActives.eleve:id,nom,prenom,photo_url')
            ->findOrFail($circuitId);

        $date   = $validated['date'] ?? today()->toDateString();
        $trajet = $validated['trajet'] ?? 'matin';

        // Récupérer les pointages existants
        $pointages = PointageBus::where('circuit_id', $circuit->id)
            ->where('date', $date)
            ->where('trajet', $trajet)
            ->with('eleve:id,nom,prenom', 'arret:id,nom')
            ->get()
            ->keyBy('eleve_id');

        // Construire la liste complète (inscrits + statut)
        $liste = $circuit->inscriptionsActives->map(function ($insc) use ($pointages) {
            $p = $pointages->get($insc->eleve_id);
            return [
                'eleve'          => $insc->eleve,
                'arret'          => $insc->arret ?? null,
                'statut'         => $p?->statut ?? 'non_pointe',
                'heure_montee'   => $p?->heure_montee,
                'sms_envoye'     => (bool) $p?->sms_parent_envoye,
                'pointage_id'    => $p?->id,
            ];
        });

        return $this->success([
            'circuit'  => ['id' => $circuit->id, 'nom' => $circuit->nom],
            'date'     => $date,
            'trajet'   => $trajet,
            'liste'    => $liste,
            'stats'    => [
                'total'      => $liste->count(),
                'montes'     => $liste->where('statut', 'monte')->count(),
                'absents'    => $liste->where('statut', 'absent')->count(),
                'non_pointe' => $liste->where('statut', 'non_pointe')->count(),
            ],
        ]);
    }

    // ═══════════════════════════════════════════
    // DASHBOARD
    // ═══════════════════════════════════════════

    public function dashboard(): JsonResponse
    {
        $today    = today();
        $circuits = CircuitTransport::actifs()->with('arrets')->get();

        $alertesMaintenance = $circuits->flatMap(fn($c) => $c->alertes_maintenance)->filter()->values();

        $pointagesAujourdhui = PointageBus::where('date', $today)
            ->selectRaw("statut, COUNT(*) as total")
            ->groupBy('statut')
            ->pluck('total', 'statut');

        return $this->success([
            'date'               => $today->format('d/m/Y'),
            'nb_circuits'        => $circuits->count(),
            'nb_eleves_total'    => $circuits->sum('nb_eleves_actifs'),
            'alertes_maintenance'=> $alertesMaintenance,
            'pointages_aujourd_hui' => [
                'montes'  => (int) ($pointagesAujourdhui['monte']  ?? 0),
                'absents' => (int) ($pointagesAujourdhui['absent'] ?? 0),
                'excuses' => (int) ($pointagesAujourdhui['excuse'] ?? 0),
            ],
            'circuits' => $circuits->map(fn($c) => [
                'id'              => $c->id,
                'nom'             => $c->nom,
                'nb_eleves'       => $c->nb_eleves_actifs,
                'capacite'        => $c->capacite,
                'taux_remplissage'=> $c->taux_remplissage,
            ]),
        ], "Tableau de bord transport — {$today->format('d/m/Y')}");
    }

    // ═══════════════════════════════════════════
    // HELPERS PRIVÉS
    // ═══════════════════════════════════════════

    private function notifierParentAbsentBus(
        PointageBus $pointage,
        string $eleveId,
        string $nomCircuit,
        string $date,
        string $trajet
    ): void {
        $eleve = Eleve::with('parents')->find($eleveId);
        if (!$eleve) return;

        $trajetLabel = $trajet === 'matin' ? 'matin' : 'soir';
        $dateFormate = \Carbon\Carbon::parse($date)->format('d/m/Y');
        $message = "EduGest DZ : Votre enfant {$eleve->prenom} {$eleve->nom} "
                 . "n'est PAS monté dans le bus {$nomCircuit} ce {$dateFormate} ({$trajetLabel}). "
                 . "Contactez l'établissement.";

        $smsSent = false;
        foreach ($eleve->parents as $parent) {
            if ($parent->telephone_1) {
                try {
                    $this->sms->send($parent->telephone_1, $message);
                    $smsSent = true;
                } catch (\Throwable $e) {
                    Log::error('SMS transport absent échoué', ['eleve_id' => $eleveId, 'error' => $e->getMessage()]);
                }
            }
        }

        if ($smsSent) {
            $pointage->update(['sms_parent_envoye' => true, 'sms_envoye_at' => now()]);
        }
    }
}
```

---

## ÉTAPE 7 — Factory

**Créer :** `edugestdz/backend/database/factories/CircuitTransportFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\CircuitTransport;
use Illuminate\Database\Eloquent\Factories\Factory;

class CircuitTransportFactory extends Factory
{
    protected $model = CircuitTransport::class;

    public function definition(): array
    {
        $wilayas = ['Alger', 'Oran', 'Constantine', 'Annaba', 'Blida', 'Tizi Ouzou'];
        return [
            'nom'            => 'Circuit ' . $this->faker->randomElement(['Nord', 'Sud', 'Est', 'Ouest', 'Centre']),
            'vehicule_immat' => $this->faker->bothify('##-???-##'),
            'vehicule_marque'=> $this->faker->randomElement(['Toyota', 'Mercedes', 'Peugeot', 'Renault']),
            'capacite'       => $this->faker->numberBetween(15, 40),
            'tarif_mensuel'  => $this->faker->numberBetween(2000, 6000),
            'actif'          => true,
            'type_abonnement'=> 'mensuel',
        ];
    }

    public function inactif(): static
    {
        return $this->state(['actif' => false]);
    }
}
```

---

## ÉTAPE 8 — Routes (ajouter dans api.php)

**Modifier :** `edugestdz/backend/routes/api.php`

Ajouter dans le groupe `middleware(['auth:api', 'resolve.tenant', 'check.subscription'])` :

```php
// ── Transport Scolaire (M09) ──
Route::prefix('transport')->group(function () {
    Route::get('dashboard',                          [\App\Http\Controllers\Api\V1\TransportController::class, 'dashboard']);

    // Circuits
    Route::get('circuits',                           [\App\Http\Controllers\Api\V1\TransportController::class, 'indexCircuits']);
    Route::post('circuits',                          [\App\Http\Controllers\Api\V1\TransportController::class, 'storeCircuit']);
    Route::get('circuits/{id}',                      [\App\Http\Controllers\Api\V1\TransportController::class, 'showCircuit']);
    Route::put('circuits/{id}',                      [\App\Http\Controllers\Api\V1\TransportController::class, 'updateCircuit']);
    Route::delete('circuits/{id}',                   [\App\Http\Controllers\Api\V1\TransportController::class, 'destroyCircuit']);

    // Arrêts
    Route::get('circuits/{id}/arrets',               [\App\Http\Controllers\Api\V1\TransportController::class, 'indexArrets']);
    Route::post('circuits/{id}/arrets',              [\App\Http\Controllers\Api\V1\TransportController::class, 'storeArret']);
    Route::put('arrets/{id}',                        [\App\Http\Controllers\Api\V1\TransportController::class, 'updateArret']);
    Route::delete('arrets/{id}',                     [\App\Http\Controllers\Api\V1\TransportController::class, 'destroyArret']);

    // Inscriptions
    Route::post('inscrire',                          [\App\Http\Controllers\Api\V1\TransportController::class, 'inscrireEleve']);
    Route::delete('inscrire/{id}',                   [\App\Http\Controllers\Api\V1\TransportController::class, 'desinscrireEleve']);
    Route::get('eleve/{eleveId}',                    [\App\Http\Controllers\Api\V1\TransportController::class, 'circuitsEleve']);

    // Pointage bus
    Route::post('pointage',                          [\App\Http\Controllers\Api\V1\TransportController::class, 'pointer']);
    Route::get('circuits/{id}/pointage',             [\App\Http\Controllers\Api\V1\TransportController::class, 'pointageDuJour']);
});
```

---

## ÉTAPE 9 — Tests

**Créer :** `edugestdz/backend/tests/Feature/Api/TransportTest.php`

```php
<?php

namespace Tests\Feature\Api;

use App\Models\ArretBus;
use App\Models\CircuitTransport;
use App\Models\Eleve;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TransportEleve;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransportTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private string $token;

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
    }

    // ─── CIRCUITS ────────────────────────────────────

    public function test_creer_circuit(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/transport/circuits', [
                'nom'           => 'Circuit Nord Alger',
                'capacite'      => 25,
                'tarif_mensuel' => 3500,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.nom', 'Circuit Nord Alger');

        $this->assertDatabaseHas('circuits_transport', [
            'nom'       => 'Circuit Nord Alger',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_liste_circuits_par_tenant(): void
    {
        CircuitTransport::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);
        $autreTenant = Tenant::factory()->create();
        CircuitTransport::factory()->count(5)->create(['tenant_id' => $autreTenant->id]);

        $this->withToken($this->token)
            ->getJson('/api/v1/transport/circuits')
            ->assertStatus(200)
            ->assertJsonPath('data.stats.total', 3);
    }

    public function test_afficher_circuit_avec_arrets(): void
    {
        $circuit = CircuitTransport::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->getJson("/api/v1/transport/circuits/{$circuit->id}")
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['circuit', 'nb_eleves', 'taux_remplissage', 'places_restantes']]);
    }

    public function test_isolation_tenant_circuit(): void
    {
        $autreTenant = Tenant::factory()->create();
        $autreCircuit = CircuitTransport::factory()->create(['tenant_id' => $autreTenant->id]);

        $this->withToken($this->token)
            ->getJson("/api/v1/transport/circuits/{$autreCircuit->id}")
            ->assertStatus(404);
    }

    public function test_supprimer_circuit_vide(): void
    {
        $circuit = CircuitTransport::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->deleteJson("/api/v1/transport/circuits/{$circuit->id}")
            ->assertStatus(200);

        $this->assertSoftDeleted('circuits_transport', ['id' => $circuit->id]);
    }

    public function test_supprimer_circuit_avec_eleves_bloque(): void
    {
        $circuit = CircuitTransport::factory()->create(['tenant_id' => $this->tenant->id]);
        $arret   = ArretBus::create([
            'tenant_id'  => $this->tenant->id,
            'circuit_id' => $circuit->id,
            'nom'        => 'Arrêt Test',
            'ordre'      => 1,
        ]);
        $eleve   = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        TransportEleve::create([
            'tenant_id'  => $this->tenant->id,
            'eleve_id'   => $eleve->id,
            'circuit_id' => $circuit->id,
            'arret_id'   => $arret->id,
            'abonnement' => 'aller_retour',
            'date_debut' => today(),
            'actif'      => true,
            'tarif_mensuel_applique' => 3500,
        ]);

        $this->withToken($this->token)
            ->deleteJson("/api/v1/transport/circuits/{$circuit->id}")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'HAS_INSCRIPTIONS');
    }

    // ─── ARRÊTS ──────────────────────────────────────

    public function test_ajouter_arret(): void
    {
        $circuit = CircuitTransport::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->postJson("/api/v1/transport/circuits/{$circuit->id}/arrets", [
                'nom'         => 'Arrêt Pharmacie',
                'adresse'     => 'Rue Didouche Mourad',
                'ordre'       => 1,
                'heure_matin' => '07:15',
                'heure_soir'  => '17:30',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.nom', 'Arrêt Pharmacie');
    }

    // ─── INSCRIPTIONS ────────────────────────────────

    public function test_inscrire_eleve_dans_circuit(): void
    {
        $circuit = CircuitTransport::factory()->create([
            'tenant_id'     => $this->tenant->id,
            'capacite'      => 20,
            'tarif_mensuel' => 3500,
        ]);
        $arret = ArretBus::create([
            'tenant_id'  => $this->tenant->id,
            'circuit_id' => $circuit->id,
            'nom'        => 'Arrêt Test',
            'ordre'      => 1,
        ]);
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->postJson('/api/v1/transport/inscrire', [
                'eleve_id'   => $eleve->id,
                'circuit_id' => $circuit->id,
                'arret_id'   => $arret->id,
                'abonnement' => 'aller_retour',
                'date_debut' => today()->toDateString(),
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.tarif_mensuel', '3500.00');

        $this->assertDatabaseHas('transport_eleves', [
            'eleve_id'   => $eleve->id,
            'circuit_id' => $circuit->id,
            'actif'      => true,
        ]);
    }

    public function test_double_inscription_bloquee(): void
    {
        $circuit = CircuitTransport::factory()->create(['tenant_id' => $this->tenant->id, 'capacite' => 20]);
        $arret   = ArretBus::create([
            'tenant_id' => $this->tenant->id, 'circuit_id' => $circuit->id,
            'nom' => 'Arrêt', 'ordre' => 1,
        ]);
        $eleve   = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        TransportEleve::create([
            'tenant_id' => $this->tenant->id, 'eleve_id' => $eleve->id,
            'circuit_id' => $circuit->id, 'arret_id' => $arret->id,
            'abonnement' => 'aller_retour', 'date_debut' => today(),
            'actif' => true, 'tarif_mensuel_applique' => 3500,
        ]);

        $this->withToken($this->token)
            ->postJson('/api/v1/transport/inscrire', [
                'eleve_id' => $eleve->id, 'circuit_id' => $circuit->id,
                'arret_id' => $arret->id, 'abonnement' => 'aller',
                'date_debut' => today()->toDateString(),
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'DEJA_INSCRIT');
    }

    public function test_circuit_complet_bloque(): void
    {
        $circuit = CircuitTransport::factory()->create([
            'tenant_id' => $this->tenant->id,
            'capacite'  => 1,  // capacité 1 pour forcer le blocage
        ]);
        $arret   = ArretBus::create([
            'tenant_id' => $this->tenant->id, 'circuit_id' => $circuit->id,
            'nom' => 'Arrêt', 'ordre' => 1,
        ]);

        // Inscrire un premier élève
        $eleve1 = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        TransportEleve::create([
            'tenant_id' => $this->tenant->id, 'eleve_id' => $eleve1->id,
            'circuit_id' => $circuit->id, 'arret_id' => $arret->id,
            'abonnement' => 'aller_retour', 'date_debut' => today(),
            'actif' => true, 'tarif_mensuel_applique' => 3500,
        ]);

        // Tenter d'inscrire un deuxième → circuit complet
        $eleve2 = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->withToken($this->token)
            ->postJson('/api/v1/transport/inscrire', [
                'eleve_id' => $eleve2->id, 'circuit_id' => $circuit->id,
                'arret_id' => $arret->id, 'abonnement' => 'aller_retour',
                'date_debut' => today()->toDateString(),
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'CIRCUIT_COMPLET');
    }

    // ─── POINTAGE ────────────────────────────────────

    public function test_pointer_eleves_bus(): void
    {
        $circuit = CircuitTransport::factory()->create(['tenant_id' => $this->tenant->id]);
        $arret   = ArretBus::create([
            'tenant_id' => $this->tenant->id, 'circuit_id' => $circuit->id,
            'nom' => 'Arrêt', 'ordre' => 1,
        ]);
        $eleve   = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->postJson('/api/v1/transport/pointage', [
                'circuit_id' => $circuit->id,
                'trajet'     => 'matin',
                'date'       => today()->toDateString(),
                'pointages'  => [
                    ['eleve_id' => $eleve->id, 'arret_id' => $arret->id, 'statut' => 'monte'],
                ],
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.enregistres', 1);

        $this->assertDatabaseHas('pointage_bus', [
            'eleve_id'   => $eleve->id,
            'circuit_id' => $circuit->id,
            'statut'     => 'monte',
            'trajet'     => 'matin',
        ]);
    }

    public function test_pointage_jour_circuit(): void
    {
        $circuit = CircuitTransport::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->getJson("/api/v1/transport/circuits/{$circuit->id}/pointage?trajet=matin")
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['circuit', 'date', 'trajet', 'liste', 'stats']]);
    }

    // ─── DASHBOARD ───────────────────────────────────

    public function test_dashboard_transport(): void
    {
        CircuitTransport::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->getJson('/api/v1/transport/dashboard')
            ->assertStatus(200)
            ->assertJsonPath('data.nb_circuits', 2)
            ->assertJsonStructure(['data' => ['nb_circuits', 'nb_eleves_total', 'alertes_maintenance', 'circuits']]);
    }
}
```

---

## ORDRE D'EXÉCUTION DEEPSEEK

```bash
# 0. Attendre que PR #2 (P1+P2+P3) soit mergée dans main
git checkout develop
git pull origin main

# 1. Créer la migration
create: edugestdz/backend/database/migrations/2026_06_29_600000_create_transport_scolaire_tables.php

# 2. Créer les modèles (dans cet ordre)
create: edugestdz/backend/app/Models/CircuitTransport.php
create: edugestdz/backend/app/Models/ArretBus.php
create: edugestdz/backend/app/Models/TransportEleve.php
create: edugestdz/backend/app/Models/PointageBus.php

# 3. Créer le controller
create: edugestdz/backend/app/Http/Controllers/Api/V1/TransportController.php

# 4. Créer la factory
create: edugestdz/backend/database/factories/CircuitTransportFactory.php

# 5. Ajouter les routes dans api.php
modify: edugestdz/backend/routes/api.php
# → Ajouter le bloc Route::prefix('transport') dans le groupe auth:api

# 6. Créer les tests
create: edugestdz/backend/tests/Feature/Api/TransportTest.php

# 7. Lancer la migration
php artisan migrate

# 8. Lancer les tests
php artisan test --parallel
# → Attendu : tests précédents + 13 nouveaux = 245+ tests verts

# 9. Si tout est vert
git add .
git commit -m "feat: M09 Transport scolaire — Circuits + Arrêts + Inscriptions + Pointage bus + 13 tests"
git push origin develop

# 10. Ouvrir PR develop → main sur GitHub
```

---

## CE QUE TU DIS À DEEPSEEK

```
Repo : https://github.com/Allintelligence2024/edugest-dz.git
Branche : develop
Attends que PR #2 (contenant P1+P2+P3) soit mergée dans main, puis :
git checkout develop && git pull origin main

Fichier : MISSION_P4_TRANSPORT_SCOLAIRE.md — 10 étapes dans l'ordre.
php artisan test --parallel → 245+ tests verts requis.
0 régression tolérée.
PR develop → main à la fin.
```
