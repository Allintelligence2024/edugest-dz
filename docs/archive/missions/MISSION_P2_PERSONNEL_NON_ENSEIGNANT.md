# 🤖 MISSION DEEPSEEK — Priorité 2 : M12 Personnel Non-Enseignant
## EduGest DZ · Branche : develop · 29 Juin 2026
## Tests actuels : 204+ ✅ (après merge PR #2) · Objectif : 220+ ✅

---

## CONTEXTE

### Priorité 2 selon l'architecture finale EduGest DZ
M12 = Gestion du personnel non-enseignant :
- Femmes de ménage
- Surveillants
- Chauffeurs (liés au futur M09 Transport)
- Proviseur / Directeur adjoint
- Secrétaires administratives

### Ce qui EXISTE déjà (ne pas recréer)
- `app/Models/Enseignant.php` — modèle enseignant complet (référence de style)
- `app/Models/Contrat.php` — modèle contrats enseignants (à réutiliser pour personnel)
- `app/Models/Paie.php` — modèle paies enseignants (même logique IRG/CNAS)
- `app/Services/PaieService.php` — calcul IRG + CNAS algérien (à étendre)
- `app/Traits/BelongsToTenant.php` — isolation tenant
- `app/Models/BaseModel.php` — UUID auto
- `app/Http/Controllers/Api/BaseApiController.php` — helpers réponse
- Migrations 0001→2026_06_29 — dernière migration numérotée à vérifier

### Ce qui MANQUE (à créer — M12 complet)
```
Migration : create_personnel_non_enseignant_tables
Migration : create_pointage_personnel_table
Migration : create_conges_personnel_table

Models :
  PersonnelNonEnseignant.php
  PointagePersonnel.php
  CongePersonnel.php

Controllers :
  PersonnelController.php
  PointagePersonnelController.php

Factories :
  PersonnelNonEnseignantFactory.php

Tests :
  Feature/Api/PersonnelTest.php

Routes : bloc personnel dans api.php
```

---

## ÉTAPE 1 — Migration personnel_non_enseignant

**Créer :** `edugestdz/backend/database/migrations/2026_06_29_400000_create_personnel_non_enseignant_tables.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personnel_non_enseignant', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();

            // Identité
            $table->string('nom', 100);
            $table->string('prenom', 100);
            $table->string('telephone', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('adresse', 300)->nullable();
            $table->string('photo_url', 500)->nullable();
            $table->date('date_naissance')->nullable();

            // Poste
            $table->enum('poste', [
                'femme_menage',
                'surveillant',
                'chauffeur',
                'proviseur',
                'directeur_adjoint',
                'secretaire',
                'technicien',
                'agent_securite',
                'autre',
            ]);
            $table->string('poste_libelle', 100)->nullable(); // personnalisable si "autre"

            // Contrat
            $table->enum('type_contrat', ['CDI', 'CDD', 'vacataire', 'stagiaire'])->default('CDI');
            $table->date('date_embauche');
            $table->date('date_fin_contrat')->nullable();
            $table->decimal('salaire_base', 10, 2)->default(0);
            $table->enum('frequence_paie', ['mensuel', 'hebdo', 'journalier'])->default('mensuel');

            // Statut
            $table->enum('statut', ['actif', 'inactif', 'suspendu'])->default('actif');
            $table->string('matricule', 30)->nullable()->unique();

            // Documents
            $table->string('num_ss', 30)->nullable();    // numéro sécurité sociale
            $table->string('num_cnas', 30)->nullable();  // CNAS Algérie

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')
                ->references('id')->on('tenants')
                ->onDelete('cascade');
        });

        // ── Pointage personnel non-enseignant ──
        Schema::create('pointage_personnel', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();
            $table->uuid('agent_id');

            $table->date('date');
            $table->time('heure_arrivee')->nullable();
            $table->time('heure_depart')->nullable();
            $table->enum('methode', ['badge', 'manuel'])->default('manuel');
            $table->string('badge_uid', 100)->nullable();
            $table->enum('statut', [
                'present', 'absent', 'retard', 'conge', 'maladie', 'demi_journee'
            ])->default('present');
            $table->boolean('impact_paie')->default(false);
            $table->decimal('retenue_dzd', 10, 2)->default(0);
            $table->text('note')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('agent_id')
                ->references('id')->on('personnel_non_enseignant')
                ->onDelete('cascade');
            $table->unique(['tenant_id', 'agent_id', 'date']);
        });

        // ── Congés et absences planifiées ──
        Schema::create('conges_personnel', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();
            $table->uuid('agent_id');

            $table->date('date_debut');
            $table->date('date_fin');
            $table->integer('nb_jours')->default(1);
            $table->enum('type', ['conge_annuel', 'maladie', 'maternite', 'sans_solde', 'autre'])
                  ->default('conge_annuel');
            $table->text('motif')->nullable();
            $table->string('document_url', 500)->nullable();
            $table->enum('statut', ['en_attente', 'approuve', 'refuse'])->default('en_attente');
            $table->uuid('approuve_par')->nullable();
            $table->timestamp('approuve_at')->nullable();

            $table->timestamps();

            $table->foreign('agent_id')
                ->references('id')->on('personnel_non_enseignant')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conges_personnel');
        Schema::dropIfExists('pointage_personnel');
        Schema::dropIfExists('personnel_non_enseignant');
    }
};
```

---

## ÉTAPE 2 — Modèle PersonnelNonEnseignant

**Créer :** `edugestdz/backend/app/Models/PersonnelNonEnseignant.php`

```php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class PersonnelNonEnseignant extends BaseModel
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'personnel_non_enseignant';

    protected $fillable = [
        'tenant_id', 'nom', 'prenom', 'telephone', 'email',
        'adresse', 'photo_url', 'date_naissance', 'poste',
        'poste_libelle', 'type_contrat', 'date_embauche',
        'date_fin_contrat', 'salaire_base', 'frequence_paie',
        'statut', 'matricule', 'num_ss', 'num_cnas',
    ];

    protected $casts = [
        'date_naissance'   => 'date',
        'date_embauche'    => 'date',
        'date_fin_contrat' => 'date',
        'salaire_base'     => 'decimal:2',
    ];

    // ── Accesseurs ──
    public function getNomCompletAttribute(): string
    {
        return strtoupper($this->nom) . ' ' . ucfirst($this->prenom);
    }

    public function getPosteAfficheAttribute(): string
    {
        if ($this->poste === 'autre' && $this->poste_libelle) {
            return $this->poste_libelle;
        }
        return match ($this->poste) {
            'femme_menage'     => 'Femme de ménage',
            'surveillant'      => 'Surveillant(e)',
            'chauffeur'        => 'Chauffeur',
            'proviseur'        => 'Proviseur',
            'directeur_adjoint'=> 'Directeur adjoint',
            'secretaire'       => 'Secrétaire',
            'technicien'       => 'Technicien',
            'agent_securite'   => 'Agent de sécurité',
            default            => ucfirst($this->poste),
        };
    }

    public function getAncienneteAnsAttribute(): int
    {
        return $this->date_embauche
            ? (int) $this->date_embauche->diffInYears(now())
            : 0;
    }

    // ── Relations ──
    public function pointages(): HasMany
    {
        return $this->hasMany(PointagePersonnel::class, 'agent_id');
    }

    public function pointageAujourdhui(): HasOne
    {
        return $this->hasOne(PointagePersonnel::class, 'agent_id')
            ->whereDate('date', today());
    }

    public function conges(): HasMany
    {
        return $this->hasMany(CongePersonnel::class, 'agent_id');
    }

    // ── Scopes ──
    public function scopeActifs($query)
    {
        return $query->where('statut', 'actif');
    }

    public function scopePoste($query, string $poste)
    {
        return $query->where('poste', $poste);
    }

    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('nom', 'ILIKE', "%{$search}%")
              ->orWhere('prenom', 'ILIKE', "%{$search}%")
              ->orWhere('matricule', 'ILIKE', "%{$search}%");
        });
    }

    // ── Méthodes métier ──
    public function isPresent(): bool
    {
        return $this->pointageAujourdhui()
            ->whereNotNull('heure_arrivee')
            ->exists();
    }

    public function soldeCongesRestants(int $annee = null): int
    {
        $annee ??= now()->year;
        $droit  = 30; // jours de congé annuel en Algérie (droit légal)
        $pris   = $this->conges()
            ->where('type', 'conge_annuel')
            ->where('statut', 'approuve')
            ->whereYear('date_debut', $annee)
            ->sum('nb_jours');
        return max(0, $droit - $pris);
    }
}
```

---

## ÉTAPE 3 — Modèle PointagePersonnel

**Créer :** `edugestdz/backend/app/Models/PointagePersonnel.php`

```php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PointagePersonnel extends BaseModel
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'pointage_personnel';

    protected $fillable = [
        'tenant_id', 'agent_id', 'date',
        'heure_arrivee', 'heure_depart',
        'methode', 'badge_uid', 'statut',
        'impact_paie', 'retenue_dzd', 'note',
    ];

    protected $casts = [
        'date'        => 'date',
        'impact_paie' => 'boolean',
        'retenue_dzd' => 'decimal:2',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(PersonnelNonEnseignant::class, 'agent_id');
    }

    public function getDureeTravailleeAttribute(): ?int
    {
        if (!$this->heure_arrivee || !$this->heure_depart) return null;
        $debut = \Carbon\Carbon::createFromTimeString($this->heure_arrivee);
        $fin   = \Carbon\Carbon::createFromTimeString($this->heure_depart);
        return max(0, $debut->diffInMinutes($fin));
    }
}
```

---

## ÉTAPE 4 — Modèle CongePersonnel

**Créer :** `edugestdz/backend/app/Models/CongePersonnel.php`

```php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CongePersonnel extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'conges_personnel';

    protected $fillable = [
        'tenant_id', 'agent_id', 'date_debut', 'date_fin',
        'nb_jours', 'type', 'motif', 'document_url',
        'statut', 'approuve_par', 'approuve_at',
    ];

    protected $casts = [
        'date_debut'  => 'date',
        'date_fin'    => 'date',
        'approuve_at' => 'datetime',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(PersonnelNonEnseignant::class, 'agent_id');
    }

    public function approbateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approuve_par');
    }
}
```

---

## ÉTAPE 5 — Controller PersonnelController

**Créer :** `edugestdz/backend/app/Http/Controllers/Api/V1/PersonnelController.php`

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\CongePersonnel;
use App\Models\PersonnelNonEnseignant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Gestion du personnel non-enseignant.
 *
 * GET    /api/v1/personnel               → liste paginée
 * POST   /api/v1/personnel               → créer un agent
 * GET    /api/v1/personnel/{id}          → fiche complète
 * PUT    /api/v1/personnel/{id}          → modifier
 * DELETE /api/v1/personnel/{id}          → supprimer (soft)
 * GET    /api/v1/personnel/{id}/conges   → liste congés
 * POST   /api/v1/personnel/{id}/conges   → demande de congé
 * PUT    /api/v1/personnel/conges/{id}/statut → approuver / refuser
 * GET    /api/v1/personnel/tableau-bord  → vue synthétique du jour
 */
class PersonnelController extends BaseApiController
{
    // ── Liste ────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search'   => 'nullable|string|max:100',
            'poste'    => 'nullable|string',
            'statut'   => 'nullable|in:actif,inactif,suspendu',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $query = PersonnelNonEnseignant::query();

        if (!empty($validated['search'])) {
            $query->search($validated['search']);
        }
        if (!empty($validated['poste'])) {
            $query->poste($validated['poste']);
        }
        if (!empty($validated['statut'])) {
            $query->where('statut', $validated['statut']);
        }

        $paginator = $query->orderBy('nom')->paginate($validated['per_page'] ?? 20);

        $stats = [
            'total'          => PersonnelNonEnseignant::count(),
            'actifs'         => PersonnelNonEnseignant::actifs()->count(),
            'par_poste'      => PersonnelNonEnseignant::actifs()
                ->selectRaw('poste, COUNT(*) as total')
                ->groupBy('poste')
                ->pluck('total', 'poste'),
        ];

        return $this->paginatedResponse($paginator, 'Personnel récupéré', ['stats' => $stats]);
    }

    // ── Créer ────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nom'              => 'required|string|max:100',
            'prenom'           => 'required|string|max:100',
            'poste'            => 'required|in:femme_menage,surveillant,chauffeur,proviseur,directeur_adjoint,secretaire,technicien,agent_securite,autre',
            'poste_libelle'    => 'nullable|string|max:100',
            'type_contrat'     => 'nullable|in:CDI,CDD,vacataire,stagiaire',
            'date_embauche'    => 'required|date',
            'salaire_base'     => 'required|numeric|min:0',
            'telephone'        => 'nullable|string|max:20',
            'email'            => 'nullable|email|max:150',
            'date_naissance'   => 'nullable|date',
            'date_fin_contrat' => 'nullable|date|after:date_embauche',
            'num_ss'           => 'nullable|string|max:30',
            'num_cnas'         => 'nullable|string|max:30',
        ]);

        $validated['matricule'] = $this->genererMatricule($validated['poste']);

        $agent = PersonnelNonEnseignant::create($validated);

        return $this->created(
            $agent,
            "Agent {$agent->nom_complet} créé ({$agent->poste_affiche})"
        );
    }

    // ── Fiche ─────────────────────────────────────────
    public function show(string $id): JsonResponse
    {
        $agent = PersonnelNonEnseignant::with([
            'pointages' => fn($q) => $q->orderByDesc('date')->limit(30),
            'conges'    => fn($q) => $q->orderByDesc('date_debut')->limit(10),
        ])->findOrFail($id);

        return $this->success([
            'agent'             => $agent,
            'poste_affiche'     => $agent->poste_affiche,
            'anciennete_ans'    => $agent->anciennete_ans,
            'solde_conges'      => $agent->soldeCongesRestants(),
            'present_aujourdhui'=> $agent->isPresent(),
            'stats_mois'        => $this->statsMois($agent),
        ]);
    }

    // ── Modifier ──────────────────────────────────────
    public function update(Request $request, string $id): JsonResponse
    {
        $agent = PersonnelNonEnseignant::findOrFail($id);

        $validated = $request->validate([
            'nom'              => 'sometimes|string|max:100',
            'prenom'           => 'sometimes|string|max:100',
            'poste'            => 'sometimes|in:femme_menage,surveillant,chauffeur,proviseur,directeur_adjoint,secretaire,technicien,agent_securite,autre',
            'poste_libelle'    => 'nullable|string|max:100',
            'type_contrat'     => 'sometimes|in:CDI,CDD,vacataire,stagiaire',
            'date_embauche'    => 'sometimes|date',
            'salaire_base'     => 'sometimes|numeric|min:0',
            'telephone'        => 'nullable|string|max:20',
            'email'            => 'nullable|email|max:150',
            'statut'           => 'sometimes|in:actif,inactif,suspendu',
            'date_fin_contrat' => 'nullable|date',
            'num_ss'           => 'nullable|string|max:30',
            'num_cnas'         => 'nullable|string|max:30',
        ]);

        $agent->update($validated);

        return $this->success($agent->fresh(), 'Agent mis à jour');
    }

    // ── Supprimer ─────────────────────────────────────
    public function destroy(string $id): JsonResponse
    {
        $agent = PersonnelNonEnseignant::findOrFail($id);
        $nom   = $agent->nom_complet;
        $agent->delete();

        return $this->success(null, "{$nom} supprimé");
    }

    // ── Congés : liste ────────────────────────────────
    public function conges(string $id): JsonResponse
    {
        $agent  = PersonnelNonEnseignant::findOrFail($id);
        $conges = CongePersonnel::where('agent_id', $agent->id)
            ->orderByDesc('date_debut')
            ->get();

        return $this->success([
            'agent'          => ['id' => $agent->id, 'nom' => $agent->nom_complet],
            'conges'         => $conges,
            'solde_restant'  => $agent->soldeCongesRestants(),
            'droit_annuel'   => 30,
        ]);
    }

    // ── Congés : demande ──────────────────────────────
    public function demanderConge(Request $request, string $id): JsonResponse
    {
        $agent = PersonnelNonEnseignant::findOrFail($id);

        $validated = $request->validate([
            'date_debut' => 'required|date|after_or_equal:today',
            'date_fin'   => 'required|date|after_or_equal:date_debut',
            'type'       => 'required|in:conge_annuel,maladie,maternite,sans_solde,autre',
            'motif'      => 'nullable|string|max:500',
        ]);

        $nbJours = \Carbon\Carbon::parse($validated['date_debut'])
            ->diffInWeekdays(\Carbon\Carbon::parse($validated['date_fin'])) + 1;

        // Vérifier solde restant pour congé annuel
        if ($validated['type'] === 'conge_annuel') {
            $solde = $agent->soldeCongesRestants();
            if ($nbJours > $solde) {
                return $this->error(
                    "Solde insuffisant : {$solde} jour(s) disponible(s), {$nbJours} demandé(s)",
                    'SOLDE_INSUFFISANT',
                    422
                );
            }
        }

        $conge = CongePersonnel::create([
            'tenant_id'  => config('tenant.current_id'),
            'agent_id'   => $agent->id,
            'date_debut' => $validated['date_debut'],
            'date_fin'   => $validated['date_fin'],
            'nb_jours'   => $nbJours,
            'type'       => $validated['type'],
            'motif'      => $validated['motif'] ?? null,
            'statut'     => 'en_attente',
        ]);

        return $this->created([
            'conge'  => $conge,
            'agent'  => ['nom' => $agent->nom_complet],
            'nb_jours' => $nbJours,
        ], "Demande de congé soumise ({$nbJours} jour(s))");
    }

    // ── Congés : approuver / refuser ──────────────────
    public function statuerConge(Request $request, string $congeId): JsonResponse
    {
        $validated = $request->validate([
            'statut' => 'required|in:approuve,refuse',
            'motif'  => 'nullable|string|max:300',
        ]);

        $conge = CongePersonnel::with('agent')->findOrFail($congeId);

        if ($conge->statut !== 'en_attente') {
            return $this->error(
                'Ce congé a déjà été traité',
                'DEJA_TRAITE',
                409
            );
        }

        $conge->update([
            'statut'      => $validated['statut'],
            'approuve_par'=> auth()->id(),
            'approuve_at' => now(),
        ]);

        $action = $validated['statut'] === 'approuve' ? 'approuvé' : 'refusé';

        return $this->success([
            'conge' => $conge->fresh(),
            'agent' => ['nom' => $conge->agent->nom_complet],
        ], "Congé {$action}");
    }

    // ── Tableau de bord du jour ───────────────────────
    public function tableauBord(): JsonResponse
    {
        $today  = today();
        $agents = PersonnelNonEnseignant::actifs()
            ->with(['pointageAujourdhui'])
            ->get();

        $data = $agents->map(function (PersonnelNonEnseignant $a) {
            $p = $a->pointageAujourdhui;
            return [
                'agent'         => [
                    'id'     => $a->id,
                    'nom'    => $a->nom_complet,
                    'poste'  => $a->poste_affiche,
                    'photo'  => $a->photo_url,
                ],
                'statut'        => $p?->statut ?? 'absent',
                'heure_arrivee' => $p?->heure_arrivee ? substr($p->heure_arrivee, 0, 5) : null,
                'heure_depart'  => $p?->heure_depart  ? substr($p->heure_depart, 0, 5)  : null,
                'pointe'        => (bool) $p?->heure_arrivee,
            ];
        });

        // Regrouper par poste
        $parPoste = $data->groupBy(fn($a) => $a['agent']['poste'])->map->values();

        return $this->success([
            'date'     => $today->format('d/m/Y'),
            'par_poste'=> $parPoste,
            'stats'    => [
                'total'    => $agents->count(),
                'presents' => $data->where('statut', 'present')->count(),
                'retards'  => $data->where('statut', 'retard')->count(),
                'absents'  => $data->where('pointe', false)->count(),
            ],
        ], "Tableau de bord personnel — {$today->format('d/m/Y')}");
    }

    // ── Helpers privés ────────────────────────────────
    private function genererMatricule(string $poste): string
    {
        $prefixes = [
            'femme_menage'     => 'FM',
            'surveillant'      => 'SU',
            'chauffeur'        => 'CH',
            'proviseur'        => 'PR',
            'directeur_adjoint'=> 'DA',
            'secretaire'       => 'SE',
            'technicien'       => 'TC',
            'agent_securite'   => 'AS',
            'autre'            => 'AG',
        ];

        $prefix = $prefixes[$poste] ?? 'AG';
        $annee  = now()->year;

        $last = PersonnelNonEnseignant::withoutGlobalScope('tenant')
            ->where('matricule', 'LIKE', "{$prefix}-{$annee}-%")
            ->orderByDesc('matricule')
            ->value('matricule');

        $seq = $last ? ((int) substr($last, -3)) + 1 : 1;

        return sprintf('%s-%d-%03d', $prefix, $annee, $seq);
    }

    private function statsMois(PersonnelNonEnseignant $agent): array
    {
        $debut = now()->startOfMonth()->toDateString();
        $fin   = today()->toDateString();

        $pointages = $agent->pointages()
            ->whereBetween('date', [$debut, $fin])
            ->get();

        return [
            'jours_travailles' => $pointages->whereNotNull('heure_arrivee')->count(),
            'absences'         => $pointages->where('statut', 'absent')->count(),
            'retards'          => $pointages->where('statut', 'retard')->count(),
        ];
    }
}
```

---

## ÉTAPE 6 — Controller PointagePersonnelController

**Créer :** `edugestdz/backend/app/Http/Controllers/Api/V1/PointagePersonnelController.php`

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\PersonnelNonEnseignant;
use App\Models\PointagePersonnel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pointage du personnel non-enseignant.
 *
 * POST /api/v1/personnel/{id}/pointer/arrivee
 * POST /api/v1/personnel/{id}/pointer/depart
 * GET  /api/v1/personnel/{id}/pointer/historique
 */
class PointagePersonnelController extends BaseApiController
{
    public function arrivee(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'heure_arrivee' => 'nullable|date_format:H:i',
            'methode'       => 'nullable|in:badge,manuel',
            'note'          => 'nullable|string|max:300',
        ]);

        $agent = PersonnelNonEnseignant::findOrFail($id);
        $today = today();
        $heure = $validated['heure_arrivee'] ?? now()->format('H:i');

        $dejaPointe = PointagePersonnel::where('agent_id', $agent->id)
            ->whereDate('date', $today)
            ->whereNotNull('heure_arrivee')
            ->exists();

        if ($dejaPointe) {
            return $this->error(
                "Arrivée déjà enregistrée pour {$agent->nom_complet}",
                'DEJA_POINTE',
                409
            );
        }

        // Retard si après 8h00 pour le personnel
        $heureLimite = config('etablissement.heure_limite_retard_personnel', '08:00');
        $enRetard    = strcmp($heure . ':00', $heureLimite . ':00') > 0;

        $pointage = PointagePersonnel::create([
            'tenant_id'     => config('tenant.current_id'),
            'agent_id'      => $agent->id,
            'date'          => $today,
            'heure_arrivee' => $heure . ':00',
            'methode'       => $validated['methode'] ?? 'manuel',
            'statut'        => $enRetard ? 'retard' : 'present',
            'note'          => $validated['note'] ?? null,
        ]);

        return $this->created([
            'pointage' => $pointage,
            'agent'    => ['nom' => $agent->nom_complet, 'poste' => $agent->poste_affiche],
            'statut'   => $pointage->statut,
        ], "Arrivée : {$agent->nom_complet} à {$heure}");
    }

    public function depart(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'heure_depart' => 'nullable|date_format:H:i',
            'note'         => 'nullable|string|max:300',
        ]);

        $agent    = PersonnelNonEnseignant::findOrFail($id);
        $today    = today();
        $heure    = $validated['heure_depart'] ?? now()->format('H:i');

        $pointage = PointagePersonnel::where('agent_id', $agent->id)
            ->whereDate('date', $today)
            ->first();

        if (!$pointage?->heure_arrivee) {
            return $this->error(
                "Enregistrez d'abord l'arrivée",
                'PAS_ARRIVEE',
                422
            );
        }

        if ($pointage->heure_depart) {
            return $this->error(
                'Départ déjà enregistré',
                'DEJA_POINTE',
                409
            );
        }

        $pointage->update(['heure_depart' => $heure . ':00']);
        $duree = $pointage->fresh()->duree_travaillee;

        return $this->success([
            'pointage'      => $pointage->fresh(),
            'duree_minutes' => $duree,
            'duree_affichee'=> $duree ? floor($duree / 60) . 'h' . ($duree % 60) . 'min' : null,
        ], "Départ : {$agent->nom_complet} à {$heure}");
    }

    public function historique(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'debut'    => 'nullable|date',
            'fin'      => 'nullable|date|after_or_equal:debut',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $agent  = PersonnelNonEnseignant::findOrFail($id);
        $debut  = $validated['debut'] ?? now()->startOfMonth()->toDateString();
        $fin    = $validated['fin']   ?? today()->toDateString();

        $paginator = PointagePersonnel::where('agent_id', $agent->id)
            ->whereBetween('date', [$debut, $fin])
            ->orderByDesc('date')
            ->paginate($validated['per_page'] ?? 30);

        $tous     = PointagePersonnel::where('agent_id', $agent->id)
            ->whereBetween('date', [$debut, $fin])
            ->get();

        $dureeTotal = $tous->sum(fn($p) => $p->duree_travaillee ?? 0);

        return $this->paginatedResponse($paginator, 'Historique pointage personnel', [
            'agent'  => ['nom' => $agent->nom_complet, 'poste' => $agent->poste_affiche],
            'periode'=> ['debut' => $debut, 'fin' => $fin],
            'stats'  => [
                'jours_travailles'     => $tous->whereNotNull('heure_arrivee')->count(),
                'presents'             => $tous->where('statut', 'present')->count(),
                'retards'              => $tous->where('statut', 'retard')->count(),
                'absents'              => $tous->where('statut', 'absent')->count(),
                'duree_totale_minutes' => $dureeTotal,
                'duree_totale_affichee'=> floor($dureeTotal / 60) . 'h' . ($dureeTotal % 60) . 'min',
            ],
        ]);
    }
}
```

---

## ÉTAPE 7 — Factory

**Créer :** `edugestdz/backend/database/factories/PersonnelNonEnseignantFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\PersonnelNonEnseignant;
use Illuminate\Database\Eloquent\Factories\Factory;

class PersonnelNonEnseignantFactory extends Factory
{
    protected $model = PersonnelNonEnseignant::class;

    public function definition(): array
    {
        $postes = [
            'femme_menage', 'surveillant', 'chauffeur',
            'secretaire', 'agent_securite', 'technicien',
        ];

        return [
            'nom'           => strtoupper($this->faker->lastName()),
            'prenom'        => $this->faker->firstName(),
            'poste'         => $this->faker->randomElement($postes),
            'type_contrat'  => $this->faker->randomElement(['CDI', 'CDD', 'vacataire']),
            'date_embauche' => $this->faker->dateTimeBetween('-5 years', '-1 month')->format('Y-m-d'),
            'salaire_base'  => $this->faker->numberBetween(25000, 80000),
            'telephone'     => '0' . $this->faker->numberBetween(5, 7) . $this->faker->numerify('########'),
            'statut'        => 'actif',
            'matricule'     => strtoupper($this->faker->bothify('AG-####-###')),
        ];
    }

    public function femme_menage(): static
    {
        return $this->state(['poste' => 'femme_menage']);
    }

    public function surveillant(): static
    {
        return $this->state(['poste' => 'surveillant']);
    }

    public function chauffeur(): static
    {
        return $this->state(['poste' => 'chauffeur']);
    }
}
```

---

## ÉTAPE 8 — Routes (ajouter dans api.php)

**Modifier :** `edugestdz/backend/routes/api.php`

Ajouter dans le groupe `middleware(['auth:api', 'resolve.tenant', 'check.subscription'])` :

```php
// ── Personnel Non-Enseignant (M12) ──
Route::prefix('personnel')->group(function () {
    Route::get('tableau-bord',           [\App\Http\Controllers\Api\V1\PersonnelController::class, 'tableauBord']);
    Route::get('/',                       [\App\Http\Controllers\Api\V1\PersonnelController::class, 'index']);
    Route::post('/',                      [\App\Http\Controllers\Api\V1\PersonnelController::class, 'store']);
    Route::get('{id}',                    [\App\Http\Controllers\Api\V1\PersonnelController::class, 'show']);
    Route::put('{id}',                    [\App\Http\Controllers\Api\V1\PersonnelController::class, 'update']);
    Route::delete('{id}',                 [\App\Http\Controllers\Api\V1\PersonnelController::class, 'destroy']);

    // Congés
    Route::get('{id}/conges',             [\App\Http\Controllers\Api\V1\PersonnelController::class, 'conges']);
    Route::post('{id}/conges',            [\App\Http\Controllers\Api\V1\PersonnelController::class, 'demanderConge']);
    Route::put('conges/{congeId}/statut', [\App\Http\Controllers\Api\V1\PersonnelController::class, 'statuerConge']);

    // Pointage
    Route::post('{id}/pointer/arrivee',   [\App\Http\Controllers\Api\V1\PointagePersonnelController::class, 'arrivee']);
    Route::post('{id}/pointer/depart',    [\App\Http\Controllers\Api\V1\PointagePersonnelController::class, 'depart']);
    Route::get('{id}/pointer/historique', [\App\Http\Controllers\Api\V1\PointagePersonnelController::class, 'historique']);
});
```

---

## ÉTAPE 9 — Tests

**Créer :** `edugestdz/backend/tests/Feature/Api/PersonnelTest.php`

```php
<?php

namespace Tests\Feature\Api;

use App\Models\PersonnelNonEnseignant;
use App\Models\PointagePersonnel;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonnelTest extends TestCase
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

    // ─── CRUD ────────────────────────────────────────

    public function test_creer_agent_valide(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/personnel', [
                'nom'           => 'BENALI',
                'prenom'        => 'Fatima',
                'poste'         => 'femme_menage',
                'type_contrat'  => 'CDI',
                'date_embauche' => '2024-01-15',
                'salaire_base'  => 30000,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.poste', 'femme_menage');

        $this->assertDatabaseHas('personnel_non_enseignant', [
            'nom'       => 'BENALI',
            'prenom'    => 'Fatima',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_liste_personnel_filtree_par_tenant(): void
    {
        PersonnelNonEnseignant::factory()->count(4)->create(['tenant_id' => $this->tenant->id]);

        $autreTenant = Tenant::factory()->create();
        PersonnelNonEnseignant::factory()->count(3)->create(['tenant_id' => $autreTenant->id]);

        $this->withToken($this->token)
            ->getJson('/api/v1/personnel')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 4); // pas 7
    }

    public function test_afficher_fiche_agent(): void
    {
        $agent = PersonnelNonEnseignant::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->getJson("/api/v1/personnel/{$agent->id}")
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['agent', 'poste_affiche', 'anciennete_ans', 'solde_conges'],
            ]);
    }

    public function test_modifier_agent(): void
    {
        $agent = PersonnelNonEnseignant::factory()->create([
            'tenant_id' => $this->tenant->id,
            'statut'    => 'actif',
        ]);

        $this->withToken($this->token)
            ->putJson("/api/v1/personnel/{$agent->id}", ['statut' => 'suspendu'])
            ->assertStatus(200)
            ->assertJsonPath('data.statut', 'suspendu');
    }

    public function test_supprimer_agent_soft_delete(): void
    {
        $agent = PersonnelNonEnseignant::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->deleteJson("/api/v1/personnel/{$agent->id}")
            ->assertStatus(200);

        $this->assertSoftDeleted('personnel_non_enseignant', ['id' => $agent->id]);
    }

    public function test_isolation_tenant_agent(): void
    {
        $autreTenant = Tenant::factory()->create();
        $autreAgent  = PersonnelNonEnseignant::factory()->create(['tenant_id' => $autreTenant->id]);

        $this->withToken($this->token)
            ->getJson("/api/v1/personnel/{$autreAgent->id}")
            ->assertStatus(404);
    }

    // ─── CONGÉS ──────────────────────────────────────

    public function test_demander_conge_valide(): void
    {
        $agent = PersonnelNonEnseignant::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->postJson("/api/v1/personnel/{$agent->id}/conges", [
                'date_debut' => today()->addDays(5)->toDateString(),
                'date_fin'   => today()->addDays(9)->toDateString(),
                'type'       => 'conge_annuel',
                'motif'      => 'Vacances',
            ])
            ->assertStatus(201)
            ->assertJsonStructure(['data' => ['conge', 'nb_jours']]);
    }

    public function test_approuver_conge(): void
    {
        $agent = PersonnelNonEnseignant::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->withToken($this->token)
            ->postJson("/api/v1/personnel/{$agent->id}/conges", [
                'date_debut' => today()->addDays(5)->toDateString(),
                'date_fin'   => today()->addDays(7)->toDateString(),
                'type'       => 'conge_annuel',
            ])
            ->assertStatus(201);

        $congeId = $response->json('data.conge.id');

        $this->withToken($this->token)
            ->putJson("/api/v1/personnel/conges/{$congeId}/statut", [
                'statut' => 'approuve',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.conge.statut', 'approuve');
    }

    // ─── POINTAGE ────────────────────────────────────

    public function test_arrivee_personnel_present(): void
    {
        $agent = PersonnelNonEnseignant::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->postJson("/api/v1/personnel/{$agent->id}/pointer/arrivee", [
                'heure_arrivee' => '07:45',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.statut', 'present');

        $this->assertDatabaseHas('pointage_personnel', [
            'agent_id' => $agent->id,
            'statut'   => 'present',
        ]);
    }

    public function test_arrivee_tardive_retard(): void
    {
        $agent = PersonnelNonEnseignant::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->postJson("/api/v1/personnel/{$agent->id}/pointer/arrivee", [
                'heure_arrivee' => '09:15',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.statut', 'retard');
    }

    public function test_double_arrivee_bloquee(): void
    {
        $agent = PersonnelNonEnseignant::factory()->create(['tenant_id' => $this->tenant->id]);

        PointagePersonnel::create([
            'tenant_id'     => $this->tenant->id,
            'agent_id'      => $agent->id,
            'date'          => today(),
            'heure_arrivee' => '08:00:00',
            'statut'        => 'present',
            'methode'       => 'manuel',
        ]);

        $this->withToken($this->token)
            ->postJson("/api/v1/personnel/{$agent->id}/pointer/arrivee", [
                'heure_arrivee' => '08:05',
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'DEJA_POINTE');
    }

    public function test_depart_sans_arrivee_bloque(): void
    {
        $agent = PersonnelNonEnseignant::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->postJson("/api/v1/personnel/{$agent->id}/pointer/depart", [
                'heure_depart' => '17:00',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'PAS_ARRIVEE');
    }

    public function test_tableau_bord_jour(): void
    {
        PersonnelNonEnseignant::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->getJson('/api/v1/personnel/tableau-bord')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['date', 'par_poste', 'stats']]);
    }

    public function test_historique_pointage_personnel(): void
    {
        $agent = PersonnelNonEnseignant::factory()->create(['tenant_id' => $this->tenant->id]);

        PointagePersonnel::create([
            'tenant_id'     => $this->tenant->id,
            'agent_id'      => $agent->id,
            'date'          => today(),
            'heure_arrivee' => '08:00:00',
            'statut'        => 'present',
            'methode'       => 'manuel',
        ]);

        $this->withToken($this->token)
            ->getJson("/api/v1/personnel/{$agent->id}/pointer/historique")
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonStructure(['meta' => ['stats']]);
    }
}
```

---

## ORDRE D'EXÉCUTION DEEPSEEK

```bash
# 0. S'assurer que PR #2 (PointageBadge controllers) est mergée dans main
#    Puis synchroniser develop
git checkout develop
git pull origin main

# 1. Créer la migration
create: edugestdz/backend/database/migrations/2026_06_29_400000_create_personnel_non_enseignant_tables.php

# 2. Créer les modèles
create: edugestdz/backend/app/Models/PersonnelNonEnseignant.php
create: edugestdz/backend/app/Models/PointagePersonnel.php
create: edugestdz/backend/app/Models/CongePersonnel.php

# 3. Créer les controllers
create: edugestdz/backend/app/Http/Controllers/Api/V1/PersonnelController.php
create: edugestdz/backend/app/Http/Controllers/Api/V1/PointagePersonnelController.php

# 4. Créer la factory
create: edugestdz/backend/database/factories/PersonnelNonEnseignantFactory.php

# 5. Ajouter les routes dans api.php
modify: edugestdz/backend/routes/api.php
# → Ajouter le bloc Route::prefix('personnel') dans le groupe auth:api

# 6. Créer les tests
create: edugestdz/backend/tests/Feature/Api/PersonnelTest.php

# 7. Lancer la migration
php artisan migrate

# 8. Lancer les tests
php artisan test --parallel
# → Attendu : tests précédents + 14 nouveaux = 219+ tests verts

# 9. Si tout est vert
git add .
git commit -m "feat: M12 Personnel non-enseignant — CRUD + Pointage + Congés + 14 tests"
git push origin develop

# 10. Ouvrir PR develop → main sur GitHub
```

---

## CE QUE TU DIS À DEEPSEEK

```
Repo : https://github.com/Allintelligence2024/edugest-dz.git
Branche : develop
S'assurer que PR #2 est mergée dans main avant de commencer.
Puis : git checkout develop && git pull origin main

Exécute les 10 étapes du fichier MISSION_P2_PERSONNEL_NON_ENSEIGNANT.md dans l'ordre.
Après l'étape 8 : php artisan test --parallel → 219+ tests verts requis.
0 régression tolérée. Si un test échoue, corrige avant de commit.
Créer la PR develop → main à la fin.
```
