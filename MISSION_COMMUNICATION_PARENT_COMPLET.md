# 🤖 MISSION DEEPSEEK — Communication Parent Complète
## Signalements comportement + Notifications toutes actions + Écran parent unifié
## EduGest DZ · Branche : develop · 5 Juillet 2026
## Tests actuels : 460+ ✅ · Objectif : ≥ 475 ✅ · 0 régression

---

## CONTEXTE — Ce qui existe déjà (NE PAS recréer)

| Existant | Fichier |
|---|---|
| Saisie notes enseignant | EvaluationController::saisirNotes() ✅ |
| Bulletins PDF | BulletinController ✅ |
| SMS absent auto 8h30 | SmsAbsentsCommand ✅ |
| Push note publiée | NoteObserver ✅ |
| Écrans parent mobile (8) | NotesScreen, BulletinsScreen, PresencesScreen... ✅ |

## Ce qu'on ajoute dans cette mission

```
1. Table signalements_comportement
   → Prof / Proviseur / Surveillant signale un élève
   → Types : perturbation, retard_répété, violence, tricherie, félicitation, autre

2. Backend API signalements (CRUD + workflow)
   → POST /api/v1/signalements (créer)
   → GET  /api/v1/signalements/eleve/{id} (voir par élève)
   → GET  /api/v1/signalements/mes-signalements (prof voit les siens)

3. Notifications push automatiques TOUTES actions enfant
   → Note publiée → push parent (déjà partiellement fait → enrichir)
   → Bulletin généré → push parent + SMS
   → Signalement comportement → push parent IMMÉDIAT
   → Absence → push parent (déjà fait → vérifier)
   → Convocation → push parent
   → Diagnostic niveau critique → push parent (déjà fait → enrichir)

4. NotesScreen enseignant mobile — SAISIE (pas lecture seulement)
   → Sélectionner groupe → saisir note par élève → envoyer

5. Écran parent mobile "Mon Enfant" — vue unifiée
   → Tout ce qui concerne l'enfant en un seul écran
   → Notes récentes + Absences + Signalements + Bulletins + Niveau

6. Écran parent mobile "Notifications" — historique complet
   → Toutes les notifications reçues avec timestamp
   → Marquer comme lu
```

### RÈGLES ABSOLUES
1. PostgreSQL uniquement — jamais SQLite
2. 0 régression — les tests existants restent verts
3. Ne pas modifier EvaluationController ni BulletinController existants
4. Ne pas supprimer les écrans parent existants — ajouter seulement

---

## ÉTAPE 0 — Synchroniser

```bash
git checkout develop && git pull origin main
```

---

## PARTIE A — BACKEND
## ════════════════════════════════════════

## ÉTAPE 1 — Migration : signalements_comportement

**Créer :**
`edugestdz/backend/database/migrations/2026_07_05_100000_create_signalements_comportement_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signalements_comportement', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->uuid('eleve_id');
            $table->uuid('signale_par');    // user_id de l'auteur (prof, proviseur, surveillant)
            $table->string('role_auteur');  // enseignant | admin | surveillant
            $table->string('type');
            // Types : perturbation | retard_répété | violence | tricherie |
            //         insolence | absentéisme | félicitation | encouragement | autre
            $table->string('gravite')->default('normale');
            // Valeurs : info | normale | grave | très_grave
            $table->text('description');    // Détail du signalement
            $table->string('lieu')->nullable(); // Salle X, Couloir, Cour...
            $table->date('date_incident');
            $table->time('heure_incident')->nullable();
            $table->boolean('notifie_parent')->default(false);
            $table->boolean('vu_par_parent')->default(false);
            $table->timestamp('vu_le')->nullable();
            $table->boolean('traite')->default(false);
            $table->text('suite_donnee')->nullable(); // Action prise par le proviseur
            $table->uuid('traite_par')->nullable();
            $table->timestamps();

            $table->index(['eleve_id', 'date_incident'],  'idx_signal_eleve_date');
            $table->index(['tenant_id', 'traite'],        'idx_signal_tenant_traite');
            $table->index(['tenant_id', 'gravite'],       'idx_signal_tenant_gravite');
            $table->index(['signale_par', 'date_incident'],'idx_signal_auteur_date');
        });

        // Table centre de notifications parent (historique push)
        Schema::create('notifications_parent', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->uuid('parent_id');
            $table->uuid('eleve_id')->nullable();
            $table->string('type');
            // Types : note | bulletin | absence | signalement | convocation |
            //         paiement | diagnostic | message | autre
            $table->string('titre');
            $table->text('corps');
            $table->jsonb('meta')->default('{}');  // données supplémentaires
            $table->boolean('lu')->default(false);
            $table->timestamp('lu_le')->nullable();
            $table->boolean('push_envoye')->default(false);
            $table->boolean('sms_envoye')->default(false);
            $table->timestamps();

            $table->index(['parent_id', 'lu'],         'idx_notif_parent_lu');
            $table->index(['parent_id', 'created_at'], 'idx_notif_parent_date');
            $table->index(['eleve_id', 'type'],        'idx_notif_eleve_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications_parent');
        Schema::dropIfExists('signalements_comportement');
    }
};
```

---

## ÉTAPE 2 — Models

**Créer :** `edugestdz/backend/app/Models/SignalementComportement.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SignalementComportement extends Model
{
    use HasUuids;

    protected $table = 'signalements_comportement';

    protected $fillable = [
        'tenant_id', 'eleve_id', 'signale_par', 'role_auteur',
        'type', 'gravite', 'description', 'lieu',
        'date_incident', 'heure_incident',
        'notifie_parent', 'vu_par_parent', 'vu_le',
        'traite', 'suite_donnee', 'traite_par',
    ];

    protected $casts = [
        'date_incident'  => 'date',
        'notifie_parent' => 'boolean',
        'vu_par_parent'  => 'boolean',
        'traite'         => 'boolean',
        'vu_le'          => 'datetime',
    ];

    // Types et leurs emojis
    public const TYPES = [
        'perturbation'   => ['label' => 'Perturbation en classe',    'emoji' => '😤', 'positif' => false],
        'retard_répété'  => ['label' => 'Retards répétés',           'emoji' => '⏰', 'positif' => false],
        'violence'       => ['label' => 'Violence / Bagarre',        'emoji' => '⚠️', 'positif' => false],
        'tricherie'      => ['label' => 'Tricherie / Fraude',        'emoji' => '📋', 'positif' => false],
        'insolence'      => ['label' => 'Insolence / Irrespect',     'emoji' => '🗣️', 'positif' => false],
        'absentéisme'    => ['label' => 'Absentéisme répété',        'emoji' => '📵', 'positif' => false],
        'félicitation'   => ['label' => 'Félicitation / Bon travail','emoji' => '⭐', 'positif' => true],
        'encouragement'  => ['label' => 'Encouragement / Progrès',   'emoji' => '📈', 'positif' => true],
        'autre'          => ['label' => 'Autre',                     'emoji' => '📝', 'positif' => false],
    ];

    public const GRAVITES = [
        'info'        => ['label' => 'Information',  'color' => '#60a5fa'],
        'normale'     => ['label' => 'Normale',      'color' => '#fb923c'],
        'grave'       => ['label' => 'Grave',        'color' => '#f87171'],
        'très_grave'  => ['label' => 'Très grave',   'color' => '#ef4444'],
    ];

    public function eleve()
    {
        return $this->belongsTo(Eleve::class);
    }

    public function auteur()
    {
        return $this->belongsTo(User::class, 'signale_par');
    }

    public function getTypeInfoAttribute(): array
    {
        return self::TYPES[$this->type] ?? ['label' => $this->type, 'emoji' => '📝', 'positif' => false];
    }

    public function estPositif(): bool
    {
        return self::TYPES[$this->type]['positif'] ?? false;
    }

    public function scopeNonTraites($query)
    {
        return $query->where('traite', false);
    }

    public function scopeGraves($query)
    {
        return $query->whereIn('gravite', ['grave', 'très_grave']);
    }
}
```

**Créer :** `edugestdz/backend/app/Models/NotificationParent.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class NotificationParent extends Model
{
    use HasUuids;

    protected $table = 'notifications_parent';

    protected $fillable = [
        'tenant_id', 'parent_id', 'eleve_id', 'type',
        'titre', 'corps', 'meta', 'lu', 'lu_le',
        'push_envoye', 'sms_envoye',
    ];

    protected $casts = [
        'meta'        => 'array',
        'lu'          => 'boolean',
        'push_envoye' => 'boolean',
        'sms_envoye'  => 'boolean',
        'lu_le'       => 'datetime',
    ];

    public function parent()
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    public function eleve()
    {
        return $this->belongsTo(Eleve::class);
    }

    public function scopeNonLues($query)
    {
        return $query->where('lu', false);
    }
}
```

---

## ÉTAPE 3 — ParentNotificationService (service central)

**Créer :** `edugestdz/backend/app/Services/ParentNotificationService.php`

```php
<?php

namespace App\Services;

use App\Models\Eleve;
use App\Models\NotificationParent;
use Illuminate\Support\Facades\Log;

/**
 * Service central pour TOUTES les notifications aux parents.
 * Tout passe par ici — SMS + Push Firebase + historique BDD.
 */
class ParentNotificationService
{
    public function __construct(
        private FirebaseService $firebase,
        private SmsService      $sms,
    ) {}

    /**
     * Notifier les parents d'un élève pour UNE raison.
     * Sauvegarde dans notifications_parent + push + SMS si critique.
     */
    public function notifier(
        string $eleveId,
        string $type,
        string $titre,
        string $corps,
        array  $meta        = [],
        bool   $avecSMS     = false,
        bool   $forcerSMS   = false
    ): void {
        $eleve = Eleve::with('parents:id,nom,prenom,telephone_1,telephone')->find($eleveId);
        if (!$eleve) return;

        foreach ($eleve->parents as $parent) {
            // 1. Sauvegarder dans l'historique
            $notif = NotificationParent::create([
                'tenant_id' => $eleve->tenant_id,
                'parent_id' => $parent->id,
                'eleve_id'  => $eleveId,
                'type'      => $type,
                'titre'     => $titre,
                'corps'     => $corps,
                'meta'      => $meta,
            ]);

            // 2. Push Firebase
            $pushed = $this->firebase->notifyUser(
                $parent->id,
                $titre,
                $corps,
                array_merge($meta, [
                    'type'     => $type,
                    'eleve_id' => $eleveId,
                    'notif_id' => $notif->id,
                ])
            );
            if ($pushed) $notif->update(['push_envoye' => true]);

            // 3. SMS si demandé ou forcé
            if ($avecSMS || $forcerSMS) {
                $tel = $parent->telephone_1 ?? $parent->telephone ?? null;
                if ($tel) {
                    try {
                        $this->sms->send($tel, "EduGest: {$titre}\n{$corps}");
                        $notif->update(['sms_envoye' => true]);
                    } catch (\Throwable $e) {
                        Log::warning("SMS parent échoué: " . $e->getMessage());
                    }
                }
            }
        }
    }

    // ── Méthodes spécialisées par type d'événement ───────────────────────────

    /** Appelé par NoteObserver quand une note est saisie */
    public function notePubliee(string $eleveId, string $matiere, float $note, float $noteMax, ?string $appreciation): void
    {
        $noteSur20 = $noteMax > 0 ? round(($note / $noteMax) * 20, 2) : $note;
        $emoji     = $note >= ($noteMax * 0.75) ? '✅' : ($note < ($noteMax * 0.25) ? '⚠️' : '📝');

        $this->notifier(
            $eleveId,
            'note',
            "{$emoji} Nouvelle note — {$matiere}",
            "Note obtenue : {$note}/{$noteMax} ({$noteSur20}/20)" .
                ($appreciation ? " — {$appreciation}" : ''),
            ['matiere' => $matiere, 'note' => $note, 'note_sur' => $noteMax],
            $noteSur20 < 5 // SMS si note < 5/20
        );
    }

    /** Appelé par BulletinObserver quand un bulletin est généré */
    public function bulletinGenere(string $eleveId, string $trimestre, float $moyenne, int $rang, int $effectif): void
    {
        $mention = match(true) {
            $moyenne >= 16 => 'Très Bien',
            $moyenne >= 14 => 'Bien',
            $moyenne >= 12 => 'Assez Bien',
            $moyenne >= 10 => 'Passable',
            default        => 'Insuffisant',
        };

        $this->notifier(
            $eleveId,
            'bulletin',
            "📄 Bulletin {$trimestre} disponible",
            "Moyenne générale : {$moyenne}/20 · Mention : {$mention} · Rang : {$rang}/{$effectif}",
            ['trimestre' => $trimestre, 'moyenne' => $moyenne, 'rang' => $rang],
            true // SMS systématique pour les bulletins
        );
    }

    /** Appelé par AbsenceJournaliereObserver quand une absence est enregistrée */
    public function absenceSignalee(string $eleveId, string $date, ?string $motif): void
    {
        $this->notifier(
            $eleveId,
            'absence',
            "⚠️ Absence signalée",
            "Votre enfant est absent le {$date}." .
                ($motif ? " Motif : {$motif}" : ' Aucun motif renseigné.'),
            ['date' => $date],
            true // SMS systématique
        );
    }

    /** Appelé par SignalementController quand un comportement est signalé */
    public function comportementSignale(
        string $eleveId,
        string $typeSignalement,
        string $gravite,
        string $description,
        string $auteurNom
    ): void {
        $typeInfo = \App\Models\SignalementComportement::TYPES[$typeSignalement]
            ?? ['label' => $typeSignalement, 'emoji' => '📝', 'positif' => false];

        $estPositif = $typeInfo['positif'];
        $emoji      = $estPositif ? '⭐' : ($gravite === 'très_grave' ? '🚨' : '⚠️');

        $titre = $estPositif
            ? "{$emoji} Félicitation — {$typeInfo['label']}"
            : "{$emoji} Signalement — {$typeInfo['label']}";

        $corps = $estPositif
            ? "Votre enfant a été félicité par {$auteurNom}. {$description}"
            : "Incident signalé par {$auteurNom} : {$description}";

        $avecSMS = $gravite === 'grave' || $gravite === 'très_grave';

        $this->notifier(
            $eleveId,
            'signalement',
            $titre,
            $corps,
            ['type' => $typeSignalement, 'gravite' => $gravite],
            $avecSMS
        );
    }

    /** Appelé par DiagnosticService quand le niveau change */
    public function niveauChange(string $eleveId, string $niveauActuel, float $moyenne): void
    {
        if ($niveauActuel === 'normal' || $niveauActuel === 'excellent') return;

        $messages = [
            'vigilance' => ['⚠️ Niveau en vigilance', "La moyenne de votre enfant ({$moyenne}/20) nécessite un suivi renforcé."],
            'danger'    => ['🔴 Niveau en danger',    "La moyenne de votre enfant ({$moyenne}/20) est préoccupante. Contactez l'établissement."],
            'critique'  => ['🚨 Niveau critique',     "La moyenne de votre enfant ({$moyenne}/20) est très insuffisante. Une convocation sera envoyée."],
        ];

        [$titre, $corps] = $messages[$niveauActuel] ?? ['📊 Niveau académique', "Moyenne : {$moyenne}/20"];

        $this->notifier(
            $eleveId,
            'diagnostic',
            $titre,
            $corps,
            ['niveau' => $niveauActuel, 'moyenne' => $moyenne],
            $niveauActuel === 'critique' // SMS si critique
        );
    }

    /** Appelé quand une convocation est émise */
    public function convocationEmise(string $eleveId, string $motif, ?string $rdvDate): void
    {
        $this->notifier(
            $eleveId,
            'convocation',
            '📅 Convocation de vos parents',
            "Vous êtes convoqué(e) à l'établissement. Motif : {$motif}" .
                ($rdvDate ? ". Date : {$rdvDate}" : ''),
            ['motif' => $motif],
            true, // SMS
            true  // Forcer SMS même si pas de push
        );
    }
}
```

---

## ÉTAPE 4 — SignalementController

**Créer :** `edugestdz/backend/app/Http/Controllers/Api/V1/SignalementController.php`

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SignalementComportement;
use App\Models\Eleve;
use App\Services\ParentNotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SignalementController extends Controller
{
    public function __construct(
        private ParentNotificationService $notificationService
    ) {}

    /**
     * @OA\Post(
     *   path="/api/v1/signalements",
     *   summary="Signaler un comportement élève (prof / proviseur / surveillant)",
     *   tags={"Signalements"},
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(ref="#/components/parameters/TenantId"),
     *   @OA\RequestBody(required=true, @OA\JsonContent(
     *     required={"eleve_id","type","gravite","description","date_incident"},
     *     @OA\Property(property="eleve_id",      type="string", format="uuid"),
     *     @OA\Property(property="type",          type="string",
     *       enum={"perturbation","retard_répété","violence","tricherie","insolence","absentéisme","félicitation","encouragement","autre"}),
     *     @OA\Property(property="gravite",       type="string",
     *       enum={"info","normale","grave","très_grave"}, default="normale"),
     *     @OA\Property(property="description",   type="string"),
     *     @OA\Property(property="lieu",          type="string", nullable=true, example="Salle 12"),
     *     @OA\Property(property="date_incident", type="string", format="date"),
     *     @OA\Property(property="heure_incident",type="string", nullable=true, example="10:30")
     *   )),
     *   @OA\Response(response=201, description="Signalement créé + parent notifié"),
     *   @OA\Response(response=422, description="Données invalides")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'eleve_id'       => 'required|uuid|exists:eleves,id',
            'type'           => 'required|in:perturbation,retard_répété,violence,tricherie,insolence,absentéisme,félicitation,encouragement,autre',
            'gravite'        => 'required|in:info,normale,grave,très_grave',
            'description'    => 'required|string|max:1000',
            'lieu'           => 'nullable|string|max:100',
            'date_incident'  => 'required|date|before_or_equal:today',
            'heure_incident' => 'nullable|date_format:H:i',
        ]);

        $auteur = auth('api')->user();

        $signalement = SignalementComportement::create([
            ...$validated,
            'tenant_id'      => config('tenant.current_id'),
            'signale_par'    => $auteur->id,
            'role_auteur'    => $auteur->role,
            'notifie_parent' => false,
        ]);

        // Notifier le parent immédiatement
        $this->notificationService->comportementSignale(
            $validated['eleve_id'],
            $validated['type'],
            $validated['gravite'],
            $validated['description'],
            "{$auteur->prenom} {$auteur->nom}"
        );

        $signalement->update(['notifie_parent' => true]);

        return response()->json([
            'success' => true,
            'data'    => $signalement->load('eleve:id,nom,prenom', 'auteur:id,nom,prenom,role'),
            'message' => 'Signalement enregistré et parent notifié',
        ], 201);
    }

    /**
     * @OA\Get(
     *   path="/api/v1/signalements/eleve/{eleveId}",
     *   summary="Voir tous les signalements d'un élève",
     *   tags={"Signalements"},
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(ref="#/components/parameters/TenantId"),
     *   @OA\Parameter(name="eleveId", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\Response(response=200, description="Signalements de l'élève")
     * )
     */
    public function byEleve(string $eleveId): JsonResponse
    {
        $signalements = SignalementComportement::where('eleve_id', $eleveId)
            ->with('auteur:id,nom,prenom,role')
            ->orderByDesc('date_incident')
            ->get()
            ->map(fn($s) => [
                ...$s->toArray(),
                'type_info'    => $s->type_info,
                'gravite_info' => SignalementComportement::GRAVITES[$s->gravite] ?? [],
            ]);

        return response()->json(['success' => true, 'data' => $signalements]);
    }

    /**
     * @OA\Get(
     *   path="/api/v1/signalements/mes-signalements",
     *   summary="Signalements créés par l'utilisateur connecté (prof / surveillant)",
     *   tags={"Signalements"},
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(response=200, description="Mes signalements")
     * )
     */
    public function mesSIgnalements(Request $request): JsonResponse
    {
        $signalements = SignalementComportement::where('signale_par', auth('api')->id())
            ->with('eleve:id,nom,prenom,niveau_scolaire')
            ->orderByDesc('date_incident')
            ->paginate($request->per_page ?? 20);

        return response()->json(['success' => true, 'data' => $signalements]);
    }

    /**
     * @OA\Get(
     *   path="/api/v1/signalements",
     *   summary="Tous les signalements du tenant (admin / proviseur)",
     *   tags={"Signalements"},
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(ref="#/components/parameters/TenantId"),
     *   @OA\Parameter(name="gravite",  in="query", @OA\Schema(type="string")),
     *   @OA\Parameter(name="traite",   in="query", @OA\Schema(type="boolean")),
     *   @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=20)),
     *   @OA\Response(response=200, description="Signalements paginés")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = SignalementComportement::with([
            'eleve:id,nom,prenom,niveau_scolaire',
            'auteur:id,nom,prenom,role',
        ]);

        if ($request->filled('gravite')) $query->where('gravite', $request->gravite);
        if ($request->filled('traite'))  $query->where('traite', (bool) $request->traite);
        if ($request->filled('type'))    $query->where('type', $request->type);

        $signalements = $query->orderByDesc('date_incident')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'data'    => $signalements,
            'stats'   => [
                'non_traites'   => SignalementComportement::nonTraites()->count(),
                'graves'        => SignalementComportement::graves()->count(),
                'ce_mois'       => SignalementComportement::whereMonth('date_incident', now()->month)->count(),
            ],
        ]);
    }

    /**
     * @OA\Post(
     *   path="/api/v1/signalements/{id}/traiter",
     *   summary="Traiter un signalement (admin / proviseur uniquement)",
     *   tags={"Signalements"},
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *   @OA\RequestBody(@OA\JsonContent(
     *     required={"suite_donnee"},
     *     @OA\Property(property="suite_donnee", type="string",
     *       example="Élève convoqué, lettre d'avertissement remise aux parents")
     *   )),
     *   @OA\Response(response=200, description="Signalement traité")
     * )
     */
    public function traiter(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'suite_donnee' => 'required|string|max:500',
        ]);

        $signalement = SignalementComportement::findOrFail($id);
        $signalement->update([
            'traite'       => true,
            'suite_donnee' => $validated['suite_donnee'],
            'traite_par'   => auth('api')->id(),
        ]);

        return response()->json([
            'success' => true,
            'data'    => $signalement->fresh(),
            'message' => 'Signalement traité',
        ]);
    }

    /**
     * @OA\Get(
     *   path="/api/v1/signalements/parent/mon-enfant",
     *   summary="Signalements de mon enfant (vue parent)",
     *   tags={"Signalements"},
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(response=200, description="Signalements côté parent")
     * )
     */
    public function monEnfantSignalements(): JsonResponse
    {
        // Trouver l'élève du parent connecté
        $parent = auth('api')->user();
        $eleves = Eleve::whereHas('parents', fn($q) => $q->where('users.id', $parent->id))
            ->pluck('id');

        $signalements = SignalementComportement::whereIn('eleve_id', $eleves)
            ->with([
                'eleve:id,nom,prenom',
                'auteur:id,nom,prenom,role',
            ])
            ->orderByDesc('date_incident')
            ->get()
            ->map(fn($s) => [
                ...$s->toArray(),
                'type_info'    => $s->type_info,
                'gravite_info' => SignalementComportement::GRAVITES[$s->gravite] ?? [],
            ]);

        // Marquer comme vu
        SignalementComportement::whereIn('eleve_id', $eleves)
            ->where('vu_par_parent', false)
            ->update(['vu_par_parent' => true, 'vu_le' => now()]);

        return response()->json(['success' => true, 'data' => $signalements]);
    }

    /**
     * @OA\Get(
     *   path="/api/v1/notifications/parent",
     *   summary="Centre de notifications parent — historique complet",
     *   tags={"Signalements"},
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="lu", in="query", @OA\Schema(type="boolean")),
     *   @OA\Response(response=200, description="Toutes les notifications parent")
     * )
     */
    public function notificationsParent(Request $request): JsonResponse
    {
        $parentId = auth('api')->id();

        $notifs = \App\Models\NotificationParent::where('parent_id', $parentId)
            ->with('eleve:id,nom,prenom')
            ->when($request->filled('lu'), fn($q) => $q->where('lu', (bool) $request->lu))
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 30);

        $nonLues = \App\Models\NotificationParent::where('parent_id', $parentId)
            ->nonLues()->count();

        return response()->json([
            'success'  => true,
            'data'     => $notifs,
            'non_lues' => $nonLues,
        ]);
    }

    /**
     * @OA\Post(
     *   path="/api/v1/notifications/parent/{id}/lire",
     *   summary="Marquer une notification comme lue",
     *   tags={"Signalements"},
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *   @OA\Response(response=200, description="Notification marquée lue")
     * )
     */
    public function marquerLue(string $id): JsonResponse
    {
        \App\Models\NotificationParent::where('id', $id)
            ->where('parent_id', auth('api')->id())
            ->update(['lu' => true, 'lu_le' => now()]);

        return response()->json(['success' => true, 'message' => 'Notification lue']);
    }

    /**
     * @OA\Post(
     *   path="/api/v1/notifications/parent/tout-lire",
     *   summary="Marquer toutes les notifications comme lues",
     *   tags={"Signalements"},
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(response=200, description="Toutes les notifications lues")
     * )
     */
    public function toutMarquerLu(): JsonResponse
    {
        \App\Models\NotificationParent::where('parent_id', auth('api')->id())
            ->nonLues()
            ->update(['lu' => true, 'lu_le' => now()]);

        return response()->json(['success' => true, 'message' => 'Toutes les notifications marquées comme lues']);
    }
}
```

---

## ÉTAPE 5 — Observer Bulletin : push automatique quand bulletin généré

**Créer :** `edugestdz/backend/app/Observers/BulletinObserver.php`

```php
<?php

namespace App\Observers;

use App\Models\Bulletin;
use App\Services\ParentNotificationService;

class BulletinObserver
{
    public function __construct(
        private ParentNotificationService $notificationService
    ) {}

    public function created(Bulletin $bulletin): void
    {
        if (!$bulletin->eleve_id) return;

        $this->notificationService->bulletinGenere(
            $bulletin->eleve_id,
            $bulletin->trimestre,
            (float) ($bulletin->moyenne_generale ?? 0),
            (int) ($bulletin->rang ?? 1),
            (int) ($bulletin->effectif_classe ?? 1)
        );
    }
}
```

---

## ÉTAPE 6 — Modifier NoteObserver pour utiliser ParentNotificationService

**Modifier :** `edugestdz/backend/app/Observers/NoteObserver.php`

Remplacer le contenu de `created()` pour utiliser le service centralisé :

```php
public function created(Note $note): void
{
    if (!$note->note || !$note->eleve_id) return;

    // 1. Diagnostic (existant)
    try {
        $diag = $this->diagnostic->analyserEleve($note->eleve_id);
        if ($diag->niveau_global !== 'normal' && $diag->niveau_global !== 'excellent') {
            $this->notificationService->niveauChange(
                $note->eleve_id,
                $diag->niveau_global,
                (float) ($diag->moyenne_generale ?? 0)
            );
        }
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::warning("Diagnostic Note: " . $e->getMessage());
    }

    // 2. Notification note publiée (centralisée)
    $matiere  = $note->evaluation?->matiere?->nom_fr
        ?? $note->evaluation?->groupe?->matiere?->nom_fr
        ?? 'Matière';
    $noteSur  = $note->evaluation?->note_sur ?? 20;
    $appreciation = $note->appreciation;

    $this->notificationService->notePubliee(
        $note->eleve_id,
        $matiere,
        (float) $note->note,
        (float) $noteSur,
        $appreciation
    );
}
```

**Modifier le constructeur de NoteObserver :**

```php
public function __construct(
    private DiagnosticService         $diagnostic,
    private ParentNotificationService  $notificationService,
) {}
```

---

## ÉTAPE 7 — Modifier AbsenceJournaliereObserver pour utiliser ParentNotificationService

**Modifier :** `edugestdz/backend/app/Observers/AbsenceJournaliereObserver.php`

```php
<?php

namespace App\Observers;

use App\Models\AbsenceJournaliere;
use App\Services\ParentNotificationService;

class AbsenceJournaliereObserver
{
    public function __construct(
        private ParentNotificationService $notificationService
    ) {}

    public function created(AbsenceJournaliere $absence): void
    {
        $eleve = $absence->eleve;
        if (!$eleve) return;

        $this->notificationService->absenceSignalee(
            $eleve->id,
            $absence->date_absence instanceof \Carbon\Carbon
                ? $absence->date_absence->format('d/m/Y')
                : $absence->date_absence,
            $absence->motif
        );

        $absence->update(['sms_envoye' => true]);
    }
}
```

---

## ÉTAPE 8 — Enregistrer tout dans AppServiceProvider

**Modifier :** `edugestdz/backend/app/Providers/AppServiceProvider.php`

Dans `boot()`, ajouter / compléter :

```php
// Observers — système de notifications parent
\App\Models\Note::observe(\App\Observers\NoteObserver::class);
\App\Models\Bulletin::observe(\App\Observers\BulletinObserver::class);
\App\Models\AbsenceJournaliere::observe(\App\Observers\AbsenceJournaliereObserver::class);
\App\Models\Eleve::observe(\App\Observers\EleveObserver::class); // déjà là
```

---

## ÉTAPE 9 — Routes

**Modifier :** `edugestdz/backend/routes/api.php`

```php
use App\Http\Controllers\Api\V1\SignalementController;

Route::middleware(['auth:api', 'tenant'])->group(function () {

    // ── Signalements comportement ─────────────────────────────────────
    // Prof / Proviseur / Surveillant → signaler
    Route::post('/v1/signalements',                         [SignalementController::class, 'store']);
    Route::get('/v1/signalements',                          [SignalementController::class, 'index']);
    Route::get('/v1/signalements/mes-signalements',         [SignalementController::class, 'mesSIgnalements']);
    Route::get('/v1/signalements/eleve/{eleveId}',          [SignalementController::class, 'byEleve']);
    Route::post('/v1/signalements/{id}/traiter',            [SignalementController::class, 'traiter']);

    // Parent → voir les signalements de son enfant
    Route::get('/v1/signalements/parent/mon-enfant',        [SignalementController::class, 'monEnfantSignalements']);

    // ── Centre de notifications parent ────────────────────────────────
    Route::get('/v1/notifications/parent',                  [SignalementController::class, 'notificationsParent']);
    Route::post('/v1/notifications/parent/{id}/lire',       [SignalementController::class, 'marquerLue']);
    Route::post('/v1/notifications/parent/tout-lire',       [SignalementController::class, 'toutMarquerLu']);
});
```

---

## PARTIE B — MOBILE (écrans manquants)
## ════════════════════════════════════════

## ÉTAPE 10 — NotesScreen enseignant : SAISIE de notes (pas lecture seulement)

**Modifier :** `edugestdz/mobile/src/screens/enseignant/NotesScreen.js`

```javascript
import React, { useState, useEffect } from 'react';
import {
  View, Text, FlatList, TouchableOpacity, TextInput,
  StyleSheet, ActivityIndicator, Alert, ScrollView,
} from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';

const BASE = 'https://app.edugest.dz/api/v1';

const apiHeaders = async () => {
  const token    = await AsyncStorage.getItem('token');
  const tenantId = await AsyncStorage.getItem('tenantId');
  return {
    'Content-Type': 'application/json',
    'Authorization': `Bearer ${token}`,
    'X-Tenant-ID': tenantId ?? '',
  };
};

export default function NotesScreen() {
  const [groupes, setGroupes]         = useState([]);
  const [selectedGroupe, setSelectedGroupe] = useState(null);
  const [evaluations, setEvaluations] = useState([]);
  const [selectedEval, setSelectedEval]     = useState(null);
  const [eleves, setEleves]           = useState([]);
  const [notes, setNotes]             = useState({});  // { eleve_id: { note, absent, commentaire } }
  const [loading, setLoading]         = useState(false);
  const [saving, setSaving]           = useState(false);
  const [mode, setMode]               = useState('liste'); // liste | saisie

  useEffect(() => { loadGroupes(); }, []);

  const loadGroupes = async () => {
    setLoading(true);
    const h = await apiHeaders();
    const r = await fetch(`${BASE}/groupes?per_page=100`, { headers: h }).then(r => r.json());
    setGroupes(r?.data?.data ?? r?.data ?? []);
    setLoading(false);
  };

  const loadEvaluations = async (groupeId) => {
    const h = await apiHeaders();
    const r = await fetch(`${BASE}/evaluations?groupe_id=${groupeId}&per_page=50`, { headers: h }).then(r => r.json());
    setEvaluations(r?.data ?? []);
  };

  const loadElevesPourSaisie = async (evalId) => {
    setLoading(true);
    const h = await apiHeaders();
    const r = await fetch(`${BASE}/evaluations/${evalId}/notes`, { headers: h }).then(r => r.json());
    const data = r?.data ?? [];
    setEleves(data);
    // Initialiser les notes avec les valeurs existantes
    const notesInit = {};
    data.forEach(e => {
      notesInit[e.eleve_id] = {
        note:        e.note?.toString() ?? '',
        absent:      e.absent ?? false,
        commentaire: e.commentaire ?? '',
      };
    });
    setNotes(notesInit);
    setLoading(false);
    setMode('saisie');
  };

  const sauvegarderNotes = async () => {
    if (!selectedEval) return;
    setSaving(true);

    const payload = eleves.map(e => ({
      eleve_id:    e.eleve_id,
      note:        parseFloat(notes[e.eleve_id]?.note) || null,
      absent:      notes[e.eleve_id]?.absent ?? false,
      commentaire: notes[e.eleve_id]?.commentaire ?? '',
    }));

    const h = await apiHeaders();
    const r = await fetch(`${BASE}/evaluations/${selectedEval.id}/notes`, {
      method:  'POST',
      headers: h,
      body:    JSON.stringify({ notes: payload }),
    }).then(r => r.json());

    setSaving(false);
    if (r?.success) {
      Alert.alert('✅ Enregistré', `${r?.stats?.nb_notes ?? payload.length} note(s) sauvegardée(s).`);
      setMode('liste');
    } else {
      Alert.alert('Erreur', r?.message ?? 'Échec de la sauvegarde');
    }
  };

  const updateNote = (eleveId, field, value) => {
    setNotes(n => ({ ...n, [eleveId]: { ...n[eleveId], [field]: value } }));
  };

  // ── Vue liste groupes ──────────────────────────────────────────────

  if (mode === 'liste') {
    if (!selectedGroupe) {
      return (
        <View style={s.container}>
          <Text style={s.title}>📝 Saisie de notes</Text>
          <Text style={s.sub}>Sélectionnez un groupe :</Text>
          {loading ? <ActivityIndicator color="#3b82f6" style={{ marginTop: 30 }} /> : (
            <FlatList
              data={groupes}
              keyExtractor={g => g.id}
              renderItem={({ item }) => (
                <TouchableOpacity style={s.groupeCard} onPress={() => {
                  setSelectedGroupe(item);
                  loadEvaluations(item.id);
                }}>
                  <Text style={s.groupeName}>{item.nom}</Text>
                  <Text style={s.groupeSub}>{item.matiere?.nom_fr} · {item.niveau ?? ''}</Text>
                </TouchableOpacity>
              )}
              ListEmptyComponent={<Text style={s.empty}>Aucun groupe trouvé</Text>}
            />
          )}
        </View>
      );
    }

    return (
      <View style={s.container}>
        <TouchableOpacity onPress={() => setSelectedGroupe(null)}>
          <Text style={s.back}>← Retour aux groupes</Text>
        </TouchableOpacity>
        <Text style={s.title}>{selectedGroupe.nom}</Text>
        <Text style={s.sub}>Sélectionnez une évaluation :</Text>
        <FlatList
          data={evaluations}
          keyExtractor={e => e.id}
          renderItem={({ item }) => (
            <TouchableOpacity style={s.evalCard} onPress={() => {
              setSelectedEval(item);
              loadElevesPourSaisie(item.id);
            }}>
              <Text style={s.evalTitre}>{item.titre}</Text>
              <Text style={s.evalSub}>{item.type_eval} · {item.date_evaluation} · /{item.note_sur}</Text>
              <Text style={s.evalCoeff}>Coeff. {item.coefficient}</Text>
            </TouchableOpacity>
          )}
          ListEmptyComponent={<Text style={s.empty}>Aucune évaluation pour ce groupe</Text>}
        />
        <TouchableOpacity style={s.addBtn} onPress={() => Alert.alert('Info', 'Créer une évaluation depuis le web pour l\'instant.')}>
          <Text style={s.addBtnText}>+ Créer une évaluation</Text>
        </TouchableOpacity>
      </View>
    );
  }

  // ── Vue saisie notes ──────────────────────────────────────────────

  return (
    <View style={s.container}>
      <View style={s.header}>
        <TouchableOpacity onPress={() => setMode('liste')}>
          <Text style={s.back}>← Retour</Text>
        </TouchableOpacity>
        <Text style={s.evalTitre}>{selectedEval?.titre}</Text>
        <Text style={s.sub}>Note sur {selectedEval?.note_sur} · Coeff. {selectedEval?.coefficient}</Text>
      </View>

      {loading ? <ActivityIndicator color="#3b82f6" style={{ marginTop: 30 }} /> : (
        <>
          <FlatList
            data={eleves}
            keyExtractor={e => e.eleve_id}
            contentContainerStyle={{ paddingBottom: 100 }}
            renderItem={({ item }) => {
              const note = notes[item.eleve_id] ?? {};
              return (
                <View style={s.eleveRow}>
                  <View style={{ flex: 1 }}>
                    <Text style={s.eleveName}>{item.nom_complet}</Text>
                    {note.absent && <Text style={{ fontSize: 10, color: '#f87171' }}>Absent</Text>}
                  </View>

                  <TouchableOpacity
                    style={[s.absentBtn, note.absent && s.absentBtnActive]}
                    onPress={() => updateNote(item.eleve_id, 'absent', !note.absent)}
                  >
                    <Text style={{ fontSize: 10, fontWeight: '700',
                      color: note.absent ? '#fff' : '#64748b' }}>ABS</Text>
                  </TouchableOpacity>

                  {!note.absent && (
                    <TextInput
                      value={note.note?.toString() ?? ''}
                      onChangeText={v => updateNote(item.eleve_id, 'note', v)}
                      placeholder={`0-${selectedEval?.note_sur}`}
                      keyboardType="decimal-pad"
                      style={s.noteInput}
                      placeholderTextColor="#475569"
                    />
                  )}
                </View>
              );
            }}
          />

          <TouchableOpacity style={s.saveBtn} onPress={sauvegarderNotes} disabled={saving}>
            {saving
              ? <ActivityIndicator color="#fff" />
              : <Text style={s.saveBtnText}>💾 Enregistrer toutes les notes</Text>
            }
          </TouchableOpacity>
        </>
      )}
    </View>
  );
}

const s = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#08090f', padding: 16 },
  title:     { fontSize: 20, fontWeight: '900', color: '#fff', marginBottom: 4 },
  sub:       { fontSize: 11, color: '#64748b', marginBottom: 16 },
  back:      { fontSize: 12, color: '#60a5fa', marginBottom: 12, fontWeight: '700' },
  header:    { marginBottom: 16 },
  empty:     { color: '#475569', textAlign: 'center', marginTop: 40 },
  groupeCard:{ background: undefined, backgroundColor: '#111318', borderRadius: 10,
               padding: 14, marginBottom: 8, borderWidth: 1, borderColor: '#1e293b' },
  groupeName:{ fontSize: 13, fontWeight: '800', color: '#f1f5f9' },
  groupeSub: { fontSize: 10, color: '#64748b', marginTop: 2 },
  evalCard:  { backgroundColor: '#111318', borderRadius: 10, padding: 14,
               marginBottom: 8, borderWidth: 1, borderColor: '#1e293b' },
  evalTitre: { fontSize: 13, fontWeight: '800', color: '#f1f5f9' },
  evalSub:   { fontSize: 10, color: '#64748b', marginTop: 2 },
  evalCoeff: { fontSize: 10, color: '#60a5fa', marginTop: 2, fontWeight: '700' },
  addBtn:    { backgroundColor: '#1e3a5f', borderRadius: 8, padding: 12,
               alignItems: 'center', marginTop: 12 },
  addBtnText:{ color: '#60a5fa', fontWeight: '700', fontSize: 13 },
  eleveRow:  { flexDirection: 'row', alignItems: 'center', gap: 10,
               backgroundColor: '#111318', borderRadius: 8, padding: 12,
               marginBottom: 6, borderWidth: 1, borderColor: '#1e293b' },
  eleveName: { fontSize: 12, fontWeight: '700', color: '#f1f5f9' },
  absentBtn: { backgroundColor: '#1e293b', borderRadius: 6, padding: 6,
               minWidth: 36, alignItems: 'center' },
  absentBtnActive: { backgroundColor: '#b91c1c' },
  noteInput: { backgroundColor: '#1e293b', borderRadius: 6, color: '#e2e8f0',
               padding: 8, width: 70, textAlign: 'center', fontSize: 14, fontWeight: '800' },
  saveBtn:   { position: 'absolute', bottom: 16, left: 16, right: 16,
               backgroundColor: '#1d4ed8', borderRadius: 10, padding: 16,
               alignItems: 'center' },
  saveBtnText: { color: '#fff', fontWeight: '800', fontSize: 14 },
});
```

---

## ÉTAPE 11 — SignalementsScreen enseignant mobile

**Créer :** `edugestdz/mobile/src/screens/enseignant/SignalementsScreen.js`

```javascript
import React, { useState, useEffect } from 'react';
import {
  View, Text, FlatList, TouchableOpacity, TextInput,
  StyleSheet, Alert, Modal, ScrollView,
} from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';

const BASE = 'https://app.edugest.dz/api/v1';

const TYPES = [
  { id: 'perturbation',  label: '😤 Perturbation',    positif: false },
  { id: 'retard_répété', label: '⏰ Retards répétés',  positif: false },
  { id: 'violence',      label: '⚠️ Violence',         positif: false },
  { id: 'tricherie',     label: '📋 Tricherie',        positif: false },
  { id: 'insolence',     label: '🗣️ Insolence',        positif: false },
  { id: 'félicitation',  label: '⭐ Félicitation',     positif: true  },
  { id: 'encouragement', label: '📈 Encouragement',    positif: true  },
  { id: 'autre',         label: '📝 Autre',            positif: false },
];

const GRAVITES = [
  { id: 'info',       label: 'Information',  color: '#60a5fa' },
  { id: 'normale',    label: 'Normale',      color: '#fb923c' },
  { id: 'grave',      label: 'Grave',        color: '#f87171' },
  { id: 'très_grave', label: 'Très grave',   color: '#ef4444' },
];

export default function SignalementsScreen() {
  const [signalements, setSignalements] = useState([]);
  const [showForm, setShowForm]         = useState(false);
  const [eleves, setEleves]             = useState([]);
  const [form, setForm] = useState({
    eleve_id: '', type: 'perturbation', gravite: 'normale',
    description: '', lieu: '', date_incident: new Date().toISOString().split('T')[0],
  });
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    loadMesSignalements();
    loadEleves();
  }, []);

  const headers = async () => {
    const token    = await AsyncStorage.getItem('token');
    const tenantId = await AsyncStorage.getItem('tenantId');
    return { 'Content-Type': 'application/json',
             'Authorization': `Bearer ${token}`, 'X-Tenant-ID': tenantId ?? '' };
  };

  const loadMesSignalements = async () => {
    const h = await headers();
    const r = await fetch(`${BASE}/signalements/mes-signalements`, { headers: h }).then(r => r.json());
    setSignalements(r?.data?.data ?? []);
  };

  const loadEleves = async () => {
    const h = await headers();
    const r = await fetch(`${BASE}/eleves?per_page=200&statut=actif`, { headers: h }).then(r => r.json());
    setEleves(r?.data?.data ?? []);
  };

  const soumettre = async () => {
    if (!form.eleve_id || !form.description) {
      Alert.alert('Manquant', 'Sélectionnez un élève et décrivez le signalement.');
      return;
    }
    setSaving(true);
    const h = await headers();
    const r = await fetch(`${BASE}/signalements`, {
      method: 'POST', headers: h, body: JSON.stringify(form),
    }).then(r => r.json());

    setSaving(false);
    if (r?.success) {
      Alert.alert('✅ Signalement envoyé', 'Le parent a été notifié automatiquement.');
      setShowForm(false);
      setForm({ eleve_id: '', type: 'perturbation', gravite: 'normale',
                description: '', lieu: '', date_incident: new Date().toISOString().split('T')[0] });
      loadMesSignalements();
    } else {
      Alert.alert('Erreur', r?.message ?? 'Échec');
    }
  };

  return (
    <View style={s.container}>
      <View style={s.header}>
        <Text style={s.title}>📋 Signalements</Text>
        <TouchableOpacity style={s.addBtn} onPress={() => setShowForm(true)}>
          <Text style={s.addBtnText}>+ Signaler</Text>
        </TouchableOpacity>
      </View>

      <FlatList
        data={signalements}
        keyExtractor={i => i.id}
        renderItem={({ item }) => (
          <View style={[s.card, { borderColor: GRAVITES.find(g => g.id === item.gravite)?.color ?? '#1e293b' }]}>
            <Text style={s.cardType}>{TYPES.find(t => t.id === item.type)?.label ?? item.type}</Text>
            <Text style={s.cardEleve}>{item.eleve?.prenom} {item.eleve?.nom}</Text>
            <Text style={s.cardDesc} numberOfLines={2}>{item.description}</Text>
            <Text style={s.cardDate}>{item.date_incident} {item.notifie_parent ? '· 📱 Parent notifié' : ''}</Text>
          </View>
        )}
        ListEmptyComponent={
          <Text style={s.empty}>Aucun signalement. Appuyez sur "+ Signaler" pour en créer un.</Text>
        }
      />

      {/* Modal formulaire signalement */}
      <Modal visible={showForm} animationType="slide" presentationStyle="pageSheet">
        <ScrollView style={s.modal}>
          <Text style={s.modalTitle}>📋 Nouveau signalement</Text>
          <Text style={s.label}>Élève *</Text>
          <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{ marginBottom: 12 }}>
            {eleves.slice(0, 20).map(e => (
              <TouchableOpacity key={e.id} onPress={() => setForm(f => ({ ...f, eleve_id: e.id }))}
                style={[s.chip, form.eleve_id === e.id && s.chipActive]}>
                <Text style={[s.chipText, form.eleve_id === e.id && s.chipTextActive]}>
                  {e.prenom} {e.nom}
                </Text>
              </TouchableOpacity>
            ))}
          </ScrollView>

          <Text style={s.label}>Type de signalement *</Text>
          <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{ marginBottom: 12 }}>
            {TYPES.map(t => (
              <TouchableOpacity key={t.id} onPress={() => setForm(f => ({ ...f, type: t.id }))}
                style={[s.chip, form.type === t.id && s.chipActive]}>
                <Text style={[s.chipText, form.type === t.id && s.chipTextActive]}>{t.label}</Text>
              </TouchableOpacity>
            ))}
          </ScrollView>

          <Text style={s.label}>Gravité *</Text>
          <View style={{ flexDirection: 'row', gap: 8, marginBottom: 12 }}>
            {GRAVITES.map(g => (
              <TouchableOpacity key={g.id} onPress={() => setForm(f => ({ ...f, gravite: g.id }))}
                style={[s.graviteBtn, form.gravite === g.id && { backgroundColor: g.color + '33', borderColor: g.color }]}>
                <Text style={[s.graviteText, form.gravite === g.id && { color: g.color }]}>{g.label}</Text>
              </TouchableOpacity>
            ))}
          </View>

          <Text style={s.label}>Description *</Text>
          <TextInput value={form.description}
            onChangeText={v => setForm(f => ({ ...f, description: v }))}
            multiline numberOfLines={4}
            placeholder="Décrivez l'incident ou la félicitation en détail..."
            placeholderTextColor="#475569"
            style={[s.input, { height: 80, textAlignVertical: 'top' }]}
          />

          <Text style={s.label}>Lieu (optionnel)</Text>
          <TextInput value={form.lieu}
            onChangeText={v => setForm(f => ({ ...f, lieu: v }))}
            placeholder="Ex: Salle 12, Couloir bâtiment A..."
            placeholderTextColor="#475569" style={s.input}
          />

          <View style={s.modalActions}>
            <TouchableOpacity style={s.cancelBtn} onPress={() => setShowForm(false)}>
              <Text style={s.cancelBtnText}>Annuler</Text>
            </TouchableOpacity>
            <TouchableOpacity style={s.submitBtn} onPress={soumettre} disabled={saving}>
              <Text style={s.submitBtnText}>
                {saving ? 'Envoi...' : '✅ Envoyer + Notifier parent'}
              </Text>
            </TouchableOpacity>
          </View>
        </ScrollView>
      </Modal>
    </View>
  );
}

const s = StyleSheet.create({
  container:    { flex: 1, backgroundColor: '#08090f', padding: 16 },
  header:       { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 },
  title:        { fontSize: 20, fontWeight: '900', color: '#fff' },
  addBtn:       { backgroundColor: '#1d4ed8', borderRadius: 8, padding: 10 },
  addBtnText:   { color: '#fff', fontWeight: '700', fontSize: 12 },
  card:         { backgroundColor: '#111318', borderRadius: 10, padding: 14,
                  marginBottom: 8, borderWidth: 1 },
  cardType:     { fontSize: 12, fontWeight: '800', color: '#f1f5f9', marginBottom: 2 },
  cardEleve:    { fontSize: 11, color: '#60a5fa', marginBottom: 4 },
  cardDesc:     { fontSize: 11, color: '#94a3b8', marginBottom: 4 },
  cardDate:     { fontSize: 9, color: '#475569' },
  empty:        { color: '#475569', textAlign: 'center', marginTop: 40, fontSize: 12 },
  modal:        { flex: 1, backgroundColor: '#08090f', padding: 20 },
  modalTitle:   { fontSize: 18, fontWeight: '900', color: '#fff', marginBottom: 20 },
  label:        { fontSize: 10, color: '#64748b', marginBottom: 6, fontWeight: '700',
                  textTransform: 'uppercase', letterSpacing: 1 },
  input:        { backgroundColor: '#1e293b', borderRadius: 8, color: '#e2e8f0',
                  padding: 12, fontSize: 12, marginBottom: 12, borderWidth: 1, borderColor: '#334155' },
  chip:         { backgroundColor: '#1e293b', borderRadius: 20, paddingHorizontal: 12,
                  paddingVertical: 6, marginRight: 8, borderWidth: 1, borderColor: '#334155' },
  chipActive:   { backgroundColor: '#1e3a5f', borderColor: '#3b82f6' },
  chipText:     { fontSize: 11, color: '#94a3b8', fontWeight: '600' },
  chipTextActive: { color: '#60a5fa' },
  graviteBtn:   { flex: 1, backgroundColor: '#1e293b', borderRadius: 6, padding: 8,
                  alignItems: 'center', borderWidth: 1, borderColor: '#334155' },
  graviteText:  { fontSize: 9, color: '#64748b', fontWeight: '700' },
  modalActions: { flexDirection: 'row', gap: 10, marginTop: 20, marginBottom: 40 },
  cancelBtn:    { flex: 1, backgroundColor: '#1e293b', borderRadius: 8, padding: 12, alignItems: 'center' },
  cancelBtnText:{ color: '#94a3b8', fontWeight: '700' },
  submitBtn:    { flex: 2, backgroundColor: '#1d4ed8', borderRadius: 8, padding: 12, alignItems: 'center' },
  submitBtnText:{ color: '#fff', fontWeight: '800', fontSize: 12 },
});
```

---

## ÉTAPE 12 — NotificationsScreen parent mobile (centre de notifications)

**Créer :** `edugestdz/mobile/src/screens/parent/NotificationsScreen.js`

```javascript
import React, { useState, useEffect } from 'react';
import {
  View, Text, FlatList, TouchableOpacity,
  StyleSheet, ActivityIndicator, RefreshControl,
} from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';

const BASE = 'https://app.edugest.dz/api/v1';

const TYPE_CONFIG = {
  note:        { emoji: '📝', color: '#60a5fa', label: 'Note' },
  bulletin:    { emoji: '📄', color: '#a78bfa', label: 'Bulletin' },
  absence:     { emoji: '⚠️', color: '#fb923c', label: 'Absence' },
  signalement: { emoji: '📋', color: '#f87171', label: 'Signalement' },
  convocation: { emoji: '📅', color: '#ef4444', label: 'Convocation' },
  paiement:    { emoji: '💳', color: '#4ade80', label: 'Paiement' },
  diagnostic:  { emoji: '🔬', color: '#fb923c', label: 'Niveau' },
  message:     { emoji: '💬', color: '#60a5fa', label: 'Message' },
  autre:       { emoji: '🔔', color: '#94a3b8', label: 'Notification' },
};

export default function NotificationsScreen() {
  const [notifs, setNotifs]         = useState([]);
  const [nonLues, setNonLues]       = useState(0);
  const [loading, setLoading]       = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  useEffect(() => { loadNotifications(); }, []);

  const headers = async () => {
    const token    = await AsyncStorage.getItem('token');
    const tenantId = await AsyncStorage.getItem('tenantId');
    return { 'Authorization': `Bearer ${token}`, 'X-Tenant-ID': tenantId ?? '' };
  };

  const loadNotifications = async () => {
    const h = await headers();
    const r = await fetch(`${BASE}/notifications/parent?per_page=50`, { headers: h }).then(r => r.json());
    setNotifs(r?.data?.data ?? []);
    setNonLues(r?.non_lues ?? 0);
    setLoading(false);
    setRefreshing(false);
  };

  const marquerLue = async (id) => {
    const h = await headers();
    await fetch(`${BASE}/notifications/parent/${id}/lire`, { method: 'POST', headers: h });
    setNotifs(prev => prev.map(n => n.id === id ? { ...n, lu: true } : n));
    setNonLues(prev => Math.max(0, prev - 1));
  };

  const toutLire = async () => {
    const h = await headers();
    await fetch(`${BASE}/notifications/parent/tout-lire`, { method: 'POST', headers: h });
    setNotifs(prev => prev.map(n => ({ ...n, lu: true })));
    setNonLues(0);
  };

  const formatDate = (dateStr) => {
    const d = new Date(dateStr);
    const now = new Date();
    const diffH = Math.floor((now - d) / 3600000);
    if (diffH < 1)  return 'À l\'instant';
    if (diffH < 24) return `Il y a ${diffH}h`;
    return d.toLocaleDateString('fr-DZ', { day: '2-digit', month: 'short' });
  };

  const renderNotif = ({ item }) => {
    const cfg = TYPE_CONFIG[item.type] ?? TYPE_CONFIG.autre;
    return (
      <TouchableOpacity
        style={[s.card, !item.lu && s.cardUnread]}
        onPress={() => !item.lu && marquerLue(item.id)}
      >
        <View style={[s.iconWrap, { backgroundColor: cfg.color + '22' }]}>
          <Text style={{ fontSize: 20 }}>{cfg.emoji}</Text>
        </View>
        <View style={{ flex: 1 }}>
          <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6, marginBottom: 2 }}>
            <Text style={[s.cardTitre, !item.lu && s.cardTitreUnread]}>{item.titre}</Text>
            {!item.lu && <View style={[s.dot, { backgroundColor: cfg.color }]} />}
          </View>
          <Text style={s.cardCorps} numberOfLines={2}>{item.corps}</Text>
          <View style={{ flexDirection: 'row', justifyContent: 'space-between', marginTop: 4 }}>
            <Text style={s.cardDate}>{formatDate(item.created_at)}</Text>
            {item.eleve && (
              <Text style={s.cardEleve}>
                {item.eleve.prenom} {item.eleve.nom}
              </Text>
            )}
          </View>
        </View>
      </TouchableOpacity>
    );
  };

  return (
    <View style={s.container}>
      <View style={s.header}>
        <View>
          <Text style={s.title}>🔔 Notifications</Text>
          {nonLues > 0 && (
            <Text style={s.badge}>{nonLues} non lue{nonLues > 1 ? 's' : ''}</Text>
          )}
        </View>
        {nonLues > 0 && (
          <TouchableOpacity onPress={toutLire} style={s.toutLireBtn}>
            <Text style={s.toutLireTxt}>Tout lire</Text>
          </TouchableOpacity>
        )}
      </View>

      {loading ? (
        <ActivityIndicator size="large" color="#3b82f6" style={{ marginTop: 40 }} />
      ) : (
        <FlatList
          data={notifs}
          keyExtractor={n => n.id}
          renderItem={renderNotif}
          refreshControl={
            <RefreshControl refreshing={refreshing}
              onRefresh={() => { setRefreshing(true); loadNotifications(); }}
              tintColor="#3b82f6" />
          }
          ListEmptyComponent={
            <Text style={s.empty}>Aucune notification pour le moment.</Text>
          }
        />
      )}
    </View>
  );
}

const s = StyleSheet.create({
  container:      { flex: 1, backgroundColor: '#08090f', padding: 16 },
  header:         { flexDirection: 'row', justifyContent: 'space-between',
                    alignItems: 'flex-start', marginBottom: 16 },
  title:          { fontSize: 20, fontWeight: '900', color: '#fff' },
  badge:          { fontSize: 11, color: '#f87171', fontWeight: '700', marginTop: 2 },
  toutLireBtn:    { backgroundColor: '#1e293b', borderRadius: 8, padding: 8 },
  toutLireTxt:    { color: '#60a5fa', fontSize: 11, fontWeight: '700' },
  card:           { backgroundColor: '#111318', borderRadius: 12, padding: 14,
                    marginBottom: 8, flexDirection: 'row', gap: 12,
                    borderWidth: 1, borderColor: '#1e293b' },
  cardUnread:     { borderColor: '#3b82f6', backgroundColor: '#0c1a30' },
  iconWrap:       { width: 44, height: 44, borderRadius: 12, alignItems: 'center',
                    justifyContent: 'center', flexShrink: 0 },
  cardTitre:      { fontSize: 12, fontWeight: '700', color: '#94a3b8' },
  cardTitreUnread:{ color: '#f1f5f9' },
  cardCorps:      { fontSize: 11, color: '#64748b', lineHeight: 16 },
  cardDate:       { fontSize: 9, color: '#475569' },
  cardEleve:      { fontSize: 9, color: '#60a5fa' },
  dot:            { width: 8, height: 8, borderRadius: 4 },
  empty:          { color: '#475569', textAlign: 'center', marginTop: 40, fontSize: 13 },
});
```

---

## ÉTAPE 13 — Mettre à jour AppNavigator.js

**Modifier :** `edugestdz/mobile/src/navigation/AppNavigator.js`

```javascript
// Ajouter les imports
import SignalementsScreen  from '../screens/enseignant/SignalementsScreen';
import NotificationsScreen from '../screens/parent/NotificationsScreen';

// Dans EnseignantTabs — ajouter :
<Tab.Screen name="Signalements" component={SignalementsScreen}
  options={{ tabBarLabel: 'Signaler', tabBarIcon: ({ color, size }) =>
    <Text style={{ fontSize: size, color }}>📋</Text> }} />

// Dans ParentTabs — ajouter :
<Tab.Screen name="Notifications" component={NotificationsScreen}
  options={{ tabBarLabel: 'Notifications',
    tabBarIcon: ({ color, size }) => <Text style={{ fontSize: size, color }}>🔔</Text>,
    tabBarBadge: undefined, // sera mis à jour dynamiquement
  }} />
```

---

## ÉTAPE 14 — Tests backend

**Créer :** `edugestdz/backend/tests/Feature/Controllers/SignalementControllerTest.php`

```php
<?php

namespace Tests\Feature\Controllers;

use App\Models\User;
use App\Models\Eleve;
use App\Models\SignalementComportement;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class SignalementControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $enseignant;
    private User $parent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin      = User::factory()->create(['role' => 'admin']);
        $this->enseignant = User::factory()->create(['role' => 'enseignant']);
        $this->parent     = User::factory()->create(['role' => 'parent']);
    }

    public function test_enseignant_peut_signaler_comportement(): void
    {
        $eleve = Eleve::factory()->create();

        $this->actingAs($this->enseignant, 'api')
            ->postJson('/api/v1/signalements', [
                'eleve_id'      => $eleve->id,
                'type'          => 'perturbation',
                'gravite'       => 'normale',
                'description'   => 'L\'élève a perturbé le cours de mathématiques.',
                'lieu'          => 'Salle 12',
                'date_incident' => today()->format('Y-m-d'),
            ])
            ->assertStatus(201)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.type', 'perturbation');
    }

    public function test_admin_peut_signaler(): void
    {
        $eleve = Eleve::factory()->create();

        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/signalements', [
                'eleve_id'      => $eleve->id,
                'type'          => 'félicitation',
                'gravite'       => 'info',
                'description'   => 'Excellent comportement toute la semaine.',
                'date_incident' => today()->format('Y-m-d'),
            ])
            ->assertStatus(201);
    }

    public function test_type_invalide_echoue(): void
    {
        $eleve = Eleve::factory()->create();

        $this->actingAs($this->enseignant, 'api')
            ->postJson('/api/v1/signalements', [
                'eleve_id'      => $eleve->id,
                'type'          => 'insulte_prof', // invalide
                'gravite'       => 'grave',
                'description'   => 'test',
                'date_incident' => today()->format('Y-m-d'),
            ])
            ->assertStatus(422);
    }

    public function test_date_future_echoue(): void
    {
        $eleve = Eleve::factory()->create();

        $this->actingAs($this->enseignant, 'api')
            ->postJson('/api/v1/signalements', [
                'eleve_id'      => $eleve->id,
                'type'          => 'perturbation',
                'gravite'       => 'normale',
                'description'   => 'test',
                'date_incident' => now()->addDay()->format('Y-m-d'), // futur = invalide
            ])
            ->assertStatus(422);
    }

    public function test_voir_signalements_eleve(): void
    {
        $eleve = Eleve::factory()->create();

        $this->actingAs($this->admin, 'api')
            ->getJson("/api/v1/signalements/eleve/{$eleve->id}")
            ->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_lister_tous_signalements_admin(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/signalements')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data', 'stats']);
    }

    public function test_traiter_signalement(): void
    {
        $eleve = Eleve::factory()->create();
        $sig   = SignalementComportement::create([
            'tenant_id'     => Str::uuid(),
            'eleve_id'      => $eleve->id,
            'signale_par'   => $this->enseignant->id,
            'role_auteur'   => 'enseignant',
            'type'          => 'perturbation',
            'gravite'       => 'normale',
            'description'   => 'Test',
            'date_incident' => today()->format('Y-m-d'),
        ]);

        $this->actingAs($this->admin, 'api')
            ->postJson("/api/v1/signalements/{$sig->id}/traiter", [
                'suite_donnee' => 'Avertissement verbal donné à l\'élève.',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.traite', true);
    }

    public function test_parent_voit_signalements_enfant(): void
    {
        $this->actingAs($this->parent, 'api')
            ->getJson('/api/v1/signalements/parent/mon-enfant')
            ->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_notifications_parent_liste(): void
    {
        $this->actingAs($this->parent, 'api')
            ->getJson('/api/v1/notifications/parent')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data', 'non_lues']);
    }

    public function test_marquer_notification_lue(): void
    {
        $notif = \App\Models\NotificationParent::create([
            'tenant_id' => Str::uuid(),
            'parent_id' => $this->parent->id,
            'type'      => 'note',
            'titre'     => 'Test',
            'corps'     => 'Test corps',
        ]);

        $this->actingAs($this->parent, 'api')
            ->postJson("/api/v1/notifications/parent/{$notif->id}/lire")
            ->assertStatus(200);
    }

    public function test_acces_sans_auth_refuse(): void
    {
        $this->postJson('/api/v1/signalements', [])->assertStatus(401);
        $this->getJson('/api/v1/notifications/parent')->assertStatus(401);
    }
}
```

---

## ORDRE D'EXÉCUTION DEEPSEEK

```bash
# 0. Synchroniser
git checkout develop && git pull origin main

# BACKEND
# 1. Migration (2 tables)
create: database/migrations/2026_07_05_100000_create_signalements_comportement_table.php
php artisan migrate

# 2. Models (2 fichiers)
create: app/Models/SignalementComportement.php
create: app/Models/NotificationParent.php

# 3. Service central notifications
create: app/Services/ParentNotificationService.php

# 4. Contrôleur signalements
create: app/Http/Controllers/Api/V1/SignalementController.php

# 5. Observer bulletin (nouveau)
create: app/Observers/BulletinObserver.php

# 6. Modifier NoteObserver (utiliser ParentNotificationService)
modify: app/Observers/NoteObserver.php → constructeur + created()

# 7. Modifier AbsenceJournaliereObserver
modify: app/Observers/AbsenceJournaliereObserver.php

# 8. Modifier AppServiceProvider (enregistrer BulletinObserver)
modify: app/Providers/AppServiceProvider.php

# 9. Routes API (10 nouvelles routes)
modify: routes/api.php

# MOBILE
# 10. NotesScreen enseignant (saisie complète)
modify: mobile/src/screens/enseignant/NotesScreen.js

# 11. SignalementsScreen enseignant (nouveau)
create: mobile/src/screens/enseignant/SignalementsScreen.js

# 12. NotificationsScreen parent (nouveau)
create: mobile/src/screens/parent/NotificationsScreen.js

# 13. AppNavigator (ajouter 2 nouveaux écrans)
modify: mobile/src/navigation/AppNavigator.js

# TESTS
# 14. Tests signalements
create: tests/Feature/Controllers/SignalementControllerTest.php

# VÉRIFIER
composer dump-autoload -o
php artisan test --parallel
# → 0 régression + 11 nouveaux tests

# COMMIT
git add .
git commit -m "feat: Communication parent complète — Signalements comportement (prof/proviseur/surveillant) + Centre notifications parent + NotesScreen saisie mobile + Push toutes actions enfant + 11 tests"
git push origin develop
# → PR develop → main
```

---

## CE QUE TU DIS À DEEPSEEK

```
Repo : https://github.com/Allintelligence2024/edugest-dz.git
Branche : develop
git checkout develop && git pull origin main

Fichier : MISSION_COMMUNICATION_PARENT_COMPLET.md — 14 étapes.

RÈGLES CRITIQUES :
1. PostgreSQL uniquement — jamais SQLite.
2. 0 régression — les 460+ tests existants restent verts.
3. NE PAS modifier EvaluationController ni BulletinController existants.
4. NE PAS supprimer les écrans parent existants.
5. Si NoteObserver existe déjà → modifier seulement le constructeur et created().
6. Si AbsenceJournaliereObserver existe → remplacer le contenu de created() uniquement.
7. ParentNotificationService est NOUVEAU — ne pas confondre avec FirebaseService.
8. Le SignalementController.monEnfantSignalements() cherche les élèves via
   la relation parents (whereHas) — vérifier que la relation existe sur Eleve model.

composer dump-autoload -o
php artisan test --parallel → verts
git push origin develop → PR develop → main
```
