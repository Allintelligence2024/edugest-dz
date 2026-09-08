# 🤖 MISSION DEEPSEEK — Rapports PDF + Simulation BEM/BAC
## EduGest DZ · Branche : develop · 3 Juillet 2026
## Tests actuels : 423+ ✅ · Objectif : ≥ 435 ✅ · 0 régression

---

## CONTEXTE

3 fonctionnalités manquantes identifiées dans l'audit :
1. **Rapport PDF mensuel absences** — récapitulatif par élève, exportable
2. **Simulation BEM** — calcul prévisionnelle basé sur les notes existantes
3. **Simulation BAC** — pondération officielle ONEC par filière

### RÈGLES
1. PostgreSQL uniquement — jamais SQLite
2. 0 régression
3. Réutiliser DomPDF (déjà dans composer via `barryvdh/laravel-dompdf`)

---

## ÉTAPE 0 — Synchroniser

```bash
git checkout develop && git pull origin main
cd edugestdz/backend
```

---

## ÉTAPE 1 — Vérifier DomPDF installé

```bash
composer require barryvdh/laravel-dompdf:^2.0
```

Si déjà installé, cette commande ne fait rien. Continuer.

---

## ÉTAPE 2 — RapportService

**Créer :** `edugestdz/backend/app/Services/RapportService.php`

```php
<?php

namespace App\Services;

use App\Models\Eleve;
use App\Models\AbsenceJournaliere;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Barryvdh\DomPDF\Facade\Pdf;

class RapportService
{
    /**
     * Générer le rapport PDF des absences du mois.
     */
    public function rapportAbsencesMensuel(int $mois, int $annee): \Barryvdh\DomPDF\PDF
    {
        $debut = Carbon::create($annee, $mois, 1)->startOfMonth();
        $fin   = $debut->copy()->endOfMonth();

        // Tous les élèves actifs du tenant
        $eleves = Eleve::where('statut', 'actif')
            ->with(['absencesJournalieres' => fn($q) =>
                $q->whereBetween('date_absence', [$debut, $fin])
                  ->orderBy('date_absence')
            ])
            ->orderBy('nom')
            ->get();

        // Calculer les stats par élève
        $data = $eleves->map(function (Eleve $eleve) {
            $absences     = $eleve->absencesJournalieres;
            $justifiees   = $absences->where('statut', 'justifiée')->count();
            $nonJustif    = $absences->where('statut', 'non_justifiée')->count();
            $enAttente    = $absences->where('statut', 'en_attente')->count();

            return [
                'eleve'          => $eleve,
                'total'          => $absences->count(),
                'justifiees'     => $justifiees,
                'non_justifiees' => $nonJustif,
                'en_attente'     => $enAttente,
                'dates'          => $absences->pluck('date_absence')->map(fn($d) =>
                    Carbon::parse($d)->format('d/m')
                )->implode(', '),
                'alerte'         => $absences->count() >= 3,
            ];
        })->filter(fn($d) => $d['total'] > 0); // Seulement les élèves avec absences

        $moisLabel = Carbon::create($annee, $mois, 1)->translatedFormat('F Y');

        $pdf = Pdf::loadView('pdf.rapport-absences', [
            'mois'        => $moisLabel,
            'debut'       => $debut->format('d/m/Y'),
            'fin'         => $fin->format('d/m/Y'),
            'data'        => $data,
            'total_eleves'=> $eleves->count(),
            'nb_absences' => $data->sum('total'),
            'genere_le'   => now()->format('d/m/Y à H:i'),
        ])->setPaper('a4', 'portrait');

        return $pdf;
    }

    /**
     * Simulation BEM — calcul prévisionnelle 4AM.
     * Coefficients officiels MEN (Ministère Education Nationale).
     */
    public function simulationBEM(string $eleveId): array
    {
        $eleve = Eleve::findOrFail($eleveId);

        // Coefficients BEM officiels
        $coefficients = [
            'Arabe'                   => 6,
            'Français'                => 4,
            'Mathématiques'           => 5,
            'Sciences Physiques'      => 3,
            'Sciences Naturelles'     => 2,
            'Histoire-Géographie'     => 2,
            'Éducation Islamique'     => 2,
            'Tamazight'               => 2, // optionnel
            'Anglais'                 => 2,
            'Éducation Technologique' => 1,
            'Arts Plastiques'         => 1,
            'Éducation Musicale'      => 1,
            'Éducation Physique'      => 1,
        ];

        return $this->calculerSimulation($eleve, $coefficients, 'BEM', '4AM');
    }

    /**
     * Simulation BAC — calcul prévisionnelle par filière.
     * Coefficients officiels ONEC BAC 2026.
     */
    public function simulationBAC(string $eleveId, string $filiere): array
    {
        $eleve = Eleve::findOrFail($eleveId);

        $coefficientsParFiliere = [
            'sciences' => [
                'Mathématiques'       => 6,
                'Sciences Physiques'  => 6,
                'Sciences Naturelles' => 5,
                'Arabe'               => 3,
                'Français'            => 3,
                'Anglais'             => 2,
                'Histoire-Géographie' => 2,
                'Philosophie'         => 2,
                'Éducation Islamique' => 1,
            ],
            'maths' => [
                'Mathématiques'       => 9,
                'Sciences Physiques'  => 7,
                'Sciences Naturelles' => 3,
                'Arabe'               => 3,
                'Français'            => 3,
                'Anglais'             => 2,
                'Philosophie'         => 2,
                'Éducation Islamique' => 1,
            ],
            'lettres_langues' => [
                'Arabe'               => 8,
                'Français'            => 6,
                'Anglais'             => 5,
                'Philosophie'         => 4,
                'Histoire-Géographie' => 4,
                'Éducation Islamique' => 2,
                'Mathématiques'       => 2,
                'Sciences Physiques'  => 1,
            ],
            'lettres_philo' => [
                'Arabe'               => 7,
                'Philosophie'         => 7,
                'Histoire-Géographie' => 5,
                'Français'            => 4,
                'Anglais'             => 3,
                'Sciences Islamiques' => 3,
                'Mathématiques'       => 2,
            ],
            'gestion_economie' => [
                'Mathématiques'       => 5,
                'Économie-Gestion'    => 7,
                'Comptabilité'        => 6,
                'Arabe'               => 4,
                'Français'            => 3,
                'Anglais'             => 3,
                'Histoire-Géographie' => 2,
            ],
            'technique_math' => [
                'Mathématiques'           => 8,
                'Sciences Physiques'      => 6,
                'Technologie-Mécanique'   => 6,
                'Arabe'                   => 3,
                'Français'                => 3,
                'Anglais'                 => 2,
                'Éducation Islamique'     => 1,
                'Philosophie'             => 1,
            ],
            'musique' => [
                'Éducation Musicale'  => 10,
                'Arabe'               => 5,
                'Français'            => 4,
                'Anglais'             => 3,
                'Mathématiques'       => 3,
                'Histoire-Géographie' => 2,
                'Philosophie'         => 2,
                'Éducation Islamique' => 1,
            ],
        ];

        $coefficients = $coefficientsParFiliere[$filiere]
            ?? $coefficientsParFiliere['sciences'];

        return $this->calculerSimulation($eleve, $coefficients, 'BAC', $filiere);
    }

    /**
     * Calcul commun pour BEM et BAC.
     */
    private function calculerSimulation(Eleve $eleve, array $coefficients, string $type, string $contexte): array
    {
        // Récupérer les dernières notes par matière
        $notes = \App\Models\Note::whereHas('evaluation', fn($q) =>
                $q->whereHas('groupe', fn($q2) =>
                    $q2->whereHas('inscriptions', fn($q3) =>
                        $q3->where('eleve_id', $eleve->id)->where('statut', 'validée')
                    )
                )
            )
            ->where('eleve_id', $eleve->id)
            ->whereNotNull('note')
            ->with('evaluation.matiere:id,nom_fr')
            ->get()
            ->groupBy('evaluation.matiere.nom_fr') // grouper par matière
            ->map(fn($notes) => round($notes->avg('note'), 2)); // moyenne par matière

        // Calculer la moyenne pondérée avec les coefficients
        $totalPondere = 0;
        $totalCoeff   = 0;
        $detail       = [];

        foreach ($coefficients as $matiere => $coeff) {
            $note = $notes[$matiere] ?? null;
            $detail[] = [
                'matiere'    => $matiere,
                'coefficient'=> $coeff,
                'note'       => $note,
                'points'     => $note !== null ? round($note * $coeff, 2) : null,
                'manquante'  => $note === null,
            ];

            if ($note !== null) {
                $totalPondere += $note * $coeff;
                $totalCoeff   += $coeff;
            }
        }

        $moyenne = $totalCoeff > 0 ? round($totalPondere / $totalCoeff, 2) : null;

        // Mention
        $mention = match (true) {
            $moyenne === null         => null,
            $moyenne >= 18            => 'Très Bien avec Félicitations',
            $moyenne >= 16            => 'Très Bien',
            $moyenne >= 14            => 'Bien',
            $moyenne >= 12            => 'Assez Bien',
            $moyenne >= 10            => 'Passable',
            default                   => 'Insuffisant',
        };

        $matieresSansNote = collect($detail)->where('manquante', true)->count();

        return [
            'eleve'               => [
                'id'     => $eleve->id,
                'nom'    => $eleve->nom_complet,
                'niveau' => $eleve->niveau_scolaire,
            ],
            'type'                => $type,
            'contexte'            => $contexte,
            'moyenne_simulee'     => $moyenne,
            'mention_simulee'     => $mention,
            'total_coefficients'  => array_sum($coefficients),
            'coefficients_couverts' => $totalCoeff,
            'matieres_sans_note'  => $matieresSansNote,
            'fiabilite'           => $matieresSansNote === 0 ? 'haute'
                : ($matieresSansNote <= 2 ? 'moyenne' : 'faible'),
            'detail'              => $detail,
            'avertissement'       => $matieresSansNote > 0
                ? "{$matieresSansNote} matière(s) sans notes — la simulation est approximative."
                : null,
        ];
    }
}
```

---

## ÉTAPE 3 — Vue Blade PDF absences

**Créer :** `edugestdz/backend/resources/views/pdf/rapport-absences.blade.php`

```html
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1e293b; padding: 20px; }
  .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #3b82f6; padding-bottom: 12px; }
  .header h1 { font-size: 18px; color: #3b82f6; font-weight: bold; }
  .header .sub { font-size: 11px; color: #64748b; margin-top: 4px; }
  .meta { display: flex; justify-content: space-between; margin-bottom: 16px; font-size: 10px; color: #475569; }
  .stats { display: flex; gap: 16px; margin-bottom: 16px; }
  .stat-box { flex: 1; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px; text-align: center; }
  .stat-val { font-size: 22px; font-weight: bold; color: #3b82f6; }
  .stat-lbl { font-size: 9px; color: #64748b; margin-top: 2px; }
  table { width: 100%; border-collapse: collapse; }
  th { background: #1e3a5f; color: #fff; padding: 8px 6px; text-align: left; font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px; }
  td { padding: 7px 6px; border-bottom: 1px solid #f1f5f9; font-size: 9px; }
  tr:nth-child(even) td { background: #f8fafc; }
  .alerte td { background: #fff7ed !important; }
  .badge { display: inline-block; padding: 2px 6px; border-radius: 10px; font-size: 8px; font-weight: bold; }
  .badge-rouge { background: #fee2e2; color: #b91c1c; }
  .badge-orange { background: #fff7ed; color: #c2410c; }
  .badge-vert { background: #dcfce7; color: #15803d; }
  .footer { margin-top: 20px; text-align: center; font-size: 8px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 10px; }
</style>
</head>
<body>

<div class="header">
  <h1>📋 Rapport Absences Mensuel</h1>
  <div class="sub">Période : {{ $debut }} → {{ $fin }} &nbsp;·&nbsp; {{ $mois }}</div>
</div>

<div class="meta">
  <span>Total élèves : <strong>{{ $total_eleves }}</strong></span>
  <span>Élèves avec absences : <strong>{{ $data->count() }}</strong></span>
  <span>Total absences : <strong>{{ $nb_absences }}</strong></span>
  <span>Généré le : {{ $genere_le }}</span>
</div>

@if($data->isEmpty())
  <p style="text-align:center; color:#64748b; margin-top:40px;">
    ✅ Aucune absence enregistrée pour cette période.
  </p>
@else
  <table>
    <thead>
      <tr>
        <th>Élève</th>
        <th>Niveau</th>
        <th>Total</th>
        <th>Justifiées</th>
        <th>Non justifiées</th>
        <th>En attente</th>
        <th>Dates</th>
        <th>Statut</th>
      </tr>
    </thead>
    <tbody>
      @foreach($data as $row)
      <tr class="{{ $row['alerte'] ? 'alerte' : '' }}">
        <td><strong>{{ $row['eleve']->nom }} {{ $row['eleve']->prenom }}</strong></td>
        <td>{{ $row['eleve']->niveau_scolaire }}</td>
        <td style="font-weight:bold; color:#1d4ed8">{{ $row['total'] }}</td>
        <td style="color:#15803d">{{ $row['justifiees'] }}</td>
        <td style="color:#b91c1c">{{ $row['non_justifiees'] }}</td>
        <td style="color:#c2410c">{{ $row['en_attente'] }}</td>
        <td style="font-size:8px; color:#475569">{{ $row['dates'] }}</td>
        <td>
          @if($row['alerte'])
            <span class="badge badge-rouge">⚠ Alerte</span>
          @elseif($row['non_justifiees'] > 0)
            <span class="badge badge-orange">À justifier</span>
          @else
            <span class="badge badge-vert">OK</span>
          @endif
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
@endif

<div class="footer">
  EduGest DZ — Plateforme SaaS de gestion scolaire &nbsp;·&nbsp; app.edugest.dz &nbsp;·&nbsp;
  Document généré automatiquement — ne pas modifier
</div>

</body>
</html>
```

---

## ÉTAPE 4 — RapportController : nouveaux endpoints

**Créer :** `edugestdz/backend/app/Http/Controllers/Api/V1/RapportController.php`

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\RapportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class RapportController extends Controller
{
    public function __construct(private RapportService $service) {}

    /**
     * @OA\Get(
     *     path="/api/v1/rapports/absences-pdf",
     *     summary="Rapport PDF mensuel des absences",
     *     tags={"Rapports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(ref="#/components/parameters/TenantId"),
     *     @OA\Parameter(name="mois",  in="query", required=false, @OA\Schema(type="integer", example=7)),
     *     @OA\Parameter(name="annee", in="query", required=false, @OA\Schema(type="integer", example=2026)),
     *     @OA\Response(response=200, description="Fichier PDF téléchargeable",
     *         @OA\MediaType(mediaType="application/pdf"))
     * )
     */
    public function absencesPDF(Request $request): \Illuminate\Http\Response
    {
        $mois  = (int) ($request->mois  ?? now()->month);
        $annee = (int) ($request->annee ?? now()->year);

        $pdf = $this->service->rapportAbsencesMensuel($mois, $annee);

        return $pdf->download("rapport-absences-{$mois}-{$annee}.pdf");
    }

    /**
     * @OA\Get(
     *     path="/api/v1/rapports/simulation-bem/{eleveId}",
     *     summary="Simuler la moyenne BEM d'un élève (4AM)",
     *     tags={"Rapports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(ref="#/components/parameters/TenantId"),
     *     @OA\Parameter(name="eleveId", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\Response(response=200, description="Simulation BEM avec détail par matière")
     * )
     */
    public function simulationBEM(string $eleveId): JsonResponse
    {
        $result = $this->service->simulationBEM($eleveId);
        return response()->json(['success' => true, 'data' => $result]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/rapports/simulation-bac/{eleveId}",
     *     summary="Simuler la moyenne BAC d'un élève par filière",
     *     tags={"Rapports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(ref="#/components/parameters/TenantId"),
     *     @OA\Parameter(name="eleveId", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\Parameter(name="filiere", in="query", required=true,
     *         @OA\Schema(type="string",
     *             enum={"sciences","maths","lettres_langues","lettres_philo","gestion_economie","technique_math","musique"})),
     *     @OA\Response(response=200, description="Simulation BAC avec mention prévisionnelle")
     * )
     */
    public function simulationBAC(Request $request, string $eleveId): JsonResponse
    {
        $filiere = $request->validate([
            'filiere' => 'required|in:sciences,maths,lettres_langues,lettres_philo,gestion_economie,technique_math,musique',
        ])['filiere'];

        $result = $this->service->simulationBAC($eleveId, $filiere);
        return response()->json(['success' => true, 'data' => $result]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/rapports/absences-stats",
     *     summary="Statistiques absences (JSON) pour le dashboard",
     *     tags={"Rapports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(ref="#/components/parameters/TenantId"),
     *     @OA\Parameter(name="mois",  in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="annee", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Stats absences JSON")
     * )
     */
    public function absencesStats(Request $request): JsonResponse
    {
        $mois  = (int) ($request->mois  ?? now()->month);
        $annee = (int) ($request->annee ?? now()->year);
        $debut = \Carbon\Carbon::create($annee, $mois, 1)->startOfMonth();
        $fin   = $debut->copy()->endOfMonth();

        $stats = \App\Models\AbsenceJournaliere::whereBetween('date_absence', [$debut, $fin])
            ->selectRaw("statut, COUNT(*) as total")
            ->groupBy('statut')
            ->get()
            ->keyBy('statut')
            ->map(fn($r) => $r->total);

        $topEleves = \App\Models\AbsenceJournaliere::whereBetween('date_absence', [$debut, $fin])
            ->selectRaw('eleve_id, COUNT(*) as nb')
            ->groupBy('eleve_id')
            ->orderByDesc('nb')
            ->limit(5)
            ->with('eleve:id,nom,prenom,niveau_scolaire')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'mois'          => $mois,
                'annee'         => $annee,
                'par_statut'    => $stats,
                'total'         => $stats->sum(),
                'top_absents'   => $topEleves,
            ],
        ]);
    }
}
```

**Ajouter les routes dans `routes/api.php`** :

```php
Route::middleware(['auth:api', 'tenant'])->prefix('v1/rapports')->group(function () {
    Route::get('/absences-pdf',          [RapportController::class, 'absencesPDF']);
    Route::get('/absences-stats',        [RapportController::class, 'absencesStats']);
    Route::get('/simulation-bem/{id}',   [RapportController::class, 'simulationBEM']);
    Route::get('/simulation-bac/{id}',   [RapportController::class, 'simulationBAC']);
});
```

Ajouter l'import :
```php
use App\Http\Controllers\Api\V1\RapportController;
```

---

## ÉTAPE 5 — Tests

**Créer :** `edugestdz/backend/tests/Feature/Controllers/RapportControllerTest.php`

```php
<?php

namespace Tests\Feature\Controllers;

use App\Models\User;
use App\Models\Eleve;
use App\Models\AbsenceJournaliere;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RapportControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_rapport_absences_pdf_retourne_pdf(): void
    {
        $this->actingAs($this->admin, 'api')
            ->get('/api/v1/rapports/absences-pdf?mois=7&annee=2026')
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_rapport_absences_stats_json(): void
    {
        AbsenceJournaliere::factory()->count(5)->create([
            'date_absence' => '2026-07-03',
            'statut'       => 'non_justifiée',
        ]);

        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/rapports/absences-stats?mois=7&annee=2026')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['mois', 'annee', 'par_statut', 'total']]);
    }

    public function test_simulation_bem_eleve(): void
    {
        $eleve = Eleve::factory()->create(['niveau_scolaire' => '4AM']);

        $this->actingAs($this->admin, 'api')
            ->getJson("/api/v1/rapports/simulation-bem/{$eleve->id}")
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => [
                'eleve', 'type', 'moyenne_simulee', 'detail',
            ]]);
    }

    public function test_simulation_bac_sciences(): void
    {
        $eleve = Eleve::factory()->create(['niveau_scolaire' => '3AS']);

        $this->actingAs($this->admin, 'api')
            ->getJson("/api/v1/rapports/simulation-bac/{$eleve->id}?filiere=sciences")
            ->assertStatus(200)
            ->assertJsonPath('data.type', 'BAC')
            ->assertJsonPath('data.contexte', 'sciences');
    }

    public function test_simulation_bac_filiere_invalide_echoue(): void
    {
        $eleve = Eleve::factory()->create();

        $this->actingAs($this->admin, 'api')
            ->getJson("/api/v1/rapports/simulation-bac/{$eleve->id}?filiere=sport")
            ->assertStatus(422);
    }

    public function test_simulation_bac_toutes_filieres(): void
    {
        $eleve = Eleve::factory()->create(['niveau_scolaire' => '3AS']);
        $filieres = ['sciences', 'maths', 'lettres_langues', 'lettres_philo',
                     'gestion_economie', 'technique_math', 'musique'];

        foreach ($filieres as $f) {
            $this->actingAs($this->admin, 'api')
                ->getJson("/api/v1/rapports/simulation-bac/{$eleve->id}?filiere={$f}")
                ->assertStatus(200)
                ->assertJsonPath('data.contexte', $f);
        }
    }

    public function test_rapport_eleve_introuvable_retourne_404(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/rapports/simulation-bem/00000000-0000-0000-0000-000000000000')
            ->assertStatus(404);
    }

    public function test_acces_sans_auth_refuse(): void
    {
        $this->getJson('/api/v1/rapports/absences-stats')
            ->assertStatus(401);
    }
}
```

---

## ÉTAPE 6 — Factory AbsenceJournaliere (si manquante)

```bash
# Vérifier si la factory existe
ls edugestdz/backend/database/factories/AbsenceJournaliereFactory.php
```

**Créer si absente :** `edugestdz/backend/database/factories/AbsenceJournaliereFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\AbsenceJournaliere;
use App\Models\Eleve;
use Illuminate\Database\Eloquent\Factories\Factory;

class AbsenceJournaliereFactory extends Factory
{
    protected $model = AbsenceJournaliere::class;

    public function definition(): array
    {
        return [
            'tenant_id'    => \Illuminate\Support\Str::uuid(),
            'eleve_id'     => Eleve::factory(),
            'date_absence' => $this->faker->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'statut'       => $this->faker->randomElement(['non_justifiée', 'justifiée', 'en_attente']),
            'motif'        => $this->faker->optional()->sentence(),
            'sms_envoye'   => false,
        ];
    }

    public function nonJustifiee(): static
    {
        return $this->state(['statut' => 'non_justifiée']);
    }

    public function justifiee(): static
    {
        return $this->state(['statut' => 'justifiée']);
    }
}
```

---

## ORDRE D'EXÉCUTION

```bash
git checkout develop && git pull origin main
cd edugestdz/backend

# 1. Packages
composer require barryvdh/laravel-dompdf:^2.0

# 2. Créer RapportService.php
# 3. Créer resources/views/pdf/rapport-absences.blade.php
# 4. Créer RapportController.php
# 5. Modifier routes/api.php (4 routes + import)
# 6. Créer AbsenceJournaliereFactory.php (si absente)
# 7. Créer tests/Feature/Controllers/RapportControllerTest.php

php artisan test --parallel
# → 0 régression + 8 nouveaux tests verts

git add .
git commit -m "feat: Rapports PDF absences + Simulation BEM/BAC 7 filières + Stats absences JSON"
git push origin develop
# → PR develop → main
```

---

## CE QUE TU DIS À DEEPSEEK

```
Repo : https://github.com/Allintelligence2024/edugest-dz.git
Branche : develop
git checkout develop && git pull origin main

Fichier : MISSION_RAPPORTS_PDF_BEM_BAC.md — 6 étapes.

RÈGLES :
1. PostgreSQL uniquement.
2. 0 régression.
3. Si barryvdh/laravel-dompdf déjà installé → continuer sans réinstaller.
4. Créer le dossier resources/views/pdf/ s'il n'existe pas.

php artisan test --parallel → verts → git push → PR develop → main.
```
