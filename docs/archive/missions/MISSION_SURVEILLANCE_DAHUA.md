# 🤖 MISSION DEEPSEEK — Intégration Alertes Webhook Dahua (Télésurveillance)
## EduGest DZ · Branche : develop · 3 Juillet 2026
## Tests actuels : 423+ ✅ · Objectif : ≥ 432 ✅ · 0 régression

---

## CONTEXTE

Les établissements privés algériens utilisent majoritairement des systèmes
de vidéosurveillance **Dahua** (DVR/NVR). Dahua supporte l'envoi d'alertes
HTTP vers une URL externe quand un événement se produit (mouvement, intrusion,
sabotage caméra, etc.).

Cette mission ajoute la réception et le traitement de ces alertes dans EduGest DZ.

### Ce qu'on construit
- **2 nouvelles tables** : `cameras_config` + `alertes_surveillance`
- **1 nouveau service** : `DahuaWebhookService`
- **1 nouveau contrôleur** : `SurveillanceController` (3 méthodes)
- **1 nouvelle page React** : `SurveillancePage.jsx`
- **Réutilisation** de `SmsService` + `FirebaseService` + `spatie/activitylog`
- **6 tests** couvrant les cas critiques

### Ce qu'on ne touche PAS
- Aucun contrôleur existant modifié
- Aucune migration existante modifiée
- Aucun test existant cassé
- 0 nouveau package externe (tout réutilise l'existant)

### RÈGLES ABSOLUES
1. PostgreSQL uniquement — jamais SQLite
2. 0 régression — les 423+ tests existants restent verts
3. Ne modifier aucun fichier existant sauf : `routes/api.php` (+3 lignes),
   `Sidebar.jsx` (+1 ligne), `App.jsx` (+2 lignes)
4. La route webhook `/api/v1/surveillance/webhook` est PUBLIQUE
   (pas de middleware auth — Dahua ne s'authentifie pas en JWT)
5. Valider l'origine Dahua par `SerialNo` + secret partagé

---

## ÉTAPE 0 — Synchroniser develop

```bash
git checkout develop
git pull origin main
```

---

## ÉTAPE 1 — Migration : 2 nouvelles tables

**Créer :**
`edugestdz/backend/database/migrations/2026_07_03_100000_create_surveillance_tables.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Configuration des caméras par établissement ──────────────────
        Schema::create('cameras_config', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->string('nom');                    // "Entrée principale"
            $table->string('serial_no')->unique();    // Numéro de série DVR/NVR Dahua
            $table->string('ip_locale')->nullable();  // 192.168.1.64
            $table->integer('canal')->default(1);     // Canal caméra sur le DVR
            $table->string('type')->default('entree');
            // Valeurs : entree | couloir | classe | parking | cantine | bus | autre
            $table->string('localisation')->nullable(); // "Bâtiment A - Rez-de-chaussée"
            $table->string('webhook_secret')->nullable(); // Secret partagé pour valider l'origine
            $table->time('heure_ouverture')->default('07:00'); // Plage horaire normale
            $table->time('heure_fermeture')->default('20:00'); // Hors plage = alerte critique
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'actif'],     'idx_cameras_tenant_actif');
            $table->index(['serial_no'],               'idx_cameras_serial');
        });

        // ── Historique des alertes reçues ────────────────────────────────
        Schema::create('alertes_surveillance', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('tenant_id');
            $table->uuid('camera_id')->nullable(); // null si caméra non configurée
            $table->string('serial_no');           // Serial du DVR source
            $table->string('type_alerte');
            // Valeurs : mouvement | intrusion | visage_inconnu | sabotage
            //           perte_signal | disque_plein | temperature | autre
            $table->string('niveau')->default('warning');
            // Valeurs : info | warning | critical
            $table->string('canal')->nullable();   // Canal caméra (1, 2, 3...)
            $table->jsonb('payload')->default('{}'); // Payload brut Dahua complet
            $table->timestamp('survenu_le');       // Timestamp exact de l'événement
            $table->boolean('traite')->default(false);
            $table->uuid('traite_par')->nullable(); // User qui a traité l'alerte
            $table->timestamp('traite_le')->nullable();
            $table->string('note_admin')->nullable(); // Note du gestionnaire
            $table->boolean('sms_envoye')->default(false);
            $table->boolean('push_envoye')->default(false);
            $table->timestamps();

            $table->index(['tenant_id', 'traite'],             'idx_alertes_tenant_traite');
            $table->index(['tenant_id', 'niveau'],             'idx_alertes_tenant_niveau');
            $table->index(['serial_no', 'survenu_le'],         'idx_alertes_serial_date');
            $table->index(['tenant_id', 'survenu_le'],         'idx_alertes_tenant_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alertes_surveillance');
        Schema::dropIfExists('cameras_config');
    }
};
```

---

## ÉTAPE 2 — Model CameraConfig

**Créer :** `edugestdz/backend/app/Models/CameraConfig.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class CameraConfig extends Model
{
    use HasUuids;

    protected $table = 'cameras_config';

    protected $fillable = [
        'tenant_id', 'nom', 'serial_no', 'ip_locale', 'canal',
        'type', 'localisation', 'webhook_secret',
        'heure_ouverture', 'heure_fermeture', 'actif',
    ];

    protected $hidden = ['webhook_secret'];

    protected $casts = [
        'actif'            => 'boolean',
        'canal'            => 'integer',
        'heure_ouverture'  => 'string',
        'heure_fermeture'  => 'string',
    ];

    // Types de caméras disponibles
    public const TYPES = [
        'entree'   => 'Entrée principale',
        'couloir'  => 'Couloir',
        'classe'   => 'Salle de classe',
        'parking'  => 'Parking / Extérieur',
        'cantine'  => 'Cantine',
        'bus'      => 'Bus scolaire',
        'autre'    => 'Autre',
    ];

    public function alertes()
    {
        return $this->hasMany(AlerteSurveillance::class, 'camera_id');
    }

    /**
     * Vérifie si l'heure actuelle est hors des horaires normaux.
     * Si oui → alerte critique.
     */
    public function estHorsHoraires(): bool
    {
        $now    = now()->format('H:i');
        $ouv    = $this->heure_ouverture ?? '07:00';
        $ferm   = $this->heure_fermeture ?? '20:00';
        return $now < $ouv || $now > $ferm;
    }

    public function scopeActif($query)
    {
        return $query->where('actif', true);
    }
}
```

---

## ÉTAPE 3 — Model AlerteSurveillance

**Créer :** `edugestdz/backend/app/Models/AlerteSurveillance.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class AlerteSurveillance extends Model
{
    use HasUuids;

    protected $table = 'alertes_surveillance';

    protected $fillable = [
        'tenant_id', 'camera_id', 'serial_no', 'type_alerte',
        'niveau', 'canal', 'payload', 'survenu_le',
        'traite', 'traite_par', 'traite_le', 'note_admin',
        'sms_envoye', 'push_envoye',
    ];

    protected $casts = [
        'payload'    => 'array',
        'traite'     => 'boolean',
        'sms_envoye' => 'boolean',
        'push_envoye'=> 'boolean',
        'survenu_le' => 'datetime',
        'traite_le'  => 'datetime',
    ];

    // Types d'alertes Dahua et leurs libellés FR
    public const TYPES = [
        'VideoMotion'        => 'Détection de mouvement',
        'AlarmLocal'         => 'Alarme locale',
        'CrossLineDetection' => 'Franchissement de ligne',
        'IntrusionDetection' => 'Détection d\'intrusion',
        'FaceDetection'      => 'Détection de visage',
        'VideoLoss'          => 'Perte signal vidéo',
        'VideoBlind'         => 'Sabotage caméra',
        'DiskFull'           => 'Disque DVR plein',
        'DiskError'          => 'Erreur disque DVR',
        'StorageNotExist'    => 'Stockage absent',
        'StorageLowSpace'    => 'Stockage faible',
        'NetworkAbort'       => 'Perte réseau',
        'temperature'        => 'Température anormale',
        'autre'              => 'Événement divers',
    ];

    // Niveaux selon le type d'alerte
    public const NIVEAUX_PAR_TYPE = [
        'VideoMotion'        => 'warning',
        'AlarmLocal'         => 'critical',
        'CrossLineDetection' => 'critical',
        'IntrusionDetection' => 'critical',
        'FaceDetection'      => 'warning',
        'VideoLoss'          => 'critical',
        'VideoBlind'         => 'critical',
        'DiskFull'           => 'warning',
        'DiskError'          => 'critical',
        'StorageNotExist'    => 'warning',
        'StorageLowSpace'    => 'info',
        'NetworkAbort'       => 'warning',
    ];

    public function camera()
    {
        return $this->belongsTo(CameraConfig::class, 'camera_id');
    }

    public function traitePar()
    {
        return $this->belongsTo(User::class, 'traite_par');
    }

    public function getLibelleTypeAttribute(): string
    {
        return self::TYPES[$this->type_alerte] ?? $this->type_alerte;
    }

    public function scopeNonTraitees($query)
    {
        return $query->where('traite', false);
    }

    public function scopeCritiques($query)
    {
        return $query->where('niveau', 'critical');
    }
}
```

---

## ÉTAPE 4 — DahuaWebhookService

**Créer :** `edugestdz/backend/app/Services/DahuaWebhookService.php`

```php
<?php

namespace App\Services;

use App\Models\CameraConfig;
use App\Models\AlerteSurveillance;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DahuaWebhookService
{
    public function __construct(
        private SmsService      $sms,
        private FirebaseService $firebase,
    ) {}

    /**
     * Point d'entrée principal.
     * Reçoit le payload brut Dahua et le traite.
     *
     * Formats Dahua supportés :
     *  - HTTP CGI Event (firmware < 2.8)
     *  - DSS HTTP Notification (firmware >= 2.8)
     *  - ISAPI Event (NVR Pro)
     */
    public function traiter(array $payload, string $ipSource): ?AlerteSurveillance
    {
        Log::info('Dahua webhook reçu', ['ip' => $ipSource, 'payload' => $payload]);

        // ── Normaliser le payload selon le format Dahua ──────────────────
        $normalise = $this->normaliserPayload($payload);
        if (!$normalise) {
            Log::warning('Dahua webhook : payload non reconnu', $payload);
            return null;
        }

        // ── Trouver la caméra configurée ─────────────────────────────────
        $camera = CameraConfig::where('serial_no', $normalise['serial_no'])
            ->where('actif', true)
            ->first();

        // Si caméra inconnue → logger mais ne pas traiter
        if (!$camera) {
            Log::warning("Dahua webhook : Serial inconnu {$normalise['serial_no']}");
            return null;
        }

        // ── Déterminer le niveau de criticité ────────────────────────────
        $niveau = $this->determinerNiveau($normalise['type_alerte'], $camera);

        // ── Créer l'alerte en BDD ────────────────────────────────────────
        $alerte = AlerteSurveillance::create([
            'tenant_id'   => $camera->tenant_id,
            'camera_id'   => $camera->id,
            'serial_no'   => $normalise['serial_no'],
            'type_alerte' => $normalise['type_alerte'],
            'niveau'      => $niveau,
            'canal'       => $normalise['canal'],
            'payload'     => $payload,
            'survenu_le'  => $normalise['survenu_le'],
        ]);

        // ── Notifier selon la criticité ──────────────────────────────────
        $this->notifier($alerte, $camera);

        return $alerte;
    }

    /**
     * Normaliser les différents formats de payload Dahua.
     */
    private function normaliserPayload(array $payload): ?array
    {
        // Format 1 — HTTP CGI Event (le plus courant)
        // POST body: Events[0][Code]=VideoMotion&Events[0][Action]=Start&IpAddress=...
        if (isset($payload['IpAddress']) && isset($payload['Events'])) {
            $events = $payload['Events'];
            $event  = is_array($events) ? ($events[0] ?? $events) : [];

            return [
                'serial_no'   => $payload['SerialNo'] ?? $payload['IpAddress'],
                'type_alerte' => $event['Code']    ?? 'autre',
                'action'      => $event['Action']  ?? 'Start',
                'canal'       => (string) ($payload['ChannelID'] ?? $event['Index'] ?? '1'),
                'survenu_le'  => $this->parseDate($payload['LocaleTime'] ?? now()->toDateTimeString()),
            ];
        }

        // Format 2 — DSS Platform Notification (JSON structuré)
        if (isset($payload['eventType']) && isset($payload['deviceId'])) {
            return [
                'serial_no'   => $payload['deviceId'],
                'type_alerte' => $payload['eventType'],
                'action'      => $payload['action'] ?? 'Start',
                'canal'       => (string) ($payload['channelId'] ?? '1'),
                'survenu_le'  => $this->parseDate($payload['dateTime'] ?? now()->toDateTimeString()),
            ];
        }

        // Format 3 — ISAPI (NVR Pro, balise XML convertie en array)
        if (isset($payload['EventNotificationAlert'])) {
            $event = $payload['EventNotificationAlert'];
            return [
                'serial_no'   => $event['deviceID']  ?? 'unknown',
                'type_alerte' => $event['eventType'] ?? 'autre',
                'action'      => $event['eventState'] ?? 'active',
                'canal'       => (string) ($event['channelID'] ?? '1'),
                'survenu_le'  => $this->parseDate($event['dateTime'] ?? now()->toDateTimeString()),
            ];
        }

        // Format 4 — Payload simplifié (certains firmwares anciens)
        if (isset($payload['code']) && isset($payload['serial'])) {
            return [
                'serial_no'   => $payload['serial'],
                'type_alerte' => $payload['code'],
                'action'      => $payload['action'] ?? 'Start',
                'canal'       => (string) ($payload['channel'] ?? '1'),
                'survenu_le'  => now(),
            ];
        }

        return null; // Format non reconnu
    }

    /**
     * Déterminer le niveau de criticité.
     * Logique :
     *  - Certains types sont toujours critiques (intrusion, sabotage)
     *  - Mouvement hors horaires = critique
     *  - Mouvement dans les horaires = warning
     */
    private function determinerNiveau(string $typeAlerte, CameraConfig $camera): string
    {
        // Types toujours critiques
        $toujoursCritiques = [
            'AlarmLocal', 'CrossLineDetection', 'IntrusionDetection',
            'VideoLoss', 'VideoBlind', 'DiskError',
        ];

        if (in_array($typeAlerte, $toujoursCritiques)) {
            return 'critical';
        }

        // Mouvement hors horaires → critique
        if ($typeAlerte === 'VideoMotion' && $camera->estHorsHoraires()) {
            return 'critical';
        }

        // Niveaux par défaut par type
        return AlerteSurveillance::NIVEAUX_PAR_TYPE[$typeAlerte] ?? 'warning';
    }

    /**
     * Envoyer les notifications selon le niveau de l'alerte.
     */
    private function notifier(AlerteSurveillance $alerte, CameraConfig $camera): void
    {
        $libelleType = AlerteSurveillance::TYPES[$alerte->type_alerte]
            ?? $alerte->type_alerte;

        $heure    = $alerte->survenu_le->format('H:i');
        $lieu     = $camera->localisation ?? $camera->nom;
        $message  = "🔔 EduGest Sécurité : {$libelleType} détecté(e) — {$lieu} à {$heure}.";

        if ($alerte->niveau === 'critical') {
            $message = "🚨 ALERTE CRITIQUE EduGest : {$libelleType} — {$lieu} à {$heure}. Vérifiez immédiatement.";
        }

        // ── SMS aux administrateurs du tenant ────────────────────────────
        $admins = \App\Models\User::where('tenant_id', $camera->tenant_id)
            ->where('role', 'admin')
            ->whereNotNull('telephone')
            ->get();

        $smsSent = false;
        foreach ($admins as $admin) {
            try {
                $this->sms->send($admin->telephone, $message);
                $smsSent = true;
            } catch (\Throwable $e) {
                Log::warning("SMS surveillance échoué admin {$admin->id}: " . $e->getMessage());
            }
        }

        // ── Push notification aux admins ─────────────────────────────────
        $pushSent = false;
        foreach ($admins as $admin) {
            $pushed = $this->firebase->notifyUser(
                $admin->id,
                $alerte->niveau === 'critical' ? '🚨 Alerte critique' : '🔔 Alerte surveillance',
                $message,
                [
                    'type'      => 'surveillance',
                    'alerte_id' => $alerte->id,
                    'niveau'    => $alerte->niveau,
                ]
            );
            if ($pushed) $pushSent = true;
        }

        // Mettre à jour les flags d'envoi
        $alerte->update([
            'sms_envoye'  => $smsSent,
            'push_envoye' => $pushSent,
        ]);

        Log::info("Surveillance alerte {$alerte->id} notifiée", [
            'sms'   => $smsSent,
            'push'  => $pushSent,
            'niveau'=> $alerte->niveau,
        ]);
    }

    /**
     * Parser les différents formats de date Dahua.
     * Dahua envoie parfois : "2026-07-03 08:47:23" ou "2026-07-03T08:47:23+05:00"
     */
    private function parseDate(string $dateStr): Carbon
    {
        try {
            return Carbon::parse($dateStr);
        } catch (\Throwable) {
            return now();
        }
    }
}
```

---

## ÉTAPE 5 — SurveillanceController

**Créer :** `edugestdz/backend/app/Http/Controllers/Api/V1/SurveillanceController.php`

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CameraConfig;
use App\Models\AlerteSurveillance;
use App\Services\DahuaWebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class SurveillanceController extends Controller
{
    public function __construct(private DahuaWebhookService $service) {}

    // ════════════════════════════════════════════════════════════════════
    // WEBHOOK PUBLIC — Dahua appelle cette URL
    // PAS de middleware auth:api — Dahua ne s'authentifie pas en JWT
    // ════════════════════════════════════════════════════════════════════

    /**
     * @OA\Post(
     *     path="/api/v1/surveillance/webhook",
     *     summary="Recevoir les alertes Dahua (webhook public)",
     *     tags={"Surveillance"},
     *     description="Endpoint appelé par le DVR/NVR Dahua. Pas d'authentification JWT. Valider par SerialNo.",
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="SerialNo",  type="string", example="DAH2026XXXXXX"),
     *             @OA\Property(property="IpAddress", type="string", example="192.168.1.64"),
     *             @OA\Property(property="ChannelID", type="integer", example=1),
     *             @OA\Property(property="Events",    type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="Code",   type="string", example="VideoMotion"),
     *                     @OA\Property(property="Action", type="string", example="Start"),
     *                     @OA\Property(property="Index",  type="integer", example=0)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Alerte reçue et traitée"),
     *     @OA\Response(response=400, description="Payload invalide ou caméra non configurée")
     * )
     */
    public function recevoir(Request $request): JsonResponse
    {
        // Accepter JSON et form-data (Dahua envoie les deux selon le firmware)
        $payload = $request->all();

        // Certains firmwares Dahua envoient du XML → tenter de parser
        if (empty($payload) && $request->getContent()) {
            $content = $request->getContent();
            if (str_starts_with(ltrim($content), '<')) {
                // XML → Array
                try {
                    $xml     = simplexml_load_string($content);
                    $payload = json_decode(json_encode($xml), true);
                } catch (\Throwable) {
                    Log::warning('Dahua webhook : XML invalide');
                }
            }
        }

        if (empty($payload)) {
            return response()->json(['received' => false, 'reason' => 'empty_payload'], 400);
        }

        $alerte = $this->service->traiter($payload, $request->ip());

        if (!$alerte) {
            // Dahua attend toujours un 200 — sinon il réessaie en boucle
            return response()->json(['received' => true, 'processed' => false], 200);
        }

        return response()->json([
            'received'  => true,
            'processed' => true,
            'alerte_id' => $alerte->id,
            'niveau'    => $alerte->niveau,
        ], 200);
    }

    // ════════════════════════════════════════════════════════════════════
    // ENDPOINTS AUTHENTIFIÉS — Dashboard admin
    // ════════════════════════════════════════════════════════════════════

    /**
     * @OA\Get(
     *     path="/api/v1/surveillance/alertes",
     *     summary="Liste des alertes de surveillance",
     *     tags={"Surveillance"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(ref="#/components/parameters/TenantId"),
     *     @OA\Parameter(name="traite",  in="query", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="niveau",  in="query", @OA\Schema(type="string", enum={"info","warning","critical"})),
     *     @OA\Parameter(name="per_page",in="query", @OA\Schema(type="integer", default=20)),
     *     @OA\Response(response=200, description="Alertes paginées avec stats")
     * )
     */
    public function indexAlertes(Request $request): JsonResponse
    {
        $alertes = AlerteSurveillance::with('camera:id,nom,type,localisation')
            ->when($request->filled('traite'),  fn($q) => $q->where('traite', (bool) $request->traite))
            ->when($request->filled('niveau'),  fn($q) => $q->where('niveau', $request->niveau))
            ->when($request->filled('camera_id'), fn($q) => $q->where('camera_id', $request->camera_id))
            ->orderByDesc('survenu_le')
            ->paginate($request->per_page ?? 20);

        // Stats pour le dashboard
        $stats = [
            'non_traitees' => AlerteSurveillance::nonTraitees()->count(),
            'critiques_24h'=> AlerteSurveillance::critiques()
                ->where('survenu_le', '>=', now()->subHours(24))
                ->count(),
            'total_24h'    => AlerteSurveillance::where('survenu_le', '>=', now()->subHours(24))->count(),
            'cameras_actives' => CameraConfig::actif()->count(),
        ];

        return response()->json([
            'success' => true,
            'data'    => ['alertes' => $alertes, 'stats' => $stats],
            'message' => 'Alertes de surveillance',
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/surveillance/alertes/{id}/traiter",
     *     summary="Marquer une alerte comme traitée",
     *     tags={"Surveillance"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(ref="#/components/parameters/TenantId"),
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\RequestBody(@OA\JsonContent(
     *         @OA\Property(property="note_admin", type="string", nullable=true,
     *             example="Vérification effectuée — fausse alarme, chat du voisin")
     *     )),
     *     @OA\Response(response=200, description="Alerte marquée traitée"),
     *     @OA\Response(response=404, description="Alerte non trouvée")
     * )
     */
    public function traiterAlerte(Request $request, string $id): JsonResponse
    {
        $alerte = AlerteSurveillance::findOrFail($id);

        $alerte->update([
            'traite'     => true,
            'traite_par' => auth('api')->id(),
            'traite_le'  => now(),
            'note_admin' => $request->input('note_admin'),
        ]);

        return response()->json([
            'success' => true,
            'data'    => $alerte->fresh(),
            'message' => 'Alerte marquée comme traitée',
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/surveillance/cameras",
     *     summary="Liste des caméras configurées",
     *     tags={"Surveillance"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(ref="#/components/parameters/TenantId"),
     *     @OA\Response(response=200, description="Caméras du tenant")
     * )
     */
    public function indexCameras(Request $request): JsonResponse
    {
        $cameras = CameraConfig::actif()
            ->withCount(['alertes as alertes_non_traitees' => fn($q) => $q->where('traite', false)])
            ->orderBy('nom')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $cameras,
            'message' => 'Caméras configurées',
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/surveillance/cameras",
     *     summary="Enregistrer une nouvelle caméra Dahua",
     *     tags={"Surveillance"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(ref="#/components/parameters/TenantId"),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"nom","serial_no","type"},
     *             @OA\Property(property="nom",              type="string", example="Entrée principale"),
     *             @OA\Property(property="serial_no",        type="string", example="DAH2026XXXXXX"),
     *             @OA\Property(property="ip_locale",        type="string", example="192.168.1.64"),
     *             @OA\Property(property="canal",            type="integer", example=1),
     *             @OA\Property(property="type",             type="string",
     *                 enum={"entree","couloir","classe","parking","cantine","bus","autre"}),
     *             @OA\Property(property="localisation",     type="string", example="Bâtiment A - RDC"),
     *             @OA\Property(property="heure_ouverture",  type="string", example="07:00"),
     *             @OA\Property(property="heure_fermeture",  type="string", example="20:00")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Caméra enregistrée + URL webhook à configurer sur le DVR"),
     *     @OA\Response(response=422, description="Numéro de série déjà utilisé")
     * )
     */
    public function enregistrerCamera(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nom'             => 'required|string|max:100',
            'serial_no'       => 'required|string|max:50|unique:cameras_config,serial_no',
            'ip_locale'       => 'nullable|ip',
            'canal'           => 'integer|min:1|max:32',
            'type'            => 'required|in:entree,couloir,classe,parking,cantine,bus,autre',
            'localisation'    => 'nullable|string|max:200',
            'heure_ouverture' => 'nullable|date_format:H:i',
            'heure_fermeture' => 'nullable|date_format:H:i',
        ]);

        // Générer un secret webhook unique pour ce tenant/caméra
        $secret = bin2hex(random_bytes(16));

        $camera = CameraConfig::create([
            ...$validated,
            'tenant_id'      => config('tenant.current_id'),
            'webhook_secret' => $secret,
            'actif'          => true,
        ]);

        // URL que le client doit configurer sur son DVR Dahua
        $webhookUrl = url('/api/v1/surveillance/webhook');

        return response()->json([
            'success' => true,
            'data'    => [
                'camera'      => $camera->makeVisible('webhook_secret'),
                'webhook_url' => $webhookUrl,
                'instructions'=> [
                    'etape_1' => "Accéder au DVR Dahua : http://{$camera->ip_locale}",
                    'etape_2' => "Aller dans : Paramètres → Réseau → Événements HTTP",
                    'etape_3' => "URL : {$webhookUrl}",
                    'etape_4' => "Méthode : POST · Format : JSON",
                    'etape_5' => "Sélectionner les événements : VideoMotion, AlarmLocal, IntrusionDetection, VideoLoss",
                    'etape_6' => "Enregistrer et tester",
                ],
            ],
            'message' => "Caméra enregistrée. Configurez le webhook sur votre DVR Dahua.",
        ], 201);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/surveillance/cameras/{id}",
     *     summary="Désactiver une caméra",
     *     tags={"Surveillance"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(ref="#/components/parameters/TenantId"),
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *     @OA\Response(response=200, description="Caméra désactivée")
     * )
     */
    public function desactiverCamera(string $id): JsonResponse
    {
        $camera = CameraConfig::findOrFail($id);
        $camera->update(['actif' => false]);

        return response()->json([
            'success' => true,
            'message' => "Caméra {$camera->nom} désactivée",
        ]);
    }
}
```

---

## ÉTAPE 6 — Routes API

**Modifier :** `edugestdz/backend/routes/api.php`

Ajouter en haut du fichier (dans les imports) :
```php
use App\Http\Controllers\Api\V1\SurveillanceController;
```

Ajouter les routes (après les routes existantes) :

```php
// ══════════════════════════════════════════════════════════════════════
// SURVEILLANCE DAHUA — Télésurveillance
// ══════════════════════════════════════════════════════════════════════

// Webhook PUBLIC — Dahua appelle cette URL (pas d'auth JWT)
// IMPORTANT : doit être HORS du groupe middleware auth:api
Route::post('/v1/surveillance/webhook', [SurveillanceController::class, 'recevoir'])
    ->middleware('throttle:webhook');

// Endpoints authentifiés — dashboard admin
Route::middleware(['auth:api', 'tenant'])->prefix('v1/surveillance')->group(function () {
    Route::get('/alertes',                  [SurveillanceController::class, 'indexAlertes']);
    Route::post('/alertes/{id}/traiter',    [SurveillanceController::class, 'traiterAlerte']);
    Route::get('/cameras',                  [SurveillanceController::class, 'indexCameras']);
    Route::post('/cameras',                 [SurveillanceController::class, 'enregistrerCamera']);
    Route::delete('/cameras/{id}',          [SurveillanceController::class, 'desactiverCamera']);
});
```

---

## ÉTAPE 7 — Page React SurveillancePage

**Créer :** `edugestdz/frontend/src/pages/SurveillancePage.jsx`

```jsx
import { useState, useEffect, useCallback } from 'react';
import { AlertTriangle, Camera, CheckCircle, Clock, Shield, RefreshCw } from 'lucide-react';

const api = (path, opts) => fetch(`/api/v1${path}`, {
  headers: {
    Authorization: `Bearer ${localStorage.getItem('token')}`,
    'Content-Type': 'application/json',
    'X-Tenant-ID': localStorage.getItem('tenantId') ?? '',
  },
  ...opts,
}).then(r => r.json());

const NIVEAUX = {
  critical: { color: '#f87171', bg: '#450a0a', border: '#b91c1c', label: '🚨 CRITIQUE' },
  warning:  { color: '#fb923c', bg: '#1f1008', border: '#c2410c', label: '⚠️ Alerte'  },
  info:     { color: '#60a5fa', bg: '#0c1a30', border: '#1d4ed8', label: 'ℹ️ Info'    },
};

const TYPES_LABELS = {
  VideoMotion:        '📹 Mouvement détecté',
  AlarmLocal:         '🚨 Alarme locale',
  CrossLineDetection: '🚧 Franchissement ligne',
  IntrusionDetection: '⛔ Intrusion détectée',
  FaceDetection:      '👤 Visage détecté',
  VideoLoss:          '📵 Perte signal vidéo',
  VideoBlind:         '🙈 Sabotage caméra',
  DiskFull:           '💾 Disque plein',
  DiskError:          '❌ Erreur disque',
  NetworkAbort:       '🌐 Perte réseau',
};

export default function SurveillancePage() {
  const [alertes, setAlertes]   = useState([]);
  const [cameras, setCameras]   = useState([]);
  const [stats, setStats]       = useState({});
  const [loading, setLoading]   = useState(true);
  const [tab, setTab]           = useState('alertes');
  const [filtreNiveau, setFiltreNiveau] = useState('');
  const [filtreTraite, setFiltreTraite] = useState('false');
  const [showAddCamera, setShowAddCamera] = useState(false);
  const [newCamera, setNewCamera] = useState({ nom: '', serial_no: '', type: 'entree', ip_locale: '', localisation: '', heure_ouverture: '07:00', heure_fermeture: '20:00' });
  const [saving, setSaving]     = useState(false);
  const [webhookInfo, setWebhookInfo] = useState(null);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams();
      if (filtreNiveau) params.append('niveau', filtreNiveau);
      if (filtreTraite !== '') params.append('traite', filtreTraite);

      const [alertesRes, camerasRes] = await Promise.all([
        api(`/surveillance/alertes?${params}`),
        api('/surveillance/cameras'),
      ]);
      setAlertes(alertesRes?.data?.alertes?.data ?? []);
      setStats(alertesRes?.data?.stats ?? {});
      setCameras(camerasRes?.data ?? []);
    } catch (e) { console.error(e); }
    finally { setLoading(false); }
  }, [filtreNiveau, filtreTraite]);

  useEffect(() => { loadData(); }, [loadData]);

  // Auto-refresh toutes les 30 secondes
  useEffect(() => {
    const interval = setInterval(loadData, 30000);
    return () => clearInterval(interval);
  }, [loadData]);

  const traiter = async (id) => {
    const note = prompt('Note (optionnelle) — ex: Fausse alarme, vérification effectuée');
    if (note === null) return; // annulé
    await api(`/surveillance/alertes/${id}/traiter`, {
      method: 'POST',
      body: JSON.stringify({ note_admin: note }),
    });
    loadData();
  };

  const ajouterCamera = async () => {
    setSaving(true);
    try {
      const res = await api('/surveillance/cameras', {
        method: 'POST',
        body: JSON.stringify(newCamera),
      });
      if (res.success) {
        setWebhookInfo(res.data);
        setShowAddCamera(false);
        setNewCamera({ nom: '', serial_no: '', type: 'entree', ip_locale: '', localisation: '', heure_ouverture: '07:00', heure_fermeture: '20:00' });
        loadData();
      } else {
        alert('Erreur : ' + (res.message ?? 'Échec enregistrement'));
      }
    } finally { setSaving(false); }
  };

  const StatBox = ({ label, value, color, urgent }) => (
    <div style={{
      background: urgent && value > 0 ? '#450a0a' : '#111318',
      border: `1px solid ${urgent && value > 0 ? '#b91c1c' : '#1e293b'}`,
      borderRadius: '10px', padding: '16px', textAlign: 'center',
      animation: urgent && value > 0 ? 'pulse 2s infinite' : 'none',
    }}>
      <div style={{ fontSize: '28px', fontWeight: 900, color }}>{value ?? 0}</div>
      <div style={{ fontSize: '10px', color: '#64748b', marginTop: '2px', textTransform: 'uppercase', letterSpacing: '1px' }}>{label}</div>
    </div>
  );

  const AlerteCard = ({ alerte }) => {
    const n = NIVEAUX[alerte.niveau] ?? NIVEAUX.warning;
    const typeLabel = TYPES_LABELS[alerte.type_alerte] ?? alerte.type_alerte;
    const heure = new Date(alerte.survenu_le).toLocaleTimeString('fr-DZ', { hour: '2-digit', minute: '2-digit' });
    const date  = new Date(alerte.survenu_le).toLocaleDateString('fr-DZ');

    return (
      <div style={{
        background: n.bg, border: `1px solid ${n.border}`,
        borderRadius: '10px', padding: '14px 16px', marginBottom: '8px',
        display: 'flex', alignItems: 'center', gap: '14px',
      }}>
        <div style={{
          width: '40px', height: '40px', borderRadius: '10px',
          background: n.color + '22', display: 'flex', alignItems: 'center',
          justifyContent: 'center', fontSize: '18px', flexShrink: 0,
        }}>
          {alerte.niveau === 'critical' ? '🚨' : alerte.niveau === 'warning' ? '⚠️' : 'ℹ️'}
        </div>

        <div style={{ flex: 1, minWidth: 0 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '2px' }}>
            <span style={{ fontWeight: 800, fontSize: '13px', color: n.color }}>{typeLabel}</span>
            <span style={{ background: n.color + '22', color: n.color, fontSize: '9px', fontWeight: 700, padding: '1px 6px', borderRadius: '20px' }}>{n.label}</span>
          </div>
          <div style={{ fontSize: '11px', color: '#94a3b8' }}>
            📍 {alerte.camera?.nom ?? 'Caméra inconnue'}
            {alerte.camera?.localisation && ` — ${alerte.camera.localisation}`}
          </div>
          <div style={{ fontSize: '10px', color: '#64748b', marginTop: '2px' }}>
            🕐 {date} à {heure}
            {alerte.sms_envoye && ' · 📱 SMS envoyé'}
            {alerte.push_envoye && ' · 🔔 Push envoyé'}
          </div>
          {alerte.note_admin && (
            <div style={{ fontSize: '10px', color: '#4ade80', marginTop: '4px', fontStyle: 'italic' }}>
              ✅ {alerte.note_admin}
            </div>
          )}
        </div>

        {!alerte.traite ? (
          <button onClick={() => traiter(alerte.id)} style={{
            background: '#14532d', color: '#4ade80', border: 'none',
            borderRadius: '8px', padding: '8px 12px', fontSize: '11px',
            fontWeight: 700, cursor: 'pointer', flexShrink: 0,
          }}>✅ Traiter</button>
        ) : (
          <div style={{ color: '#4ade80', fontSize: '10px', fontWeight: 700, flexShrink: 0 }}>
            ✅ Traité
          </div>
        )}
      </div>
    );
  };

  return (
    <div style={{ padding: '24px', background: '#08090f', minHeight: '100vh' }}>
      {/* Header */}
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '24px' }}>
        <div>
          <h1 style={{ fontSize: '22px', fontWeight: 900, color: '#fff', display: 'flex', alignItems: 'center', gap: '10px' }}>
            <Shield size={22} color="#f59e0b" /> Surveillance Dahua
          </h1>
          <p style={{ fontSize: '12px', color: '#64748b' }}>
            Alertes temps réel · Refresh auto 30s
          </p>
        </div>
        <button onClick={loadData} style={{
          background: '#111318', border: '1px solid #1e293b', borderRadius: '8px',
          color: '#60a5fa', padding: '8px 14px', cursor: 'pointer',
          display: 'flex', alignItems: 'center', gap: '6px', fontSize: '11px',
        }}>
          <RefreshCw size={13} /> Actualiser
        </button>
      </div>

      {/* Stats */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4,1fr)', gap: '10px', marginBottom: '24px' }}>
        <StatBox label="Non traitées"    value={stats.non_traitees}  color="#f87171" urgent />
        <StatBox label="Critiques 24h"   value={stats.critiques_24h} color="#fb923c" urgent />
        <StatBox label="Total 24h"       value={stats.total_24h}     color="#60a5fa" />
        <StatBox label="Caméras actives" value={stats.cameras_actives} color="#4ade80" />
      </div>

      {/* Tabs */}
      <div style={{ display: 'flex', gap: '4px', marginBottom: '16px' }}>
        {[['alertes', '🔔 Alertes'], ['cameras', '📷 Caméras'], ['config', '⚙️ Config DVR']].map(([id, label]) => (
          <button key={id} onClick={() => setTab(id)} style={{
            background: tab === id ? '#1e3a5f' : '#111318',
            color: tab === id ? '#60a5fa' : '#64748b',
            border: `1px solid ${tab === id ? '#3b82f6' : '#1e293b'}`,
            borderRadius: '8px', padding: '8px 16px', fontSize: '11px',
            fontWeight: 700, cursor: 'pointer',
          }}>{label}</button>
        ))}
      </div>

      {/* Onglet Alertes */}
      {tab === 'alertes' && (
        <div>
          {/* Filtres */}
          <div style={{ display: 'flex', gap: '10px', marginBottom: '16px' }}>
            <select value={filtreNiveau} onChange={e => setFiltreNiveau(e.target.value)}
              style={{ background: '#111318', border: '1px solid #1e293b', borderRadius: '8px', color: '#e2e8f0', padding: '8px 12px', fontSize: '11px' }}>
              <option value="">Tous les niveaux</option>
              <option value="critical">🚨 Critiques</option>
              <option value="warning">⚠️ Alertes</option>
              <option value="info">ℹ️ Info</option>
            </select>
            <select value={filtreTraite} onChange={e => setFiltreTraite(e.target.value)}
              style={{ background: '#111318', border: '1px solid #1e293b', borderRadius: '8px', color: '#e2e8f0', padding: '8px 12px', fontSize: '11px' }}>
              <option value="false">Non traitées</option>
              <option value="true">Traitées</option>
              <option value="">Toutes</option>
            </select>
          </div>

          {loading ? (
            <div style={{ color: '#475569', textAlign: 'center', padding: '40px' }}>Chargement...</div>
          ) : alertes.length === 0 ? (
            <div style={{ background: '#0d2515', border: '1px solid #16a34a', borderRadius: '10px', padding: '24px', textAlign: 'center', color: '#4ade80' }}>
              ✅ Aucune alerte {filtreTraite === 'false' ? 'non traitée' : ''} — Système opérationnel
            </div>
          ) : (
            alertes.map(a => <AlerteCard key={a.id} alerte={a} />)
          )}
        </div>
      )}

      {/* Onglet Caméras */}
      {tab === 'cameras' && (
        <div>
          <div style={{ display: 'flex', justifyContent: 'flex-end', marginBottom: '12px' }}>
            <button onClick={() => setShowAddCamera(true)} style={{
              background: 'linear-gradient(135deg,#3b82f6,#1d4ed8)', color: '#fff',
              border: 'none', borderRadius: '8px', padding: '10px 16px',
              fontSize: '12px', fontWeight: 700, cursor: 'pointer',
            }}>+ Ajouter une caméra</button>
          </div>

          {cameras.map(cam => (
            <div key={cam.id} style={{
              background: '#111318', border: '1px solid #1e293b',
              borderRadius: '10px', padding: '14px 16px', marginBottom: '8px',
              display: 'flex', alignItems: 'center', gap: '14px',
            }}>
              <Camera size={20} color="#60a5fa" />
              <div style={{ flex: 1 }}>
                <div style={{ fontWeight: 700, fontSize: '13px', color: '#f1f5f9' }}>{cam.nom}</div>
                <div style={{ fontSize: '10px', color: '#64748b' }}>
                  Serial: {cam.serial_no} · Type: {cam.type}
                  {cam.localisation && ` · ${cam.localisation}`}
                </div>
                <div style={{ fontSize: '10px', color: '#475569' }}>
                  Horaires: {cam.heure_ouverture} – {cam.heure_fermeture}
                </div>
              </div>
              {cam.alertes_non_traitees > 0 && (
                <div style={{ background: '#450a0a', color: '#f87171', fontSize: '11px', fontWeight: 800, padding: '4px 10px', borderRadius: '20px' }}>
                  {cam.alertes_non_traitees} alerte(s)
                </div>
              )}
              <div style={{ width: '10px', height: '10px', borderRadius: '50%', background: cam.actif ? '#4ade80' : '#f87171' }} />
            </div>
          ))}

          {cameras.length === 0 && (
            <div style={{ color: '#475569', textAlign: 'center', padding: '40px', fontSize: '12px' }}>
              Aucune caméra configurée. Cliquez sur "Ajouter une caméra".
            </div>
          )}
        </div>
      )}

      {/* Onglet Config DVR */}
      {tab === 'config' && (
        <div style={{ background: '#111318', border: '1px solid #1e293b', borderRadius: '12px', padding: '20px' }}>
          <h3 style={{ color: '#f59e0b', fontWeight: 800, marginBottom: '16px', fontSize: '14px' }}>
            ⚙️ Configuration DVR/NVR Dahua
          </h3>
          <div style={{ fontSize: '12px', color: '#94a3b8', lineHeight: '2' }}>
            <p style={{ marginBottom: '16px' }}>
              Pour recevoir les alertes Dahua dans EduGest, configurer le <strong style={{ color: '#60a5fa' }}>webhook HTTP</strong> sur votre DVR :
            </p>
            {[
              ['1', 'Accéder au DVR', 'Navigateur → http://[IP_DVR] (ex: http://192.168.1.64)'],
              ['2', 'Paramètres réseau', 'Menu → Paramètres → Réseau → Notification HTTP'],
              ['3', 'URL Webhook', `https://app.edugest.dz/api/v1/surveillance/webhook`],
              ['4', 'Méthode', 'POST · Format : JSON'],
              ['5', 'Événements', 'Cocher : Détection mouvement, Alarme, Intrusion, Perte vidéo'],
              ['6', 'Test', 'Cliquer "Tester" — vérifier qu\'une alerte apparaît dans EduGest'],
            ].map(([num, titre, detail]) => (
              <div key={num} style={{ display: 'flex', gap: '12px', marginBottom: '12px', alignItems: 'flex-start' }}>
                <div style={{ background: '#1e3a5f', color: '#60a5fa', width: '24px', height: '24px', borderRadius: '50%', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '11px', fontWeight: 900, flexShrink: 0 }}>{num}</div>
                <div>
                  <div style={{ fontWeight: 700, color: '#e2e8f0', fontSize: '12px' }}>{titre}</div>
                  <div style={{ color: '#64748b', fontSize: '11px', fontFamily: num === '3' ? 'monospace' : 'inherit', background: num === '3' ? '#1e293b' : 'none', padding: num === '3' ? '4px 8px' : '0', borderRadius: '4px', marginTop: '2px' }}>{detail}</div>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Modal ajouter caméra */}
      {showAddCamera && (
        <div style={{ position: 'fixed', inset: 0, background: 'rgba(0,0,0,.7)', display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 1000 }}
          onClick={() => setShowAddCamera(false)}>
          <div style={{ background: '#111318', border: '1px solid #1e293b', borderRadius: '16px', padding: '24px', width: '500px', maxWidth: '90%' }}
            onClick={e => e.stopPropagation()}>
            <h3 style={{ color: '#fff', fontWeight: 800, marginBottom: '16px' }}>📷 Ajouter une caméra Dahua</h3>

            {[
              { label: 'Nom de la caméra *', key: 'nom', placeholder: 'Entrée principale' },
              { label: 'Numéro de série DVR *', key: 'serial_no', placeholder: 'DAH2026XXXXXX' },
              { label: 'IP locale du DVR', key: 'ip_locale', placeholder: '192.168.1.64' },
              { label: 'Localisation', key: 'localisation', placeholder: 'Bâtiment A - RDC' },
            ].map(({ label, key, placeholder }) => (
              <div key={key} style={{ marginBottom: '10px' }}>
                <label style={{ fontSize: '10px', color: '#64748b', display: 'block', marginBottom: '4px' }}>{label}</label>
                <input value={newCamera[key]} onChange={e => setNewCamera(c => ({ ...c, [key]: e.target.value }))}
                  placeholder={placeholder}
                  style={{ width: '100%', background: '#1e293b', border: '1px solid #334155', borderRadius: '8px', color: '#e2e8f0', padding: '9px 12px', fontSize: '12px' }} />
              </div>
            ))}

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '10px', marginBottom: '10px' }}>
              <div>
                <label style={{ fontSize: '10px', color: '#64748b', display: 'block', marginBottom: '4px' }}>Type</label>
                <select value={newCamera.type} onChange={e => setNewCamera(c => ({ ...c, type: e.target.value }))}
                  style={{ width: '100%', background: '#1e293b', border: '1px solid #334155', borderRadius: '8px', color: '#e2e8f0', padding: '9px 12px', fontSize: '12px' }}>
                  {['entree', 'couloir', 'classe', 'parking', 'cantine', 'bus', 'autre'].map(t => (
                    <option key={t} value={t}>{t}</option>
                  ))}
                </select>
              </div>
              <div>
                <label style={{ fontSize: '10px', color: '#64748b', display: 'block', marginBottom: '4px' }}>Horaires normaux</label>
                <div style={{ display: 'flex', gap: '4px' }}>
                  <input type="time" value={newCamera.heure_ouverture} onChange={e => setNewCamera(c => ({ ...c, heure_ouverture: e.target.value }))}
                    style={{ flex: 1, background: '#1e293b', border: '1px solid #334155', borderRadius: '6px', color: '#e2e8f0', padding: '8px', fontSize: '11px' }} />
                  <input type="time" value={newCamera.heure_fermeture} onChange={e => setNewCamera(c => ({ ...c, heure_fermeture: e.target.value }))}
                    style={{ flex: 1, background: '#1e293b', border: '1px solid #334155', borderRadius: '6px', color: '#e2e8f0', padding: '8px', fontSize: '11px' }} />
                </div>
              </div>
            </div>

            <div style={{ display: 'flex', gap: '10px', marginTop: '16px' }}>
              <button onClick={() => setShowAddCamera(false)}
                style={{ flex: 1, background: '#1e293b', color: '#94a3b8', border: 'none', borderRadius: '8px', padding: '10px', cursor: 'pointer', fontWeight: 700 }}>
                Annuler
              </button>
              <button onClick={ajouterCamera} disabled={saving || !newCamera.nom || !newCamera.serial_no}
                style={{ flex: 2, background: 'linear-gradient(135deg,#3b82f6,#1d4ed8)', color: '#fff', border: 'none', borderRadius: '8px', padding: '10px', cursor: 'pointer', fontWeight: 700 }}>
                {saving ? 'Enregistrement...' : '✅ Enregistrer'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Modal instructions webhook après ajout caméra */}
      {webhookInfo && (
        <div style={{ position: 'fixed', inset: 0, background: 'rgba(0,0,0,.8)', display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 1001 }}>
          <div style={{ background: '#0d2515', border: '1px solid #16a34a', borderRadius: '16px', padding: '24px', width: '500px', maxWidth: '90%' }}>
            <h3 style={{ color: '#4ade80', fontWeight: 800, marginBottom: '16px' }}>✅ Caméra enregistrée !</h3>
            <p style={{ color: '#94a3b8', fontSize: '12px', marginBottom: '16px' }}>
              Configurez maintenant le webhook sur votre DVR Dahua :
            </p>
            {webhookInfo.instructions && Object.entries(webhookInfo.instructions).map(([k, v]) => (
              <div key={k} style={{ marginBottom: '8px', fontSize: '11px' }}>
                <span style={{ color: '#4ade80', fontWeight: 700 }}>{k.replace('_', ' ').toUpperCase()} : </span>
                <span style={{ color: '#94a3b8', fontFamily: v.includes('http') ? 'monospace' : 'inherit', background: v.includes('http') ? '#1e293b' : 'none', padding: v.includes('http') ? '2px 6px' : '0', borderRadius: '4px' }}>{v}</span>
              </div>
            ))}
            <button onClick={() => setWebhookInfo(null)} style={{
              width: '100%', background: '#14532d', color: '#4ade80', border: 'none',
              borderRadius: '8px', padding: '10px', marginTop: '16px', cursor: 'pointer', fontWeight: 700,
            }}>Fermer</button>
          </div>
        </div>
      )}
    </div>
  );
}
```

---

## ÉTAPE 8 — Mettre à jour App.jsx et Sidebar.jsx

**Modifier :** `edugestdz/frontend/src/App.jsx`

```jsx
import SurveillancePage from './pages/SurveillancePage';
// Dans les routes :
<Route path="/surveillance" element={<SurveillancePage />} />
```

**Modifier :** `edugestdz/frontend/src/components/Sidebar.jsx`

```jsx
{ path: '/surveillance', icon: '🔒', label: 'Surveillance', role: 'admin' },
```

---

## ÉTAPE 9 — Tests

**Créer :** `edugestdz/backend/tests/Feature/Controllers/SurveillanceControllerTest.php`

```php
<?php

namespace Tests\Feature\Controllers;

use App\Models\User;
use App\Models\CameraConfig;
use App\Models\AlerteSurveillance;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class SurveillanceControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    // ── Webhook public ─────────────────────────────────────────────────

    public function test_webhook_dahua_retourne_200_meme_sans_payload(): void
    {
        // Dahua attend TOUJOURS un 200 — sinon il réessaie en boucle
        $this->postJson('/api/v1/surveillance/webhook', [])
            ->assertStatus(200);
    }

    public function test_webhook_dahua_payload_video_motion(): void
    {
        // Créer une caméra configurée
        $camera = CameraConfig::create([
            'tenant_id'   => Str::uuid(),
            'nom'         => 'Entrée principale',
            'serial_no'   => 'DAH2026TEST001',
            'type'        => 'entree',
            'actif'       => true,
        ]);

        $payload = [
            'SerialNo'  => 'DAH2026TEST001',
            'IpAddress' => '192.168.1.64',
            'ChannelID' => 1,
            'Events'    => [['Code' => 'VideoMotion', 'Action' => 'Start', 'Index' => 0]],
            'LocaleTime'=> now()->format('Y-m-d H:i:s'),
        ];

        $this->postJson('/api/v1/surveillance/webhook', $payload)
            ->assertStatus(200)
            ->assertJsonPath('received', true)
            ->assertJsonPath('processed', true);

        // Vérifier que l'alerte est créée en BDD
        $this->assertDatabaseHas('alertes_surveillance', [
            'serial_no'   => 'DAH2026TEST001',
            'type_alerte' => 'VideoMotion',
        ]);
    }

    public function test_webhook_serial_inconnu_retourne_200_non_traite(): void
    {
        $this->postJson('/api/v1/surveillance/webhook', [
            'SerialNo'  => 'SERIAL_INCONNU_XXXXX',
            'IpAddress' => '10.0.0.1',
            'Events'    => [['Code' => 'VideoMotion', 'Action' => 'Start']],
        ])
            ->assertStatus(200)
            ->assertJsonPath('processed', false);
    }

    // ── Endpoints authentifiés ─────────────────────────────────────────

    public function test_lister_alertes_authentifie(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/surveillance/alertes')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['alertes', 'stats']]);
    }

    public function test_lister_cameras_authentifie(): void
    {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/v1/surveillance/cameras')
            ->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_enregistrer_camera(): void
    {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/surveillance/cameras', [
                'nom'          => 'Caméra Test',
                'serial_no'    => 'DAH2026NEWCAM',
                'type'         => 'entree',
                'ip_locale'    => '192.168.1.100',
                'localisation' => 'Bâtiment A',
            ])
            ->assertStatus(201)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['data' => ['camera', 'webhook_url', 'instructions']]);
    }

    public function test_serial_duplique_echoue(): void
    {
        CameraConfig::create([
            'tenant_id' => Str::uuid(), 'nom' => 'Existante',
            'serial_no' => 'DAH2026DUPLIC', 'type' => 'entree', 'actif' => true,
        ]);

        $this->actingAs($this->admin, 'api')
            ->postJson('/api/v1/surveillance/cameras', [
                'nom'       => 'Autre',
                'serial_no' => 'DAH2026DUPLIC', // dupliqué
                'type'      => 'couloir',
            ])
            ->assertStatus(422);
    }

    public function test_traiter_alerte(): void
    {
        $camera = CameraConfig::create([
            'tenant_id' => config('tenant.current_id', Str::uuid()),
            'nom' => 'Test', 'serial_no' => 'S1', 'type' => 'entree', 'actif' => true,
        ]);
        $alerte = AlerteSurveillance::create([
            'tenant_id'   => $camera->tenant_id,
            'camera_id'   => $camera->id,
            'serial_no'   => 'S1',
            'type_alerte' => 'VideoMotion',
            'niveau'      => 'warning',
            'survenu_le'  => now(),
        ]);

        $this->actingAs($this->admin, 'api')
            ->postJson("/api/v1/surveillance/alertes/{$alerte->id}/traiter", [
                'note_admin' => 'Fausse alarme',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.traite', true);
    }

    public function test_webhook_sans_auth_accessible(): void
    {
        // Le webhook DOIT être accessible sans JWT
        $response = $this->postJson('/api/v1/surveillance/webhook', ['test' => true]);
        $this->assertNotEquals(401, $response->status());
    }

    public function test_alertes_sans_auth_refuse(): void
    {
        $this->getJson('/api/v1/surveillance/alertes')->assertStatus(401);
    }
}
```

---

## ÉTAPE 10 — Lancer les tests et committer

```bash
cd edugestdz/backend

# Migrer
php artisan migrate

# Vérifier l'autoload
composer dump-autoload -o

# Tests
php artisan test --parallel
# → 0 régression + 9 nouveaux tests verts

# Commit
git add .
git commit -m "feat: Intégration télésurveillance Dahua — Webhook alertes + cameras_config + SurveillancePage React + 9 tests"
git push origin develop
# → PR develop → main
```

---

## CE QUE TU DIS À DEEPSEEK

```
Repo : https://github.com/Allintelligence2024/edugest-dz.git
Branche : develop
git checkout develop && git pull origin main

Fichier : MISSION_SURVEILLANCE_DAHUA.md — 10 étapes dans l'ordre.

RÈGLES CRITIQUES :
1. PostgreSQL uniquement — jamais SQLite.
2. 0 régression — les tests existants restent verts.
3. La route /api/v1/surveillance/webhook est PUBLIQUE — PAS de middleware
   auth:api dessus. Dahua ne s'authentifie pas en JWT.
4. DahuaWebhookService réutilise SmsService et FirebaseService existants.
   Si FirebaseService n'existe pas encore → créer un stub qui retourne false.
5. Tester d'abord que le webhook retourne 200 même avec payload vide —
   c'est critique (Dahua réessaie en boucle si réponse != 200).

php artisan migrate
php artisan test --parallel → verts → git push → PR develop → main.
```
