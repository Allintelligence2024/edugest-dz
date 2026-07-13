# PHASE2 — F4 : Rapport ONDEC (Export Excel Examen)

## Objectif
Exporter un rapport ONDEC complet (candidats + statistiques) au format Excel (.xlsx) depuis une session d'examen.

---

## Étape 1 : Export Excel — `RapportOnecExport.php`

### Fichier : `app/Exports/RapportOnecExport.php`

3 classes dans un même fichier :
- `RapportOnecExport` — `WithMultipleSheets`, coordonne les feuilles
- `CandidatsOnecSheet` — `FromQuery`, `WithHeadings`, `WithStyles`, `ShouldAutoSize` — Feuille 1
- `StatistiquesOnecSheet` — `FromQuery`, `WithHeadings`, `WithStyles`, `ShouldAutoSize` — Feuille 2

```php
<?php

namespace App\Exports;

use App\Models\CandidatExamen;
use App\Models\SessionExamen;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RapportOnecExport implements WithMultipleSheets
{
    protected string $sessionId;

    public function __construct(string $sessionId)
    {
        $this->sessionId = $sessionId;
    }

    public function sheets(): array
    {
        return [
            new CandidatsOnecSheet($this->sessionId),
            new StatistiquesOnecSheet($this->sessionId),
        ];
    }
}
```

### CandidatsOnecSheet

```php
class CandidatsOnecSheet implements FromQuery, WithHeadings, WithStyles, ShouldAutoSize
{
    protected string $sessionId;

    public function __construct(string $sessionId) { $this->sessionId = $sessionId; }

    public function query(): \Illuminate\Database\Eloquent\Builder
    {
        return CandidatExamen::query()
            ->where('session_id', $this->sessionId)
            ->leftJoin('salles_examen', 'candidats_examen.salle_id', '=', 'salles_examen.id')
            ->select([
                'candidats_examen.numero_inscription',
                'candidats_examen.nom',
                'candidats_examen.prenom',
                'candidats_examen.date_naissance',
                'candidats_examen.lieu_naissance',
                'candidats_examen.type_candidat',
                'candidats_examen.filiere',
                'salles_examen.nom as salle',
                'candidats_examen.numero_place',
                'candidats_examen.present',
            ])
            ->orderBy('candidats_examen.nom')
            ->orderBy('candidats_examen.prenom');
    }

    public function headings(): array
    {
        return ['N° Inscription', 'Nom', 'Prénom', 'Date naissance', 'Lieu naissance',
                'Type', 'Filière', 'Salle', 'N° Place', 'Présent'];
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true, 'size' => 11]]];
    }
}
```

### StatistiquesOnecSheet

`calculerStats()` est `protected` — accessible via `ReflectionMethod` dans les tests.

```php
class StatistiquesOnecSheet implements FromQuery, WithHeadings, WithStyles, ShouldAutoSize
{
    protected string $sessionId;

    public function __construct(string $sessionId) { $this->sessionId = $sessionId; }

    public function query(): \Illuminate\Database\Eloquent\Builder
    {
        $stats = $this->calculerStats();

        return CandidatExamen::query()
            ->where('session_id', $this->sessionId)
            ->limit(0)
            ->select([
                DB::raw("'{$stats['total_candidats']}' as indicateur"),
                DB::raw("'{$stats['candidats_scolarises']}' as valeur"),
            ]);
    }

    public function headings(): array { return ['Indicateur', 'Valeur']; }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true, 'size' => 11]]];
    }

    protected function calculerStats(): array
    {
        $candidats = CandidatExamen::where('session_id', $this->sessionId);

        $total = (clone $candidats)->count();
        $presents = (clone $candidats)->where('present', true)->count();
        $scolarises = (clone $candidats)->where('type_candidat', 'scolarise')->count();
        $libres = (clone $candidats)->where('type_candidat', 'libre')->count();

        $session = SessionExamen::findOrFail($this->sessionId);
        $nb_salles = $session->salles()->count();
        $nb_epreuves = $session->epreuves()->count();

        return [
            'total_candidats'       => $total,
            'candidats_presents'    => $presents,
            'candidats_absents'     => $total - $presents,
            'taux_presence'         => $total > 0 ? round($presents / $total * 100, 1).'%' : '0%',
            'candidats_scolarises'  => $scolarises,
            'candidats_libres'      => $libres,
            'nb_salles'             => $nb_salles,
            'nb_epreuves'           => $nb_epreuves,
            'type_session'          => $session->type,
            'annee_scolaire'        => $session->annee_scolaire,
            'wilaya'                => $session->wilaya ?? 'N/A',
            'centre'                => $session->nom_centre ?? 'N/A',
        ];
    }
}
```

---

## Étape 2 : Méthode à ajouter à `ExamenController.php`

Ajouter `exportOnec()` à la **fin** de `app/Http/Controllers/Api/V1/ExamenController.php` :

```php
public function exportOnec(string $sessionId): \Symfony\Component\HttpFoundation\BinaryFileResponse
{
    $session = SessionExamen::findOrFail($sessionId);
    $nomFichier = "rapport-ondec-{$session->type}-{$session->annee_scolaire}.xlsx";

    return Excel::download(new RapportOnecExport($sessionId), $nomFichier);
}
```

Ajouter l'import en haut du fichier :
```php
use App\Exports\RapportOnecExport;
use Maatwebsite\Excel\Facades\Excel;
```

---

## Étape 3 : Route — `routes/api/extended.php`

Ajouter dans le groupe examens (après les routes existantes) :
```php
Route::get('/{sessionId}/export-onec', [ExamenController::class, 'exportOnec']);
```

---

## Étape 4 : Tests — `RapportOnecTest.php`

### Fichier : `tests/Feature/Api/RapportOnecTest.php`

6 tests — `Excel::fake()` dans setUp(), assertStatus(200) au lieu de `assertDownloaded` (BinaryFileResponse incompatible).

```php
<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\SessionExamen;
use App\Models\CandidatExamen;
use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class RapportOnecTest extends TestCase
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
        Excel::fake();
    }

    private function makeSession(array $attrs = []): SessionExamen
    {
        return SessionExamen::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'type' => 'BAC',
            'annee_scolaire' => '2025/2026',
            'session' => 'principale',
            'date_debut' => '2026-06-07',
            'date_fin' => '2026-06-11',
            'wilaya' => 'Oran',
            'nom_centre' => 'Lycée Test',
            'max_candidats_par_salle' => 20,
            'nb_surveillants_par_salle' => 3,
            'statut' => 'planifie',
        ], $attrs));
    }

    public function test_export_onec_telecharge_fichier(): void { /* ... */ }
    public function test_export_onec_contient_deux_feuilles(): void { /* ... */ }
    public function test_export_onec_session_inexistante_404(): void { /* ... */ }
    public function test_export_onec_session_vide(): void { /* ... */ }
    public function test_export_onec_bem(): void { /* ... */ }
    public function test_export_onec_sans_auth_refuse(): void { /* ... */ }
}
```

---

## Vérification Finale

```bash
php artisan test tests/Feature/Api/RapportOnecTest.php    # → 6 ✅
php artisan test                                          # → ≥ 873 ✅
git add -A && git commit -m "feat: export rapport ONDEC examens (F4)"
git push origin develop
```
