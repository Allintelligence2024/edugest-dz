# 🤖 MISSION DEEPSEEK — Option C : Paie Personnel + Billets Entrée/Sortie
## EduGest DZ · Branche : develop · 30 Juin 2026
## Tests actuels : 260+ ✅ · Objectif : 278+ ✅

---

## CONTEXTE — Ce qui EXISTE (ne pas recréer)

### Paie enseignants
- `app/Services/PaieService.php` → calcul IRG/CNAS correct (barème algérien 5 tranches)
- `app/Http/Controllers/Api/V1/PaieController.php` → **PROBLÈME** : utilise `montant * 0.91` au lieu du vrai PaieService
- `resources/views/pdf/bulletin_paie.blade.php` → template PDF fiche de paie enseignant ✅
- Routes `/api/v1/paies/*` → déjà déclarées ✅

### Personnel non-enseignant
- `app/Models/PersonnelNonEnseignant.php` → `salaire_base`, `type_contrat`, `num_cnas`, `num_ss`
- `app/Models/PointagePersonnel.php` → heures travaillées calculables
- `app/Models/CongePersonnel.php` → absences approuvées
- **MANQUE** : service calcul paie + template PDF + routes

### Billets
- `app/Models/AbsenceJournaliere.php` → retard/absent/demi_journée ✅
- `app/Models/PointageEnseignant.php` → pointage enseignants ✅
- **MANQUE** : tout — modèle Billet, template PDF, controller, routes

---

## PARTIE 1 — CORRIGER PaieController + Paie Personnel Non-Enseignant

---

### ÉTAPE 1 — Corriger PaieController.php (incohérence calcul)

**Modifier :** `edugestdz/backend/app/Http/Controllers/Api/V1/PaieController.php`

Remplacer la méthode `calculer()` entière par :

```php
public function calculer(Request $request): JsonResponse
{
    $validated = $request->validate([
        'enseignant_id' => 'required|uuid|exists:enseignants,id',
        'mois'          => 'required|integer|between:1,12',
        'annee'         => 'required|integer|min:2020',
    ]);

    $enseignant = Enseignant::with('contratsActifs')->findOrFail($validated['enseignant_id']);

    // ✅ Utiliser le vrai PaieService (IRG + CNAS barème algérien)
    $calcul = $this->service->calculerPaie($enseignant, $validated['mois'], $validated['annee']);

    $paie = Paie::updateOrCreate(
        [
            'enseignant_id' => $enseignant->id,
            'mois'          => $validated['mois'],
            'annee'         => $validated['annee'],
        ],
        array_merge($calcul, ['tenant_id' => config('tenant.current_id')])
    );

    return response()->json([
        'success' => true,
        'message' => 'Paie calculée avec IRG et CNAS algériens',
        'data'    => $paie->load('enseignant'),
    ], 201);
}
```

S'assurer que le constructeur du controller injecte le service :
```php
public function __construct(private readonly PaieService $service) {}
```

---

### ÉTAPE 2 — Créer PaiePersonnelService

**Créer :** `edugestdz/backend/app/Services/PaiePersonnelService.php`

```php
<?php

namespace App\Services;

use App\Models\CongePersonnel;
use App\Models\PaiePersonnel;
use App\Models\PersonnelNonEnseignant;
use App\Models\PointagePersonnel;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PaiePersonnelService
{
    // Barème IRG algérien 2024 (même que enseignants)
    private array $baremeIRG = [
        ['min' => 0,      'max' => 20000,  'taux' => 0,  'deduction' => 0],
        ['min' => 20001,  'max' => 40000,  'taux' => 23, 'deduction' => 4600],
        ['min' => 40001,  'max' => 80000,  'taux' => 27, 'deduction' => 6200],
        ['min' => 80001,  'max' => 160000, 'taux' => 30, 'deduction' => 8600],
        ['min' => 160001, 'max' => 320000, 'taux' => 33, 'deduction' => 13400],
        ['min' => 320001, 'max' => null,   'taux' => 35, 'deduction' => 19800],
    ];

    public function calculerPaie(PersonnelNonEnseignant $agent, int $mois, int $annee): array
    {
        $debut = Carbon::create($annee, $mois, 1)->startOfMonth();
        $fin   = $debut->copy()->endOfMonth();

        // Jours travaillés réels (depuis pointage)
        $joursTravailles = PointagePersonnel::where('agent_id', $agent->id)
            ->whereBetween('date', [$debut->toDateString(), $fin->toDateString()])
            ->whereIn('statut', ['present', 'retard'])
            ->count();

        // Jours ouvrables du mois (hors vendredi/samedi en Algérie)
        $joursOuvrables = 0;
        $current = $debut->copy();
        while ($current->lte($fin)) {
            if (!in_array($current->dayOfWeek, [5, 6])) { // vendredi=5, samedi=6
                $joursOuvrables++;
            }
            $current->addDay();
        }

        // Calcul salaire selon type contrat
        $salaireBrut = match ($agent->type_contrat) {
            'journalier' => $joursTravailles * ($agent->salaire_base / 26), // 26 jours/mois
            'vacataire'  => $joursTravailles * ($agent->salaire_base),       // salaire_base = taux journalier
            default      => (float) $agent->salaire_base,                    // CDI/CDD = fixe mensuel
        };

        // Retenues pour absences injustifiées (CDI/CDD uniquement)
        $absencesInjustifiees = PointagePersonnel::where('agent_id', $agent->id)
            ->whereBetween('date', [$debut->toDateString(), $fin->toDateString()])
            ->where('statut', 'absent')
            ->where('impact_paie', true)
            ->sum('retenue_dzd');

        $salaireBrut = max(0, $salaireBrut - $absencesInjustifiees);

        // CNAS 9% (si affilié)
        $cnas = $agent->num_cnas ? round($salaireBrut * 0.09, 2) : 0.0;

        // IRG
        $baseImposable = $salaireBrut - $cnas;
        $irg = $this->calculerIRG($baseImposable);

        // Net
        $salaireNet = max(0, round($salaireBrut - $cnas - $irg, 2));

        return [
            'agent_id'            => $agent->id,
            'mois'                => $mois,
            'annee'               => $annee,
            'salaire_base'        => round($salaireBrut, 2),
            'jours_travailles'    => $joursTravailles,
            'jours_ouvrables'     => $joursOuvrables,
            'retenues_absences'   => round($absencesInjustifiees, 2),
            'cnas'                => $cnas,
            'irg'                 => $irg,
            'salaire_net'         => $salaireNet,
            'statut'              => 'brouillon',
        ];
    }

    private function calculerIRG(float $base): float
    {
        if ($base <= 0) return 0.0;
        foreach ($this->baremeIRG as $tranche) {
            if ($tranche['max'] === null || $base <= $tranche['max']) {
                return max(0.0, round(($base * $tranche['taux'] / 100) - $tranche['deduction'], 2));
            }
        }
        $last = end($this->baremeIRG);
        return max(0.0, round(($base * $last['taux'] / 100) - $last['deduction'], 2));
    }

    public function genererPDF(PaiePersonnel $paie): string
    {
        $paie->load('agent');
        $tenant  = Tenant::find($paie->tenant_id);
        $moisNom = Carbon::create($paie->annee, $paie->mois)->translatedFormat('F Y');

        $pdf = Pdf::loadView('pdf.paie_personnel', [
            'paie'    => $paie,
            'agent'   => $paie->agent,
            'tenant'  => $tenant,
            'moisNom' => $moisNom,
            'detail'  => [
                'taux_cnas'      => '9%',
                'base_imposable' => max(0, $paie->salaire_base - $paie->cnas),
                'smig'           => '20 000 DA',
            ],
        ])->setPaper('A4', 'portrait');

        $path = "paies_personnel/{$paie->tenant_id}/{$paie->annee}/{$paie->mois}/{$paie->agent->matricule}.pdf";
        Storage::disk('public')->put($path, $pdf->output());
        $paie->update(['fichier_url' => $path]);
        return $path;
    }

    public function calculerTousMois(int $mois, int $annee): array
    {
        $agents    = PersonnelNonEnseignant::actifs()->get();
        $resultats = [];

        DB::transaction(function () use ($agents, $mois, $annee, &$resultats) {
            foreach ($agents as $agent) {
                $calcul = $this->calculerPaie($agent, $mois, $annee);
                $paie   = PaiePersonnel::updateOrCreate(
                    ['agent_id' => $agent->id, 'mois' => $mois, 'annee' => $annee],
                    array_merge($calcul, ['tenant_id' => $agent->tenant_id])
                );
                $resultats[] = [
                    'agent'   => $agent->nom_complet,
                    'poste'   => $agent->poste_affiche,
                    'paie_id' => $paie->id,
                    'net'     => $calcul['salaire_net'],
                ];
            }
        });

        return $resultats;
    }
}
```

---

### ÉTAPE 3 — Modèle PaiePersonnel

**Créer :** `edugestdz/backend/app/Models/PaiePersonnel.php`

```php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaiePersonnel extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'paies_personnel';

    protected $fillable = [
        'tenant_id', 'agent_id', 'mois', 'annee',
        'salaire_base', 'jours_travailles', 'jours_ouvrables',
        'retenues_absences', 'cnas', 'irg', 'salaire_net',
        'statut', 'date_paiement', 'fichier_url',
    ];

    protected $casts = [
        'salaire_base'      => 'decimal:2',
        'retenues_absences' => 'decimal:2',
        'cnas'              => 'decimal:2',
        'irg'               => 'decimal:2',
        'salaire_net'       => 'decimal:2',
        'date_paiement'     => 'date',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(PersonnelNonEnseignant::class, 'agent_id');
    }
}
```

---

### ÉTAPE 4 — Migration paies_personnel

**Créer :** `edugestdz/backend/database/migrations/2026_06_30_100000_create_paies_personnel_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paies_personnel', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();
            $table->uuid('agent_id');

            $table->unsignedTinyInteger('mois');
            $table->unsignedSmallInteger('annee');
            $table->decimal('salaire_base', 10, 2)->default(0);
            $table->unsignedSmallInteger('jours_travailles')->default(0);
            $table->unsignedSmallInteger('jours_ouvrables')->default(26);
            $table->decimal('retenues_absences', 10, 2)->default(0);
            $table->decimal('cnas', 10, 2)->default(0);
            $table->decimal('irg', 10, 2)->default(0);
            $table->decimal('salaire_net', 10, 2)->default(0);
            $table->enum('statut', ['brouillon', 'valide', 'paye'])->default('brouillon');
            $table->date('date_paiement')->nullable();
            $table->string('fichier_url', 500)->nullable();

            $table->timestamps();

            $table->foreign('agent_id')
                ->references('id')->on('personnel_non_enseignant')
                ->onDelete('cascade');
            $table->unique(['tenant_id', 'agent_id', 'mois', 'annee']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paies_personnel');
    }
};
```

---

### ÉTAPE 5 — Controller PaiePersonnelController

**Créer :** `edugestdz/backend/app/Http/Controllers/Api/V1/PaiePersonnelController.php`

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\PaiePersonnel;
use App\Models\PersonnelNonEnseignant;
use App\Services\PaiePersonnelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaiePersonnelController extends BaseApiController
{
    public function __construct(private readonly PaiePersonnelService $service) {}

    // GET /api/v1/personnel/paies
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mois'     => 'nullable|integer|between:1,12',
            'annee'    => 'nullable|integer|min:2020',
            'statut'   => 'nullable|in:brouillon,valide,paye',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $paginator = PaiePersonnel::with('agent:id,nom,prenom,poste,matricule')
            ->when($validated['mois'] ?? null, fn($q, $m) => $q->where('mois', $m))
            ->when($validated['annee'] ?? null, fn($q, $a) => $q->where('annee', $a))
            ->when($validated['statut'] ?? null, fn($q, $s) => $q->where('statut', $s))
            ->orderByDesc('annee')->orderByDesc('mois')
            ->paginate($validated['per_page'] ?? 20);

        return $this->paginatedResponse($paginator, 'Paies personnel récupérées');
    }

    // POST /api/v1/personnel/paies/calculer
    public function calculer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'agent_id' => 'required|uuid|exists:personnel_non_enseignant,id',
            'mois'     => 'required|integer|between:1,12',
            'annee'    => 'required|integer|min:2020',
        ]);

        $agent  = PersonnelNonEnseignant::findOrFail($validated['agent_id']);
        $calcul = $this->service->calculerPaie($agent, $validated['mois'], $validated['annee']);

        $paie = PaiePersonnel::updateOrCreate(
            ['agent_id' => $agent->id, 'mois' => $validated['mois'], 'annee' => $validated['annee']],
            array_merge($calcul, ['tenant_id' => config('tenant.current_id')])
        );

        return $this->created([
            'paie'  => $paie->load('agent'),
            'detail'=> $calcul,
        ], "Paie calculée : {$agent->nom_complet} — Net : " . number_format($calcul['salaire_net'], 2) . " DA");
    }

    // POST /api/v1/personnel/paies/calculer-tous
    public function calculerTous(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mois'  => 'required|integer|between:1,12',
            'annee' => 'required|integer|min:2020',
        ]);

        $resultats = $this->service->calculerTousMois($validated['mois'], $validated['annee']);

        return $this->success([
            'resultats' => $resultats,
            'total'     => count($resultats),
            'masse_salariale' => collect($resultats)->sum('net'),
        ], count($resultats) . " paie(s) calculée(s)");
    }

    // PUT /api/v1/personnel/paies/{id}/valider
    public function valider(string $id): JsonResponse
    {
        $paie = PaiePersonnel::findOrFail($id);
        $paie->update(['statut' => 'valide']);
        return $this->success($paie->fresh('agent'), 'Paie validée');
    }

    // PUT /api/v1/personnel/paies/{id}/payer
    public function payer(string $id): JsonResponse
    {
        $paie = PaiePersonnel::findOrFail($id);
        $paie->update(['statut' => 'paye', 'date_paiement' => today()]);
        return $this->success($paie->fresh('agent'), 'Paie marquée comme payée');
    }

    // GET /api/v1/personnel/paies/{id}/pdf
    public function pdf(string $id)
    {
        $paie = PaiePersonnel::with('agent')->findOrFail($id);

        if ($paie->fichier_url && \Storage::disk('public')->exists($paie->fichier_url)) {
            return response()->download(storage_path('app/public/' . $paie->fichier_url));
        }

        $path = $this->service->genererPDF($paie);
        return response()->download(storage_path('app/public/' . $path));
    }
}
```

---

### ÉTAPE 6 — Template PDF fiche de paie personnel

**Créer :** `edugestdz/backend/resources/views/pdf/paie_personnel.blade.php`

```blade
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; margin: 0; padding: 20px; }
  .header { display: flex; justify-content: space-between; border-bottom: 2px solid #1e40af; padding-bottom: 12px; margin-bottom: 16px; }
  .logo-zone h2 { color: #1e40af; font-size: 16px; margin: 0; }
  .logo-zone p  { margin: 2px 0; font-size: 10px; color: #555; }
  .titre { text-align: center; background: #1e40af; color: #fff; padding: 10px; font-size: 14px; font-weight: bold; margin-bottom: 16px; border-radius: 4px; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
  th { background: #e8f0fe; color: #1e40af; text-align: left; padding: 7px 10px; font-size: 10px; text-transform: uppercase; }
  td { padding: 6px 10px; border-bottom: 1px solid #e5e7eb; font-size: 11px; }
  .net-box { background: #1e40af; color: #fff; text-align: center; padding: 16px; border-radius: 6px; margin: 16px 0; }
  .net-box .montant { font-size: 24px; font-weight: bold; }
  .net-box .label   { font-size: 11px; margin-bottom: 4px; opacity: 0.85; }
  .signatures { display: flex; justify-content: space-between; margin-top: 40px; }
  .sig-box { text-align: center; width: 45%; }
  .sig-line { border-top: 1px solid #333; margin-top: 50px; padding-top: 6px; font-size: 10px; color: #555; }
  .mention { font-size: 9px; color: #888; text-align: center; margin-top: 20px; }
  .badge { display: inline-block; background: #dbeafe; color: #1e40af; padding: 2px 8px; border-radius: 20px; font-size: 10px; }
</style>
</head>
<body>

<div class="header">
  <div class="logo-zone">
    <h2>{{ $tenant->nom_etablissement ?? 'Établissement' }}</h2>
    <p>{{ $tenant->adresse ?? '' }}</p>
    <p>NIF : {{ $tenant->nif ?? '—' }} | NIS : {{ $tenant->nis ?? '—' }}</p>
  </div>
  <div style="text-align:right;">
    <p style="font-size:10px;color:#555;">N° Fiche : PAIE-{{ strtoupper($agent->matricule ?? 'XXX') }}-{{ $paie->mois }}-{{ $paie->annee }}</p>
    <p style="font-size:10px;color:#555;">Émise le : {{ now()->format('d/m/Y') }}</p>
  </div>
</div>

<div class="titre">FICHE DE PAIE — {{ strtoupper($moisNom) }}</div>

<!-- Identité agent -->
<table>
  <tr><th colspan="4">Informations Agent</th></tr>
  <tr>
    <td><strong>Nom & Prénom</strong></td>
    <td>{{ $agent->nom_complet }}</td>
    <td><strong>Poste</strong></td>
    <td>{{ $agent->poste_affiche }} <span class="badge">{{ $agent->type_contrat }}</span></td>
  </tr>
  <tr>
    <td><strong>Matricule</strong></td>
    <td>{{ $agent->matricule ?? '—' }}</td>
    <td><strong>N° CNAS</strong></td>
    <td>{{ $agent->num_cnas ?? 'Non affilié' }}</td>
  </tr>
  <tr>
    <td><strong>Date embauche</strong></td>
    <td>{{ $agent->date_embauche?->format('d/m/Y') ?? '—' }}</td>
    <td><strong>Ancienneté</strong></td>
    <td>{{ $agent->anciennete_ans }} an(s)</td>
  </tr>
</table>

<!-- Présence -->
<table>
  <tr><th colspan="4">Présence & Activité</th></tr>
  <tr>
    <td><strong>Jours ouvrables</strong></td>
    <td>{{ $paie->jours_ouvrables }} jours</td>
    <td><strong>Jours travaillés</strong></td>
    <td>{{ $paie->jours_travailles }} jours</td>
  </tr>
  @if($paie->retenues_absences > 0)
  <tr>
    <td><strong>Absences injustifiées</strong></td>
    <td colspan="3" style="color:#dc2626;">− {{ number_format($paie->retenues_absences, 2) }} DA</td>
  </tr>
  @endif
</table>

<!-- Calcul paie -->
<table>
  <tr><th colspan="2">Détail du Salaire</th></tr>
  <tr><td>Salaire brut</td><td style="text-align:right;"><strong>{{ number_format($paie->salaire_base, 2) }} DA</strong></td></tr>
  @if($paie->cnas > 0)
  <tr><td>Cotisation CNAS salariale ({{ $detail['taux_cnas'] }})</td><td style="text-align:right;color:#dc2626;">− {{ number_format($paie->cnas, 2) }} DA</td></tr>
  @endif
  <tr><td>Base imposable IRG</td><td style="text-align:right;">{{ number_format($detail['base_imposable'], 2) }} DA</td></tr>
  <tr><td>Impôt sur le Revenu Global (IRG)</td><td style="text-align:right;color:#dc2626;">− {{ number_format($paie->irg, 2) }} DA</td></tr>
</table>

<div class="net-box">
  <div class="label">NET À PAYER</div>
  <div class="montant">{{ number_format($paie->salaire_net, 2) }} DA</div>
  <div style="font-size:10px;margin-top:4px;opacity:0.8;">SMIG mensuel Algérie : {{ $detail['smig'] }}</div>
</div>

<div class="signatures">
  <div class="sig-box">
    <div class="sig-line">Signature de l'agent</div>
    <p style="font-size:10px;margin-top:6px;">{{ $agent->nom_complet }}</p>
  </div>
  <div class="sig-box">
    <div class="sig-line">Cachet & Signature employeur</div>
    <p style="font-size:10px;margin-top:6px;">{{ $tenant->nom_etablissement ?? '' }}</p>
  </div>
</div>

<p class="mention">Document à conserver sans limitation de durée · EduGest DZ</p>
</body>
</html>
```

---

### ÉTAPE 7 — Ajouter routes paie personnel dans api.php

**Modifier :** `edugestdz/backend/routes/api.php`

Dans le bloc `Route::prefix('personnel')` existant, ajouter **après** les routes congés :

```php
// Paies personnel non-enseignant
Route::get('paies',                    [\App\Http\Controllers\Api\V1\PaiePersonnelController::class, 'index']);
Route::post('paies/calculer',          [\App\Http\Controllers\Api\V1\PaiePersonnelController::class, 'calculer']);
Route::post('paies/calculer-tous',     [\App\Http\Controllers\Api\V1\PaiePersonnelController::class, 'calculerTous']);
Route::put('paies/{id}/valider',       [\App\Http\Controllers\Api\V1\PaiePersonnelController::class, 'valider']);
Route::put('paies/{id}/payer',         [\App\Http\Controllers\Api\V1\PaiePersonnelController::class, 'payer']);
Route::get('paies/{id}/pdf',           [\App\Http\Controllers\Api\V1\PaiePersonnelController::class, 'pdf']);
```

---

## PARTIE 2 — BILLETS D'ENTRÉE / RETARD / SORTIE

---

### ÉTAPE 8 — Migration billets

**Créer :** `edugestdz/backend/database/migrations/2026_06_30_200000_create_billets_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billets', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id')->index();
            $table->uuid('eleve_id');

            $table->enum('type', [
                'retard',          // arrivée tardive — billet de retard
                'sortie_autorisee',// sortie avant fin des cours — autorisation parentale
                'convocation',     // convocation parent
                'entree_exceptionnelle', // entrée après absence — justification
            ]);

            $table->date('date_billet');
            $table->time('heure')->nullable();           // heure du retard ou sortie
            $table->string('motif', 300)->nullable();    // motif déclaré
            $table->boolean('parent_prevenu')->default(false);
            $table->uuid('etabli_par')->nullable();      // user qui l'a créé
            $table->string('fichier_url', 500)->nullable();
            $table->text('note')->nullable();

            $table->timestamps();

            $table->foreign('eleve_id')->references('id')->on('eleves')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billets');
    }
};
```

---

### ÉTAPE 9 — Modèle Billet

**Créer :** `edugestdz/backend/app/Models/Billet.php`

```php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Billet extends BaseModel
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'eleve_id', 'type', 'date_billet',
        'heure', 'motif', 'parent_prevenu', 'etabli_par',
        'fichier_url', 'note',
    ];

    protected $casts = [
        'date_billet'    => 'date',
        'parent_prevenu' => 'boolean',
    ];

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    public function etabliPar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'etabli_par');
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'retard'                  => 'Billet de Retard',
            'sortie_autorisee'        => 'Autorisation de Sortie',
            'convocation'             => 'Convocation Parent',
            'entree_exceptionnelle'   => 'Entrée Exceptionnelle',
            default                   => 'Billet',
        };
    }
}
```

---

### ÉTAPE 10 — Controller BilletController

**Créer :** `edugestdz/backend/app/Http/Controllers/Api/V1/BilletController.php`

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Billet;
use App\Models\Eleve;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BilletController extends BaseApiController
{
    // GET /api/v1/billets
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type'     => 'nullable|in:retard,sortie_autorisee,convocation,entree_exceptionnelle',
            'date'     => 'nullable|date',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $paginator = Billet::with('eleve:id,nom,prenom,niveau_scolaire,photo_url')
            ->when($validated['type'] ?? null, fn($q, $t) => $q->where('type', $t))
            ->when($validated['date'] ?? null, fn($q, $d) => $q->whereDate('date_billet', $d))
            ->orderByDesc('date_billet')
            ->paginate($validated['per_page'] ?? 20);

        return $this->paginatedResponse($paginator, 'Billets récupérés');
    }

    // POST /api/v1/billets — Créer et générer le PDF immédiatement
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'eleve_id'       => 'required|uuid|exists:eleves,id',
            'type'           => 'required|in:retard,sortie_autorisee,convocation,entree_exceptionnelle',
            'date_billet'    => 'nullable|date',
            'heure'          => 'nullable|date_format:H:i',
            'motif'          => 'nullable|string|max:300',
            'parent_prevenu' => 'nullable|boolean',
            'note'           => 'nullable|string|max:500',
        ]);

        $validated['date_billet'] = $validated['date_billet'] ?? today()->toDateString();
        $validated['etabli_par']  = auth()->id();

        $billet = Billet::create($validated);

        // Générer le PDF immédiatement
        $path = $this->genererPDF($billet->load('eleve'));
        $billet->update(['fichier_url' => $path]);

        return $this->created([
            'billet'      => $billet->fresh('eleve'),
            'type_label'  => $billet->type_label,
            'fichier_url' => $path,
            'pdf_link'    => "/api/v1/billets/{$billet->id}/pdf",
        ], "{$billet->type_label} créé pour {$billet->eleve->prenom} {$billet->eleve->nom}");
    }

    // GET /api/v1/billets/{id}/pdf — Télécharger le billet
    public function pdf(string $id)
    {
        $billet = Billet::with('eleve')->findOrFail($id);

        if ($billet->fichier_url && Storage::disk('public')->exists($billet->fichier_url)) {
            return response()->download(storage_path('app/public/' . $billet->fichier_url));
        }

        $path = $this->genererPDF($billet);
        return response()->download(storage_path('app/public/' . $path));
    }

    // GET /api/v1/billets/eleve/{eleveId} — Historique billets d'un élève
    public function parEleve(string $eleveId): JsonResponse
    {
        $eleve   = Eleve::findOrFail($eleveId);
        $billets = Billet::where('eleve_id', $eleveId)
            ->orderByDesc('date_billet')
            ->get();

        return $this->success([
            'eleve'   => ['id' => $eleve->id, 'nom' => $eleve->nom, 'prenom' => $eleve->prenom],
            'billets' => $billets,
            'stats'   => [
                'retards'   => $billets->where('type', 'retard')->count(),
                'sorties'   => $billets->where('type', 'sortie_autorisee')->count(),
                'total'     => $billets->count(),
            ],
        ]);
    }

    // ── Générateur PDF interne ──
    private function genererPDF(Billet $billet): string
    {
        $eleve  = $billet->eleve;
        $tenant = app('tenant') ?? Tenant::find(config('tenant.current_id'));

        $pdf = Pdf::loadView('pdf.billet', [
            'billet' => $billet,
            'eleve'  => $eleve,
            'tenant' => $tenant,
        ])->setPaper([0, 0, 595, 300], 'landscape'); // format demi-page A4

        $path = "billets/{$billet->tenant_id}/{$billet->date_billet}/{$billet->id}.pdf";
        Storage::disk('public')->put($path, $pdf->output());
        return $path;
    }
}
```

---

### ÉTAPE 11 — Template PDF billet (tous types)

**Créer :** `edugestdz/backend/resources/views/pdf/billet.blade.php`

```blade
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 11px; padding: 16px 24px; }

  /* ── En-tête ── */
  .top { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #1e40af; padding-bottom: 10px; margin-bottom: 12px; }
  .etab h3 { font-size: 13px; color: #1e40af; margin-bottom: 2px; }
  .etab p  { font-size: 9px; color: #555; }
  .type-badge {
    padding: 6px 16px; border-radius: 4px; font-size: 12px; font-weight: bold;
    text-align: center;
  }

  /* Couleurs par type */
  @php
    $colors = [
      'retard'               => ['bg' => '#fef3c7', 'border' => '#f59e0b', 'text' => '#92400e'],
      'sortie_autorisee'     => ['bg' => '#dbeafe', 'border' => '#3b82f6', 'text' => '#1e3a8a'],
      'convocation'          => ['bg' => '#fee2e2', 'border' => '#ef4444', 'text' => '#7f1d1d'],
      'entree_exceptionnelle'=> ['bg' => '#d1fae5', 'border' => '#10b981', 'text' => '#064e3b'],
    ];
    $c = $colors[$billet->type] ?? ['bg' => '#f3f4f6', 'border' => '#6b7280', 'text' => '#111827'];
  @endphp

  .type-badge { background: {{ $c['bg'] }}; border: 2px solid {{ $c['border'] }}; color: {{ $c['text'] }}; }

  /* ── Corps ── */
  .grid2 { display: flex; gap: 20px; margin-bottom: 10px; }
  .col { flex: 1; }
  .field { margin-bottom: 7px; }
  .field label { font-size: 9px; color: #6b7280; text-transform: uppercase; display: block; }
  .field span  { font-size: 12px; font-weight: bold; }

  /* ── Motif ── */
  .motif-box { border: 1px solid #e5e7eb; border-radius: 4px; padding: 8px 12px; margin-bottom: 10px; min-height: 36px; }
  .motif-box label { font-size: 9px; color: #6b7280; text-transform: uppercase; display: block; margin-bottom: 4px; }

  /* ── Signatures ── */
  .sigs { display: flex; justify-content: space-between; margin-top: 12px; }
  .sig  { text-align: center; width: 30%; }
  .sig-line { border-top: 1px solid #333; margin-top: 30px; padding-top: 4px; font-size: 9px; color: #555; }

  /* ── Pied de page ── */
  .footer { text-align: center; font-size: 8px; color: #9ca3af; margin-top: 10px; border-top: 1px solid #e5e7eb; padding-top: 6px; }

  .numero { font-size: 9px; color: #9ca3af; }
</style>
</head>
<body>

<div class="top">
  <div class="etab">
    <h3>{{ $tenant->nom_etablissement ?? 'Établissement' }}</h3>
    <p>{{ $tenant->adresse ?? '' }} | Tél : {{ $tenant->telephone ?? '—' }}</p>
  </div>
  <div style="text-align:center;">
    <div class="type-badge">{{ strtoupper($billet->type_label) }}</div>
    <p class="numero" style="margin-top:4px;">N° {{ strtoupper(substr($billet->id, 0, 8)) }}</p>
  </div>
  <div style="text-align:right;">
    <p style="font-size:9px;color:#555;">Date : {{ $billet->date_billet->format('d/m/Y') }}</p>
    @if($billet->heure)
    <p style="font-size:9px;color:#555;">Heure : {{ \Carbon\Carbon::createFromTimeString($billet->heure)->format('H:i') }}</p>
    @endif
  </div>
</div>

<div class="grid2">
  <div class="col">
    <div class="field">
      <label>Nom & Prénom</label>
      <span>{{ strtoupper($eleve->nom) }} {{ ucfirst($eleve->prenom) }}</span>
    </div>
    <div class="field">
      <label>Niveau</label>
      <span>{{ $eleve->niveau_scolaire ?? '—' }}</span>
    </div>
  </div>
  <div class="col">
    <div class="field">
      <label>N° Inscription</label>
      <span>{{ $eleve->numero_inscription ?? '—' }}</span>
    </div>
    <div class="field">
      <label>Parent prévenu</label>
      <span>{{ $billet->parent_prevenu ? '✓ Oui' : '✗ Non' }}</span>
    </div>
  </div>
</div>

<div class="motif-box">
  <label>
    @if($billet->type === 'retard') Motif du retard
    @elseif($billet->type === 'sortie_autorisee') Motif de la sortie
    @elseif($billet->type === 'convocation') Objet de la convocation
    @else Motif
    @endif
  </label>
  {{ $billet->motif ?? 'Non précisé' }}
</div>

@if($billet->note)
<div class="motif-box" style="font-size:10px;color:#555;">
  <label>Observations</label>
  {{ $billet->note }}
</div>
@endif

<div class="sigs">
  <div class="sig">
    <div class="sig-line">Signature Direction</div>
  </div>
  <div class="sig">
    <div class="sig-line">Signature Parent / Tuteur</div>
  </div>
  <div class="sig">
    <div class="sig-line">Signature Élève</div>
  </div>
</div>

<div class="footer">
  EduGest DZ — {{ $tenant->nom_etablissement ?? '' }} — Billet généré le {{ now()->format('d/m/Y à H:i') }}
</div>

</body>
</html>
```

---

### ÉTAPE 12 — Routes billets (ajouter dans api.php)

**Modifier :** `edugestdz/backend/routes/api.php`

Ajouter dans le groupe `middleware(['auth:api', 'resolve.tenant', 'check.subscription'])` :

```php
// ── Billets (entrée / retard / sortie / convocation) ──
Route::prefix('billets')->group(function () {
    Route::get('/',                    [\App\Http\Controllers\Api\V1\BilletController::class, 'index']);
    Route::post('/',                   [\App\Http\Controllers\Api\V1\BilletController::class, 'store']);
    Route::get('{id}/pdf',             [\App\Http\Controllers\Api\V1\BilletController::class, 'pdf']);
    Route::get('eleve/{eleveId}',      [\App\Http\Controllers\Api\V1\BilletController::class, 'parEleve']);
});
```

---

### ÉTAPE 13 — Tests

**Créer :** `edugestdz/backend/tests/Feature/Api/PaiePersonnelTest.php`

```php
<?php

namespace Tests\Feature\Api;

use App\Models\PaiePersonnel;
use App\Models\PersonnelNonEnseignant;
use App\Models\PointagePersonnel;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaiePersonnelTest extends TestCase
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

    public function test_calculer_paie_personnel(): void
    {
        $agent = PersonnelNonEnseignant::factory()->create([
            'tenant_id'    => $this->tenant->id,
            'salaire_base' => 35000,
            'type_contrat' => 'CDI',
            'statut'       => 'actif',
        ]);

        $this->withToken($this->token)
            ->postJson('/api/v1/personnel/paies/calculer', [
                'agent_id' => $agent->id,
                'mois'     => now()->month,
                'annee'    => now()->year,
            ])
            ->assertStatus(201)
            ->assertJsonStructure(['data' => ['paie', 'detail' => ['salaire_base', 'cnas', 'irg', 'salaire_net']]]);
    }

    public function test_paie_cdi_inclut_irg_cnas(): void
    {
        $agent = PersonnelNonEnseignant::factory()->create([
            'tenant_id'    => $this->tenant->id,
            'salaire_base' => 50000,
            'type_contrat' => 'CDI',
            'num_cnas'     => '123456789',
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/personnel/paies/calculer', [
                'agent_id' => $agent->id,
                'mois'     => now()->month,
                'annee'    => now()->year,
            ])
            ->assertStatus(201);

        // CNAS 9% de 50000 = 4500
        $this->assertEquals('4500.00', $response->json('data.detail.cnas'));
        // Net < Brut
        $this->assertLessThan(50000, $response->json('data.detail.salaire_net'));
    }

    public function test_valider_paie(): void
    {
        $agent = PersonnelNonEnseignant::factory()->create(['tenant_id' => $this->tenant->id]);
        $paie  = PaiePersonnel::create([
            'tenant_id' => $this->tenant->id,
            'agent_id'  => $agent->id,
            'mois'      => now()->month,
            'annee'     => now()->year,
            'salaire_base' => 30000,
            'salaire_net'  => 27000,
            'statut'       => 'brouillon',
        ]);

        $this->withToken($this->token)
            ->putJson("/api/v1/personnel/paies/{$paie->id}/valider")
            ->assertStatus(200)
            ->assertJsonPath('data.statut', 'valide');
    }

    public function test_isolation_tenant_paie_personnel(): void
    {
        $autreTenant = Tenant::factory()->create();
        $autreAgent  = PersonnelNonEnseignant::factory()->create(['tenant_id' => $autreTenant->id]);
        $autrePaie   = PaiePersonnel::create([
            'tenant_id' => $autreTenant->id, 'agent_id' => $autreAgent->id,
            'mois' => now()->month, 'annee' => now()->year,
            'salaire_base' => 30000, 'salaire_net' => 27000, 'statut' => 'brouillon',
        ]);

        $this->withToken($this->token)
            ->putJson("/api/v1/personnel/paies/{$autrePaie->id}/valider")
            ->assertStatus(404);
    }
}
```

**Créer :** `edugestdz/backend/tests/Feature/Api/BilletTest.php`

```php
<?php

namespace Tests\Feature\Api;

use App\Models\Billet;
use App\Models\Eleve;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BilletTest extends TestCase
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

    public function test_creer_billet_retard(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->postJson('/api/v1/billets', [
                'eleve_id'    => $eleve->id,
                'type'        => 'retard',
                'heure'       => '08:45',
                'motif'       => 'Embouteillages',
                'date_billet' => today()->toDateString(),
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.type_label', 'Billet de Retard');

        $this->assertDatabaseHas('billets', [
            'eleve_id' => $eleve->id,
            'type'     => 'retard',
        ]);
    }

    public function test_creer_billet_sortie_autorisee(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->postJson('/api/v1/billets', [
                'eleve_id'       => $eleve->id,
                'type'           => 'sortie_autorisee',
                'heure'          => '14:30',
                'motif'          => 'Rendez-vous médical',
                'parent_prevenu' => true,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.type_label', 'Autorisation de Sortie');
    }

    public function test_creer_convocation(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->postJson('/api/v1/billets', [
                'eleve_id' => $eleve->id,
                'type'     => 'convocation',
                'motif'    => 'Comportement en classe',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.type_label', 'Convocation Parent');
    }

    public function test_liste_billets_filtree_par_tenant(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        Billet::create([
            'tenant_id'  => $this->tenant->id,
            'eleve_id'   => $eleve->id,
            'type'       => 'retard',
            'date_billet'=> today(),
        ]);

        $autreTenant = Tenant::factory()->create();
        $autreEleve  = Eleve::factory()->create(['tenant_id' => $autreTenant->id]);
        Billet::create([
            'tenant_id'  => $autreTenant->id,
            'eleve_id'   => $autreEleve->id,
            'type'       => 'retard',
            'date_billet'=> today(),
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v1/billets')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_historique_billets_eleve(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
        Billet::create(['tenant_id' => $this->tenant->id, 'eleve_id' => $eleve->id, 'type' => 'retard',           'date_billet' => today()]);
        Billet::create(['tenant_id' => $this->tenant->id, 'eleve_id' => $eleve->id, 'type' => 'sortie_autorisee', 'date_billet' => today()->subDay()]);

        $this->withToken($this->token)
            ->getJson("/api/v1/billets/eleve/{$eleve->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.stats.retards', 1)
            ->assertJsonPath('data.stats.sorties', 1)
            ->assertJsonPath('data.stats.total', 2);
    }

    public function test_validation_type_billet(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->postJson('/api/v1/billets', [
                'eleve_id' => $eleve->id,
                'type'     => 'type_invalide',
            ])
            ->assertStatus(422);
    }
}
```

---

## ORDRE D'EXÉCUTION DEEPSEEK

```bash
# 0. Synchroniser develop avec main
git checkout develop
git pull origin main

# ─── PARTIE 1 : PAIE PERSONNEL ───

# 1. Corriger PaieController.php (méthode calculer)
modify: edugestdz/backend/app/Http/Controllers/Api/V1/PaieController.php

# 2. Créer PaiePersonnelService.php
create: edugestdz/backend/app/Services/PaiePersonnelService.php

# 3. Créer modèle PaiePersonnel.php
create: edugestdz/backend/app/Models/PaiePersonnel.php

# 4. Créer migration paies_personnel
create: edugestdz/backend/database/migrations/2026_06_30_100000_create_paies_personnel_table.php

# 5. Créer PaiePersonnelController.php
create: edugestdz/backend/app/Http/Controllers/Api/V1/PaiePersonnelController.php

# 6. Créer template PDF fiche de paie personnel
create: edugestdz/backend/resources/views/pdf/paie_personnel.blade.php

# 7. Ajouter routes paie personnel dans api.php
modify: edugestdz/backend/routes/api.php

# ─── PARTIE 2 : BILLETS ───

# 8. Créer migration billets
create: edugestdz/backend/database/migrations/2026_06_30_200000_create_billets_table.php

# 9. Créer modèle Billet.php
create: edugestdz/backend/app/Models/Billet.php

# 10. Créer BilletController.php
create: edugestdz/backend/app/Http/Controllers/Api/V1/BilletController.php

# 11. Créer template PDF billet
create: edugestdz/backend/resources/views/pdf/billet.blade.php

# 12. Ajouter routes billets dans api.php
modify: edugestdz/backend/routes/api.php

# ─── TESTS ───

# 13. Créer PaiePersonnelTest.php
create: edugestdz/backend/tests/Feature/Api/PaiePersonnelTest.php

# 14. Créer BilletTest.php
create: edugestdz/backend/tests/Feature/Api/BilletTest.php

# ─── VALIDATION ───

# 15. Lancer les migrations
php artisan migrate

# 16. Lancer tous les tests
php artisan test --parallel
# → Attendu : 260 + 9 (PaiePersonnel) + 6 (Billet) = 275+ tests verts

# 17. Si tout est vert
git add .
git commit -m "feat: Paie personnel non-enseignant (IRG/CNAS) + Billets entrée/retard/sortie + 15 tests"
git push origin develop

# 18. Ouvrir PR develop → main sur GitHub
```

---

## CE QUE TU DIS À DEEPSEEK

```
Repo : https://github.com/Allintelligence2024/edugest-dz.git
Branche : develop
git checkout develop && git pull origin main

Fichier : MISSION_C_PAIE_PERSONNEL_BILLETS.md
18 étapes dans l'ordre — 2 parties.

Partie 1 : Paie personnel non-enseignant (IRG + CNAS barème algérien)
Partie 2 : Billets PDF (retard / sortie autorisée / convocation / entrée)

php artisan test --parallel → 275+ tests verts requis.
0 régression tolérée.
PR develop → main à la fin.
```
