<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\JwtBlacklistService;
use App\Services\SecurityMonitorService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class BreachResponseController extends Controller
{
    public function __construct(
        private JwtBlacklistService    $jwtBlacklist,
        private SecurityMonitorService $monitor
    ) {}

    public function verrouillageUrgence(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'raison'         => 'required|string|max:500',
            'confirmer_avec' => 'required|string',
        ]);

        if ($validated['confirmer_avec'] !== 'VERROUILLAGE_URGENCE_CONFIRME') {
            return response()->json([
                'success' => false,
                'message' => 'Confirmation invalide. Tapez exactement : VERROUILLAGE_URGENCE_CONFIRME',
            ], 422);
        }

        $timestamp = now()->timestamp;
        Cache::put('global_tokens_invalidated_at', $timestamp, now()->addDays(30));

        $this->monitor->alerter(
            'emergency_lockdown',
            'emergency',
            "🔴 VERROUILLAGE D'URGENCE ACTIVÉ par " . auth('api')->user()->email,
            ['raison' => $validated['raison'], 'timestamp' => $timestamp]
        );

        Log::emergency('EMERGENCY LOCKDOWN ACTIVATED', [
            'admin'     => auth('api')->id(),
            'raison'    => $validated['raison'],
            'timestamp' => $timestamp,
        ]);

        return response()->json([
            'success'  => true,
            'message'  => '🔴 Verrouillage d\'urgence activé. Tous les tokens sont invalides.',
            'raison'   => $validated['raison'],
            'timestamp'=> $timestamp,
            'action'   => 'Tous les utilisateurs devront se reconnecter.',
        ]);
    }

    public function declarerIncident(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type_incident'          => 'required|in:data_leak,unauthorized_access,ransomware,insider_threat,other',
            'severite'               => 'required|in:low,medium,high,critical',
            'description'            => 'required|string|max:2000',
            'donnees_affectees'      => 'array',
            'nb_personnes_affectees' => 'required|integer|min:0',
            'detecte_le'             => 'required|date',
        ]);

        $incident = DB::table('breach_declarations')->insert(array_merge($validated, [
            'id'                => \Illuminate\Support\Str::uuid(),
            'tenant_id'         => $request->tenant_id ?? null,
            'donnees_affectees' => json_encode($validated['donnees_affectees'] ?? []),
            'statut'            => 'ouvert',
            'declare_par'       => auth('api')->id(),
            'created_at'        => now(),
            'updated_at'        => now(),
        ]));

        $this->monitor->alerter(
            'breach_declared',
            $validated['severite'],
            "📋 Incident déclaré ({$validated['type_incident']}) — {$validated['nb_personnes_affectees']} personnes affectées",
            $validated
        );

        $delai72h = now()->addHours(72)->format('d/m/Y H:i');

        return response()->json([
            'success'      => true,
            'message'      => 'Incident enregistré.',
            'alerte_legal' => "⚠️ LOI 18-07 : Vous devez notifier l'ANPDP avant le {$delai72h} (délai légal 72h).",
            'contact_anpdp'=> 'www.anpdp.dz',
        ], 201);
    }

    public function indexIncidents(): JsonResponse
    {
        $incidents = DB::table('breach_declarations')
            ->orderByDesc('detecte_le')
            ->get();

        $nonNotifiesAnpdp = $incidents->whereNull('notifie_anpdp_le')
            ->where('detecte_le', '<', now()->subHours(72)->toDateTimeString())
            ->count();

        return response()->json([
            'success' => true,
            'data'    => [
                'incidents'          => $incidents,
                'en_retard_anpdp'    => $nonNotifiesAnpdp,
                'alerte'             => $nonNotifiesAnpdp > 0
                    ? "⚠️ {$nonNotifiesAnpdp} incident(s) non notifiés à l'ANPDP — délai légal dépassé"
                    : null,
            ],
        ]);
    }

    public function leverVerrouillage(): JsonResponse
    {
        Cache::forget('global_tokens_invalidated_at');

        Log::info('Emergency lockdown lifted by ' . auth('api')->id());

        return response()->json([
            'success' => true,
            'message' => '✅ Verrouillage levé. Les nouveaux tokens seront valides.',
        ]);
    }
}
