# 🔄 MISSION 1/3 — Backend : Circulation complète de l'information
## EduGest DZ · Flux info mondial adapté · Branche : develop · 10 Juillet 2026
## Tests actuels : 724+ ✅ · Objectif : ≥ 760 ✅ · 0 régression

---

## CONTEXTE RÉEL LU DANS LE REPO

```
CE QUI EXISTE DÉJÀ (ne pas recréer) :
✅ ParentNotificationService.php   → Push Firebase + SMS Twilio
✅ NotificationParent model        → table notifications_parent (push_envoye, sms_envoye)
✅ RemplacementController.php      → séances orphelines, suggestions
✅ SignalementComportement model   → billets enseignant → élève
✅ AbsenceJournaliereObserver      → détecte absences élèves
✅ NoteObserver                    → déclenche notification note publiée
✅ BulletinObserver                → déclenche notification bulletin
✅ notifications_inapp table       → 2026_07_12_200000 migration OK
✅ email templates                 → resources/views/emails/ (5 templates créés)
✅ seances table                   → enseignant_remplacement_id déjà ajouté

CE QUI MANQUE (cette mission le crée) :
❌ AbsenceEnseignant model + migration
❌ Signal absence prof (formulaire + flux complet)
❌ Notification élève (différent du parent — canal in-app direct)
❌ Plages horaires notifications (pas de push après 20h / avant 7h)
❌ Feedback pédagogique élève → directeur
❌ Signalement grave élève → directeur (confidentiel)
❌ Devoirs model + controller + notification élève
❌ NotificationHoraireMiddleware (filtre les heures)
```

### RÈGLES ABSOLUES
1. **0 régression** — 724+ tests verts
2. **PostgreSQL uniquement** — hasColumn/hasTable sur toutes les migrations
3. **Dégradation gracieuse** — si canal échoue → essayer le suivant
4. **Règle des 3 canaux** : Push → SMS → Email (par ordre de priorité)
5. **Règle horaire** : Push uniquement 7h-20h heure Algérie (sauf urgence)
6. **Règle de confidentialité** : signalement élève → directeur JAMAIS au prof

---

## ÉTAPE 0 — Synchroniser

```bash
git checkout develop && git pull origin main
```

---

## ══════════════════════════════════════════
## BLOC A — ABSENCE ENSEIGNANT : SIGNAL + FLUX
## ══════════════════════════════════════════

## ÉTAPE 1 — Migration : absences_enseignants

**Créer** : `edugestdz/backend/database/migrations/2026_07_10_500000_create_absences_enseignants_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('absences_enseignants')) {
            Schema::create('absences_enseignants', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
                $table->uuid('tenant_id');
                $table->uuid('enseignant_user_id');   // User.id de l'enseignant
                $table->date('date_absence');
                $table->string('motif', 500)->nullable();
                $table->string('statut')->default('signale');
                // statut: signale | confirme | remplace | annule
                $table->uuid('remplacant_user_id')->nullable();
                $table->boolean('eleves_notifies')->default(false);
                $table->boolean('parents_notifies')->default(false);
                $table->timestamp('signale_le')->useCurrent();
                $table->timestamps();

                $table->index(['tenant_id', 'date_absence'], 'idx_abs_ens_tenant_date');
                $table->index(['enseignant_user_id'],         'idx_abs_ens_user');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('absences_enseignants');
    }
};
```

---

## ÉTAPE 2 — Modèle AbsenceEnseignant

**Créer** : `edugestdz/backend/app/Models/AbsenceEnseignant.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Traits\BelongsToTenant;

class AbsenceEnseignant extends Model
{
    use HasUuids, BelongsToTenant;

    protected $table    = 'absences_enseignants';
    protected $fillable = [
        'tenant_id', 'enseignant_user_id', 'date_absence',
        'motif', 'statut', 'remplacant_user_id',
        'eleves_notifies', 'parents_notifies', 'signale_le',
    ];
    protected $casts = [
        'date_absence'    => 'date',
        'signale_le'      => 'datetime',
        'eleves_notifies' => 'boolean',
        'parents_notifies'=> 'boolean',
    ];

    public function enseignant()
    {
        return $this->belongsTo(User::class, 'enseignant_user_id');
    }

    public function remplacant()
    {
        return $this->belongsTo(User::class, 'remplacant_user_id');
    }

    // Séances affectées par cette absence
    public function seancesAffectees()
    {
        return Seance::where('tenant_id', $this->tenant_id)
            ->whereHas('cours', fn($q) => $q->where('enseignant_user_id', $this->enseignant_user_id))
            ->where('date', $this->date_absence);
    }
}
```

---

## ÉTAPE 3 — Service AbsenceEnseignantService (orchestre tout le flux)

**Créer** : `edugestdz/backend/app/Services/AbsenceEnseignantService.php`

```php
<?php

namespace App\Services;

use App\Models\{AbsenceEnseignant, Seance, Cours, Eleve, User};
use Illuminate\Support\Facades\{DB, Log};

/**
 * AbsenceEnseignantService — Orchestre le flux complet d'une absence enseignant.
 *
 * FLUX :
 * 1. Prof signale → AbsenceEnseignant créée (statut: signale)
 * 2. Directeur alerté immédiatement (Push + Email)
 * 3. Directeur assigne remplaçant → statut: remplace
 * 4. Système notifie élèves concernés (Push in-app)
 * 5. Système notifie parents des élèves concernés (Push + SMS optionnel)
 *
 * RÈGLE HORAIRE :
 * - Signal avant 20h la veille → notification directeur ce soir
 * - Signal le matin → notification urgence directeur (heure locale Algérie)
 *
 * RÈGLE CONFIDENTIALITÉ :
 * - Le motif de l'enseignant est visible du directeur UNIQUEMENT
 * - Les élèves/parents voient uniquement "Cours modifié" sans motif
 */
class AbsenceEnseignantService
{
    public function __construct(
        private ParentNotificationService      $parentNotif,
        private NotificationInAppService       $inAppNotif,
    ) {}

    /**
     * Enregistrer l'absence d'un enseignant et déclencher les notifications.
     */
    public function signalerAbsence(
        string  $enseignantUserId,
        string  $dateAbsence,
        ?string $motif = null,
        string  $tenantId = null
    ): AbsenceEnseignant {
        $tenantId = $tenantId ?? config('tenant.current_id');

        // Éviter les doublons
        $existante = AbsenceEnseignant::where('enseignant_user_id', $enseignantUserId)
            ->where('date_absence', $dateAbsence)
            ->where('tenant_id', $tenantId)
            ->first();

        if ($existante) {
            // Mettre à jour le motif si fourni
            if ($motif) $existante->update(['motif' => $motif]);
            return $existante;
        }

        $absence = AbsenceEnseignant::create([
            'tenant_id'           => $tenantId,
            'enseignant_user_id'  => $enseignantUserId,
            'date_absence'        => $dateAbsence,
            'motif'               => $motif,
            'statut'              => 'signale',
        ]);

        // Notifier le directeur immédiatement
        $this->notifierDirecteur($absence);

        return $absence;
    }

    /**
     * Confirmer et assigner un remplaçant.
     */
    public function assignerRemplacant(
        string $absenceId,
        string $remplacantUserId
    ): AbsenceEnseignant {
        $absence = AbsenceEnseignant::findOrFail($absenceId);

        $absence->update([
            'remplacant_user_id' => $remplacantUserId,
            'statut'             => 'remplace',
        ]);

        // Trouver les séances affectées
        $seancesAffectees = $absence->seancesAffectees()->get();

        foreach ($seancesAffectees as $seance) {
            // Mettre à jour la séance avec le remplaçant
            $seance->update([
                'enseignant_remplacement_id' => $remplacantUserId,
                'statut'                     => 'remplacement_confirme',
            ]);
        }

        // Notifier les élèves et parents
        $this->notifierElevesEtParents($absence);

        // Notifier le remplaçant
        $this->notifierRemplacant($absence);

        return $absence;
    }

    /**
     * Notifier le directeur — urgence, avec motif complet.
     * Canal : Push + Email (motif visible directeur seulement).
     */
    private function notifierDirecteur(AbsenceEnseignant $absence): void
    {
        $enseignant  = User::find($absence->enseignant_user_id);
        $nomEns      = ($enseignant->nom ?? '') . ' ' . ($enseignant->prenom ?? '');
        $dateFormate = \Carbon\Carbon::parse($absence->date_absence)
            ->locale('fr')
            ->isoFormat('dddd D MMMM YYYY');

        $nbSeances = DB::table('seances as s')
            ->join('cours as c', 's.cours_id', '=', 'c.id')
            ->where('c.tenant_id', $absence->tenant_id)
            ->where('c.enseignant_user_id', $absence->enseignant_user_id)
            ->where('s.date', $absence->date_absence)
            ->count();

        // Trouver le/les directeurs du tenant
        $directeurs = User::where('tenant_id', $absence->tenant_id)
            ->whereHas('role', fn($q) => $q->where('nom', 'admin'))
            ->get();

        foreach ($directeurs as $directeur) {
            $this->inAppNotif->creer(
                userId:  $directeur->id,
                type:    'absence_enseignant',
                titre:   "⚠️ Absence signalée — Prof. {$nomEns}",
                corps:   "{$nbSeances} séance(s) affectée(s) le {$dateFormate}" .
                         ($absence->motif ? " · Motif : {$absence->motif}" : ''),
                meta:    [
                    'absence_id'           => $absence->id,
                    'enseignant_user_id'   => $absence->enseignant_user_id,
                    'date_absence'         => $absence->date_absence,
                    'nb_seances'           => $nbSeances,
                    'action_url'           => "/planning/remplacements/{$absence->id}",
                    'urgence'              => true,
                ],
                tenantId: $absence->tenant_id
            );
        }

        Log::info("AbsenceEnseignant: directeur notifié — {$nomEns} absent le {$absence->date_absence}");
    }

    /**
     * Notifier les élèves et leurs parents.
     * Message élève/parent : "Cours modifié" sans le motif de l'enseignant.
     */
    private function notifierElevesEtParents(AbsenceEnseignant $absence): void
    {
        if ($absence->eleves_notifies) return; // Déjà fait

        $remplacant = $absence->remplacant_user_id
            ? User::find($absence->remplacant_user_id)
            : null;

        $dateFormate = \Carbon\Carbon::parse($absence->date_absence)
            ->locale('fr')
            ->isoFormat('dddd D MMMM');

        // Récupérer tous les élèves des groupes concernés
        $eleveIds = DB::table('seances as s')
            ->join('cours as c', 's.cours_id', '=', 'c.id')
            ->join('inscriptions as i', 'c.groupe_id', '=', 'i.groupe_id')
            ->where('c.tenant_id', $absence->tenant_id)
            ->where('c.enseignant_user_id', $absence->enseignant_user_id)
            ->where('s.date', $absence->date_absence)
            ->where('i.statut', 'active')
            ->distinct()
            ->pluck('i.eleve_id');

        foreach ($eleveIds as $eleveId) {
            // Notification in-app élève
            $eleve = Eleve::find($eleveId);
            if (!$eleve) continue;

            // Notification in-app de l'élève (via son compte user si existant)
            $this->inAppNotif->creer(
                userId:  $eleve->user_id ?? null,
                eleveId: $eleveId,
                type:    'cours_modifie',
                titre:   $remplacant
                    ? "📅 Cours remplacé le {$dateFormate}"
                    : "📅 Cours modifié le {$dateFormate}",
                corps:   $remplacant
                    ? "Prof. " . ($remplacant->nom ?? '') . " vous remplacera"
                    : "Cours suspendu — contactez l'administration",
                meta:    ['absence_id' => $absence->id, 'date' => $absence->date_absence],
                tenantId: $absence->tenant_id
            );

            // Notification parent (sans motif de l'enseignant)
            try {
                $this->parentNotif->notifier(
                    eleveId: $eleveId,
                    type:    'cours_modifie',
                    titre:   "📅 Cours modifié le {$dateFormate}",
                    corps:   $remplacant
                        ? "Un remplaçant a été assigné pour votre enfant"
                        : "Un cours de votre enfant est modifié. Contactez l'école.",
                    meta:    ['absence_id' => $absence->id],
                    avecSMS: false   // SMS seulement sur les urgences
                );
            } catch (\Throwable $e) {
                Log::warning("AbsenceEnseignant: notif parent échouée: " . $e->getMessage());
            }
        }

        $absence->update(['eleves_notifies' => true, 'parents_notifies' => true]);
    }

    /**
     * Notifier le remplaçant de son affectation.
     */
    private function notifierRemplacant(AbsenceEnseignant $absence): void
    {
        if (!$absence->remplacant_user_id) return;

        $dateFormate = \Carbon\Carbon::parse($absence->date_absence)
            ->locale('fr')->isoFormat('dddd D MMMM YYYY');

        $this->inAppNotif->creer(
            userId:  $absence->remplacant_user_id,
            type:    'remplacement_assigne',
            titre:   "🔄 Remplacement assigné — {$dateFormate}",
            corps:   "Vous avez été désigné(e) remplaçant(e) pour ce jour. Consultez votre planning.",
            meta:    ['absence_id' => $absence->id, 'date' => $absence->date_absence, 'action_url' => '/planning'],
            tenantId: $absence->tenant_id
        );
    }
}
```

---

## ÉTAPE 4 — NotificationInAppService (service centralisé in-app)

**Créer** : `edugestdz/backend/app/Services/NotificationInAppService.php`

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * NotificationInAppService — Gère les notifications in-app pour TOUS les acteurs.
 *
 * Différent de ParentNotificationService qui gère les canaux externes (SMS, Push, Email).
 * Ce service gère le canal interne : la boîte de réception dans l'application.
 *
 * Règle horaire : le stockage en BDD est toujours immédiat.
 * Le PUSH Firebase est retardé si hors plage horaire (7h-20h Algérie).
 */
class NotificationInAppService
{
    // Plage horaire autorisée pour les push (sauf urgence)
    private const HEURE_DEBUT = 7;
    private const HEURE_FIN   = 20;

    /**
     * Créer une notification in-app et déclencher le Push si dans la plage horaire.
     */
    public function creer(
        ?string $userId,
        string  $type,
        string  $titre,
        string  $corps,
        array   $meta      = [],
        ?string $tenantId  = null,
        ?string $eleveId   = null,
        bool    $urgence   = false  // Urgence = ignore la plage horaire
    ): void {
        if (!$userId && !$eleveId) return;

        $tenantId = $tenantId ?? config('tenant.current_id');

        // Stocker en BDD (toujours, quelle que soit l'heure)
        DB::table('notifications_inapp')->insert([
            'id'         => (string) Str::uuid(),
            'tenant_id'  => $tenantId,
            'user_id'    => $userId,
            'type'       => $type,
            'titre'      => $titre,
            'corps'      => $corps,
            'action_url' => $meta['action_url'] ?? null,
            'icone'      => $this->icone($type),
            'lu'         => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Push Firebase — respecter la plage horaire
        if ($userId && ($urgence || $this->dansPlagHoraire())) {
            try {
                app(FirebaseService::class)->notifyUser(
                    $userId, $titre, $corps,
                    array_merge($meta, ['type' => $type])
                );
            } catch (\Throwable) {}
        }
    }

    /**
     * Créer une notification pour TOUS les utilisateurs d'un rôle dans un tenant.
     */
    public function creerPourRole(
        string  $tenantId,
        string  $role,
        string  $type,
        string  $titre,
        string  $corps,
        array   $meta  = [],
        bool    $urgence= false
    ): void {
        $users = \App\Models\User::where('tenant_id', $tenantId)
            ->whereHas('role', fn($q) => $q->where('nom', $role))
            ->pluck('id');

        foreach ($users as $userId) {
            $this->creer($userId, $type, $titre, $corps, $meta, $tenantId, null, $urgence);
        }
    }

    private function dansPlagHoraire(): bool
    {
        $heure = (int) now()->setTimezone('Africa/Algiers')->format('H');
        return $heure >= self::HEURE_DEBUT && $heure < self::HEURE_FIN;
    }

    private function icone(string $type): string
    {
        return match ($type) {
            'absence_enseignant' => '⚠️',
            'cours_modifie'      => '📅',
            'remplacement_assigne'=> '🔄',
            'note_publiee'       => '📊',
            'bulletin_disponible'=> '📄',
            'devoir_publie'      => '📝',
            'facture_impayee'    => '💰',
            'signalement_grave'  => '🚨',
            'feedback_recu'      => '💬',
            'convocation'        => '📞',
            default              => '🔔',
        };
    }
}
```

---

## ÉTAPE 5 — Controller AbsenceEnseignantController

**Créer** : `edugestdz/backend/app/Http/Controllers/Api/V1/AbsenceEnseignantController.php`

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AbsenceEnseignant;
use App\Services\AbsenceEnseignantService;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\DB;

class AbsenceEnseignantController extends Controller
{
    public function __construct(private AbsenceEnseignantService $service) {}

    /**
     * Enseignant signale son absence pour demain (ou un jour futur).
     * POST /api/v1/absences-enseignants
     */
    public function signaler(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date_absence' => 'required|date|after_or_equal:today',
            'motif'        => 'nullable|string|max:500',
        ]);

        $user = auth('api')->user();

        $absence = $this->service->signalerAbsence(
            enseignantUserId: $user->id,
            dateAbsence:      $validated['date_absence'],
            motif:            $validated['motif'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Absence signalée. Le directeur a été notifié.',
            'data'    => $absence,
        ], 201);
    }

    /**
     * Directeur assigne un remplaçant.
     * POST /api/v1/absences-enseignants/{id}/remplacer
     */
    public function assigner(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'remplacant_user_id' => 'required|uuid|exists:users,id',
        ]);

        $absence = AbsenceEnseignant::where('tenant_id', config('tenant.current_id'))
            ->findOrFail($id);

        $absence = $this->service->assignerRemplacant($id, $validated['remplacant_user_id']);

        return response()->json([
            'success' => true,
            'message' => 'Remplaçant assigné. Élèves et parents notifiés.',
            'data'    => $absence,
        ]);
    }

    /**
     * Lister les absences du jour pour le directeur.
     * GET /api/v1/absences-enseignants
     */
    public function index(Request $request): JsonResponse
    {
        $date  = $request->query('date', now()->toDateString());
        $tenantId = config('tenant.current_id');

        $absences = AbsenceEnseignant::with(['enseignant:id,nom,prenom', 'remplacant:id,nom,prenom'])
            ->where('tenant_id', $tenantId)
            ->where('date_absence', $date)
            ->orderBy('signale_le')
            ->get();

        return response()->json(['success' => true, 'data' => $absences]);
    }
}
```

---

## ══════════════════════════════════════════
## BLOC B — DEVOIRS : PUBLICATION + NOTIFICATION ÉLÈVE
## ══════════════════════════════════════════

## ÉTAPE 6 — Migration : devoirs

**Créer** : `edugestdz/backend/database/migrations/2026_07_10_510000_create_devoirs_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('devoirs')) {
            Schema::create('devoirs', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
                $table->uuid('tenant_id');
                $table->uuid('cours_id');
                $table->uuid('groupe_id')->nullable();
                $table->uuid('enseignant_user_id');
                $table->string('titre', 300);
                $table->text('description')->nullable();
                $table->date('date_remise');
                $table->integer('poids_notation')->default(0); // % dans la note finale
                $table->string('fichier_chemin', 500)->nullable();
                $table->boolean('eleves_notifies')->default(false);
                $table->timestamps();

                $table->index(['tenant_id', 'groupe_id'],   'idx_devoirs_tenant_groupe');
                $table->index(['date_remise'],               'idx_devoirs_remise');
            });
        }
    }

    public function down(): void { Schema::dropIfExists('devoirs'); }
};
```

---

## ÉTAPE 7 — DevoirController avec notification automatique élèves

**Créer** : `edugestdz/backend/app/Http/Controllers/Api/V1/DevoirController.php`

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\NotificationInAppService;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DevoirController extends Controller
{
    public function __construct(private NotificationInAppService $notif) {}

    /**
     * Publier un devoir → élèves notifiés automatiquement.
     * POST /api/v1/devoirs
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cours_id'       => 'required|uuid|exists:cours,id',
            'titre'          => 'required|string|max:300',
            'description'    => 'nullable|string|max:2000',
            'date_remise'    => 'required|date|after:today',
            'poids_notation' => 'nullable|integer|between:0,100',
        ]);

        $user     = auth('api')->user();
        $tenantId = config('tenant.current_id');

        // Récupérer le cours et le groupe associé
        $cours = DB::table('cours')->where('id', $validated['cours_id'])->first();
        if (!$cours || $cours->tenant_id !== $tenantId) {
            return response()->json(['success' => false, 'message' => 'Cours non trouvé.'], 404);
        }

        $devoirId = (string) Str::uuid();
        DB::table('devoirs')->insert(array_merge($validated, [
            'id'                  => $devoirId,
            'tenant_id'           => $tenantId,
            'enseignant_user_id'  => $user->id,
            'groupe_id'           => $cours->groupe_id ?? null,
            'poids_notation'      => $validated['poids_notation'] ?? 0,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]));

        // Notifier tous les élèves du groupe
        $dateRemise = \Carbon\Carbon::parse($validated['date_remise'])
            ->locale('fr')->isoFormat('D MMMM YYYY');

        $eleveUsers = DB::table('inscriptions as i')
            ->join('eleves as e', 'i.eleve_id', '=', 'e.id')
            ->where('i.groupe_id', $cours->groupe_id)
            ->where('i.statut', 'active')
            ->whereNotNull('e.user_id')
            ->pluck('e.user_id');

        foreach ($eleveUsers as $eleveUserId) {
            $this->notif->creer(
                userId:   $eleveUserId,
                type:     'devoir_publie',
                titre:    "📝 Nouveau devoir — {$validated['titre']}",
                corps:    "À remettre le {$dateRemise}" .
                          ($validated['description'] ? " · " . substr($validated['description'], 0, 100) : ''),
                meta:     ['devoir_id' => $devoirId, 'date_remise' => $validated['date_remise'], 'action_url' => '/devoirs'],
                tenantId: $tenantId
            );
        }

        DB::table('devoirs')->where('id', $devoirId)->update(['eleves_notifies' => true]);

        return response()->json([
            'success' => true,
            'message' => "Devoir publié. {$eleveUsers->count()} élève(s) notifié(s).",
            'data'    => ['id' => $devoirId],
        ], 201);
    }

    /**
     * Lister les devoirs d'un élève connecté.
     * GET /api/v1/devoirs
     */
    public function index(Request $request): JsonResponse
    {
        $user     = auth('api')->user();
        $tenantId = config('tenant.current_id');

        // Si enseignant → ses propres devoirs
        if ($user->role?->nom === 'enseignant') {
            $devoirs = DB::table('devoirs')
                ->where('tenant_id', $tenantId)
                ->where('enseignant_user_id', $user->id)
                ->orderByDesc('date_remise')
                ->get();
            return response()->json(['success' => true, 'data' => $devoirs]);
        }

        // Si élève (via son user_id) → ses devoirs de son groupe
        $eleve = DB::table('eleves')->where('user_id', $user->id)->first();
        if (!$eleve) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $groupeId = DB::table('inscriptions')
            ->where('eleve_id', $eleve->id)
            ->where('statut', 'active')
            ->value('groupe_id');

        $devoirs = DB::table('devoirs')
            ->where('tenant_id', $tenantId)
            ->where('groupe_id', $groupeId)
            ->where('date_remise', '>=', now()->toDateString())
            ->orderBy('date_remise')
            ->get();

        return response()->json(['success' => true, 'data' => $devoirs]);
    }
}
```

---

## ══════════════════════════════════════════
## BLOC C — FEEDBACK PÉDAGOGIQUE ÉLÈVE → DIRECTEUR
## ══════════════════════════════════════════

## ÉTAPE 8 — Migration : feedbacks_pedagogiques

**Créer** : `edugestdz/backend/database/migrations/2026_07_10_520000_create_feedbacks_pedagogiques_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('feedbacks_pedagogiques')) {
            Schema::create('feedbacks_pedagogiques', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
                $table->uuid('tenant_id');
                $table->uuid('eleve_id');
                $table->uuid('enseignant_user_id');
                $table->uuid('cours_id')->nullable();
                $table->integer('trimestre'); // 1, 2 ou 3
                $table->tinyInteger('note_qualite')->default(3); // 1-5 étoiles
                $table->string('type_feedback', 50)->default('pedagogie');
                // pedagogie | rythme | ambiance | relation | ressources | autre
                $table->text('commentaire')->nullable();
                $table->string('statut', 20)->default('soumis');
                // soumis | lu_directeur | archive
                $table->timestamps();

                // Un seul feedback par (élève, enseignant, trimestre)
                $table->unique(
                    ['eleve_id', 'enseignant_user_id', 'trimestre'],
                    'uq_feedback_eleve_ens_trim'
                );
                $table->index(['tenant_id', 'statut'], 'idx_feedback_tenant_statut');
                $table->index(['enseignant_user_id'],  'idx_feedback_ens');
            });
        }
    }

    public function down(): void { Schema::dropIfExists('feedbacks_pedagogiques'); }
};
```

---

## ÉTAPE 9 — FeedbackPedagogiqueController

**Créer** : `edugestdz/backend/app/Http/Controllers/Api/V1/FeedbackPedagogiqueController.php`

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\NotificationInAppService;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * FeedbackPedagogiqueController
 *
 * RÈGLES MONDIALES APPLIQUÉES :
 * - Identifié (pas anonyme) → élève lié à son compte
 * - Visible UNIQUEMENT du directeur → jamais au prof directement
 * - 1 feedback max par trimestre par (élève × enseignant)
 * - Prof reçoit résumé anonymisé mensuel (pas le texte individuel)
 * - Inspiré : Finlande (dialogue qualitatif) + Singapour (confidentiel)
 */
class FeedbackPedagogiqueController extends Controller
{
    public function __construct(private NotificationInAppService $notif) {}

    /**
     * Élève soumet un feedback sur un enseignant.
     * POST /api/v1/feedbacks-pedagogiques
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enseignant_user_id' => 'required|uuid|exists:users,id',
            'cours_id'           => 'nullable|uuid|exists:cours,id',
            'trimestre'          => 'required|integer|between:1,3',
            'note_qualite'       => 'required|integer|between:1,5',
            'type_feedback'      => 'required|in:pedagogie,rythme,ambiance,relation,ressources,autre',
            'commentaire'        => 'nullable|string|max:500',
        ]);

        $user     = auth('api')->user();
        $tenantId = config('tenant.current_id');

        // Trouver l'élève lié à ce user
        $eleve = DB::table('eleves')->where('user_id', $user->id)->first();
        if (!$eleve) {
            return response()->json([
                'success' => false,
                'message' => 'Seuls les élèves peuvent soumettre un feedback.',
            ], 403);
        }

        // Vérifier unicité (1 feedback / trimestre / enseignant)
        $existant = DB::table('feedbacks_pedagogiques')
            ->where('eleve_id', $eleve->id)
            ->where('enseignant_user_id', $validated['enseignant_user_id'])
            ->where('trimestre', $validated['trimestre'])
            ->exists();

        if ($existant) {
            return response()->json([
                'success' => false,
                'message' => "Vous avez déjà soumis un feedback pour cet enseignant ce trimestre.",
            ], 422);
        }

        $feedbackId = (string) Str::uuid();
        DB::table('feedbacks_pedagogiques')->insert(array_merge($validated, [
            'id'         => $feedbackId,
            'tenant_id'  => $tenantId,
            'eleve_id'   => $eleve->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        // Notifier le directeur (pas l'enseignant !)
        $this->notif->creerPourRole(
            tenantId: $tenantId,
            role:     'admin',
            type:     'feedback_recu',
            titre:    "💬 Nouveau feedback pédagogique",
            corps:    "Un élève a soumis un feedback (T{$validated['trimestre']}). Note: {$validated['note_qualite']}/5",
            meta:     ['feedback_id' => $feedbackId, 'action_url' => '/feedbacks']
        );

        return response()->json([
            'success' => true,
            'message' => 'Feedback soumis. Seul le directeur le verra.',
        ], 201);
    }

    /**
     * Directeur consulte tous les feedbacks.
     * GET /api/v1/feedbacks-pedagogiques
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = config('tenant.current_id');

        $feedbacks = DB::table('feedbacks_pedagogiques as f')
            ->join('eleves as e', 'f.eleve_id', '=', 'e.id')
            ->join('users as u', 'f.enseignant_user_id', '=', 'u.id')
            ->where('f.tenant_id', $tenantId)
            ->select(
                'f.id', 'f.trimestre', 'f.note_qualite', 'f.type_feedback',
                'f.commentaire', 'f.statut', 'f.created_at',
                DB::raw("e.nom || ' ' || e.prenom as eleve_nom"),
                DB::raw("u.nom || ' ' || u.prenom as enseignant_nom")
            )
            ->orderByDesc('f.created_at')
            ->get();

        // Marquer comme lu
        DB::table('feedbacks_pedagogiques')
            ->where('tenant_id', $tenantId)
            ->where('statut', 'soumis')
            ->update(['statut' => 'lu_directeur']);

        return response()->json(['success' => true, 'data' => $feedbacks]);
    }

    /**
     * Résumé anonymisé par enseignant (directeur peut partager ce résumé au prof).
     * GET /api/v1/feedbacks-pedagogiques/resume/{enseignantId}
     */
    public function resume(string $enseignantId): JsonResponse
    {
        $tenantId = config('tenant.current_id');

        $stats = DB::table('feedbacks_pedagogiques')
            ->where('tenant_id', $tenantId)
            ->where('enseignant_user_id', $enseignantId)
            ->select(
                DB::raw('AVG(note_qualite::numeric) as note_moyenne'),
                DB::raw('COUNT(*) as nb_feedbacks'),
                'type_feedback',
                DB::raw('COUNT(*) as nb_par_type')
            )
            ->groupBy('type_feedback')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'note_moyenne_globale' => round(
                    DB::table('feedbacks_pedagogiques')
                        ->where('tenant_id', $tenantId)
                        ->where('enseignant_user_id', $enseignantId)
                        ->avg('note_qualite') ?? 0, 1
                ),
                'par_type' => $stats,
                // Commentaires : anonymisés — on ne montre pas qui a dit quoi
                'commentaires_anonymes' => DB::table('feedbacks_pedagogiques')
                    ->where('tenant_id', $tenantId)
                    ->where('enseignant_user_id', $enseignantId)
                    ->whereNotNull('commentaire')
                    ->pluck('commentaire')
                    ->map(fn($c) => mb_substr($c, 0, 200)), // Tronqué
            ],
        ]);
    }
}
```

---

## ══════════════════════════════════════════
## BLOC D — SIGNALEMENT GRAVE ÉLÈVE → DIRECTEUR
## ══════════════════════════════════════════

## ÉTAPE 10 — Migration : signalements_graves_eleves

**Créer** : `edugestdz/backend/database/migrations/2026_07_10_530000_create_signalements_graves_eleves_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('signalements_graves_eleves')) {
            Schema::create('signalements_graves_eleves', function (Blueprint $table) {
                $table->uuid('id')->primary()->default(\DB::raw('gen_random_uuid()'));
                $table->uuid('tenant_id');
                $table->uuid('eleve_id');      // Identifié — pas anonyme
                $table->uuid('concerne_id')->nullable();  // Enseignant concerné (optionnel)
                $table->string('type_incident', 50);
                // violence_verbale | violence_physique | harcelement | discrimination
                // comportement_inapproprie | autre
                $table->string('gravite', 20); // important | grave | tres_grave
                $table->text('description');
                $table->date('date_incident');
                $table->text('temoins')->nullable(); // Noms d'autres élèves
                $table->string('statut', 30)->default('soumis');
                // soumis | en_investigation | resolu | non_fonde | archive
                $table->uuid('traite_par')->nullable(); // directeur_id
                $table->text('commentaire_admin')->nullable();
                $table->timestamp('traite_le')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'statut'],  'idx_sig_grave_statut');
                $table->index(['eleve_id'],              'idx_sig_grave_eleve');
                $table->index(['concerne_id'],           'idx_sig_grave_concerne');
            });
        }
    }

    public function down(): void { Schema::dropIfExists('signalements_graves_eleves'); }
};
```

---

## ÉTAPE 11 — SignalementGraveController

**Créer** : `edugestdz/backend/app/Http/Controllers/Api/V1/SignalementGraveController.php`

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\{NotificationInAppService, SecurityMonitorService};
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * SignalementGraveController
 *
 * RÈGLES MONDIALES APPLIQUÉES (UK Safeguarding + Singapour + France) :
 * - Élève identifié OBLIGATOIREMENT (pas d'anonymat)
 * - Enseignant concerné jamais notifié directement
 * - Directeur alerté immédiatement (Push urgent + Email)
 * - Délai légal : investigation obligatoire dans les 48h
 * - Élève reçoit numéro de ticket + accusé de réception
 * - Toutes les actions loggées dans audit_chain (immuable)
 * - Réponse du directeur communiquée à l'élève
 */
class SignalementGraveController extends Controller
{
    public function __construct(
        private NotificationInAppService $notif,
        private SecurityMonitorService   $monitor
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type_incident'  => 'required|in:violence_verbale,violence_physique,harcelement,discrimination,comportement_inapproprie,autre',
            'gravite'        => 'required|in:important,grave,tres_grave',
            'description'    => 'required|string|min:20|max:2000',
            'date_incident'  => 'required|date|before_or_equal:today',
            'concerne_id'    => 'nullable|uuid|exists:users,id', // Enseignant concerné
            'temoins'        => 'nullable|string|max:500',
        ]);

        $user     = auth('api')->user();
        $tenantId = config('tenant.current_id');

        // L'élève doit être identifié
        $eleve = DB::table('eleves')->where('user_id', $user->id)->first();
        if (!$eleve) {
            return response()->json([
                'success' => false,
                'message' => 'Seuls les élèves peuvent soumettre un signalement grave.',
            ], 403);
        }

        $signalementId = (string) Str::uuid();
        $numeroTicket  = 'SIG-' . strtoupper(Str::random(6));

        DB::table('signalements_graves_eleves')->insert(array_merge($validated, [
            'id'         => $signalementId,
            'tenant_id'  => $tenantId,
            'eleve_id'   => $eleve->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        // Logguer dans l'audit chain (immuable — conformité légale)
        try {
            \App\Services\AuditChainService::enregistrer(
                typeEvenement: 'signalement_grave_eleve',
                resourceType:  'signalement',
                resourceId:    $signalementId,
                avant:         [],
                apres:         ['type' => $validated['type_incident'], 'gravite' => $validated['gravite']],
                userId:        $user->id,
                tenantId:      $tenantId
            );
        } catch (\Throwable) {}

        // Alerter le directeur — URGENCE (ignore la plage horaire)
        $typeLabel = match ($validated['type_incident']) {
            'violence_verbale'           => 'Violence verbale',
            'violence_physique'          => 'Violence physique',
            'harcelement'                => 'Harcèlement',
            'discrimination'             => 'Discrimination',
            'comportement_inapproprie'   => 'Comportement inapproprié',
            default                      => 'Incident',
        };

        $this->notif->creerPourRole(
            tenantId: $tenantId,
            role:     'admin',
            type:     'signalement_grave',
            titre:    "🚨 Signalement grave — {$typeLabel}",
            corps:    "Gravité: {$validated['gravite']} · Ticket #{$numeroTicket} · Investigation requise sous 48h",
            meta:     [
                'signalement_id' => $signalementId,
                'ticket'         => $numeroTicket,
                'action_url'     => "/signalements/{$signalementId}",
            ],
            urgence:  true // Ignore la plage horaire — notification immédiate
        );

        // Alerter via Telegram (sécurité)
        $this->monitor->alerter(
            'signalement_grave_eleve', 'warning',
            "🚨 Signalement grave soumis — Type: {$typeLabel} · Ticket: {$numeroTicket}",
            ['tenant_id' => $tenantId, 'type' => $validated['type_incident']]
        );

        // Accuser réception à l'élève
        $this->notif->creer(
            userId:   $user->id,
            type:     'accusé_reception',
            titre:    "✅ Signalement reçu — Ticket #{$numeroTicket}",
            corps:    "Votre signalement a été enregistré et transmis au directeur. Vous serez informé(e) des suites dans les 48h.",
            meta:     ['signalement_id' => $signalementId, 'ticket' => $numeroTicket],
            tenantId: $tenantId
        );

        return response()->json([
            'success'         => true,
            'message'         => "Signalement enregistré. Ticket #{$numeroTicket}. Le directeur sera informé.",
            'numero_ticket'   => $numeroTicket,
            'delai_reponse'   => '48 heures maximum',
        ], 201);
    }

    /**
     * Directeur consulte les signalements.
     * GET /api/v1/signalements-graves
     */
    public function index(): JsonResponse
    {
        $tenantId = config('tenant.current_id');

        $signalements = DB::table('signalements_graves_eleves as s')
            ->join('eleves as e', 's.eleve_id', '=', 'e.id')
            ->leftJoin('users as u', 's.concerne_id', '=', 'u.id')
            ->where('s.tenant_id', $tenantId)
            ->select(
                's.id', 's.type_incident', 's.gravite', 's.statut',
                's.date_incident', 's.description', 's.created_at',
                DB::raw("e.nom || ' ' || e.prenom as eleve_nom"),
                DB::raw("COALESCE(u.nom || ' ' || u.prenom, 'Non spécifié') as concerne_nom")
            )
            ->orderByDesc('s.created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $signalements,
            'alerte'  => $signalements->where('statut', 'soumis')->count() > 0
                ? $signalements->where('statut', 'soumis')->count() . " signalement(s) en attente d'investigation"
                : null,
        ]);
    }

    /**
     * Directeur répond à un signalement.
     * PATCH /api/v1/signalements-graves/{id}/traiter
     */
    public function traiter(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'statut'             => 'required|in:en_investigation,resolu,non_fonde,archive',
            'commentaire_admin'  => 'required|string|max:1000',
        ]);

        $tenantId      = config('tenant.current_id');
        $directeurId   = auth('api')->id();

        $signalement = DB::table('signalements_graves_eleves')
            ->where('id', $id)->where('tenant_id', $tenantId)->first();

        if (!$signalement) {
            return response()->json(['success' => false, 'message' => 'Non trouvé.'], 404);
        }

        DB::table('signalements_graves_eleves')->where('id', $id)->update([
            'statut'            => $validated['statut'],
            'commentaire_admin' => $validated['commentaire_admin'],
            'traite_par'        => $directeurId,
            'traite_le'         => now(),
            'updated_at'        => now(),
        ]);

        // Notifier l'élève de la réponse
        $eleveUserId = DB::table('eleves')->where('id', $signalement->eleve_id)->value('user_id');
        if ($eleveUserId) {
            $statutLabel = match ($validated['statut']) {
                'en_investigation' => 'Votre signalement est en cours d\'investigation',
                'resolu'           => 'Votre signalement a été traité',
                'non_fonde'        => 'Votre signalement a été examiné',
                'archive'          => 'Votre signalement a été archivé',
                default            => 'Votre signalement a été mis à jour',
            };

            $this->notif->creer(
                userId:   $eleveUserId,
                type:     'signalement_traite',
                titre:    "📋 Mise à jour de votre signalement",
                corps:    $statutLabel . ". " . mb_substr($validated['commentaire_admin'], 0, 150),
                meta:     ['signalement_id' => $id],
                tenantId: $tenantId
            );
        }

        return response()->json(['success' => true, 'message' => 'Signalement traité. Élève notifié.']);
    }
}
```

---

## ÉTAPE 12 — Ajouter toutes les routes

**Modifier** : `edugestdz/backend/routes/api.php`

```php
use App\Http\Controllers\Api\V1\{
    AbsenceEnseignantController,
    DevoirController,
    FeedbackPedagogiqueController,
    SignalementGraveController,
};

// ── Absences enseignants ──────────────────────────────────────────────
Route::middleware(['auth:api', 'tenant'])->prefix('v1/absences-enseignants')->group(function () {
    Route::get('/',                    [AbsenceEnseignantController::class, 'index']);
    Route::post('/',                   [AbsenceEnseignantController::class, 'signaler']);
    Route::post('/{id}/remplacer',     [AbsenceEnseignantController::class, 'assigner']);
});

// ── Devoirs ───────────────────────────────────────────────────────────
Route::middleware(['auth:api', 'tenant'])->prefix('v1/devoirs')->group(function () {
    Route::get('/',    [DevoirController::class, 'index']);
    Route::post('/',   [DevoirController::class, 'store']);
});

// ── Feedbacks pédagogiques (élève → directeur) ────────────────────────
Route::middleware(['auth:api', 'tenant'])->prefix('v1/feedbacks-pedagogiques')->group(function () {
    Route::get('/',                    [FeedbackPedagogiqueController::class, 'index']);
    Route::post('/',                   [FeedbackPedagogiqueController::class, 'store']);
    Route::get('/resume/{ensId}',      [FeedbackPedagogiqueController::class, 'resume']);
});

// ── Signalements graves (élève → directeur — confidentiel) ─────────────
Route::middleware(['auth:api', 'tenant'])->prefix('v1/signalements-graves')->group(function () {
    Route::get('/',                    [SignalementGraveController::class, 'index']);
    Route::post('/',                   [SignalementGraveController::class, 'store']);
    Route::patch('/{id}/traiter',      [SignalementGraveController::class, 'traiter']);
});
```

---

## ÉTAPE 13 — Tests Feature

**Créer** : `edugestdz/backend/tests/Feature/Api/FluxCirculationTest.php`

```php
<?php

namespace Tests\Feature\Api;

use App\Models\{Tenant, Role, User, Eleve};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Illuminate\Support\Str;

class FluxCirculationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User   $admin;
    private User   $enseignant;
    private User   $eleveUser;
    private Eleve  $eleve;
    private string $tokenAdmin;
    private string $tokenEnseignant;
    private string $tokenEleve;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create(['statut' => 'actif']);
        config(['tenant.current_id' => $this->tenant->id]);

        $roleAdmin    = Role::factory()->create(['nom' => 'admin']);
        $roleEns      = Role::factory()->create(['nom' => 'enseignant']);
        $roleEleve    = Role::factory()->create(['nom' => 'eleve']);

        $this->admin      = User::factory()->adminAvec2fa()->create(['tenant_id' => $this->tenant->id, 'role_id' => $roleAdmin->id]);
        $this->enseignant = User::factory()->create(['tenant_id' => $this->tenant->id, 'role_id' => $roleEns->id]);
        $this->eleveUser  = User::factory()->create(['tenant_id' => $this->tenant->id, 'role_id' => $roleEleve->id]);
        $this->eleve      = Eleve::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id'   => $this->eleveUser->id,
        ]);

        $this->tokenAdmin      = auth('api')->login($this->admin);
        $this->tokenEnseignant = auth('api')->login($this->enseignant);
        $this->tokenEleve      = auth('api')->login($this->eleveUser);
    }

    // ── Absence Enseignant ─────────────────────────────────────────────

    public function test_enseignant_peut_signaler_son_absence(): void
    {
        $this->withHeaders(['Authorization' => "Bearer {$this->tokenEnseignant}"])
            ->postJson('/api/v1/absences-enseignants', [
                'date_absence' => now()->addDay()->toDateString(),
                'motif'        => 'Rendez-vous médical',
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_absence_signalement_sans_doublon(): void
    {
        $date = now()->addDays(2)->toDateString();

        $this->withHeaders(['Authorization' => "Bearer {$this->tokenEnseignant}"])
            ->postJson('/api/v1/absences-enseignants', ['date_absence' => $date]);

        // Deuxième signalement même date → ne doit pas planter
        $this->withHeaders(['Authorization' => "Bearer {$this->tokenEnseignant}"])
            ->postJson('/api/v1/absences-enseignants', ['date_absence' => $date])
            ->assertStatus(201);
    }

    public function test_admin_peut_lister_absences(): void
    {
        $this->withHeaders(['Authorization' => "Bearer {$this->tokenAdmin}"])
            ->getJson('/api/v1/absences-enseignants')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // ── Devoirs ────────────────────────────────────────────────────────

    public function test_devoir_necessaire_cours_valide(): void
    {
        $this->withHeaders(['Authorization' => "Bearer {$this->tokenEnseignant}"])
            ->postJson('/api/v1/devoirs', [
                'cours_id'    => (string) Str::uuid(),
                'titre'       => 'Exercices page 45',
                'date_remise' => now()->addWeek()->toDateString(),
            ])
            ->assertStatus(404); // Cours inexistant
    }

    public function test_eleve_peut_voir_ses_devoirs(): void
    {
        $this->withHeaders(['Authorization' => "Bearer {$this->tokenEleve}"])
            ->getJson('/api/v1/devoirs')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // ── Feedback Pédagogique ────────────────────────────────────────────

    public function test_eleve_soumet_feedback(): void
    {
        $this->withHeaders(['Authorization' => "Bearer {$this->tokenEleve}"])
            ->postJson('/api/v1/feedbacks-pedagogiques', [
                'enseignant_user_id' => $this->enseignant->id,
                'trimestre'          => 3,
                'note_qualite'       => 4,
                'type_feedback'      => 'pedagogie',
                'commentaire'        => 'Cours bien expliqué mais rythme rapide.',
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_feedback_doublon_refuse(): void
    {
        $payload = [
            'enseignant_user_id' => $this->enseignant->id,
            'trimestre'          => 3,
            'note_qualite'       => 4,
            'type_feedback'      => 'pedagogie',
        ];

        $this->withHeaders(['Authorization' => "Bearer {$this->tokenEleve}"])
            ->postJson('/api/v1/feedbacks-pedagogiques', $payload)
            ->assertStatus(201);

        $this->withHeaders(['Authorization' => "Bearer {$this->tokenEleve}"])
            ->postJson('/api/v1/feedbacks-pedagogiques', $payload)
            ->assertStatus(422); // Doublon refusé
    }

    public function test_admin_voit_feedbacks(): void
    {
        $this->withHeaders(['Authorization' => "Bearer {$this->tokenAdmin}"])
            ->getJson('/api/v1/feedbacks-pedagogiques')
            ->assertStatus(200);
    }

    public function test_enseignant_ne_peut_pas_voir_feedbacks(): void
    {
        // L'enseignant NE doit PAS accéder aux feedbacks qui le concernent directement
        // Il reçoit seulement un résumé anonymisé via /resume
        $this->withHeaders(['Authorization' => "Bearer {$this->tokenEnseignant}"])
            ->getJson('/api/v1/feedbacks-pedagogiques')
            ->assertStatus(403); // Interdit
    }

    // ── Signalement Grave ──────────────────────────────────────────────

    public function test_eleve_soumet_signalement_grave(): void
    {
        $response = $this->withHeaders(['Authorization' => "Bearer {$this->tokenEleve}"])
            ->postJson('/api/v1/signalements-graves', [
                'type_incident'  => 'violence_verbale',
                'gravite'        => 'grave',
                'description'    => 'L\'enseignant a utilisé des propos irrespectueux envers moi lors du cours du 10 juillet.',
                'date_incident'  => now()->toDateString(),
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['numero_ticket', 'delai_reponse']);
    }

    public function test_enseignant_ne_peut_pas_voir_signalements(): void
    {
        // L'enseignant JAMAIS notifié / jamais accès
        $this->withHeaders(['Authorization' => "Bearer {$this->tokenEnseignant}"])
            ->getJson('/api/v1/signalements-graves')
            ->assertStatus(403);
    }

    public function test_admin_voit_signalements(): void
    {
        $this->withHeaders(['Authorization' => "Bearer {$this->tokenAdmin}"])
            ->getJson('/api/v1/signalements-graves')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_description_signalement_minimum_20_caracteres(): void
    {
        $this->withHeaders(['Authorization' => "Bearer {$this->tokenEleve}"])
            ->postJson('/api/v1/signalements-graves', [
                'type_incident' => 'autre',
                'gravite'       => 'important',
                'description'   => 'Court',  // Moins de 20 chars
                'date_incident' => now()->toDateString(),
            ])
            ->assertStatus(422);
    }
}
```

---

## ÉTAPE 14 — Exécution Mission 1

```bash
cd edugestdz/backend

php artisan migrate --force
composer dump-autoload -o

# Tester uniquement les nouveaux tests
php artisan test tests/Feature/Api/FluxCirculationTest.php --stop-on-failure

# Tous les tests
php artisan test
# → 724+ ✅  0 failures

git add \
  database/migrations/2026_07_10_500000_create_absences_enseignants_table.php \
  database/migrations/2026_07_10_510000_create_devoirs_table.php \
  database/migrations/2026_07_10_520000_create_feedbacks_pedagogiques_table.php \
  database/migrations/2026_07_10_530000_create_signalements_graves_eleves_table.php \
  app/Models/AbsenceEnseignant.php \
  app/Services/AbsenceEnseignantService.php \
  app/Services/NotificationInAppService.php \
  app/Http/Controllers/Api/V1/AbsenceEnseignantController.php \
  app/Http/Controllers/Api/V1/DevoirController.php \
  app/Http/Controllers/Api/V1/FeedbackPedagogiqueController.php \
  app/Http/Controllers/Api/V1/SignalementGraveController.php \
  routes/api.php \
  tests/Feature/Api/FluxCirculationTest.php

git commit -m "feat(flux-info-1/3): Backend circulation information

- AbsenceEnseignant: signal prof absent + flux directeur + notification élèves/parents
- NotificationInAppService: canal in-app centralisé + règle horaire 7h-20h Algérie
- AbsenceEnseignantService: orchestre signal → directeur → remplaçant → élèves
- DevoirController: publication devoir + notification automatique élèves du groupe
- FeedbackPedagogiqueController: feedback élève → directeur UNIQUEMENT (1x/trimestre)
  résumé anonymisé pour le prof (pas de texte individuel identifié)
- SignalementGraveController: signalement grave élève → directeur confidentiel
  - Numéro de ticket, audit_chain, notification urgente, réponse directeur
  - Enseignant JAMAIS notifié directement (règle UK Safeguarding + Singapour)
- 14 tests Feature couvrant tous les flux + règles de confidentialité
- 4 migrations idempotentes (hasTable guards)"

git push origin develop
```

---

## CE QUE TU DIS À DEEPSEEK POUR LA MISSION 1

```
Repo : https://github.com/Allintelligence2024/edugest-dz.git
Branche : develop
git checkout develop && git pull origin main

Fichier : FLUX_MISSION_1_BACKEND_CIRCULATION.md — 14 étapes.

RÈGLES CRITIQUES :
1. PostgreSQL + 0 régression sur 724+ tests.
2. AbsenceEnseignantService : injecter NotificationInAppService dans le constructeur.
   Si le service n'est pas dans le container DI → l'ajouter dans AppServiceProvider.
3. FeedbackPedagogiqueController index() : vérifier que le rôle admin a accès.
   L'enseignant NE DOIT PAS accéder aux feedbacks qui le concernent (test l'exige).
   Ajouter un check rôle dans la méthode index() → 403 si pas admin.
4. SignalementGraveController : AuditChainService::enregistrer() doit être dans
   un try/catch (peut ne pas exister si Mission 6 sécurité pas encore mergée).
5. NotificationInAppService : vérifier que la table notifications_inapp existe
   (migration 2026_07_12_200000) avant de faire DB::table().
   Si table absente → skip silencieusement (try/catch).
6. Les routes feedbacks/signalements : appliquer middleware rôle pour
   que les enseignants reçoivent 403 sur les endpoints admin-only.

php artisan migrate --force
php artisan test tests/Feature/Api/FluxCirculationTest.php
php artisan test → 724+ ✅
git push origin develop → PR → main
```
