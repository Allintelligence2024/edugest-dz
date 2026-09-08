# PHASE3 — F6 : Appel Vocal Absence (Twilio Voice)

## Objectif
Notifier les parents par appel vocal automatique (Twilio Voice / TTS) lorsque leur enfant est absent.

---

## Étape 1 : Service — `TwilioVoiceService.php`

### Fichier : `app/Services/TwilioVoiceService.php`

Utilise `Http::withBasicAuth()` de Laravel (PAS twilio/sdk qui n'est pas installé).
Twilio REST API POST `Calls.json` avec paramètre `Twiml` inline (pas de callback URL).

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TwilioVoiceService
{
    protected ?string $sid;
    protected ?string $token;
    protected ?string $from;
    protected string  $langue;
    protected string  $voix;

    public function __construct()
    {
        $this->sid    = config('services.twilio.sid');
        $this->token  = config('services.twilio.token');
        $this->from   = config('services.twilio.from');
        $this->langue = config('services.twilio.voice_langue', 'fr-FR');
        $this->voix   = config('services.twilio.voice_nom', 'alice');
    }

    /**
     * Passer un appel vocal avec message TTS.
     */
    public function appeler(string $numero, string $message): array
    {
        $numero = $this->formaterNumero($numero);

        if ($numero === null) {
            return ['success' => false, 'to' => $numero ?? '', 'error' => 'Numéro invalide'];
        }

        if (!$this->sid || !$this->token || !$this->from) {
            Log::channel('sms')->error('Twilio Voice non configuré');
            return ['success' => false, 'to' => $numero, 'error' => 'Twilio non configuré'];
        }

        try {
            $twiml = '<Response><Say language="' . $this->langue . '" voice="' . $this->voix . '">'
                     . e($message)
                     . '</Say></Response>';

            $response = Http::withBasicAuth($this->sid, $this->token)
                ->timeout(15)
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$this->sid}/Calls.json", [
                    'To'      => $numero,
                    'From'    => $this->from,
                    'Twiml'   => $twiml,
                    'Timeout' => 30,
                ]);

            if ($response->successful()) {
                $body = $response->json();
                Log::channel('sms')->info('Appel vocal lancé', [
                    'to'       => $numero,
                    'call_sid' => $body['sid'] ?? null,
                    'status'   => $body['status'] ?? null,
                ]);
                return [
                    'success'  => true,
                    'call_sid' => $body['sid'] ?? null,
                    'to'       => $numero,
                    'status'   => $body['status'] ?? null,
                    'error'    => null,
                ];
            }

            $error = $response->json('message') ?? "Erreur HTTP {$response->status()}";
            Log::channel('sms')->error('Échec appel vocal Twilio', ['to' => $numero, 'error' => $error]);
            return ['success' => false, 'to' => $numero, 'error' => $error];

        } catch (\Throwable $e) {
            Log::channel('sms')->error('Exception appel vocal', [
                'to'    => $numero,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'to' => $numero, 'error' => $e->getMessage()];
        }
    }

    protected function formaterNumero(string $numero): ?string
    {
        if (function_exists('formaterNumeroAlgerien')) {
            return formaterNumeroAlgerien($numero);
        }
        $clean = preg_replace('/[^0-9+]/', '', $numero);
        if ($clean === '') return null;
        if (str_starts_with($clean, '0') && strlen($clean) === 10) return '+213' . substr($clean, 1);
        if (str_starts_with($clean, '+213') && strlen($clean) === 13) return $clean;
        return null;
    }
}
```

---

## Étape 2 : Migration — Colonnes appel vocal

### Fichier : `database/migrations/2026_07_12_800000_add_appel_vocal_to_absences_journalieres.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('absences_journalieres', 'sms_parent_envoye')
            && !Schema::hasColumn('absences_journalieres', 'appel_vocal_envoye')) {
            Schema::table('absences_journalieres', function (Blueprint $table) {
                $table->boolean('appel_vocal_envoye')->default(false);
                $table->timestamp('appel_vocal_envoye_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('absences_journalieres', 'appel_vocal_envoye')) {
            Schema::table('absences_journalieres', function (Blueprint $table) {
                $table->dropColumn(['appel_vocal_envoye', 'appel_vocal_envoye_at']);
            });
        }
    }
};
```

---

## Étape 3 : Model `AbsenceJournaliere` — Ajouter fillable + casts

Ajouter à `$fillable` :
```php
'appel_vocal_envoye', 'appel_vocal_envoye_at',
```

Ajouter à `$casts` :
```php
'appel_vocal_envoye' => 'boolean',
'appel_vocal_envoye_at' => 'datetime',
```

---

## Étape 4 : Commande — `AppelVocalAbsenceCommand.php`

### Fichier : `app/Console/Commands/AppelVocalAbsenceCommand.php`

```php
<?php

namespace App\Console\Commands;

use App\Models\AbsenceJournaliere;
use App\Models\Tenant;
use App\Services\TwilioVoiceService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AppelVocalAbsenceCommand extends Command
{
    protected $signature = 'edugest:appel-vocal-absence
                            {--date= : Date au format Y-m-d}
                            {--dry-run : Simuler sans appeler}
                            {--force : Ignorer le cache d\'idempotence}';

    protected $description = 'Appeler les parents des élèves absents (Twilio Voice TTS)';

    public function handle(TwilioVoiceService $voice): int
    {
        $date = $this->option('date') ?? today()->format('Y-m-d');
        $dryRun = $this->option('dry-run');
        $this->info("Appel vocal absence — {$date}" . ($dryRun ? ' [DRY-RUN]' : ''));

        if ($dryRun) {
            return $this->dryRun($date);
        }

        $cleCache = "appel_vocal_{$date}";
        if (!$this->option('force') && Cache::get($cleCache)) {
            $this->info('Déjà effectué aujourd\'hui. Utiliser --force pour relancer.');
            return Command::SUCCESS;
        }

        $appelles = 0;
        $echecs = 0;

        Tenant::where('statut', 'actif')->each(function (Tenant $tenant) use ($date, $voice, &$appelles, &$echecs) {
            config(['tenant.current_id' => $tenant->id]);

            $absences = AbsenceJournaliere::with(['eleve.parents'])
                ->whereDate('date_absence', $date)
                ->where('appel_vocal_envoye', false)
                ->get();

            foreach ($absences as $absence) {
                $eleve = $absence->eleve;
                if (!$eleve) continue;

                $parents = $eleve->parents;
                if ($parents->isEmpty()) {
                    $this->line("  ⚠ Aucun parent pour {$eleve->prenom} {$eleve->nom}");
                    continue;
                }

                foreach ($parents as $parent) {
                    $tel = $parent->telephone_1 ?? $parent->telephone_2 ?? null;
                    if (!$tel) continue;

                    $message = $this->composerMessage($eleve, $date);
                    $result = $voice->appeler($tel, $message);

                    $this->loggerAppelDansAudit($absence, $parent, $result);

                    if ($result['success']) {
                        $appelles++;
                        $this->line("  ✓ Appelé {$parent->prenom} {$parent->nom} ({$tel})");
                    } else {
                        $echecs++;
                        $this->line("  ✗ Échec {$parent->prenom} : {$result['error']}");
                    }
                }

                $absence->update([
                    'appel_vocal_envoye'    => true,
                    'appel_vocal_envoye_at' => now(),
                ]);
            }
        });

        Cache::put($cleCache, true, now()->addDay());
        $this->info("Terminé : {$appelles} appel(s), {$echecs} échec(s)");
        return Command::SUCCESS;
    }

    private function dryRun(string $date): int
    {
        $total = 0;
        Tenant::where('statut', 'actif')->each(function (Tenant $tenant) use ($date, &$total) {
            config(['tenant.current_id' => $tenant->id]);
            $count = AbsenceJournaliere::with('eleve.parents')
                ->whereDate('date_absence', $date)
                ->where('appel_vocal_envoye', false)
                ->count();
            $total += $count;
        });

        $this->info("[DRY-RUN] {$total} absence(s) à traiter — aucun appel passé.");
        return Command::SUCCESS;
    }

    private function composerMessage($eleve, string $date): string
    {
        $dateFormatee = Carbon::parse($date)->translatedFormat('d/m/Y');
        return "Bonjour. Ceci est un appel automatique de l'établissement scolaire. "
             . "Nous vous informons que votre enfant {$eleve->prenom} {$eleve->nom} "
             . "est absent(e) aujourd'hui, le {$dateFormatee}. "
             . "Merci de contacter l'établissement pour justifier cette absence. "
             . "Merci et bonne journée.";
    }

    private function loggerAppelDansAudit($absence, $parent, array $result): void
    {
        try {
            DB::table('audit_logs')->insert([
                'tenant_id'          => config('tenant.current_id'),
                'action'             => 'appel_vocal_absence',
                'table_concernee'    => 'absences_journalieres',
                'enregistrement_id'  => $absence->id,
                'nouvelles_valeurs'  => json_encode([
                    'parent_id'  => $parent->id,
                    'telephone'  => $parent->telephone_1 ?? null,
                    'success'    => $result['success'],
                    'call_sid'   => $result['call_sid'] ?? null,
                    'error'      => $result['error'] ?? null,
                ]),
                'created_at'         => now(),
            ]);
        } catch (\Throwable $e) {
            Log::debug('Audit log appel vocal ignoré : ' . $e->getMessage());
        }
    }
}
```

---

## Étape 5 : Config + .env.example + Scheduler

### config/services.php — Ajouter dans le bloc `twilio` :

```php
'twilio' => [
    'sid'          => env('TWILIO_SID'),
    'token'        => env('TWILIO_TOKEN'),
    'from'         => env('TWILIO_FROM'),
    'voice_langue' => env('TWILIO_VOICE_LANGUE', 'fr-FR'),
    'voice_nom'    => env('TWILIO_VOICE_NOM', 'alice'),
],
```

### .env.example — Ajouter après TWILIO_FROM :

```ini
# ── Twilio Voice (Appels vocaux TTS) ──
# Langue TTS : fr-FR, en-US, ar-SA, etc.
TWILIO_VOICE_LANGUE=fr-FR
# Voix : alice (standard), Polly.Celine (Amazon Polly), Polly.Mathieu
TWILIO_VOICE_NOM=alice
```

### bootstrap/app.php — Ajouter dans `withSchedule` :

```php
$schedule->command('edugest:appel-vocal-absence')
         ->weekdays()
         ->at('09:00')
         ->timezone('Africa/Algiers')
         ->withoutOverlapping()
         ->runInBackground();
```

---

## Étape 6 : Tests — `AppelVocalAbsenceTest.php`

### Fichier : `tests/Feature/Commands/AppelVocalAbsenceTest.php`

6 tests — `Http::fake()` + `Cache::flush()` dans setUp().

```php
<?php

namespace Tests\Feature\Commands;

use App\Models\AbsenceJournaliere;
use App\Models\Eleve;
use App\Models\ParentEleve;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AppelVocalAbsenceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);
        config(['tenant.current_id' => $this->tenant->id]);
        config(['services.twilio.sid' => 'test-sid']);
        config(['services.twilio.token' => 'test-token']);
        config(['services.twilio.from' => '+1234567890']);
    }

    // Tests here...
}
```

**Test 1 — dry run :**
```php
public function test_dry_run_appelle_rien(): void
{
    Http::fake();

    $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
    $parent = ParentEleve::factory()->create(['tenant_id' => $this->tenant->id]);
    $eleve->parents()->attach($parent->id, ['est_principal' => true]);

    AbsenceJournaliere::create([
        'tenant_id'    => $this->tenant->id,
        'eleve_id'     => $eleve->id,
        'date_absence' => today(),
        'statut'       => 'absent',
    ]);

    $this->artisan('edugest:appel-vocal-absence', ['--dry-run' => true])
        ->assertExitCode(0);

    Http::assertNothingSent();
}
```

**Test 2 — appelle les parents :**
```php
public function test_appelle_parents_des_absents(): void
{
    Http::fake([
        'api.twilio.com/*' => Http::response(['sid' => 'CA123', 'status' => 'queued'], 201),
    ]);

    $eleve = Eleve::factory()->create([
        'tenant_id' => $this->tenant->id,
        'nom' => 'Benali', 'prenom' => 'Amira',
    ]);
    $parent = ParentEleve::factory()->create([
        'tenant_id' => $this->tenant->id,
        'telephone_1' => '0555555555',
    ]);
    $eleve->parents()->attach($parent->id, ['est_principal' => true]);

    AbsenceJournaliere::create([
        'tenant_id'    => $this->tenant->id,
        'eleve_id'     => $eleve->id,
        'date_absence' => today(),
        'statut'       => 'absent',
    ]);

    $this->artisan('edugest:appel-vocal-absence')->assertExitCode(0);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'Calls.json')
            && $request->method() === 'POST';
    });

    $this->assertDatabaseHas('absences_journalieres', [
        'eleve_id'            => $eleve->id,
        'appel_vocal_envoye'  => true,
    ]);
}
```

**Test 3 — ignore parent sans téléphone :**
```php
public function test_ignore_parent_sans_telephone(): void
{
    Http::fake();

    $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
    $parent = ParentEleve::factory()->create([
        'tenant_id' => $this->tenant->id,
        'telephone_1' => null,
        'telephone_2' => null,
    ]);
    $eleve->parents()->attach($parent->id, ['est_principal' => true]);

    AbsenceJournaliere::create([
        'tenant_id'    => $this->tenant->id,
        'eleve_id'     => $eleve->id,
        'date_absence' => today(),
        'statut'       => 'absent',
    ]);

    $this->artisan('edugest:appel-vocal-absence')->assertExitCode(0);

    Http::assertNothingSent();
}
```

**Test 4 — sans Twilio configuré :**
```php
public function test_sans_twilio_configure(): void
{
    config(['services.twilio.sid' => null]);
    config(['services.twilio.token' => null]);
    config(['services.twilio.from' => null]);

    Http::fake();

    $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
    $parent = ParentEleve::factory()->create([
        'tenant_id' => $this->tenant->id,
        'telephone_1' => '0555555555',
    ]);
    $eleve->parents()->attach($parent->id, ['est_principal' => true]);

    AbsenceJournaliere::create([
        'tenant_id'    => $this->tenant->id,
        'eleve_id'     => $eleve->id,
        'date_absence' => today(),
        'statut'       => 'absent',
    ]);

    $this->artisan('edugest:appel-vocal-absence')->assertExitCode(0);

    Http::assertNothingSent();
}
```

**Test 5 — idempotence cache :**
```php
public function test_idempotence_cache(): void
{
    Http::fake([
        'api.twilio.com/*' => Http::response(['sid' => 'CA123', 'status' => 'queued'], 201),
    ]);

    $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
    $parent = ParentEleve::factory()->create([
        'tenant_id' => $this->tenant->id,
        'telephone_1' => '0555555555',
    ]);
    $eleve->parents()->attach($parent->id, ['est_principal' => true]);

    AbsenceJournaliere::create([
        'tenant_id'    => $this->tenant->id,
        'eleve_id'     => $eleve->id,
        'date_absence' => today(),
        'statut'       => 'absent',
    ]);

    $this->artisan('edugest:appel-vocal-absence')->assertExitCode(0);

    $count1 = Http::assertCount(1);

    $this->artisan('edugest:appel-vocal-absence')->assertExitCode(0);

    // 2nd run: no new calls because cache prevents re-run
    Http::assertSentCount(1);
}
```

**Test 6 — force override cache :**
```php
public function test_force_override_cache(): void
{
    Http::fake([
        'api.twilio.com/*' => Http::response(['sid' => 'CA123', 'status' => 'queued'], 201),
    ]);

    $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);
    $parent = ParentEleve::factory()->create([
        'tenant_id' => $this->tenant->id,
        'telephone_1' => '0555555555',
    ]);
    $eleve->parents()->attach($parent->id, ['est_principal' => true]);

    AbsenceJournaliere::create([
        'tenant_id'    => $this->tenant->id,
        'eleve_id'     => $eleve->id,
        'date_absence' => today(),
        'statut'       => 'absent',
    ]);

    $this->artisan('edugest:appel-vocal-absence')->assertExitCode(0);
    $this->artisan('edugest:appel-vocal-absence', ['--force' => true])->assertExitCode(0);

    // 2 calls made because --force overrides cache
    Http::assertSentCount(2);
}
```

---

## Vérification Finale

```bash
php artisan migrate --force
php artisan test tests/Feature/Commands/AppelVocalAbsenceTest.php   # → 6 ✅
php artisan test                                                    # → ≥ 879 ✅
git add -A && git commit -m "feat: appel vocal absence Twilio Voice (F6)"
git push origin develop
```
