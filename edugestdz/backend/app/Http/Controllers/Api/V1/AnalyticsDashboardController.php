<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Cache};

class AnalyticsDashboardController extends Controller
{
    private const CACHE_TTL = 900;

    public function dashboard(): JsonResponse
    {
        $tenantId = config('tenant.current_id');
        $cacheKey = "analytics_dashboard:{$tenantId}:" . now()->format('Y-m-d-H');

        $data = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($tenantId) {
            $moisCourant = now()->month;
            $anneeCourante = now()->year;
            $moisPrecedent = now()->subMonth()->month;
            $anneePrecedente = now()->subMonth()->year;

            $totalEleves    = DB::table('eleves')
                ->where('tenant_id', $tenantId)->where('statut', 'actif')->count();
            $elevesMoisPasse = DB::table('eleves')
                ->where('tenant_id', $tenantId)->where('statut', 'actif')
                ->whereMonth('created_at', '<', $moisCourant)->count();

            $caMois = DB::table('paiements')
                ->where('tenant_id', $tenantId)->where('statut', 'confirmé')
                ->whereMonth('date_paiement', $moisCourant)
                ->whereYear('date_paiement', $anneeCourante)
                ->sum('montant');

            $caMoisPrecedent = DB::table('paiements')
                ->where('tenant_id', $tenantId)->where('statut', 'confirmé')
                ->whereMonth('date_paiement', $moisPrecedent)
                ->whereYear('date_paiement', $anneePrecedente)
                ->sum('montant');

            $impayes = DB::table('factures')
                ->where('tenant_id', $tenantId)
                ->whereIn('statut', ['émise', 'en_retard', 'partiellement_payée'])
                ->where('date_echeance', '<', now()->toDateString());

            $montantImpayes = $impayes->sum('total_ttc');
            $nbImpayes      = $impayes->count();

            $impayesCritiques = DB::table('factures')
                ->where('tenant_id', $tenantId)
                ->whereIn('statut', ['émise', 'en_retard'])
                ->where('date_echeance', '<', now()->subDays(30)->toDateString())
                ->count();

            $facturesEmises = DB::table('factures')
                ->where('tenant_id', $tenantId)
                ->whereMonth('date_emission', $moisCourant)
                ->whereYear('date_emission', $anneeCourante)
                ->sum('total_ttc');

            $tauxRecouvrement = $facturesEmises > 0
                ? round(($caMois / $facturesEmises) * 100, 1)
                : 0;

            $seancesAujourdHui = DB::table('seances')
                ->where('tenant_id', $tenantId)
                ->where('date_seance', now()->toDateString())
                ->count();

            $absencesAujourdHui = DB::table('presences')
                ->join('seances', 'presences.seance_id', '=', 'seances.id')
                ->where('seances.tenant_id', $tenantId)
                ->where('seances.date_seance', now()->toDateString())
                ->where('presences.statut', 'absent')
                ->count();

            $caSixMois = [];
            for ($i = 5; $i >= 0; $i--) {
                $date = now()->subMonths($i);
                $ca   = DB::table('paiements')
                    ->where('tenant_id', $tenantId)
                    ->where('statut', 'confirmé')
                    ->whereMonth('date_paiement', $date->month)
                    ->whereYear('date_paiement', $date->year)
                    ->sum('montant');

                $caSixMois[] = [
                    'mois'   => $date->locale('fr')->isoFormat('MMM YY'),
                    'valeur' => (float) $ca,
                    'label'  => $date->format('m/Y'),
                ];
            }

            $topMatieres = DB::table('notes as n')
                ->join('evaluations as e', 'n.evaluation_id', '=', 'e.id')
                ->join('groupes as g', 'e.groupe_id', '=', 'g.id')
                ->join('matieres as m', 'g.matiere_id', '=', 'm.id')
                ->where('e.tenant_id', $tenantId)
                ->select('m.nom_fr as matiere', DB::raw('ROUND(AVG(n.note::numeric), 2) as moyenne'))
                ->groupBy('m.nom_fr')
                ->orderByDesc('moyenne')
                ->limit(5)
                ->get();

            $assiduite = [];
            for ($w = 3; $w >= 0; $w--) {
                $debut = now()->subWeeks($w)->startOfWeek();
                $fin   = now()->subWeeks($w)->endOfWeek();
                $totalPresences  = DB::table('presences')
                    ->join('seances', 'presences.seance_id', '=', 'seances.id')
                    ->where('seances.tenant_id', $tenantId)
                    ->whereBetween('seances.date_seance', [$debut->toDateString(), $fin->toDateString()])
                    ->count();
                $totalAbsences = DB::table('presences')
                    ->join('seances', 'presences.seance_id', '=', 'seances.id')
                    ->where('seances.tenant_id', $tenantId)
                    ->whereBetween('seances.date_seance', [$debut->toDateString(), $fin->toDateString()])
                    ->where('presences.statut', 'absent')
                    ->count();

                $tauxPresence = $totalPresences > 0
                    ? round((($totalPresences - $totalAbsences) / $totalPresences) * 100, 1)
                    : 0;

                $assiduite[] = [
                    'semaine'       => 'S' . ($debut->week),
                    'debut'         => $debut->format('d/m'),
                    'taux_presence' => $tauxPresence,
                    'absences'      => $totalAbsences,
                ];
            }

            $alertes = [];

            if ($impayesCritiques > 0) {
                $alertes[] = [
                    'type'     => 'danger',
                    'icone'    => '💰',
                    'message'  => "{$impayesCritiques} facture(s) impayée(s) depuis plus de 30 jours",
                    'action'   => 'Voir les impayés',
                    'route'    => '/finance?filtre=retard',
                    'priorite' => 1,
                ];
            }

            $elevesEws = DB::table('diagnostics_eleves')
                ->where('tenant_id', $tenantId)
                ->where('score_risque', '>=', 70)
                ->where('created_at', '>=', now()->subWeek())
                ->count();

            if ($elevesEws > 0) {
                $alertes[] = [
                    'type'     => 'warning',
                    'icone'    => '🔬',
                    'message'  => "{$elevesEws} élève(s) en situation critique (EWS score ≥ 70)",
                    'action'   => 'Voir le diagnostic',
                    'route'    => '/diagnostic',
                    'priorite' => 2,
                ];
            }

            $evolutionCA = $caMoisPrecedent > 0
                ? round((($caMois - $caMoisPrecedent) / $caMoisPrecedent) * 100, 1)
                : 0;

            $evolutionEleves = $elevesMoisPasse > 0
                ? round((($totalEleves - $elevesMoisPasse) / $elevesMoisPasse) * 100, 1)
                : 0;

            return [
                'kpis' => [
                    'total_eleves'          => $totalEleves,
                    'evolution_eleves'      => $evolutionEleves,
                    'ca_mois'               => (float) $caMois,
                    'ca_mois_precedent'     => (float) $caMoisPrecedent,
                    'evolution_ca_pct'      => $evolutionCA,
                    'impayes_montant'       => (float) $montantImpayes,
                    'impayes_nb'            => $nbImpayes,
                    'impayes_critiques_nb'  => $impayesCritiques,
                    'taux_recouvrement'     => $tauxRecouvrement,
                    'seances_aujourd_hui'   => $seancesAujourdHui,
                    'absences_aujourd_hui'  => $absencesAujourdHui,
                ],
                'graphiques' => [
                    'ca_six_mois'  => $caSixMois,
                    'top_matieres' => $topMatieres,
                    'assiduite'    => $assiduite,
                ],
                'alertes'  => collect($alertes)->sortBy('priorite')->values(),
                'mis_a_jour_le' => now()->toIso8601String(),
                'periode'       => now()->locale('fr')->isoFormat('MMMM YYYY'),
            ];
        });

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function finances(Request $request): JsonResponse
    {
        $tenantId = config('tenant.current_id');
        $mois     = (int) $request->query('mois', now()->month);
        $annee    = (int) $request->query('annee', now()->year);

        $data = Cache::remember(
            "analytics_finances:{$tenantId}:{$annee}-{$mois}",
            self::CACHE_TTL,
            function () use ($tenantId, $mois, $annee) {
                $parMode = DB::table('paiements')
                    ->where('tenant_id', $tenantId)
                    ->where('statut', 'confirmé')
                    ->whereMonth('date_paiement', $mois)
                    ->whereYear('date_paiement', $annee)
                    ->select('mode_paiement', DB::raw('SUM(montant) as total'), DB::raw('COUNT(*) as nb'))
                    ->groupBy('mode_paiement')
                    ->get();

                $evolution = DB::table('paiements')
                    ->where('tenant_id', $tenantId)
                    ->where('statut', 'confirmé')
                    ->whereMonth('date_paiement', $mois)
                    ->whereYear('date_paiement', $annee)
                    ->select(
                        DB::raw('DATE(date_paiement) as jour'),
                        DB::raw('SUM(montant) as total'),
                        DB::raw('COUNT(*) as nb_paiements')
                    )
                    ->groupBy('jour')
                    ->orderBy('jour')
                    ->get();

                $impayesTop = DB::table('factures as f')
                    ->join('eleves as e', 'f.eleve_id', '=', 'e.id')
                    ->where('f.tenant_id', $tenantId)
                    ->whereIn('f.statut', ['émise', 'en_retard'])
                    ->where('f.date_echeance', '<', now()->toDateString())
                    ->select(
                        'f.id', 'f.numero_facture', 'f.total_ttc',
                        'f.date_echeance', 'f.statut',
                        DB::raw("e.nom || ' ' || e.prenom as eleve_nom"),
                        DB::raw("CURRENT_DATE - f.date_echeance::date as jours_retard")
                    )
                    ->orderByDesc('jours_retard')
                    ->limit(10)
                    ->get();

                return [
                    'par_mode_paiement' => $parMode,
                    'evolution_journaliere' => $evolution,
                    'impayes_urgents' => $impayesTop,
                    'periode' => ['mois' => $mois, 'annee' => $annee],
                ];
            }
        );

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function pedagogique(Request $request): JsonResponse
    {
        $tenantId  = config('tenant.current_id');
        $trimestre = (int) $request->query('trimestre', $this->trimestreCourant());

        $data = Cache::remember(
            "analytics_pedagogique:{$tenantId}:t{$trimestre}",
            self::CACHE_TTL,
            function () use ($tenantId, $trimestre) {
                $parGroupe = DB::table('notes as n')
                    ->join('evaluations as e', 'n.evaluation_id', '=', 'e.id')
                    ->join('groupes as g', 'e.groupe_id', '=', 'g.id')
                    ->where('e.tenant_id', $tenantId)
                    ->where('e.trimestre', $trimestre)
                    ->select('g.nom as groupe', DB::raw('ROUND(AVG(n.note::numeric), 2) as moyenne'))
                    ->groupBy('g.nom')
                    ->orderBy('g.nom')
                    ->get();

                $distribution = DB::table('notes as n')
                    ->join('evaluations as e', 'n.evaluation_id', '=', 'e.id')
                    ->where('e.tenant_id', $tenantId)
                    ->where('e.trimestre', $trimestre)
                    ->select(
                        DB::raw("FLOOR(n.note / 5) * 5 as tranche"),
                        DB::raw('COUNT(*) as nb')
                    )
                    ->groupBy('tranche')
                    ->orderBy('tranche')
                    ->get()
                    ->map(fn($row) => [
                        'label'     => $row->tranche . '-' . ($row->tranche + 5),
                        'nb_eleves' => $row->nb,
                    ]);

                $repartition = [
                    'excellents'     => DB::table('bulletins')->where('tenant_id', $tenantId)->where('trimestre', $trimestre)->where('moyenne_generale', '>=', 16)->count(),
                    'bons'           => DB::table('bulletins')->where('tenant_id', $tenantId)->where('trimestre', $trimestre)->whereBetween('moyenne_generale', [13, 16])->count(),
                    'moyens'         => DB::table('bulletins')->where('tenant_id', $tenantId)->where('trimestre', $trimestre)->whereBetween('moyenne_generale', [10, 13])->count(),
                    'en_difficulte'  => DB::table('bulletins')->where('tenant_id', $tenantId)->where('trimestre', $trimestre)->where('moyenne_generale', '<', 10)->count(),
                ];

                return [
                    'moyennes_par_groupe' => $parGroupe,
                    'distribution_notes'  => $distribution,
                    'repartition_eleves'  => $repartition,
                    'trimestre'           => $trimestre,
                ];
            }
        );

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function rapportPdf(Request $request)
    {
        $validated = $request->validate([
            'mois'  => 'nullable|integer|between:1,12',
            'annee' => 'nullable|integer|between:2020,2030',
        ]);

        $mois  = $validated['mois']  ?? now()->month;
        $annee = $validated['annee'] ?? now()->year;

        $dashData = $this->dashboard()->getData(true)['data'];
        $finData  = $this->finances(new Request(['mois' => $mois, 'annee' => $annee]))->getData(true)['data'];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.rapport-mensuel-directeur', [
            'dashboard'     => $dashData,
            'finances'      => $finData,
            'mois'          => $mois,
            'annee'         => $annee,
            'periode'       => \Carbon\Carbon::createFromDate($annee, $mois, 1)->locale('fr')->isoFormat('MMMM YYYY'),
            'genere_le'     => now()->format('d/m/Y à H:i'),
            'tenant_id'     => config('tenant.current_id'),
        ]);

        $filename = "rapport-mensuel-{$annee}-{$mois}-directeur.pdf";

        return $pdf->download($filename);
    }

    private function trimestreCourant(): int
    {
        $mois = now()->month;
        if ($mois <= 4)  return 1;
        if ($mois <= 8)  return 2;
        return 3;
    }
}
