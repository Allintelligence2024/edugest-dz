<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\{Evaluation, Note, Eleve};
use App\Services\BulletinService;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\DB;

class EvaluationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $evals = Evaluation::with('groupe.matiere')
            ->when($request->groupe_id,  fn($q) => $q->where('groupe_id',  $request->groupe_id))
            ->when($request->trimestre,  fn($q) => $q->where('trimestre',  $request->trimestre))
            ->when($request->type_eval,  fn($q) => $q->where('type_eval',  $request->type_eval))
            ->orderByDesc('date_evaluation')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'data'    => $evals->items(),
            'meta'    => ['total' => $evals->total()],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'groupe_id'       => 'required|uuid|exists:groupes,id',
            'titre'           => 'required|string|max:200',
            'type_eval'       => 'required|in:devoir_classe,devoir_maison,test_rapide,examen_mensuel,examen_module',
            'date_evaluation' => 'required|date',
            'note_sur'        => 'required|numeric|min:1|max:100',
            'coefficient'     => 'required|numeric|min:0.5|max:5',
            'trimestre'       => 'required|in:T1,T2,T3',
            'description'     => 'nullable|string',
        ]);

        $eval = Evaluation::create([
            ...$validated,
            'tenant_id'   => config('tenant.current_id'),
            'created_by'  => auth('api')->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Évaluation créée',
            'data'    => $eval->load('groupe.matiere'),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $eval = Evaluation::with(['groupe.matiere', 'notes.eleve'])->findOrFail($id);
        return response()->json(['success' => true, 'data' => $eval]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $eval = Evaluation::findOrFail($id);
        $eval->update($request->only(['titre', 'coefficient', 'description', 'date_evaluation']));
        return response()->json(['success' => true, 'data' => $eval->fresh('groupe.matiere')]);
    }

    public function destroy(string $id): JsonResponse
    {
        Evaluation::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Évaluation supprimée']);
    }

    public function saisirNotes(Request $request, string $id): JsonResponse
    {
        $eval = Evaluation::with('groupe')->findOrFail($id);

        $request->validate([
            'notes'              => 'required|array',
            'notes.*.eleve_id'   => 'required|uuid|exists:eleves,id',
            'notes.*.note'       => 'nullable|numeric|min:0|max:' . $eval->note_sur,
            'notes.*.absent'     => 'nullable|boolean',
            'notes.*.commentaire'=> 'nullable|string|max:200',
        ]);

        $sauvegardees = 0;

        DB::transaction(function () use ($request, $eval, &$sauvegardees) {
            foreach ($request->notes as $noteData) {
                $absent = $noteData['absent'] ?? false;

                Note::updateOrCreate(
                    [
                        'evaluation_id' => $eval->id,
                        'eleve_id'      => $noteData['eleve_id'],
                    ],
                    [
                        'tenant_id'   => config('tenant.current_id'),
                        'note'        => $absent ? null : $noteData['note'],
                        'absent'      => $absent,
                        'appreciation'=> !$absent && isset($noteData['note'])
                            ? $this->getAppreciation($noteData['note'], $eval->note_sur)
                            : null,
                        'commentaire' => $noteData['commentaire'] ?? null,
                        'saisie_par'  => auth('api')->id(),
                    ]
                );
                $sauvegardees++;
            }
        });

        return response()->json([
            'success' => true,
            'message' => "{$sauvegardees} note(s) sauvegardée(s)",
            'stats'   => $this->getStatsEval($eval),
        ]);
    }

    public function notes(string $id): JsonResponse
    {
        $eval  = Evaluation::with('groupe.matiere')->findOrFail($id);
        $notes = Note::with('eleve')
            ->where('evaluation_id', $id)
            ->get()
            ->keyBy('eleve_id');

        $eleves = Eleve::whereHas('inscriptions', fn($q) =>
            $q->where('groupe_id', $eval->groupe_id)
              ->where('statut', 'validée')
        )->get();

        $data = $eleves->map(fn($eleve) => [
            'eleve_id'    => $eleve->id,
            'nom_complet' => $eleve->nom_complet,
            'photo_url'   => $eleve->photo_url_full,
            'note'        => $notes[$eleve->id]?->note,
            'absent'      => $notes[$eleve->id]?->absent ?? false,
            'appreciation'=> $notes[$eleve->id]?->appreciation,
            'commentaire' => $notes[$eleve->id]?->commentaire,
            'note_id'     => $notes[$eleve->id]?->id,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $data,
            'eval'    => [
                'id'         => $eval->id,
                'titre'      => $eval->titre,
                'note_sur'   => $eval->note_sur,
                'coefficient'=> $eval->coefficient,
            ],
            'stats' => $this->getStatsEval($eval),
        ]);
    }

    private function getAppreciation(float $note, float $noteSur): string
    {
        $pct = ($note / $noteSur) * 100;
        return match(true) {
            $pct >= 90 => 'excellent',
            $pct >= 80 => 'très_bien',
            $pct >= 70 => 'bien',
            $pct >= 60 => 'assez_bien',
            $pct >= 50 => 'passable',
            default    => 'insuffisant',
        };
    }

    private function getStatsEval(Evaluation $eval): array
    {
        $notes = Note::where('evaluation_id', $eval->id)
            ->whereNotNull('note')->pluck('note');

        if ($notes->isEmpty()) return ['rempli' => false];

        return [
            'rempli'   => true,
            'moyenne'  => round($notes->avg(), 2),
            'max'      => $notes->max(),
            'min'      => $notes->min(),
            'absents'  => Note::where('evaluation_id', $eval->id)->where('absent', true)->count(),
            'nb_notes' => $notes->count(),
        ];
    }
}
