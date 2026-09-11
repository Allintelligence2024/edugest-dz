<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\PaiePersonnel;
use App\Models\PersonnelNonEnseignant;
use App\Services\PaiePersonnelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaiePersonnelController extends BaseApiController
{
    public function __construct(private readonly PaiePersonnelService $service) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mois'     => 'nullable|integer|between:1,12',
            'annee'    => 'nullable|integer|min:2020',
            'statut'   => 'nullable|in:brouillon,valide,paye',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $paginator = PaiePersonnel::with('agent:id,nom,prenom,poste,matricule')
            ->when($validated['mois'] ?? null, fn($q, $m) => $q->where('mois', $m))
            ->when($validated['annee'] ?? null, fn($q, $a) => $q->where('annee', $a))
            ->when($validated['statut'] ?? null, fn($q, $s) => $q->where('statut', $s))
            ->orderByDesc('annee')->orderByDesc('mois')
            ->paginate($validated['per_page'] ?? 20);

        return $this->paginatedResponse($paginator, 'Paies personnel récupérées');
    }

    public function calculer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'agent_id' => 'required|uuid|exists:personnel_non_enseignant,id',
            'mois'     => 'required|integer|between:1,12',
            'annee'    => 'required|integer|min:2020',
        ]);

        $agent  = PersonnelNonEnseignant::findOrFail($validated['agent_id']);
        $calcul = $this->service->calculerPaie($agent, $validated['mois'], $validated['annee']);

        $paie = PaiePersonnel::updateOrCreate(
            ['agent_id' => $agent->id, 'mois' => $validated['mois'], 'annee' => $validated['annee']],
            array_merge($calcul, ['tenant_id' => config('tenant.current_id')])
        );

        return $this->created([
            'paie'  => $paie->load('agent'),
            'detail'=> $calcul,
        ], "Paie calculée : {$agent->nom_complet} — Net : " . number_format($calcul['salaire_net'], 2) . " DA");
    }

    public function calculerTous(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mois'  => 'required|integer|between:1,12',
            'annee' => 'required|integer|min:2020',
        ]);

        $resultats = $this->service->calculerTousMois($validated['mois'], $validated['annee']);

        return $this->success([
            'resultats' => $resultats,
            'total'     => count($resultats),
            'masse_salariale' => collect($resultats)->sum('net'),
        ], count($resultats) . " paie(s) calculée(s)");
    }

    public function valider(string $id): JsonResponse
    {
        $paie = PaiePersonnel::findOrFail($id);
        $paie->update(['statut' => 'valide']);
        return $this->success($paie->fresh('agent'), 'Paie validée');
    }

    public function payer(string $id): JsonResponse
    {
        $paie = PaiePersonnel::findOrFail($id);
        $paie->update(['statut' => 'paye', 'date_paiement' => today()]);
        return $this->success($paie->fresh('agent'), 'Paie marquée comme payée');
    }

    public function pdf(string $id)
    {
        $paie = PaiePersonnel::with('agent')->findOrFail($id);

        if ($paie->fichier_url && \Storage::disk('public')->exists($paie->fichier_url)) {
            return response()->download(storage_path('app/public/' . $paie->fichier_url));
        }

        $path = $this->service->genererPDF($paie);
        return response()->download(storage_path('app/public/' . $path));
    }
}
