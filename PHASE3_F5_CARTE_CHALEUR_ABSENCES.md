# PHASE3 — F5 : Carte Chaleur des Absences Géographiques

## Objectif
Visualiser la répartition géographique des absences par wilaya sur une carte chaleur interactive (SVG 58 wilayas).

---

## Étape 1 : Controller — `AbsencesGeographiquesController.php`

### Fichier : `app/Http/Controllers/Api/V1/AbsencesGeographiquesController.php`

4 endpoints :
- `parWilaya` — Nombre d'absences par wilaya (LEFT JOIN `eleves → wilayas`)
- `tauxAbsentisme` — Taux (%) d'absentéisme par wilaya (requête SQL brute)
- `parWilayaDetail` — Liste paginée des absences d'une wilaya
- `resume` — KPIs globaux : total absences, élèves avec wilaya, top 5

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AbsenceJournaliere;
use App\Models\Wilaya;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AbsencesGeographiquesController extends Controller
{
    public function parWilaya(Request $request): JsonResponse
    {
        $request->validate([
            'date_debut' => 'nullable|date',
            'date_fin'   => 'nullable|date|after_or_equal:date_debut',
        ]);

        $absences = AbsenceJournaliere::query()
            ->select('wilayas.id as wilaya_id', 'wilayas.code', 'wilayas.nom_fr')
            ->selectRaw('COUNT(*) as nb_absences')
            ->leftJoin('eleves', 'absences_journalieres.eleve_id', '=', 'eleves.id')
            ->leftJoin('wilayas', 'eleves.wilaya_id', '=', 'wilayas.id')
            ->where('absences_journalieres.tenant_id', config('tenant.current_id'))
            ->when($request->filled('date_debut'), fn($q, $v) => $q->where('absences_journalieres.date_absence', '>=', $v))
            ->when($request->filled('date_fin'), fn($q, $v) => $q->where('absences_journalieres.date_absence', '<=', $v))
            ->groupBy('wilayas.id', 'wilayas.code', 'wilayas.nom_fr')
            ->orderByDesc('nb_absences')
            ->get();

        return response()->json(['success' => true, 'data' => $absences]);
    }

    public function tauxAbsentisme(Request $request): JsonResponse
    {
        $request->validate([
            'date_debut' => 'nullable|date',
            'date_fin'   => 'nullable|date|after_or_equal:date_debut',
        ]);

        $result = DB::select("
            SELECT
                w.id as wilaya_id, w.code, w.nom_fr,
                COUNT(DISTINCT aj.eleve_id) as nb_absents,
                COUNT(DISTINCT e.id) as nb_eleves,
                CASE WHEN COUNT(DISTINCT e.id) > 0
                    THEN ROUND(COUNT(DISTINCT aj.eleve_id)::numeric / COUNT(DISTINCT e.id) * 100, 1)
                    ELSE 0
                END as taux_pct
            FROM wilayas w
            LEFT JOIN eleves e ON e.wilaya_id = w.id AND e.tenant_id = ?
            LEFT JOIN absences_journalieres aj ON aj.eleve_id = e.id
                AND aj.tenant_id = ?
                AND (?::date IS NULL OR aj.date_absence >= ?::date)
                AND (?::date IS NULL OR aj.date_absence <= ?::date)
            GROUP BY w.id, w.code, w.nom_fr
            ORDER BY taux_pct DESC
        ", [
            config('tenant.current_id'), config('tenant.current_id'),
            $request->input('date_debut'), $request->input('date_debut'),
            $request->input('date_fin'), $request->input('date_fin'),
        ]);

        return response()->json(['success' => true, 'data' => $result]);
    }

    public function parWilayaDetail(Request $request, string $wilayaId): JsonResponse
    {
        $absences = AbsenceJournaliere::query()
            ->select('absences_journalieres.*')
            ->leftJoin('eleves', 'absences_journalieres.eleve_id', '=', 'eleves.id')
            ->where('eleves.wilaya_id', $wilayaId)
            ->where('absences_journalieres.tenant_id', config('tenant.current_id'))
            ->orderByDesc('absences_journalieres.date_absence')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $absences]);
    }

    public function resume(Request $request): JsonResponse
    {
        $totalAbsences = AbsenceJournaliere::where('tenant_id', config('tenant.current_id'))->count();
        $totalElevesAvecWilaya = DB::table('eleves')
            ->where('tenant_id', config('tenant.current_id'))
            ->whereNotNull('wilaya_id')->count();
        $wilayasConcernees = AbsenceJournaliere::query()
            ->leftJoin('eleves', 'absences_journalieres.eleve_id', '=', 'eleves.id')
            ->where('absences_journalieres.tenant_id', config('tenant.current_id'))
            ->whereNotNull('eleves.wilaya_id')
            ->distinct('eleves.wilaya_id')
            ->count('eleves.wilaya_id');

        $top5 = AbsenceJournaliere::query()
            ->select('wilayas.nom_fr', DB::raw('COUNT(*) as nb_absences'))
            ->leftJoin('eleves', 'absences_journalieres.eleve_id', '=', 'eleves.id')
            ->leftJoin('wilayas', 'eleves.wilaya_id', '=', 'wilayas.id')
            ->where('absences_journalieres.tenant_id', config('tenant.current_id'))
            ->whereNotNull('wilayas.id')
            ->groupBy('wilayas.nom_fr')
            ->orderByDesc('nb_absences')
            ->limit(5)->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_absences'     => $totalAbsences,
                'eleves_avec_wilaya' => $totalElevesAvecWilaya,
                'wilayas_concernees' => $wilayasConcernees,
                'top_5_wilayas'      => $top5,
            ],
        ]);
    }
}
```

---

## Étape 2 : Routes — `routes/api/pedagogie.php`

Ajouter dans le groupe `absences` (après les routes existantes) :

```php
// ── Absences géographiques (carte chaleur) ──
Route::prefix('geographie')->group(function () {
    Route::get('/par-wilaya',      [AbsencesGeographiquesController::class, 'parWilaya']);
    Route::get('/taux-absentisme', [AbsencesGeographiquesController::class, 'tauxAbsentisme']);
    Route::get('/wilaya/{wilayaId}', [AbsencesGeographiquesController::class, 'parWilayaDetail']);
    Route::get('/resume',          [AbsencesGeographiquesController::class, 'resume']);
});
```

Ajouter l'import `AbsencesGeographiquesController` dans le bloc `use` en haut du fichier.

---

## Étape 3 : Frontend — `CarteAbsencesPage.jsx`

### Fichier : `frontend/src/pages/CarteAbsencesPage.jsx`

Carte SVG 700×560 avec les 58 wilayas algériennes. Heat colors : gris → jaune → orange → rouge.

- `WILAYAS_POSITIONS` — tableau de 58 objets `{ id, code, nom, x, y }`
- `getHeatColor(nbAbsences, max)` — 5 niveaux de couleur
- Filtres date_debut / date_fin
- KPIs : total absences, élèves avec wilaya, wilayas concernées, sans données
- Sélection wilaya → détail (nombre absences + code)
- Top 5 wilayas les plus touchées

---

## Étape 4 : Tests — `AbsencesGeographiquesTest.php`

### Fichier : `tests/Feature/Api/AbsencesGeographiquesTest.php`

5 tests — `Wilaya::updateOrCreate()` au lieu de `create()` car les 58 wilayas sont déjà seedées.

```php
<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Eleve;
use App\Models\Wilaya;
use App\Models\AbsenceJournaliere;
use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AbsencesGeographiquesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $role = Role::factory()->create(['nom' => 'admin']);
        $this->tenant = Tenant::factory()->create();
        config(['tenant.current_id' => $this->tenant->id]);
        $this->admin = User::factory()->create([
            'role_id' => $role->id, 'tenant_id' => $this->tenant->id, 'statut' => 'actif',
        ]);
    }

    private function getOrCreateWilaya(array $attrs = []): Wilaya
    {
        $id = $attrs['id'] ?? null;
        if ($id && Wilaya::find($id)) return Wilaya::find($id);
        return Wilaya::updateOrCreate(
            ['id' => $id ?? rand(100, 999)],
            array_merge(['code' => str_pad(rand(1,58), 2, '0', STR_PAD_LEFT), 'nom_fr' => 'Test Wilaya', 'nom_ar' => 'ولاية اختبار'], $attrs)
        );
    }

    private function makeEleveWithWilaya(string $wilayaId): Eleve
    {
        return Eleve::factory()->create(['tenant_id' => $this->tenant->id, 'wilaya_id' => $wilayaId]);
    }

    public function test_par_wilaya_retourne_donnees(): void { /* ... */ }
    public function test_par_wilaya_avec_absences(): void { /* ... */ }
    public function test_taux_absentisme(): void { /* ... */ }
    public function test_resume(): void { /* ... */ }
    public function test_sans_auth_refuse(): void { /* ... */ }
}
```

---

## Vérification Finale

```bash
php artisan test tests/Feature/Api/AbsencesGeographiquesTest.php   # → 5 ✅
php artisan test                                                   # → ≥ 873 ✅
git add -A && git commit -m "feat: carte chaleur absences géographiques (F5)"
git push origin develop
```
