<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DiagnosticEleve;
use App\Models\PlanRattrapage;
use App\Models\ConvocationParent;
use App\Services\DiagnosticService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiagnosticController extends Controller
{
    public function __construct(private DiagnosticService $service) {}

    public function dashboard(): JsonResponse
    {
        $data = $this->service->getDashboard(config('tenant.current_id'));
        return response()->json(['success' => true, 'data' => $data]);
    }

    public function indexDiagnostics(Request $request): JsonResponse
    {
        $query = DiagnosticEleve::with([
            'eleve:id,nom,prenom,niveau_scolaire,photo_url',
        ]);

        if ($request->filled('niveau')) {
            $query->where('niveau_global', $request->niveau);
        }
        if ($request->filled('action')) {
            match ($request->action) {
                'rattrapage'  => $query->where('rattrapage_requis', true),
                'convocation' => $query->where('convocation_requise', true),
                'excellence'  => $query->where('mention_excellence', true),
                default       => null,
            };
        }

        $diagnostics = $query->orderByDesc('score_risque')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'data'    => $diagnostics,
            'message' => 'Diagnostics récupérés',
        ]);
    }

    public function showDiagnostic(string $eleveId): JsonResponse
    {
        $diagnostic = DiagnosticEleve::where('eleve_id', $eleveId)
            ->with(['eleve:id,nom,prenom,niveau_scolaire'])
            ->firstOrFail();

        $historique = \App\Models\HistoriqueDiagnostic::where('eleve_id', $eleveId)
            ->orderByDesc('analyse_le')
            ->limit(10)
            ->get();

        $rattrapages = PlanRattrapage::where('eleve_id', $eleveId)
            ->whereIn('statut', ['planifié', 'en_cours'])
            ->get();

        $convocations = ConvocationParent::where('eleve_id', $eleveId)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $recommandations = $this->genererRecommandations($diagnostic);

        return response()->json([
            'success' => true,
            'data'    => [
                'diagnostic'      => $diagnostic,
                'historique'      => $historique,
                'rattrapages'     => $rattrapages,
                'convocations'    => $convocations,
                'recommandations' => $recommandations,
            ],
        ]);
    }

    public function analyserEleve(string $eleveId): JsonResponse
    {
        $diagnostic = $this->service->analyserEleve($eleveId);
        return response()->json([
            'success' => true,
            'data'    => $diagnostic->load('eleve'),
            'message' => 'Analyse effectuée',
        ]);
    }

    public function analyserTous(): JsonResponse
    {
        $resultats = $this->service->analyserTousLesEleves(config('tenant.current_id'));
        return response()->json([
            'success' => true,
            'data'    => $resultats,
            'message' => "Analyse de {$resultats['total']} élèves terminée",
        ]);
    }

    public function creerRattrapage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'eleve_id'      => 'required|uuid|exists:eleves,id',
            'matiere'       => 'required|string|max:100',
            'objectifs'     => 'required|string',
            'programme'     => 'nullable|string',
            'date_debut'    => 'required|date|after_or_equal:today',
            'date_fin'      => 'required|date|after:date_debut',
            'enseignant_id' => 'nullable|uuid|exists:users,id',
        ]);

        $plan = PlanRattrapage::create([
            ...$validated,
            'tenant_id' => config('tenant.current_id'),
            'statut'    => 'planifié',
            'cree_par'  => auth('api')->id(),
        ]);

        DiagnosticEleve::where('eleve_id', $validated['eleve_id'])
            ->update(['rattrapage_requis' => true]);

        return response()->json([
            'success' => true,
            'data'    => $plan->load('eleve', 'enseignant'),
            'message' => 'Plan de rattrapage créé',
        ], 201);
    }

    public function envoyerConvocation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'eleve_id'       => 'required|uuid|exists:eleves,id',
            'motif'          => 'required|in:niveau_critique,absences_excessives,comportement,autre',
            'message'        => 'required|string|max:500',
            'canal'          => 'in:sms,whatsapp,email,courrier',
            'rendez_vous_le' => 'nullable|date|after:now',
        ]);

        $eleve = \App\Models\Eleve::with('parents')->findOrFail($validated['eleve_id']);

        $sent = false;
        if (($validated['canal'] ?? 'sms') === 'sms') {
            $smsService = app(\App\Services\Sms\SmsService::class);
            foreach ($eleve->parents ?? [] as $parent) {
                $tel = $parent->telephone_1 ?? $parent->telephone ?? null;
                if ($tel) {
                    try { $smsService->send($tel, $validated['message']); $sent = true; }
                    catch (\Throwable) {}
                }
            }
        }

        $convocation = ConvocationParent::create([
            ...$validated,
            'tenant_id'  => config('tenant.current_id'),
            'statut'     => 'envoyée',
            'envoyee_le' => now(),
            'cree_par'   => auth('api')->id(),
        ]);

        return response()->json([
            'success' => true,
            'data'    => $convocation,
            'message' => 'Convocation envoyée' . ($sent ? ' par SMS' : ' (SMS non envoyé)'),
        ], 201);
    }

    private function genererRecommandations(DiagnosticEleve $diag): array
    {
        $recs = [];

        if ($diag->niveau_global === 'critique') {
            $recs[] = ['priorite' => 'urgente', 'action' => 'Convoquer les parents immédiatement'];
            $recs[] = ['priorite' => 'urgente', 'action' => 'Mettre en place un plan de rattrapage intensif'];
            $recs[] = ['priorite' => 'haute',   'action' => 'Réunion enseignants + directeur'];
        }
        if ($diag->niveau_global === 'danger') {
            $recs[] = ['priorite' => 'haute',   'action' => 'Programmer des séances de rattrapage'];
            $recs[] = ['priorite' => 'haute',   'action' => "Informer les parents par SMS"];
            $recs[] = ['priorite' => 'normale',  'action' => 'Suivi hebdomadaire des notes'];
        }
        if ($diag->tendance && $diag->tendance <= -3) {
            $recs[] = ['priorite' => 'haute', 'action' => "Chute de {$diag->tendance} points — analyser les causes"];
        }
        if ($diag->nb_absences_mois > 3) {
            $recs[] = ['priorite' => 'haute', 'action' => "Absentéisme ({$diag->nb_absences_mois} absences) — contact parent"];
        }
        foreach ($diag->matieres_en_danger ?? [] as $mat) {
            $recs[] = ['priorite' => 'normale', 'action' => "Rattrapage {$mat['matiere']} (moy: {$mat['moyenne']}/20)"];
        }
        if ($diag->mention_excellence) {
            $recs[] = ['priorite' => 'info', 'action' => "Féliciter l'élève — certificat d'excellence à envisager"];
        }

        return $recs;
    }
}
