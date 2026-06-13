<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\{Cours, Seance};
use App\Services\PlanningService;
use App\Http\Requests\StoreCoursRequest;
use Illuminate\Http\{Request, JsonResponse};

class CoursController extends Controller
{
    public function __construct(private PlanningService $planning) {}

    public function index(Request $request): JsonResponse
    {
        $cours = Cours::with([
                'enseignant', 'groupe.matiere', 'salle'
            ])
            ->when($request->enseignant_id, fn($q) =>
                $q->where('enseignant_id', $request->enseignant_id)
            )
            ->when($request->groupe_id, fn($q) =>
                $q->where('groupe_id', $request->groupe_id)
            )
            ->when($request->statut, fn($q) =>
                $q->where('statut', $request->statut)
            )
            ->orderBy('jour_semaine')
            ->orderBy('heure_debut')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'data'    => $cours->map(fn($c) => $this->formatCours($c)),
            'meta'    => ['total' => $cours->total()],
        ]);
    }

    public function store(StoreCoursRequest $request): JsonResponse
    {
        $conflits = $this->planning->detecterConflits($request->validated());

        if (!empty($conflits) && !$request->boolean('forcer')) {
            return response()->json([
                'success'  => false,
                'error'    => [
                    'code'    => 'PLANNING_CONFLICT',
                    'message' => 'Conflits détectés dans le planning',
                ],
                'conflits' => $conflits,
            ], 409);
        }

        $cours = Cours::create($request->validated());
        $nbSeances = $this->planning->genererSeances($cours);

        return response()->json([
            'success' => true,
            'message' => "Cours créé avec {$nbSeances} séance(s) planifiée(s)",
            'data'    => $this->formatCours($cours->load('enseignant','groupe.matiere','salle')),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $cours = Cours::with([
            'enseignant', 'groupe.matiere', 'salle',
            'seances' => fn($q) => $q->orderBy('date_seance')->limit(10)
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $this->formatCours($cours),
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $cours = Cours::findOrFail($id);

        $validated = $request->validate([
            'heure_debut'  => 'sometimes|date_format:H:i',
            'heure_fin'    => 'sometimes|date_format:H:i|after:heure_debut',
            'salle_id'     => 'nullable|exists:salles,id',
            'date_fin'     => 'nullable|date',
            'tarif_seance' => 'nullable|numeric|min:0',
            'statut'       => 'sometimes|in:actif,suspendu,terminé',
        ]);

        $cours->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Cours mis à jour',
            'data'    => $this->formatCours($cours->fresh(['enseignant','groupe.matiere','salle'])),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $cours = Cours::findOrFail($id);
        $cours->seances()
              ->where('date_seance', '>=', today())
              ->where('statut', 'planifiée')
              ->update(['statut' => 'annulée']);
        $cours->update(['statut' => 'terminé']);

        return response()->json([
            'success' => true,
            'message' => 'Cours archivé avec succès',
        ]);
    }

    private function formatCours(Cours $c): array
    {
        $jours = ['Dim','Lun','Mar','Mer','Jeu','Ven','Sam'];
        return [
            'id'           => $c->id,
            'jour'         => $jours[$c->jour_semaine] ?? '?',
            'jour_num'     => $c->jour_semaine,
            'heure_debut'  => $c->heure_debut,
            'heure_fin'    => $c->heure_fin,
            'recurrence'   => $c->recurrence,
            'date_debut'   => $c->date_debut,
            'date_fin'     => $c->date_fin,
            'tarif_seance' => $c->tarif_seance,
            'statut'       => $c->statut,
            'enseignant'   => $c->relationLoaded('enseignant') ? [
                'id'        => $c->enseignant->id,
                'nom'       => $c->enseignant->nom,
                'prenom'    => $c->enseignant->prenom,
                'photo_url' => $c->enseignant->photo_url,
            ] : null,
            'groupe'  => $c->relationLoaded('groupe') ? [
                'id'              => $c->groupe->id,
                'nom'             => $c->groupe->nom,
                'niveau_scolaire' => $c->groupe->niveau_scolaire,
                'matiere'         => $c->groupe->matiere?->nom_fr,
                'couleur'         => $c->groupe->matiere?->couleur,
            ] : null,
            'salle' => $c->relationLoaded('salle') ? [
                'id'  => $c->salle?->id,
                'nom' => $c->salle?->nom,
            ] : null,
            'seances' => $c->relationLoaded('seances')
                ? $c->seances->map(fn($s) => [
                    'id'         => $s->id,
                    'date'       => $s->date_seance,
                    'statut'     => $s->statut,
                ])->toArray()
                : [],
        ];
    }
}
