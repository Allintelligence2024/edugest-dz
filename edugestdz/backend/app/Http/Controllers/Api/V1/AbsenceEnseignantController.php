<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AbsenceEnseignant;
use App\Services\AbsenceEnseignantService;
use Illuminate\Http\{Request, JsonResponse};

class AbsenceEnseignantController extends Controller
{
    public function __construct(private AbsenceEnseignantService $service) {}

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
            motif:            $validated['motif'] ?? null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Absence signal\u00e9e. Le directeur a \u00e9t\u00e9 notifi\u00e9.',
            'data'    => $absence,
        ], 201);
    }

    public function assigner(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'remplacant_user_id' => 'required|uuid|exists:users,id',
        ]);

        $absence = AbsenceEnseignant::where('tenant_id', config('tenant.current_id'))
            ->withoutGlobalScopes()
            ->findOrFail($id);

        $absence = $this->service->assignerRemplacant($id, $validated['remplacant_user_id']);

        return response()->json([
            'success' => true,
            'message' => 'Rempla\u00e7ant assign\u00e9. \u00c9l\u00e8ves et parents notifi\u00e9s.',
            'data'    => $absence,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $date     = $request->query('date', now()->toDateString());
        $tenantId = config('tenant.current_id');

        $absences = AbsenceEnseignant::with(['enseignant:id,nom,prenom', 'remplacant:id,nom,prenom'])
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('date_absence', $date)
            ->orderBy('signale_le')
            ->get();

        return response()->json(['success' => true, 'data' => $absences]);
    }
}
