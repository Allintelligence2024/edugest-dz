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

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cours_id'       => 'required|uuid',
            'titre'          => 'required|string|max:300',
            'description'    => 'nullable|string|max:2000',
            'date_remise'    => 'required|date|after:today',
            'poids_notation' => 'nullable|integer|between:0,100',
        ]);

        $user     = auth('api')->user();
        $tenantId = config('tenant.current_id');

        $cours = DB::table('cours')->where('id', $validated['cours_id'])->first();
        if (!$cours || $cours->tenant_id !== $tenantId) {
            return response()->json(['success' => false, 'message' => 'Cours non trouv\u00e9.'], 404);
        }

        $devoirId = (string) Str::uuid();
        DB::table('devoirs')->insert(array_merge($validated, [
            'id'                 => $devoirId,
            'tenant_id'          => $tenantId,
            'enseignant_user_id' => $user->id,
            'groupe_id'          => $cours->groupe_id ?? null,
            'poids_notation'     => $validated['poids_notation'] ?? 0,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]));

        $dateRemise = \Carbon\Carbon::parse($validated['date_remise'])
            ->locale('fr')
            ->isoFormat('D MMMM YYYY');

        $eleveUsers = DB::table('inscriptions as i')
            ->join('eleves as e', 'i.eleve_id', '=', 'e.id')
            ->where('i.groupe_id', $cours->groupe_id)
            ->where('i.statut', 'valid\u00e9e')
            ->whereNotNull('e.user_id')
            ->pluck('e.user_id');

        foreach ($eleveUsers as $eleveUserId) {
            $this->notif->creer(
                userId:   $eleveUserId,
                type:     'devoir_publie',
                titre:    "Nouveau devoir \u2014 {$validated['titre']}",
                corps:    "\u00c0 remettre le {$dateRemise}" .
                          ($validated['description'] ? " \u00b7 " . mb_substr($validated['description'], 0, 100) : ''),
                meta:     ['devoir_id' => $devoirId, 'date_remise' => $validated['date_remise'], 'action_url' => '/devoirs'],
                tenantId: $tenantId,
            );
        }

        DB::table('devoirs')->where('id', $devoirId)->update(['eleves_notifies' => true]);

        return response()->json([
            'success' => true,
            'message' => "Devoir publi\u00e9. {$eleveUsers->count()} \u00e9l\u00e8ve(s) notifi\u00e9(s).",
            'data'    => ['id' => $devoirId],
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $user     = auth('api')->user();
        $tenantId = config('tenant.current_id');

        if ($user->role?->nom === 'enseignant') {
            $devoirs = DB::table('devoirs')
                ->where('tenant_id', $tenantId)
                ->where('enseignant_user_id', $user->id)
                ->orderByDesc('date_remise')
                ->get();
            return response()->json(['success' => true, 'data' => $devoirs]);
        }

        $eleve = DB::table('eleves')->where('user_id', $user->id)->first();
        if (!$eleve) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $groupeId = DB::table('inscriptions')
            ->where('eleve_id', $eleve->id)
            ->where('statut', 'valid\u00e9e')
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
