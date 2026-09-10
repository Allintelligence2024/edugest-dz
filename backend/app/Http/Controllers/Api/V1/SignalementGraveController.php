<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\{NotificationInAppService, SecurityMonitorService};
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SignalementGraveController extends Controller
{
    public function __construct(
        private NotificationInAppService $notif,
        private SecurityMonitorService   $monitor,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type_incident' => 'required|in:violence_verbale,violence_physique,harcelement,discrimination,comportement_inapproprie,autre',
            'gravite'       => 'required|in:important,grave,tres_grave',
            'description'   => 'required|string|min:20|max:2000',
            'date_incident' => 'required|date|before_or_equal:today',
            'concerne_id'   => 'nullable|uuid|exists:users,id',
            'temoins'       => 'nullable|string|max:500',
        ]);

        $user     = auth('api')->user();
        $tenantId = config('tenant.current_id');

        $eleve = DB::table('eleves')->where('user_id', $user->id)->first();
        if (!$eleve) {
            return response()->json([
                'success' => false,
                'message' => 'Seuls les \u00e9l\u00e8ves peuvent soumettre un signalement grave.',
            ], 403);
        }

        $signalementId = (string) Str::uuid();
        $numeroTicket  = 'SIG-' . strtoupper(Str::random(6));

        DB::table('signalements_graves_eleves')->insert(array_merge($validated, [
            'id'         => $signalementId,
            'tenant_id'  => $tenantId,
            'eleve_id'   => $eleve->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        try {
            \App\Services\AuditChainService::enregistrer(
                typeEvenement: 'signalement_grave_eleve',
                resourceType:  'signalement',
                resourceId:    $signalementId,
                avant:         [],
                apres:         ['type' => $validated['type_incident'], 'gravite' => $validated['gravite']],
                userId:        $user->id,
                tenantId:      $tenantId,
            );
        } catch (\Throwable) {
        }

        $typeLabel = match ($validated['type_incident']) {
            'violence_verbale'         => 'Violence verbale',
            'violence_physique'        => 'Violence physique',
            'harcelement'              => 'Harc\u00e8lement',
            'discrimination'           => 'Discrimination',
            'comportement_inapproprie' => 'Comportement inappropri\u00e9',
            default                    => 'Incident',
        };

        $this->notif->creerPourRole(
            tenantId: $tenantId,
            role:     'admin',
            type:     'signalement_grave',
            titre:    "Signalement grave \u2014 {$typeLabel}",
            corps:    "Gravit\u00e9: {$validated['gravite']} \u00b7 Ticket #{$numeroTicket} \u00b7 Investigation requise sous 48h",
            meta:     [
                'signalement_id' => $signalementId,
                'ticket'         => $numeroTicket,
                'action_url'     => "/signalements/{$signalementId}",
            ],
            urgence: true,
        );

        try {
            $this->monitor->alerter(
                'signalement_grave_eleve', 'warning',
                "Signalement grave soumis \u2014 Type: {$typeLabel} \u00b7 Ticket: {$numeroTicket}",
                ['tenant_id' => $tenantId, 'type' => $validated['type_incident']],
            );
        } catch (\Throwable) {
        }

        $this->notif->creer(
            userId:   $user->id,
            type:     'accuse_reception',
            titre:    "Signalement re\u00e7u \u2014 Ticket #{$numeroTicket}",
            corps:    "Votre signalement a \u00e9t\u00e9 enregistr\u00e9 et transmis au directeur. Vous serez inform\u00e9(e) des suites dans les 48h.",
            meta:     ['signalement_id' => $signalementId, 'ticket' => $numeroTicket],
            tenantId: $tenantId,
        );

        return response()->json([
            'success'       => true,
            'message'       => "Signalement enregistr\u00e9. Ticket #{$numeroTicket}. Le directeur sera inform\u00e9.",
            'numero_ticket' => $numeroTicket,
            'delai_reponse' => '48 heures maximum',
        ], 201);
    }

    /**
     * Directeur consulte les signalements. Admin-only.
     */
    public function index(): JsonResponse
    {
        $user = auth('api')->user();
        if ($user->role?->nom !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Acc\u00e8s r\u00e9serv\u00e9 aux directeurs.',
            ], 403);
        }

        $tenantId = config('tenant.current_id');

        $signalements = DB::table('signalements_graves_eleves as s')
            ->join('eleves as e', 's.eleve_id', '=', 'e.id')
            ->leftJoin('users as u', 's.concerne_id', '=', 'u.id')
            ->where('s.tenant_id', $tenantId)
            ->select(
                's.id', 's.type_incident', 's.gravite', 's.statut',
                's.date_incident', 's.description', 's.created_at',
                DB::raw("e.nom || ' ' || e.prenom as eleve_nom"),
                DB::raw("COALESCE(u.nom || ' ' || u.prenom, 'Non sp\u00e9cifi\u00e9') as concerne_nom")
            )
            ->orderByDesc('s.created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $signalements,
            'alerte'  => $signalements->where('statut', 'soumis')->count() > 0
                ? $signalements->where('statut', 'soumis')->count() . " signalement(s) en attente d'investigation"
                : null,
        ]);
    }

    public function traiter(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'statut'            => 'required|in:en_investigation,resolu,non_fonde,archive',
            'commentaire_admin' => 'required|string|max:1000',
        ]);

        $tenantId    = config('tenant.current_id');
        $directeurId = auth('api')->id();

        $signalement = DB::table('signalements_graves_eleves')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$signalement) {
            return response()->json(['success' => false, 'message' => 'Non trouv\u00e9.'], 404);
        }

        DB::table('signalements_graves_eleves')->where('id', $id)->update([
            'statut'            => $validated['statut'],
            'commentaire_admin' => $validated['commentaire_admin'],
            'traite_par'        => $directeurId,
            'traite_le'         => now(),
            'updated_at'        => now(),
        ]);

        $eleveUserId = DB::table('eleves')->where('id', $signalement->eleve_id)->value('user_id');
        if ($eleveUserId) {
            $statutLabel = match ($validated['statut']) {
                'en_investigation' => "Votre signalement est en cours d'investigation",
                'resolu'           => 'Votre signalement a \u00e9t\u00e9 trait\u00e9',
                'non_fonde'        => 'Votre signalement a \u00e9t\u00e9 examin\u00e9',
                'archive'          => 'Votre signalement a \u00e9t\u00e9 archiv\u00e9',
                default            => 'Votre signalement a \u00e9t\u00e9 mis \u00e0 jour',
            };

            $this->notif->creer(
                userId:   $eleveUserId,
                type:     'signalement_traite',
                titre:    'Mise \u00e0 jour de votre signalement',
                corps:    $statutLabel . '. ' . mb_substr($validated['commentaire_admin'], 0, 150),
                meta:     ['signalement_id' => $id],
                tenantId: $tenantId,
            );
        }

        return response()->json(['success' => true, 'message' => 'Signalement trait\u00e9. \u00c9l\u00e8ve notifi\u00e9.']);
    }
}
