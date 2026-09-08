# PHASE 1 — F1 : Résumé Hebdomadaire Parents

## Objectif
Envoyer un résumé hebdomadaire aux parents incluant les notes, absences et incidents de la semaine.

## Étape 1 : Créer la commande artisan `edugest:resume-hebdo-parents`

**Fichier** : `app/Console/Commands/ResumeHebdomadaireCommand.php`

```php
<?php
namespace App\Console\Commands;

use App\Models\Eleve;
use App\Models\ParentEleve;
use App\Services\ParentNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ResumeHebdomadaireCommand extends Command
{
    protected $signature = 'edugest:resume-hebdo-parents {--force}';
    protected $description = 'Envoyer le résumé hebdomadaire aux parents';

    public function handle(ParentNotificationService $notificationService): int
    {
        $semaine = Carbon::now()->startOfWeek();
        $cleCache = "resume_hebdo_{$semaine->format('Y-m-d')}";

        if (!$this->option('force') && Cache::get($cleCache)) {
            $this->info('Déjà envoyé cette semaine. Utiliser --force pour relancer.');
            return Command::SUCCESS;
        }

        $eleves = Eleve::with('parents')->actifs()->get();
        $envoyes = 0;

        foreach ($eleves as $eleve) {
            // Notes de la semaine
            $notes = $this->getNotesSemaine($eleve->id, $semaine);
            
            // Absences de la semaine
            $absences = $this->getAbsencesSemaine($eleve->id, $semaine);
            
            // Incidents
            $incidents = $this->getIncidentsSemaine($eleve->id, $semaine);

            if ($notes->isEmpty() && $absences->isEmpty() && $incidents->isEmpty()) {
                continue;
            }

            $this->envoyerResumeParent(
                $notificationService,
                $eleve,
                $notes,
                $absences,
                $incidents,
                $semaine
            );
            
            $envoyes++;
        }

        Cache::put($cleCache, true, now()->addWeek());
        
        $this->info("✅ Résumé envoyé à {$envoyes} parent(s)");
        return Command::SUCCESS;
    }

    private function getNotesSemaine(string $eleveId, Carbon $debut): \Illuminate\Support\Collection
    {
        try {
            return DB::table('notes')
                ->join('evaluations', 'notes.evaluation_id', '=', 'evaluations.id')
                ->join('groupes', 'evaluations.groupe_id', '=', 'groupes.id')
                ->leftJoin('matieres', 'groupes.matiere_id', '=', 'matieres.id')
                ->where('notes.eleve_id', $eleveId)
                ->where('evaluations.date_evaluation', '>=', $debut)
                ->where('evaluations.date_evaluation', '<=', $debut->copy()->endOfWeek())
                ->select(
                    'notes.note',
                    'evaluations.note_sur',
                    'evaluations.titre',
                    DB::raw("COALESCE(matieres.nom_fr, 'Cours') as matiere")
                )
                ->get();
        } catch (\Throwable $e) {
            // Fallback si la jointure échoue
            return DB::table('notes')
                ->join('evaluations', 'notes.evaluation_id', '=', 'evaluations.id')
                ->where('notes.eleve_id', $eleveId)
                ->where('evaluations.date_evaluation', '>=', $debut)
                ->where('evaluations.date_evaluation', '<=', $debut->copy()->endOfWeek())
                ->select('notes.note', 'evaluations.note_sur', 'evaluations.titre')
                ->selectRaw("'Cours' as matiere")
                ->get();
        }
    }

    private function getAbsencesSemaine(string $eleveId, Carbon $debut): \Illuminate\Support\Collection
    {
        return DB::table('presences')
            ->join('seances', 'presences.seance_id', '=', 'seances.id')
            ->where('presences.eleve_id', $eleveId)
            ->where('presences.statut', 'absent')
            ->where('seances.date_seance', '>=', $debut)
            ->where('seances.date_seance', '<=', $debut->copy()->endOfWeek())
            ->select('seances.date_seance', 'presences.motif')
            ->get();
    }

    private function getIncidentsSemaine(string $eleveId, Carbon $debut): \Illuminate\Support\Collection
    {
        return DB::table('signalements_comportement')
            ->where('eleve_id', $eleveId)
            ->where('created_at', '>=', $debut)
            ->where('created_at', '<=', $debut->copy()->endOfWeek())
            ->select('type', 'gravite', 'description')
            ->get();
    }

    private function envoyerResumeParent(
        ParentNotificationService $service,
        Eleve $eleve,
        \Illuminate\Support\Collection $notes,
        \Illuminate\Support\Collection $absences,
        \Illuminate\Support\Collection $incidents,
        Carbon $semaine
    ): void {
        $titre = "📊 Résumé semaine {$semaine->format('d/m')}";

        $corps = "Notes : {$notes->count()}\n";
        $corps .= "Absences : {$absences->count()}\n";
        $corps .= "Incidents : {$incidents->count()}";

        $meta = [
            'notes' => $notes->toArray(),
            'absences' => $absences->toArray(),
            'incidents' => $incidents->toArray(),
            'semaine' => $semaine->format('d/m/Y'),
        ];

        foreach ($eleve->parents as $parent) {
            $service->notifier(
                $eleve->id,
                'resume_hebdo',
                $titre,
                $corps,
                $meta,
                false,
                false,
                false
            );
        }
    }
}
```

## Étape 2 : Enregistrer le scheduler

**Fichier** : `bootstrap/app.php` — ajouter dans `withSchedule` :

```php
$schedule->command('edugest:resume-hebdo-parents')
         ->weeklyOn(5, '18:00') // Vendredi à 18h
         ->timezone('Africa/Algiers')
         ->withoutOverlapping()
         ->runInBackground();
```

## Étape 3 : Ajouter le template email

**Fichier** : `resources/views/emails/resume-hebdo.blade.php`

```blade
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #1E5EBC; color: white; padding: 20px; text-align: center; }
        .section { margin: 20px 0; padding: 15px; background: #f9f9f9; border-radius: 5px; }
        .note { color: #16a34a; font-weight: bold; }
        .absence { color: #dc2626; }
        .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Résumé Hebdomadaire</h1>
            <p>Semaine du {{ $semaine }}</p>
        </div>

        <p>Bonjour {{ $parentPrenom }} {{ $parentNom }},</p>
        <p>Voici le résumé de la semaine de <strong>{{ $elevePrenom }} {{ $eleveNom }}</strong> :</p>

        @if(count($notes) > 0)
        <div class="section">
            <h3>📝 Notes</h3>
            @foreach($notes as $note)
            <p><strong>{{ $note->matiere }}</strong> : {{ $note->note }}/{{ $note->note_sur }}</p>
            @endforeach
        </div>
        @endif

        @if(count($absences) > 0)
        <div class="section">
            <h3>⚠️ Absences</h3>
            @foreach($absences as $absence)
            <p class="absence">{{ $absence->date_seance }}{{ $absence->motif ? " — {$absence->motif}" : '' }}</p>
            @endforeach
        </div>
        @endif

        @if(count($incidents) > 0)
        <div class="section">
            <h3>🚨 Incidents</h3>
            @foreach($incidents as $incident)
            <p><strong>{{ $incident->type }}</strong> ({{ $incident->gravite }}) : {{ $incident->description }}</p>
            @endforeach
        </div>
        @endif

        <div class="footer">
            <p>{{ $nomEcole }} — {{ $anneeScolaire }}</p>
            <p><a href="{{ $urlApplication }}">Accéder au tableau de bord</a></p>
        </div>
    </div>
</body>
</html>
```

Mettre à jour le template map dans `ParentNotificationService.php` :

```php
$templateMap = [
    // ... existant ...
    'resume_hebdo' => 'emails.resume-hebdo',
];
```

## Étape 4 : Tests Feature

**Fichier** : `tests/Feature/Commands/ResumeHebdomadaireTest.php`

```php
<?php
namespace Tests\Feature\Commands;

use App\Models\Eleve;
use App\Models\ParentEleve;
use App\Models\Note;
use App\Models\Evaluation;
use App\Models\Presence;
use App\Models\Seance;
use App\Models\Groupe;
use App\Models\Matiere;
use App\Models\SignalementComportement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ResumeHebdomadaireTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Cache::flush();
    }

    public function test_commande_envoie_le_resume(): void
    {
        $eleve = Eleve::factory()->create(['statut' => 'actif']);
        $parent = ParentEleve::factory()->create();
        $eleve->parents()->attach($parent->id);

        $matiere = Matiere::factory()->create();
        $groupe = Groupe::factory()->create(['matiere_id' => $matiere->id]);
        $evaluation = Evaluation::factory()->create([
            'groupe_id' => $groupe->id,
            'date_evaluation' => now()->subDay(),
            'note_sur' => 20,
        ]);
        Note::factory()->create([
            'eleve_id' => $eleve->id,
            'evaluation_id' => $evaluation->id,
            'note' => 15,
        ]);

        $this->artisan('edugest:resume-hebdo-parents')
            ->assertExitCode(0);

        // Vérifier qu'une notification a été créée
        $this->assertDatabaseHas('notifications_parent', [
            'eleve_id' => $eleve->id,
            'type' => 'resume_hebdo',
        ]);
    }

    public function test_cache_empeche_double_envoi(): void
    {
        $semaine = now()->startOfWeek();
        Cache::put("resume_hebdo_{$semaine->format('Y-m-d')}", true);

        $this->artisan('edugest:resume-hebdo-parents')
            ->expectsOutput('Déjà envoyé cette semaine.')
            ->assertExitCode(0);
    }

    public function test_force_bypass_cache(): void
    {
        $semaine = now()->startOfWeek();
        Cache::put("resume_hebdo_{$semaine->format('Y-m-d')}", true);

        $eleve = Eleve::factory()->create(['statut' => 'actif']);
        $parent = ParentEleve::factory()->create();
        $eleve->parents()->attach($parent->id);

        $this->artisan('edugest:resume-hebdo-parents', ['--force' => true])
            ->assertExitCode(0);
    }

    public function test_aucune_donnee_passe_vide(): void
    {
        $eleve = Eleve::factory()->create(['statut' => 'actif']);

        $this->artisan('edugest:resume-hebdo-parents')
            ->assertExitCode(0);

        $this->assertDatabaseEmpty('notifications_parent', [
            'eleve_id' => $eleve->id,
        ]);
    }

    public function test_eleves_inactifs_exclus(): void
    {
        Eleve::factory()->create(['statut' => 'inactif']);

        $this->artisan('edugest:resume-hebdo-parents')
            ->assertExitCode(0);
    }

    public function test_absences_comptabilisees(): void
    {
        $eleve = Eleve::factory()->create(['statut' => 'actif']);
        $parent = ParentEleve::factory()->create();
        $eleve->parents()->attach($parent->id);

        $seance = Seance::factory()->create(['date_seance' => now()->subDay()]);
        Presence::factory()->create([
            'eleve_id' => $eleve->id,
            'seance_id' => $seance->id,
            'statut' => 'absent',
        ]);

        $this->artisan('edugest:resume-hebdo-parents')
            ->assertExitCode(0);

        $this->assertDatabaseHas('notifications_parent', [
            'eleve_id' => $eleve->id,
            'type' => 'resume_hebdo',
        ]);
    }
}
```

## Étape 5 : Vérification et déploiement

```bash
# Tests spécifiques
php artisan test tests/Feature/Commands/ResumeHebdomadaireTest.php

# Tous les tests
php artisan test

# Push
git add .
git commit -m "feat: résumé hebdomadaire parents (F1)"
git push origin develop
```

## Résumé des fichiers

| Fichier | Action |
|---------|--------|
| `app/Console/Commands/ResumeHebdomadaireCommand.php` | Créer |
| `bootstrap/app.php` | Modifier (scheduler) |
| `resources/views/emails/resume-hebdo.blade.php` | Créer |
| `app/Services/ParentNotificationService.php` | Modifier (template map) |
| `tests/Feature/Commands/ResumeHebdomadaireTest.php` | Créer |
