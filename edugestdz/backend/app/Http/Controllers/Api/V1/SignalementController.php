<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SignalementComportement;
use App\Models\Eleve;
use App\Models\ParentEleve;
use App\Services\ParentNotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SignalementController extends Controller
{
    public function __construct(
        private ParentNotificationService $notificationService
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'eleve_id'       => 'required|uuid|exists:eleves,id',
            'type'           => 'required|in:perturbation,retard_répété,violence,tricherie,insolence,absentéisme,félicitation,encouragement,autre',
            'gravite'        => 'required|in:info,normale,grave,très_grave',
            'description'    => 'required|string|max:1000',
            'lieu'           => 'nullable|string|max:100',
            'date_incident'  => 'required|date|before_or_equal:today',
            'heure_incident' => 'nullable|date_format:H:i',
        ]);

        $auteur = auth('api')->user();

        $signalement = SignalementComportement::create([
            ...$validated,
            'tenant_id'      => config('tenant.current_id'),
            'signale_par'    => $auteur->id,
            'role_auteur'    => $auteur->role?->nom ?? 'inconnu',
            'notifie_parent' => false,
        ]);

        $this->notificationService->comportementSignale(
            $validated['eleve_id'],
            $validated['type'],
            $validated['gravite'],
            $validated['description'],
            "{$auteur->prenom} {$auteur->nom}"
        );

        $signalement->update(['notifie_parent' => true]);

        $signalement->load('eleve:id,nom,prenom', 'auteur:id,nom,prenom,role_id');
        $signalement->load('auteur.role');

        return response()->json([
            'success' => true,
            'data'    => $signalement,
            'message' => 'Signalement enregistré et parent notifié',
        ], 201);
    }

    public function byEleve(string $eleveId): JsonResponse
    {
        $signalements = SignalementComportement::where('eleve_id', $eleveId)
            ->with('auteur:id,nom,prenom,role_id', 'auteur.role')
            ->orderByDesc('date_incident')
            ->get()
            ->map(fn($s) => [
                ...$s->toArray(),
                'type_info'    => $s->type_info,
                'gravite_info' => SignalementComportement::GRAVITES[$s->gravite] ?? [],
            ]);

        return response()->json(['success' => true, 'data' => $signalements]);
    }

    public function mesSIgnalements(Request $request): JsonResponse
    {
        $signalements = SignalementComportement::where('signale_par', auth('api')->id())
            ->with('eleve:id,nom,prenom,niveau_scolaire')
            ->orderByDesc('date_incident')
            ->paginate($request->per_page ?? 20);

        return response()->json(['success' => true, 'data' => $signalements]);
    }

    public function index(Request $request): JsonResponse
    {
        $query = SignalementComportement::with([
            'eleve:id,nom,prenom,niveau_scolaire',
            'auteur:id,nom,prenom,role_id',
            'auteur.role',
        ]);

        if ($request->filled('gravite')) $query->where('gravite', $request->gravite);
        if ($request->filled('traite'))  $query->where('traite', (bool) $request->traite);
        if ($request->filled('type'))    $query->where('type', $request->type);

        $signalements = $query->orderByDesc('date_incident')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'data'    => $signalements,
            'stats'   => [
                'non_traites'   => SignalementComportement::nonTraites()->count(),
                'graves'        => SignalementComportement::graves()->count(),
                'ce_mois'       => SignalementComportement::whereMonth('date_incident', now()->month)->count(),
            ],
        ]);
    }

    public function traiter(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'suite_donnee' => 'required|string|max:500',
        ]);

        $signalement = SignalementComportement::findOrFail($id);
        $signalement->update([
            'traite'       => true,
            'suite_donnee' => $validated['suite_donnee'],
            'traite_par'   => auth('api')->id(),
        ]);

        return response()->json([
            'success' => true,
            'data'    => $signalement->fresh(),
            'message' => 'Signalement traité',
        ]);
    }

    public function monEnfantSignalements(): JsonResponse
    {
        $parent = ParentEleve::where('user_id', auth('api')->id())->first();
        $eleves = $parent?->eleves()->pluck('id') ?? collect();

        $signalements = SignalementComportement::whereIn('eleve_id', $eleves)
            ->with([
                'eleve:id,nom,prenom',
                'auteur:id,nom,prenom,role_id',
                'auteur.role',
            ])
            ->orderByDesc('date_incident')
            ->get()
            ->map(fn($s) => [
                ...$s->toArray(),
                'type_info'    => $s->type_info,
                'gravite_info' => SignalementComportement::GRAVITES[$s->gravite] ?? [],
            ]);

        SignalementComportement::whereIn('eleve_id', $eleves)
            ->where('vu_par_parent', false)
            ->update(['vu_par_parent' => true, 'vu_le' => now()]);

        return response()->json(['success' => true, 'data' => $signalements]);
    }

    public function notificationsParent(Request $request): JsonResponse
    {
        $parentId = auth('api')->id();

        $notifs = \App\Models\NotificationParent::where('parent_id', $parentId)
            ->with('eleve:id,nom,prenom')
            ->when($request->filled('lu'), fn($q) => $q->where('lu', (bool) $request->lu))
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 30);

        $nonLues = \App\Models\NotificationParent::where('parent_id', $parentId)
            ->nonLues()->count();

        return response()->json([
            'success'  => true,
            'data'     => $notifs,
            'non_lues' => $nonLues,
        ]);
    }

    public function marquerLue(string $id): JsonResponse
    {
        \App\Models\NotificationParent::where('id', $id)
            ->where('parent_id', auth('api')->id())
            ->update(['lu' => true, 'lu_le' => now()]);

        return response()->json(['success' => true, 'message' => 'Notification lue']);
    }

    public function toutMarquerLu(): JsonResponse
    {
        \App\Models\NotificationParent::where('parent_id', auth('api')->id())
            ->nonLues()
            ->update(['lu' => true, 'lu_le' => now()]);

        return response()->json(['success' => true, 'message' => 'Toutes les notifications marquées comme lues']);
    }
}
