<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AbsenceJournaliere;
use App\Models\Wilaya;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AbsencesGeographiquesController extends Controller
{
    /**
     * Nombre d'absences par wilaya (carte chaleur).
     */
    public function parWilaya(Request $request): JsonResponse
    {
        $request->validate([
            'date_debut' => 'nullable|date',
            'date_fin'   => 'nullable|date|after_or_equal:date_debut',
        ]);

        $absences = AbsenceJournaliere::query()
            ->select('wilayas.id as wilaya_id', 'wilayas.code', 'wilayas.nom_fr')
            ->selectRaw('COUNT(*) as nb_absences')
            ->leftJoin('eleves', 'absences_journalieres.eleve_id', '=', 'eleves.id')
            ->leftJoin('wilayas', 'eleves.wilaya_id', '=', 'wilayas.id')
            ->where('absences_journalieres.tenant_id', config('tenant.current_id'))
            ->when($request->filled('date_debut'), fn($q, $v) => $q->where('absences_journalieres.date_absence', '>=', $v))
            ->when($request->filled('date_fin'), fn($q, $v) => $q->where('absences_journalieres.date_absence', '<=', $v))
            ->groupBy('wilayas.id', 'wilayas.code', 'wilayas.nom_fr')
            ->orderByDesc('nb_absences')
            ->get();

        return response()->json(['success' => true, 'data' => $absences]);
    }

    /**
     * Taux d'absentéisme par wilaya (absent / total élèves).
     */
    public function tauxAbsentisme(Request $request): JsonResponse
    {
        $request->validate([
            'date_debut' => 'nullable|date',
            'date_fin'   => 'nullable|date|after_or_equal:date_debut',
        ]);

        $result = DB::select("
            SELECT
                w.id as wilaya_id,
                w.code,
                w.nom_fr,
                COUNT(DISTINCT aj.eleve_id) as nb_absents,
                COUNT(DISTINCT e.id) as nb_eleves,
                CASE WHEN COUNT(DISTINCT e.id) > 0
                    THEN ROUND(COUNT(DISTINCT aj.eleve_id)::numeric / COUNT(DISTINCT e.id) * 100, 1)
                    ELSE 0
                END as taux_pct
            FROM wilayas w
            LEFT JOIN eleves e ON e.wilaya_id = w.id AND e.tenant_id = ?
            LEFT JOIN absences_journalieres aj ON aj.eleve_id = e.id
                AND aj.tenant_id = ?
                AND (?::date IS NULL OR aj.date_absence >= ?::date)
                AND (?::date IS NULL OR aj.date_absence <= ?::date)
            GROUP BY w.id, w.code, w.nom_fr
            ORDER BY taux_pct DESC
        ", [
            config('tenant.current_id'),
            config('tenant.current_id'),
            $request->input('date_debut'),
            $request->input('date_debut'),
            $request->input('date_fin'),
            $request->input('date_fin'),
        ]);

        return response()->json(['success' => true, 'data' => $result]);
    }

    /**
     * Liste des absences d'une wilaya spécifique.
     */
    public function parWilayaDetail(Request $request, string $wilayaId): JsonResponse
    {
        $absences = AbsenceJournaliere::query()
            ->select('absences_journalieres.*')
            ->leftJoin('eleves', 'absences_journalieres.eleve_id', '=', 'eleves.id')
            ->where('eleves.wilaya_id', $wilayaId)
            ->where('absences_journalieres.tenant_id', config('tenant.current_id'))
            ->orderByDesc('absences_journalieres.date_absence')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $absences]);
    }

    /**
     * Résumé global : top wilayas + stats.
     */
    public function resume(Request $request): JsonResponse
    {
        $totalAbsences = AbsenceJournaliere::where('tenant_id', config('tenant.current_id'))->count();
        $totalElevesAvecWilaya = DB::table('eleves')
            ->where('tenant_id', config('tenant.current_id'))
            ->whereNotNull('wilaya_id')
            ->count();
        $wilayasConcernees = AbsenceJournaliere::query()
            ->leftJoin('eleves', 'absences_journalieres.eleve_id', '=', 'eleves.id')
            ->where('absences_journalieres.tenant_id', config('tenant.current_id'))
            ->whereNotNull('eleves.wilaya_id')
            ->distinct('eleves.wilaya_id')
            ->count('eleves.wilaya_id');

        $top5 = AbsenceJournaliere::query()
            ->select('wilayas.nom_fr', DB::raw('COUNT(*) as nb_absences'))
            ->leftJoin('eleves', 'absences_journalieres.eleve_id', '=', 'eleves.id')
            ->leftJoin('wilayas', 'eleves.wilaya_id', '=', 'wilayas.id')
            ->where('absences_journalieres.tenant_id', config('tenant.current_id'))
            ->whereNotNull('wilayas.id')
            ->groupBy('wilayas.nom_fr')
            ->orderByDesc('nb_absences')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_absences'        => $totalAbsences,
                'eleves_avec_wilaya'    => $totalElevesAvecWilaya,
                'wilayas_concernees'    => $wilayasConcernees,
                'top_5_wilayas'         => $top5,
            ],
        ]);
    }
}
