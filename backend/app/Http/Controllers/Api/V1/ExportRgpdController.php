<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\{DB, Storage, Log};
use Illuminate\Support\Str;

class ExportRgpdController extends Controller
{
    public function exporterTenant(): JsonResponse
    {
        $tenantId = config('tenant.current_id');
        $jobId    = (string) Str::uuid();

        \App\Jobs\ExportDonneesTenantJob::dispatch($tenantId, $jobId, auth('api')->id());

        return response()->json([
            'success'  => true,
            'message'  => 'Export en cours. Vous serez notifié par email et notification quand il sera prêt (2-5 minutes).',
            'job_id'   => $jobId,
        ]);
    }

    public function exporterEleve(string $eleveId)
    {
        $tenantId = config('tenant.current_id');

        $eleve = DB::table('eleves')->where('id', $eleveId)->where('tenant_id', $tenantId)->first();
        if (!$eleve) return response()->json(['success' => false, 'message' => 'Élève non trouvé'], 404);

        $donnees = [
            'informations_personnelles' => (array) $eleve,
            'notes'     => DB::table('notes as n')
                ->join('evaluations as e', 'n.evaluation_id', '=', 'e.id')
                ->where('n.eleve_id', $eleveId)->get()->toArray(),
            'absences'  => DB::table('absences_journalieres')
                ->where('eleve_id', $eleveId)->get()->toArray(),
            'factures'  => DB::table('factures')
                ->where('eleve_id', $eleveId)->where('tenant_id', $tenantId)->get()->toArray(),
            'bulletins' => DB::table('bulletins')
                ->where('eleve_id', $eleveId)->get()->toArray(),
            'billets'   => DB::table('billets')
                ->where('eleve_id', $eleveId)->get()->toArray(),
        ];

        $nomFichier = "donnees_eleve_{$eleve->nom}_{$eleve->prenom}_" . date('Y-m-d') . ".json";
        $contenu    = json_encode($donnees, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        DB::table('demandes_rgpd')->insert([
            'id'        => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'user_id'   => auth('api')->id(),
            'type'      => 'portabilite',
            'statut'    => 'traite',
            'traite_le' => now(),
            'created_at'=> now(),
            'updated_at'=> now(),
        ]);

        return response($contenu, 200, [
            'Content-Type'        => 'application/json',
            'Content-Disposition' => "attachment; filename=\"{$nomFichier}\"",
        ]);
    }

    public function demanderSuppression(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'eleve_id'   => 'nullable|uuid',
            'motif'      => 'nullable|string|max:500',
            'confirme'   => 'required|boolean|accepted',
        ]);

        $tenantId = config('tenant.current_id');
        $userId   = auth('api')->id();

        DB::table('demandes_rgpd')->insert([
            'id'         => (string) Str::uuid(),
            'tenant_id'  => $tenantId,
            'user_id'    => $userId,
            'type'       => 'effacement',
            'statut'     => 'en_cours',
            'commentaire'=> $validated['motif'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            \App\Services\AuditChainService::enregistrer(
                event: 'demande_effacement_rgpd',
                payload: ['resource_type' => 'eleve', 'resource_id' => $validated['eleve_id'] ?? $userId, 'motif' => $validated['motif'] ?? null],
                causerId: $userId,
            );
        } catch (\Throwable) {}

        return response()->json([
            'success' => true,
            'message' => 'Demande d\'effacement enregistrée. Traitée dans les 30 jours (loi 18-07 Algérie).',
        ]);
    }

    public function archiverAnnee(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'annee_scolaire' => 'required|string|regex:/^\d{4}-\d{4}$/',
            'confirme'       => 'required|boolean|accepted',
        ]);

        $tenantId = config('tenant.current_id');

        \App\Jobs\ExportDonneesTenantJob::dispatch($tenantId, (string) Str::uuid(), auth('api')->id());

        return response()->json([
            'success' => true,
            'message' => "Archivage de l'année scolaire {$validated['annee_scolaire']} lancé. ZIP disponible dans 5-10 minutes.",
        ]);
    }

    public function listeDemandes(): JsonResponse
    {
        $tenantId = config('tenant.current_id');

        $demandes = DB::table('demandes_rgpd as d')
            ->join('users as u', 'd.user_id', '=', 'u.id')
            ->where('d.tenant_id', $tenantId)
            ->select('d.id', 'd.type', 'd.statut', 'd.created_at', 'd.traite_le', 'd.commentaire',
                DB::raw("u.nom || ' ' || u.prenom as demandeur"))
            ->orderByDesc('d.created_at')
            ->get();

        return response()->json(['success' => true, 'data' => $demandes]);
    }
}
