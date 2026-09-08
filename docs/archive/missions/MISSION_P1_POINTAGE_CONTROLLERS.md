# 🤖 MISSION DEEPSEEK — PRIORITÉ 1 : Controllers Pointage
## EduGest DZ · Branche : develop · 29 Juin 2026

---

## CONTEXTE

Les routes suivantes existent déjà dans `api.php` mais leurs controllers sont **absents** :

```
POST   /api/v1/pointage/badge                     → PointageBadgeController@scan
GET    /api/v1/pointage/enseignants/aujourd-hui   → PointageEnseignantController@aujourdhui
POST   /api/v1/pointage/enseignants/{id}/arrivee  → PointageEnseignantController@arrivee
POST   /api/v1/pointage/enseignants/{id}/depart   → PointageEnseignantController@depart
GET    /api/v1/pointage/enseignants/{id}/historique → PointageEnseignantController@historique
```

Les migrations `pointage_enseignants` et `badges` existent déjà dans develop.  
Le modèle `AbsenceJournaliere` existe déjà dans develop.  
Le service SMS `SmsService` existe déjà dans `app/Services/Sms/SmsService.php`.  
Le `BaseApiController` avec `success()`, `error()`, `notFound()`, `created()`, `paginatedResponse()` existe.

**Objectif :** créer les 2 controllers + 2 tests + 1 modèle manquant + 1 route absences.  
**Contrainte :** le CI doit rester vert (194 tests actuellement).

---

## FICHIER 1 — Modèle PointageEnseignant

**Chemin :** `edugestdz/backend/app/Models/PointageEnseignant.php`

```php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PointageEnseignant extends BaseModel
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'pointage_enseignants';

    protected $fillable = [
        'tenant_id',
        'enseignant_id',
        'date',
        'heure_arrivee',
        'heure_depart',
        'methode',
        'badge_uid',
        'statut',
        'notif_eleves_envoye',
        'impact_paie',
        'retenue_dzd',
        'note',
    ];

    protected $casts = [
        'date'                 => 'date',
        'notif_eleves_envoye'  => 'boolean',
        'impact_paie'          => 'boolean',
        'retenue_dzd'          => 'decimal:2',
    ];

    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(Enseignant::class);
    }

    // ── Durée travaillée en minutes ──
    public function getDureeTravailleeAttribute(): ?int
    {
        if (!$this->heure_arrivee || !$this->heure_depart) return null;
        $debut = \Carbon\Carbon::createFromTimeString($this->heure_arrivee);
        $fin   = \Carbon\Carbon::createFromTimeString($this->heure_depart);
        return $debut->diffInMinutes($fin);
    }
}
```

---

## FICHIER 2 — Modèle Badge

**Chemin :** `edugestdz/backend/app/Models/Badge.php`

```php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;

class Badge extends BaseModel
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'badge_uid',
        'proprietaire_id',
        'type_proprietaire',
        'actif',
        'date_emission',
    ];

    protected $casts = [
        'actif'         => 'boolean',
        'date_emission' => 'date',
    ];

    // ── Résoudre dynamiquement le propriétaire (élève, enseignant, personnel) ──
    public function proprietaire()
    {
        return match ($this->type_proprietaire) {
            'eleve'       => $this->belongsTo(Eleve::class,       'proprietaire_id'),
            'enseignant'  => $this->belongsTo(Enseignant::class,  'proprietaire_id'),
            default       => null,
        };
    }

    // ── Trouver un badge par son UID dans le tenant courant ──
    public static function trouverParUid(string $uid): ?self
    {
        return static::where('badge_uid', $uid)
            ->where('actif', true)
            ->first();
    }
}
```

---

## FICHIER 3 — Controller PointageBadgeController

**Chemin :** `edugestdz/backend/app/Http/Controllers/Api/V1/PointageBadgeController.php`

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\AbsenceJournaliere;
use App\Models\Badge;
use App\Models\Eleve;
use App\Models\Enseignant;
use App\Models\PointageEnseignant;
use App\Services\Sms\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Endpoint universel de pointage par badge RFID / NFC.
 *
 * Un lecteur physique (ou l'app mobile) envoie le badge_uid.
 * Le controller identifie la personne (élève ou enseignant),
 * enregistre l'entrée/sortie et notifie les parents si besoin.
 *
 * POST /api/v1/pointage/badge
 */
class PointageBadgeController extends BaseApiController
{
    public function __construct(private readonly SmsService $sms) {}

    public function scan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'badge_uid'   => 'required|string|max:100',
            'type'        => 'required|in:entrée,sortie',
            'terminal_id' => 'nullable|string|max:50',
        ]);

        $badge = Badge::trouverParUid($validated['badge_uid']);

        // ── Badge inconnu ou inactif ──
        if (!$badge) {
            Log::warning('PointageBadge: badge inconnu', ['uid' => $validated['badge_uid']]);
            return $this->error('Badge non reconnu ou inactif', 'BADGE_INCONNU', 404);
        }

        $heure = now()->format('H:i:s');
        $today = today();

        return match ($badge->type_proprietaire) {
            'eleve'      => $this->pointerEleve($badge, $validated['type'], $heure, $today),
            'enseignant' => $this->pointerEnseignant($badge, $validated['type'], $heure, $today),
            default      => $this->error('Type de propriétaire non géré', 'TYPE_INCONNU', 422),
        };
    }

    // ────────────────────────────────────────────
    // POINTAGE ÉLÈVE
    // ────────────────────────────────────────────
    private function pointerEleve(Badge $badge, string $type, string $heure, $today): JsonResponse
    {
        $eleve = Eleve::find($badge->proprietaire_id);

        if (!$eleve) {
            return $this->notFound('Élève associé au badge introuvable');
        }

        // Créer ou mettre à jour l'absence journalière
        $absence = AbsenceJournaliere::firstOrNew([
            'tenant_id'    => config('tenant.current_id'),
            'eleve_id'     => $eleve->id,
            'date_absence' => $today,
        ]);

        if ($type === 'entrée') {
            $heureLimite = config('etablissement.heure_limite_retard', '08:30');
            $enRetard    = $heure > $heureLimite;

            $absence->statut       = $enRetard ? 'retard' : 'present';
            $absence->heure_arrivee = $heure;
            $absence->signale_par  = 'badge';
            $absence->save();

            // SMS parent si retard
            if ($enRetard && !$absence->sms_parent_envoye) {
                $this->envoyerSmsRetardEleve($eleve, $heure);
                $absence->update(['sms_parent_envoye' => true, 'sms_envoye_at' => now()]);
            }

            $statut = $enRetard ? 'retard' : 'présent';
            $message = "✅ {$eleve->prenom} {$eleve->nom} — {$statut} à {$heure}";
        } else {
            $absence->statut = 'present';
            $absence->save();
            $message = "👋 {$eleve->prenom} {$eleve->nom} — sortie à {$heure}";
        }

        return $this->success([
            'personne'   => ['nom' => $eleve->nom, 'prenom' => $eleve->prenom],
            'role'       => 'élève',
            'type'       => $type,
            'heure'      => $heure,
            'statut'     => $absence->statut,
            'absence_id' => $absence->id,
        ], $message);
    }

    // ────────────────────────────────────────────
    // POINTAGE ENSEIGNANT
    // ────────────────────────────────────────────
    private function pointerEnseignant(Badge $badge, string $type, string $heure, $today): JsonResponse
    {
        $enseignant = Enseignant::find($badge->proprietaire_id);

        if (!$enseignant) {
            return $this->notFound('Enseignant associé au badge introuvable');
        }

        $pointage = PointageEnseignant::firstOrNew([
            'tenant_id'     => config('tenant.current_id'),
            'enseignant_id' => $enseignant->id,
            'date'          => $today,
        ]);

        if ($type === 'entrée') {
            if ($pointage->heure_arrivee) {
                return $this->error(
                    'Arrivée déjà enregistrée à ' . $pointage->heure_arrivee,
                    'DEJA_POINTE',
                    409
                );
            }

            $heureLimite = config('etablissement.heure_limite_retard_prof', '08:15');
            $enRetard    = $heure > $heureLimite;

            $pointage->heure_arrivee = $heure;
            $pointage->methode       = 'badge';
            $pointage->badge_uid     = $badge->badge_uid;
            $pointage->statut        = $enRetard ? 'retard' : 'present';
            $pointage->save();

            $statut  = $enRetard ? 'retard' : 'présent';
            $message = "✅ Prof {$enseignant->nom} {$enseignant->prenom} — {$statut} à {$heure}";

        } else {
            if (!$pointage->heure_arrivee) {
                return $this->error('Aucune arrivée enregistrée pour ce jour', 'PAS_ARRIVEE', 422);
            }

            $pointage->heure_depart = $heure;
            $pointage->save();

            $message = "👋 Prof {$enseignant->nom} {$enseignant->prenom} — sortie à {$heure}";
        }

        return $this->success([
            'personne'    => ['nom' => $enseignant->nom, 'prenom' => $enseignant->prenom],
            'role'        => 'enseignant',
            'type'        => $type,
            'heure'       => $heure,
            'statut'      => $pointage->statut,
            'pointage_id' => $pointage->id,
        ], $message);
    }

    // ────────────────────────────────────────────
    // SMS retard élève
    // ────────────────────────────────────────────
    private function envoyerSmsRetardEleve(Eleve $eleve, string $heure): void
    {
        $message = "EduGest DZ : Votre enfant {$eleve->prenom} {$eleve->nom} "
                 . "est arrivé en retard à {$heure}. Merci.";

        foreach ($eleve->parents as $parent) {
            if ($parent->telephone_1) {
                try {
                    $this->sms->send($parent->telephone_1, $message);
                } catch (\Throwable $e) {
                    Log::error('SMS retard élève échoué', [
                        'eleve_id' => $eleve->id,
                        'error'    => $e->getMessage(),
                    ]);
                }
            }
        }
    }
}
```

---

## FICHIER 4 — Controller PointageEnseignantController

**Chemin :** `edugestdz/backend/app/Http/Controllers/Api/V1/PointageEnseignantController.php`

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Enseignant;
use App\Models\PointageEnseignant;
use App\Services\Sms\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Gestion du pointage des enseignants.
 * Utilisé par l'admin / secrétaire pour les cas manuels
 * ou pour consulter l'historique.
 *
 * GET    /api/v1/pointage/enseignants/aujourd-hui
 * POST   /api/v1/pointage/enseignants/{id}/arrivee
 * POST   /api/v1/pointage/enseignants/{id}/depart
 * GET    /api/v1/pointage/enseignants/{id}/historique
 */
class PointageEnseignantController extends BaseApiController
{
    public function __construct(private readonly SmsService $sms) {}

    // ────────────────────────────────────────────
    // Vue du jour — tous les enseignants
    // GET /api/v1/pointage/enseignants/aujourd-hui
    // ────────────────────────────────────────────
    public function aujourdhui(): JsonResponse
    {
        $today       = today();
        $enseignants = Enseignant::with(['pointages' => fn($q) => $q->whereDate('date', $today)])
            ->where('statut', 'actif')
            ->get();

        $data = $enseignants->map(function (Enseignant $e) use ($today) {
            $pointage = $e->pointages->first();

            return [
                'enseignant'    => [
                    'id'     => $e->id,
                    'nom'    => $e->nom,
                    'prenom' => $e->prenom,
                    'photo'  => $e->photo_url,
                ],
                'statut'        => $pointage?->statut ?? 'absent',
                'heure_arrivee' => $pointage?->heure_arrivee,
                'heure_depart'  => $pointage?->heure_depart,
                'methode'       => $pointage?->methode,
                'pointe'        => (bool) $pointage,
            ];
        });

        $stats = [
            'total'    => $enseignants->count(),
            'presents' => $data->where('statut', 'present')->count(),
            'absents'  => $data->where('statut', 'absent')->count(),
            'retards'  => $data->where('statut', 'retard')->count(),
        ];

        return $this->success([
            'date'        => $today->format('d/m/Y'),
            'enseignants' => $data,
            'stats'       => $stats,
        ], "Pointage du {$today->format('d/m/Y')}");
    }

    // ────────────────────────────────────────────
    // Enregistrer arrivée manuelle
    // POST /api/v1/pointage/enseignants/{id}/arrivee
    // ────────────────────────────────────────────
    public function arrivee(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'heure_arrivee' => 'nullable|date_format:H:i',
            'note'          => 'nullable|string|max:500',
        ]);

        $enseignant = Enseignant::findOrFail($id);
        $today      = today();
        $heure      = $validated['heure_arrivee'] ?? now()->format('H:i');

        // Vérifier qu'il n'est pas déjà pointé
        $existant = PointageEnseignant::where('enseignant_id', $enseignant->id)
            ->whereDate('date', $today)
            ->whereNotNull('heure_arrivee')
            ->first();

        if ($existant) {
            return $this->error(
                "Arrivée déjà enregistrée à {$existant->heure_arrivee}",
                'DEJA_POINTE',
                409
            );
        }

        $heureLimite = config('etablissement.heure_limite_retard_prof', '08:15');
        $enRetard    = $heure > $heureLimite;

        $pointage = PointageEnseignant::create([
            'tenant_id'      => config('tenant.current_id'),
            'enseignant_id'  => $enseignant->id,
            'date'           => $today,
            'heure_arrivee'  => $heure,
            'methode'        => 'manuel',
            'statut'         => $enRetard ? 'retard' : 'present',
            'note'           => $validated['note'] ?? null,
        ]);

        return $this->created([
            'pointage'   => $pointage,
            'enseignant' => ['nom' => $enseignant->nom, 'prenom' => $enseignant->prenom],
            'statut'     => $pointage->statut,
        ], "Arrivée enregistrée : {$enseignant->prenom} {$enseignant->nom} à {$heure}");
    }

    // ────────────────────────────────────────────
    // Enregistrer départ
    // POST /api/v1/pointage/enseignants/{id}/depart
    // ────────────────────────────────────────────
    public function depart(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'heure_depart' => 'nullable|date_format:H:i',
            'note'         => 'nullable|string|max:500',
        ]);

        $enseignant = Enseignant::findOrFail($id);
        $today      = today();
        $heure      = $validated['heure_depart'] ?? now()->format('H:i');

        $pointage = PointageEnseignant::where('enseignant_id', $enseignant->id)
            ->whereDate('date', $today)
            ->first();

        if (!$pointage) {
            return $this->error(
                'Aucune arrivée enregistrée aujourd\'hui. Enregistrez d\'abord l\'arrivée.',
                'PAS_ARRIVEE',
                422
            );
        }

        if ($pointage->heure_depart) {
            return $this->error(
                "Départ déjà enregistré à {$pointage->heure_depart}",
                'DEJA_POINTE',
                409
            );
        }

        $pointage->update([
            'heure_depart' => $heure,
            'note'         => $validated['note'] ?? $pointage->note,
        ]);

        return $this->success([
            'pointage'        => $pointage->fresh(),
            'duree_minutes'   => $pointage->fresh()->duree_travaillee,
            'enseignant'      => ['nom' => $enseignant->nom, 'prenom' => $enseignant->prenom],
        ], "Départ enregistré : {$enseignant->prenom} {$enseignant->nom} à {$heure}");
    }

    // ────────────────────────────────────────────
    // Historique par enseignant
    // GET /api/v1/pointage/enseignants/{id}/historique
    // ────────────────────────────────────────────
    public function historique(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'debut'    => 'nullable|date',
            'fin'      => 'nullable|date|after_or_equal:debut',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $enseignant = Enseignant::findOrFail($id);

        $debut = $validated['debut'] ?? now()->startOfMonth()->toDateString();
        $fin   = $validated['fin']   ?? now()->toDateString();

        $paginator = PointageEnseignant::where('enseignant_id', $enseignant->id)
            ->whereBetween('date', [$debut, $fin])
            ->orderByDesc('date')
            ->paginate($validated['per_page'] ?? 30);

        // Stats de la période
        $items = PointageEnseignant::where('enseignant_id', $enseignant->id)
            ->whereBetween('date', [$debut, $fin])
            ->get();

        $stats = [
            'total_jours'   => $items->count(),
            'presents'      => $items->where('statut', 'present')->count(),
            'absents'       => $items->where('statut', 'absent')->count(),
            'retards'       => $items->where('statut', 'retard')->count(),
            'conges'        => $items->where('statut', 'conge')->count(),
            'maladies'      => $items->where('statut', 'maladie')->count(),
            'duree_totale_minutes' => $items->sum(fn($p) => $p->duree_travaillee ?? 0),
        ];

        return $this->paginatedResponse($paginator, 'Historique récupéré', [
            'enseignant' => ['id' => $enseignant->id, 'nom' => "{$enseignant->nom} {$enseignant->prenom}"],
            'periode'    => ['debut' => $debut, 'fin' => $fin],
            'stats'      => $stats,
        ]);
    }
}
```

---

## FICHIER 5 — Ajouter relation `pointages` sur le modèle Enseignant

**Chemin :** `edugestdz/backend/app/Models/Enseignant.php`  
**Action :** Ajouter ces méthodes à la fin de la classe (avant la dernière `}`)

```php
// ── Pointages ──
public function pointages(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(PointageEnseignant::class);
}

public function pointageAujourdhui(): \Illuminate\Database\Eloquent\Relations\HasOne
{
    return $this->hasOne(PointageEnseignant::class)
        ->whereDate('date', today());
}

public function isPresent(): bool
{
    return $this->pointageAujourdhui()->whereNotNull('heure_arrivee')->exists();
}
```

---

## FICHIER 6 — Routes absences élèves (à ajouter dans api.php)

**Chemin :** `edugestdz/backend/routes/api.php`  
**Action :** Ajouter dans le groupe protégé par `auth:api`, après le bloc `// ── Présences ──` existant :

```php
// ── Absences journalières élèves ──
Route::prefix('absences')->group(function () {
    Route::get('/',                          [\App\Http\Controllers\Api\V1\AbsenceController::class, 'index']);
    Route::post('/{eleveId}',                [\App\Http\Controllers\Api\V1\AbsenceController::class, 'marquerPresent']);
    Route::put('/{id}/justifier',            [\App\Http\Controllers\Api\V1\AbsenceController::class, 'justifier']);
    Route::get('/rapport',                   [\App\Http\Controllers\Api\V1\AbsenceController::class, 'rapport']);
    Route::post('/badges/assigner',          [\App\Http\Controllers\Api\V1\AbsenceController::class, 'assignerBadge']);
});
```

---

## FICHIER 7 — Controller AbsenceController

**Chemin :** `edugestdz/backend/app/Http/Controllers/Api/V1/AbsenceController.php`

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Jobs\NotifierAbsenceParent;
use App\Models\AbsenceJournaliere;
use App\Models\Badge;
use App\Models\Eleve;
use App\Models\JustificatifAbsence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gestion des absences journalières élèves.
 *
 * GET  /api/v1/absences               → Liste du jour (ou filtrable)
 * POST /api/v1/absences/{eleveId}     → Marquer présent manuellement
 * PUT  /api/v1/absences/{id}/justifier → Soumettre / valider justificatif
 * GET  /api/v1/absences/rapport       → Rapport mensuel
 * POST /api/v1/absences/badges/assigner → Assigner un badge à un élève
 */
class AbsenceController extends BaseApiController
{
    // ────────────────────────────────────────────
    // Liste des absences du jour (ou par période)
    // ────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date'          => 'nullable|date',
            'statut'        => 'nullable|in:absent,present,retard,demi_journee',
            'classe_id'     => 'nullable|uuid',
            'per_page'      => 'nullable|integer|min:5|max:100',
        ]);

        $date = $validated['date'] ?? today()->toDateString();

        $query = AbsenceJournaliere::with(['eleve:id,nom,prenom,photo_url,niveau_scolaire', 'justificatif'])
            ->whereDate('date_absence', $date);

        if (isset($validated['statut'])) {
            $query->where('statut', $validated['statut']);
        }

        $paginator = $query->orderBy('created_at', 'asc')
            ->paginate($validated['per_page'] ?? 30);

        $stats = AbsenceJournaliere::whereDate('date_absence', $date)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN statut = 'absent' THEN 1 ELSE 0 END) as absents,
                SUM(CASE WHEN statut = 'present' THEN 1 ELSE 0 END) as presents,
                SUM(CASE WHEN statut = 'retard' THEN 1 ELSE 0 END) as retards
            ")
            ->first();

        return $this->paginatedResponse($paginator, "Absences du {$date}", [
            'date'  => $date,
            'stats' => $stats,
        ]);
    }

    // ────────────────────────────────────────────
    // Marquer un élève présent manuellement
    // POST /api/v1/absences/{eleveId}
    // ────────────────────────────────────────────
    public function marquerPresent(Request $request, string $eleveId): JsonResponse
    {
        $validated = $request->validate([
            'statut'        => 'required|in:present,retard,demi_journee',
            'heure_arrivee' => 'nullable|date_format:H:i',
            'note'          => 'nullable|string|max:500',
        ]);

        $eleve = Eleve::findOrFail($eleveId);
        $today = today();

        $absence = AbsenceJournaliere::updateOrCreate(
            [
                'tenant_id'    => config('tenant.current_id'),
                'eleve_id'     => $eleve->id,
                'date_absence' => $today,
            ],
            [
                'statut'        => $validated['statut'],
                'heure_arrivee' => $validated['heure_arrivee'] ?? now()->format('H:i'),
                'signale_par'   => 'admin',
                'motif'         => $validated['note'] ?? null,
            ]
        );

        return $this->success([
            'absence' => $absence,
            'eleve'   => ['nom' => $eleve->nom, 'prenom' => $eleve->prenom],
        ], "{$eleve->prenom} {$eleve->nom} marqué(e) {$validated['statut']}");
    }

    // ────────────────────────────────────────────
    // Soumettre ou valider un justificatif
    // PUT /api/v1/absences/{id}/justifier
    // ────────────────────────────────────────────
    public function justifier(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'motif'    => 'required|string|max:500',
            'document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'action'   => 'nullable|in:soumettre,valider,refuser',
        ]);

        $absence = AbsenceJournaliere::with('eleve')->findOrFail($id);
        $action  = $validated['action'] ?? 'soumettre';

        $documentUrl = null;
        if ($request->hasFile('document')) {
            $documentUrl = $request->file('document')
                ->store('justificatifs/' . config('tenant.current_id'), 'public');
        }

        $justificatif = JustificatifAbsence::updateOrCreate(
            ['absence_id' => $absence->id],
            [
                'tenant_id'    => config('tenant.current_id'),
                'motif'        => $validated['motif'],
                'document_url' => $documentUrl ?? null,
                'statut'       => match ($action) {
                    'valider'  => 'valide',
                    'refuser'  => 'refuse',
                    default    => 'en_attente',
                },
                'valide_par'   => in_array($action, ['valider', 'refuser']) ? auth()->id() : null,
                'valide_at'    => in_array($action, ['valider', 'refuser']) ? now() : null,
            ]
        );

        return $this->success([
            'justificatif' => $justificatif,
            'absence'      => $absence,
        ], "Justificatif {$justificatif->statut}");
    }

    // ────────────────────────────────────────────
    // Rapport mensuel d'absences
    // GET /api/v1/absences/rapport
    // ────────────────────────────────────────────
    public function rapport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mois'  => 'nullable|integer|min:1|max:12',
            'annee' => 'nullable|integer|min:2020|max:2030',
        ]);

        $mois  = $validated['mois']  ?? now()->month;
        $annee = $validated['annee'] ?? now()->year;

        $absences = AbsenceJournaliere::with('eleve:id,nom,prenom,niveau_scolaire')
            ->whereMonth('date_absence', $mois)
            ->whereYear('date_absence', $annee)
            ->get();

        // Grouper par élève
        $parEleve = $absences->groupBy('eleve_id')->map(function ($absences) {
            $eleve = $absences->first()->eleve;
            return [
                'eleve'          => ['id' => $eleve->id, 'nom' => $eleve->nom, 'prenom' => $eleve->prenom, 'niveau' => $eleve->niveau_scolaire],
                'total_absences' => $absences->where('statut', 'absent')->count(),
                'total_retards'  => $absences->where('statut', 'retard')->count(),
                'justifiees'     => $absences->filter(fn($a) => $a->justificatif?->statut === 'valide')->count(),
                'non_justifiees' => $absences->where('statut', 'absent')
                    ->filter(fn($a) => !$a->justificatif || $a->justificatif->statut !== 'valide')->count(),
            ];
        })->values();

        // Élèves à risque (3+ absences non justifiées)
        $aRisque = $parEleve->filter(fn($e) => $e['non_justifiees'] >= 3);

        return $this->success([
            'periode'       => ['mois' => $mois, 'annee' => $annee],
            'par_eleve'     => $parEleve,
            'a_risque'      => $aRisque->values(),
            'total_absences'=> $absences->where('statut', 'absent')->count(),
            'total_retards' => $absences->where('statut', 'retard')->count(),
        ], "Rapport absences {$mois}/{$annee}");
    }

    // ────────────────────────────────────────────
    // Assigner un badge RFID à un élève
    // POST /api/v1/absences/badges/assigner
    // ────────────────────────────────────────────
    public function assignerBadge(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'eleve_id'      => 'required|uuid|exists:eleves,id',
            'badge_uid'     => 'required|string|max:100',
            'date_emission' => 'nullable|date',
        ]);

        // Vérifier que le badge n'est pas déjà assigné à quelqu'un d'autre
        $existant = Badge::where('badge_uid', $validated['badge_uid'])
            ->where('proprietaire_id', '!=', $validated['eleve_id'])
            ->exists();

        if ($existant) {
            return $this->error('Ce badge est déjà assigné à un autre utilisateur', 'BADGE_DEJA_ASSIGNE', 409);
        }

        $badge = Badge::updateOrCreate(
            [
                'tenant_id' => config('tenant.current_id'),
                'badge_uid' => $validated['badge_uid'],
            ],
            [
                'proprietaire_id'    => $validated['eleve_id'],
                'type_proprietaire'  => 'eleve',
                'actif'              => true,
                'date_emission'      => $validated['date_emission'] ?? today(),
            ]
        );

        $eleve = Eleve::find($validated['eleve_id']);

        return $this->created([
            'badge' => $badge,
            'eleve' => ['nom' => $eleve->nom, 'prenom' => $eleve->prenom],
        ], "Badge {$validated['badge_uid']} assigné à {$eleve->prenom} {$eleve->nom}");
    }
}
```

---

## FICHIER 8 — Tests

**Chemin :** `edugestdz/backend/tests/Feature/Api/PointageTest.php`

```php
<?php

namespace Tests\Feature\Api;

use App\Models\Badge;
use App\Models\Eleve;
use App\Models\Enseignant;
use App\Models\PointageEnseignant;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PointageTest extends TestCase
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

    // ── Tableau du jour ──
    public function test_aujourdhui_retourne_liste_enseignants(): void
    {
        Enseignant::factory()->count(3)->create(['tenant_id' => $this->tenant->id, 'statut' => 'actif']);

        $this->withToken($this->token)
            ->getJson('/api/v1/pointage/enseignants/aujourd-hui')
            ->assertStatus(200)
            ->assertJsonPath('data.stats.total', 3)
            ->assertJsonStructure(['data' => ['date', 'enseignants', 'stats']]);
    }

    // ── Arrivée manuelle ──
    public function test_arrivee_manuelle_enregistree(): void
    {
        $enseignant = Enseignant::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->postJson("/api/v1/pointage/enseignants/{$enseignant->id}/arrivee", [
                'heure_arrivee' => '08:00',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.statut', 'present');

        $this->assertDatabaseHas('pointage_enseignants', [
            'enseignant_id' => $enseignant->id,
            'heure_arrivee' => '08:00:00',
            'statut'        => 'present',
        ]);
    }

    // ── Retard détecté ──
    public function test_arrivee_tardive_detecte_retard(): void
    {
        $enseignant = Enseignant::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->postJson("/api/v1/pointage/enseignants/{$enseignant->id}/arrivee", [
                'heure_arrivee' => '09:30',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.statut', 'retard');
    }

    // ── Double arrivée bloquée ──
    public function test_double_arrivee_bloquee(): void
    {
        $enseignant = Enseignant::factory()->create(['tenant_id' => $this->tenant->id]);

        PointageEnseignant::create([
            'tenant_id'     => $this->tenant->id,
            'enseignant_id' => $enseignant->id,
            'date'          => today(),
            'heure_arrivee' => '08:00',
            'statut'        => 'present',
            'methode'       => 'manuel',
        ]);

        $this->withToken($this->token)
            ->postJson("/api/v1/pointage/enseignants/{$enseignant->id}/arrivee", [
                'heure_arrivee' => '08:05',
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'DEJA_POINTE');
    }

    // ── Départ sans arrivée bloqué ──
    public function test_depart_sans_arrivee_bloque(): void
    {
        $enseignant = Enseignant::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->postJson("/api/v1/pointage/enseignants/{$enseignant->id}/depart", [
                'heure_depart' => '17:00',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'PAS_ARRIVEE');
    }

    // ── Historique ──
    public function test_historique_retourne_donnees(): void
    {
        $enseignant = Enseignant::factory()->create(['tenant_id' => $this->tenant->id]);

        PointageEnseignant::create([
            'tenant_id'     => $this->tenant->id,
            'enseignant_id' => $enseignant->id,
            'date'          => today(),
            'heure_arrivee' => '08:00',
            'statut'        => 'present',
            'methode'       => 'manuel',
        ]);

        $this->withToken($this->token)
            ->getJson("/api/v1/pointage/enseignants/{$enseignant->id}/historique")
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonStructure(['meta' => ['stats']]);
    }

    // ── Scan badge élève inconnu ──
    public function test_badge_inconnu_retourne_404(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/pointage/badge', [
                'badge_uid' => 'BADGE-INCONNU-999',
                'type'      => 'entrée',
            ])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'BADGE_INCONNU');
    }

    // ── Assigner badge à un élève ──
    public function test_assigner_badge_eleve(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->withToken($this->token)
            ->postJson('/api/v1/absences/badges/assigner', [
                'eleve_id'  => $eleve->id,
                'badge_uid' => 'AB:CD:EF:01',
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('badges', [
            'badge_uid'         => 'AB:CD:EF:01',
            'proprietaire_id'   => $eleve->id,
            'type_proprietaire' => 'eleve',
            'actif'             => true,
        ]);
    }

    // ── Scan badge élève existant → marque présent ──
    public function test_scan_badge_eleve_marque_present(): void
    {
        $eleve = Eleve::factory()->create(['tenant_id' => $this->tenant->id]);

        Badge::create([
            'tenant_id'         => $this->tenant->id,
            'badge_uid'         => 'AA:BB:CC:DD',
            'proprietaire_id'   => $eleve->id,
            'type_proprietaire' => 'eleve',
            'actif'             => true,
        ]);

        $this->withToken($this->token)
            ->postJson('/api/v1/pointage/badge', [
                'badge_uid' => 'AA:BB:CC:DD',
                'type'      => 'entrée',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.role', 'élève');

        $this->assertDatabaseHas('absences_journalieres', [
            'eleve_id'     => $eleve->id,
            'signale_par'  => 'badge',
        ]);
    }

    // ── Isolation tenant : badge autre tenant invisible ──
    public function test_badge_autre_tenant_invisible(): void
    {
        $autreTenant = Tenant::factory()->create();
        $autreEleve  = Eleve::factory()->create(['tenant_id' => $autreTenant->id]);

        // Badge créé sans BelongsToTenant (on bypass pour le test)
        \DB::table('badges')->insert([
            'id'                => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id'         => $autreTenant->id,
            'badge_uid'         => 'AUTRE-TENANT-BADGE',
            'proprietaire_id'   => $autreEleve->id,
            'type_proprietaire' => 'eleve',
            'actif'             => true,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        // Scan depuis tenant A → badge du tenant B non trouvé
        $this->withToken($this->token)
            ->postJson('/api/v1/pointage/badge', [
                'badge_uid' => 'AUTRE-TENANT-BADGE',
                'type'      => 'entrée',
            ])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'BADGE_INCONNU');
    }
}
```

---

## ORDRE D'EXÉCUTION POUR DEEPSEEK

```
1. git checkout develop

2. Créer FICHIER 1 : app/Models/PointageEnseignant.php

3. Créer FICHIER 2 : app/Models/Badge.php

4. Créer FICHIER 3 : app/Http/Controllers/Api/V1/PointageBadgeController.php

5. Créer FICHIER 4 : app/Http/Controllers/Api/V1/PointageEnseignantController.php

6. Modifier FICHIER 5 : app/Models/Enseignant.php
   → Ajouter les 3 méthodes pointages(), pointageAujourdhui(), isPresent()

7. Modifier FICHIER 6 : routes/api.php
   → Ajouter le bloc Route::prefix('absences') dans le groupe auth:api

8. Créer FICHIER 7 : app/Http/Controllers/Api/V1/AbsenceController.php

9. Créer FICHIER 8 : tests/Feature/Api/PointageTest.php

10. Lancer les tests :
    php artisan test --parallel
    → Attendu : 194 + ~10 nouveaux = ~204 tests verts

11. Si un test échoue → corriger avant de continuer

12. git add .
    git commit -m "feat: PointageBadge + PointageEnseignant + AbsenceController + 10 tests"
    git push origin develop

13. Ouvrir PR develop → main sur GitHub
```

---

## VÉRIFICATION FINALE

Après exécution, vérifier :
- ✅ `GET /api/v1/pointage/enseignants/aujourd-hui` → 200
- ✅ `POST /api/v1/pointage/enseignants/{id}/arrivee` → 201
- ✅ `POST /api/v1/pointage/badge` avec badge inconnu → 404
- ✅ `POST /api/v1/pointage/badge` avec badge connu → 200
- ✅ Tous les tests passent (aucune régression)
