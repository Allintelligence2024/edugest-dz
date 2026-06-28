<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\{Campagne, CampagneDestinataire, User, Eleve};
use App\Jobs\{EnvoyerSMSJob, EnvoyerEmailJob};
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\{DB, Queue};

class CampagneController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $campagnes = Campagne::where('tenant_id', config('tenant.current_id'))
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'data'    => $campagnes->items(),
            'meta'    => ['total' => $campagnes->total()],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'titre'       => 'required|string|max:200',
            'message'     => 'required|string',
            'canaux'      => 'required|array|min:1',
            'canaux.*'    => 'in:in_app,email,sms,push',
            'filtres'     => 'nullable|array',
            'destinataires' => 'nullable|array',
            'programmee_le' => 'nullable|date|after:now',
        ]);

        $tenantId = config('tenant.current_id');
        $user = auth()->user();

        $destinataires = $this->resoudreDestinataires($validated, $tenantId);
        $canaux = $validated['canaux'];
        $statut = isset($validated['programmee_le']) ? 'programmée' : 'brouillon';

        $campagne = DB::transaction(function () use ($validated, $destinataires, $canaux, $statut, $tenantId, $user) {
            $campagne = Campagne::create([
                'tenant_id'       => $tenantId,
                'titre'           => $validated['titre'],
                'message'         => $validated['message'],
                'canaux'          => $canaux,
                'filtres'         => $validated['filtres'] ?? null,
                'destinataires'   => $destinataires,
                'nb_destinataires'=> count($destinataires),
                'statut'          => $statut,
                'programmee_le'   => $validated['programmee_le'] ?? null,
                'cree_par'        => $user->id,
            ]);

            foreach ($destinataires as $destId) {
                foreach ($canaux as $canal) {
                    CampagneDestinataire::create([
                        'campagne_id'     => $campagne->id,
                        'destinataire_id' => $destId,
                        'canal'           => $canal,
                        'statut'          => 'en_attente',
                    ]);
                }
            }

            return $campagne;
        });

        if ($statut === 'brouillon') {
            $this->envoyerCampagne($campagne);
        }

        return response()->json([
            'success' => true,
            'data'    => $campagne->fresh(),
            'message' => 'Campagne créée' . ($statut === 'brouillon' ? ' et envoyée' : ' et programmée'),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $campagne = Campagne::where('tenant_id', config('tenant.current_id'))
            ->with('destinataires')
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $campagne]);
    }

    public function envoyer(string $id): JsonResponse
    {
        $campagne = Campagne::where('tenant_id', config('tenant.current_id'))
            ->findOrFail($id);

        $this->envoyerCampagne($campagne);

        return response()->json(['success' => true, 'message' => 'Campagne en cours d\'envoi']);
    }

    private function envoyerCampagne(Campagne $campagne): void
    {
        $campagne->update(['statut' => 'en_cours', 'envoyee_le' => now()]);

        $destinataires = $campagne->destinataires;

        foreach ($destinataires as $dest) {
            $user = User::find($dest->destinataire_id);
            if (!$user) continue;

            switch ($dest->canal) {
                case 'email':
                    EnvoyerEmailJob::dispatch($campagne, $user, $dest);
                    break;
                case 'sms':
                    EnvoyerSMSJob::dispatch($campagne, $user, $dest);
                    break;
                case 'in_app':
                    \App\Models\Notification::create([
                        'tenant_id' => $campagne->tenant_id,
                        'user_id'   => $user->id,
                        'titre'     => $campagne->titre,
                        'message'   => $campagne->message,
                        'type'      => 'campagne',
                    ]);
                    $dest->update(['statut' => 'envoyé', 'envoye_le' => now()]);
                    break;
            }
        }
    }

    private function resoudreDestinataires(array $validated, string $tenantId): array
    {
        if (!empty($validated['destinataires'])) {
            return $validated['destinataires'];
        }

        $query = User::where('tenant_id', $tenantId)->where('statut', 'actif');
        $filtres = $validated['filtres'] ?? [];

        if (in_array('parents', $filtres)) {
            $query->whereIn('role', ['parent', 'admin', 'secretaire']);
        }

        if (in_array('impayes', $filtres)) {
            $eleveIds = \App\Models\Facture::where('tenant_id', $tenantId)
                ->whereNotIn('statut', ['payee', 'annulee'])
                ->pluck('eleve_id');
            $parentIds = \App\Models\ParentEleve::whereHas('eleves', fn($q) => $q->whereIn('id', $eleveIds))
                ->pluck('user_id');
            $query->whereIn('id', $parentIds);
        }

        return $query->pluck('id')->toArray();
    }
}
