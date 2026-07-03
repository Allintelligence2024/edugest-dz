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

    public function aujourdhui(): JsonResponse
    {
        $today = today()->format('Y-m-d');
        $seances = $this->service->getPlanningHebdomadaire($today, $today);

        return response()->json([
            'success' => true,
            'data'    => [
                'total'   => count($seances),
                'seances' => $seances,
            ],
        ]);
    }

    public function exportICal(Request $request): \Illuminate\Http\Response
    {
        $enseignantId = $request->enseignant_id ?? auth('api')->id();

        $seances = \App\Models\Seance::with(['cours.matiere', 'cours.groupe', 'salle'])
            ->whereHas('cours', fn($q) => $q->where('enseignant_id', $enseignantId))
            ->where('statut', '!=', 'annulée')
            ->where('date_seance', '>=', today()->subMonth()->format('Y-m-d'))
            ->where('date_seance', '<=', today()->addMonths(3)->format('Y-m-d'))
            ->get();

        $ical  = "BEGIN:VCALENDAR\r\n";
        $ical .= "VERSION:2.0\r\n";
        $ical .= "PRODID:-//EduGest DZ//Planning Enseignant//FR\r\n";
        $ical .= "CALSCALE:GREGORIAN\r\n";
        $ical .= "METHOD:PUBLISH\r\n";
        $ical .= "X-WR-CALNAME:Planning EduGest DZ\r\n";
        $ical .= "X-WR-TIMEZONE:Africa/Algiers\r\n";

        foreach ($seances as $seance) {
            $debut  = \Carbon\Carbon::parse($seance->date_seance . ' ' . $seance->heure_debut);
            $fin    = \Carbon\Carbon::parse($seance->date_seance . ' ' . $seance->heure_fin);
            $titre  = $seance->cours?->matiere?->nom_fr ?? 'Cours';
            $groupe = $seance->cours?->groupe?->nom ?? '';
            $salle  = $seance->salle?->nom ?? '';

            $ical .= "BEGIN:VEVENT\r\n";
            $ical .= "UID:" . $seance->id . "@edugest.dz\r\n";
            $ical .= "DTSTART:" . $debut->format('Ymd\THis') . "\r\n";
            $ical .= "DTEND:"   . $fin->format('Ymd\THis')   . "\r\n";
            $ical .= "SUMMARY:" . $titre . ($groupe ? " — {$groupe}" : '') . "\r\n";
            $ical .= "LOCATION:{$salle}\r\n";
            $ical .= "STATUS:" . ($seance->statut === 'terminée' ? 'CONFIRMED' : 'TENTATIVE') . "\r\n";
            $ical .= "DTSTAMP:" . now()->format('Ymd\THis\Z') . "\r\n";
            $ical .= "END:VEVENT\r\n";
        }

        $ical .= "END:VCALENDAR\r\n";

        return response($ical, 200, [
            'Content-Type'        => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="planning-edugest.ics"',
        ]);
    }
}
