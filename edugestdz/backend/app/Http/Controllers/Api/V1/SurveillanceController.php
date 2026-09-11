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

    public function recevoir(Request $request): JsonResponse
    {
        $payload = $request->all();

        if (empty($payload) && $request->getContent()) {
            $content = $request->getContent();
            if (str_starts_with(ltrim($content), '<')) {
                try {
                    $xml     = simplexml_load_string($content);
                    $payload = json_decode(json_encode($xml), true);
                } catch (\Throwable) {
                    Log::warning('Dahua webhook : XML invalide');
                }
            }
        }

        if (empty($payload)) {
            Log::info('Dahua webhook : payload vide, retour 200');
            return response()->json(['received' => false, 'reason' => 'empty_payload'], 200);
        }

        $alerte = $this->service->traiter($payload, $request->ip());

        if (!$alerte) {
            return response()->json(['received' => true, 'processed' => false], 200);
        }

        return response()->json([
            'received'  => true,
            'processed' => true,
            'alerte_id' => $alerte->id,
            'niveau'    => $alerte->niveau,
        ], 200);
    }

    public function indexAlertes(Request $request): JsonResponse
    {
        $alertes = AlerteSurveillance::with('camera:id,nom,type,localisation')
            ->when($request->filled('traite'),  fn($q) => $q->where('traite', filter_var($request->traite, FILTER_VALIDATE_BOOLEAN)))
            ->when($request->filled('niveau'),  fn($q) => $q->where('niveau', $request->niveau))
            ->when($request->filled('camera_id'), fn($q) => $q->where('camera_id', $request->camera_id))
            ->orderByDesc('survenu_le')
            ->paginate($request->per_page ?? 20);

        $stats = [
            'non_traitees'   => AlerteSurveillance::nonTraitees()->count(),
            'critiques_24h'  => AlerteSurveillance::critiques()
                ->where('survenu_le', '>=', now()->subHours(24))
                ->count(),
            'total_24h'      => AlerteSurveillance::where('survenu_le', '>=', now()->subHours(24))->count(),
            'cameras_actives'=> CameraConfig::actif()->count(),
        ];

        return response()->json([
            'success' => true,
            'data'    => ['alertes' => $alertes, 'stats' => $stats],
            'message' => 'Alertes de surveillance',
        ]);
    }

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

        $secret = bin2hex(random_bytes(16));

        $camera = CameraConfig::create([
            ...$validated,
            'tenant_id'      => config('tenant.current_id'),
            'webhook_secret' => $secret,
            'actif'          => true,
        ]);

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
