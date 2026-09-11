<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\NotificationInAppService;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FeedbackPedagogiqueController extends Controller
{
    public function __construct(private NotificationInAppService $notif) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enseignant_user_id' => 'required|uuid|exists:users,id',
            'cours_id'           => 'nullable|uuid|exists:cours,id',
            'trimestre'          => 'required|integer|between:1,3',
            'note_qualite'       => 'required|integer|between:1,5',
            'type_feedback'      => 'required|in:pedagogie,rythme,ambiance,relation,ressources,autre',
            'commentaire'        => 'nullable|string|max:500',
        ]);

        $user     = auth('api')->user();
        $tenantId = config('tenant.current_id');

        $eleve = DB::table('eleves')->where('user_id', $user->id)->first();
        if (!$eleve) {
            return response()->json([
                'success' => false,
                'message' => 'Seuls les \u00e9l\u00e8ves peuvent soumettre un feedback.',
            ], 403);
        }

        $existant = DB::table('feedbacks_pedagogiques')
            ->where('eleve_id', $eleve->id)
            ->where('enseignant_user_id', $validated['enseignant_user_id'])
            ->where('trimestre', $validated['trimestre'])
            ->exists();

        if ($existant) {
            return response()->json([
                'success' => false,
                'message' => "Vous avez d\u00e9j\u00e0 soumis un feedback pour cet enseignant ce trimestre.",
            ], 422);
        }

        $feedbackId = (string) Str::uuid();
        DB::table('feedbacks_pedagogiques')->insert(array_merge($validated, [
            'id'         => $feedbackId,
            'tenant_id'  => $tenantId,
            'eleve_id'   => $eleve->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        $this->notif->creerPourRole(
            tenantId: $tenantId,
            role:     'admin',
            type:     'feedback_recu',
            titre:    "Nouveau feedback p\u00e9dagogique",
            corps:    "Un \u00e9l\u00e8ve a soumis un feedback (T{$validated['trimestre']}). Note: {$validated['note_qualite']}/5",
            meta:     ['feedback_id' => $feedbackId, 'action_url' => '/feedbacks'],
        );

        return response()->json([
            'success' => true,
            'message' => 'Feedback soumis. Seul le directeur le verra.',
        ], 201);
    }

    /**
     * Directeur consulte tous les feedbacks. Admin-only.
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        if ($user->role?->nom !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Accès réservé aux directeurs.',
            ], 403);
        }

        $tenantId = config('tenant.current_id');

        $feedbacks = DB::table('feedbacks_pedagogiques as f')
            ->join('eleves as e', 'f.eleve_id', '=', 'e.id')
            ->join('users as u', 'f.enseignant_user_id', '=', 'u.id')
            ->where('f.tenant_id', $tenantId)
            ->select(
                'f.id', 'f.trimestre', 'f.note_qualite', 'f.type_feedback',
                'f.commentaire', 'f.statut', 'f.created_at',
                DB::raw("e.nom || ' ' || e.prenom as eleve_nom"),
                DB::raw("u.nom || ' ' || u.prenom as enseignant_nom")
            )
            ->orderByDesc('f.created_at')
            ->get();

        DB::table('feedbacks_pedagogiques')
            ->where('tenant_id', $tenantId)
            ->where('statut', 'soumis')
            ->update(['statut' => 'lu_directeur']);

        return response()->json(['success' => true, 'data' => $feedbacks]);
    }

    public function resume(string $enseignantId): JsonResponse
    {
        $tenantId = config('tenant.current_id');

        $stats = DB::table('feedbacks_pedagogiques')
            ->where('tenant_id', $tenantId)
            ->where('enseignant_user_id', $enseignantId)
            ->select(
                DB::raw('AVG(note_qualite::numeric) as note_moyenne'),
                DB::raw('COUNT(*) as nb_feedbacks'),
                'type_feedback',
                DB::raw('COUNT(*) as nb_par_type')
            )
            ->groupBy('type_feedback')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'note_moyenne_globale' => round(
                    DB::table('feedbacks_pedagogiques')
                        ->where('tenant_id', $tenantId)
                        ->where('enseignant_user_id', $enseignantId)
                        ->avg('note_qualite') ?? 0, 1
                ),
                'par_type' => $stats,
                'commentaires_anonymes' => DB::table('feedbacks_pedagogiques')
                    ->where('tenant_id', $tenantId)
                    ->where('enseignant_user_id', $enseignantId)
                    ->whereNotNull('commentaire')
                    ->pluck('commentaire')
                    ->map(fn($c) => mb_substr($c, 0, 200)),
            ],
        ]);
    }
}
