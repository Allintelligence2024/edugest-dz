<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cours;
use App\Services\PlanningService;
use Illuminate\Http\{Request, JsonResponse};
use Carbon\Carbon;

class PlanningController extends Controller
{
    public function __construct(private PlanningService $service) {}

    public function index(Request $request): JsonResponse
    {
        $debut = $request->date_debut ?? Carbon::now()->startOfWeek(Carbon::SUNDAY)->toDateString();
        $fin   = $request->date_fin   ?? Carbon::now()->endOfWeek(Carbon::SATURDAY)->toDateString();

        $planning = $this->service->getPlanningHebdomadaire($debut, $fin, [
            'enseignant_id' => $request->enseignant_id,
            'groupe_id'     => $request->groupe_id,
        ]);

        return response()->json([
            'success'    => true,
            'data'       => $planning,
            'periode'    => ['debut' => $debut, 'fin' => $fin],
        ]);
    }

    public function conflits(Request $request): JsonResponse
    {
        $request->validate([
            'enseignant_id' => 'required|uuid',
            'jour_semaine'  => 'required|integer|between:0,6',
            'heure_debut'   => 'required|date_format:H:i',
            'heure_fin'     => 'required|date_format:H:i',
            'salle_id'      => 'nullable|uuid',
            'exclude_id'    => 'nullable|uuid',
        ]);

        $conflits = $this->service->detecterConflits($request->all());

        return response()->json([
            'success'      => true,
            'has_conflits' => !empty($conflits),
            'conflits'     => $conflits,
        ]);
    }

    public function generer(Request $request): JsonResponse
    {
        $request->validate(['cours_id' => 'required|uuid|exists:cours,id']);
        $cours = Cours::findOrFail($request->cours_id);
        $nb = $this->service->genererSeances($cours);
        return response()->json([
            'success' => true,
            'message' => "{$nb} séances générées",
        ]);
    }

    public function export(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'message' => 'Export PDF à implémenter']);
    }
}
