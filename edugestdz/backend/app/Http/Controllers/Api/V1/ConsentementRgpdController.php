<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sprint 6 § 5 — Loi 18-07 : consentement parental.
 *
 * La table consentements_rgpd existait depuis juillet 2026 mais aucun code
 * ne l'écrivait : impossible de prouver qu'un parent a consenti au
 * traitement des données de son enfant, ce que la loi 18-07 exige pour les
 * mineurs (art. 3 et 7 — consentement du titulaire de l'autorité parentale).
 *
 * Principe : un consentement est un FAIT HISTORIQUE — on ne modifie ni ne
 * supprime jamais une ligne. Un retrait de consentement = une nouvelle ligne
 * avec accepte=false : l'historique complet reste auditable, comme pour la
 * chaîne Merkle des journaux.
 */
class ConsentementRgpdController extends Controller
{
    /** Types de consentement gérés (whitelist stricte). */
    private const TYPES = [
        'droit_image',
        'sorties_scolaires',
        'donnees_sante',
        'transport_scolaire',
        'communication_electronique',
        'publication_resultats',
    ];

    /**
     * Enregistrer un consentement (ou un refus) parental.
     *
     * Saisi par la direction (parent présent à l'école, formulaire papier
     * numérisé, ou parent via l'application).
     */
    public function enregistrer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'parent_id'         => 'nullable|uuid',
            'eleve_id'          => 'nullable|uuid',
            'type_consentement' => 'required|string|in:' . implode(',', self::TYPES),
            'accepte'           => 'required|boolean',
            'version'           => 'nullable|string|max:20',
        ]);

        $parentId = $validated['parent_id'] ?? null;
        $eleveId  = $validated['eleve_id'] ?? null;

        // Un consentement parental porte sur un mineur : sans parent NI élève
        // identifié, la trace ne prouve rien.
        if ($parentId === null && $eleveId === null) {
            return response()->json([
                'success' => false,
                'message' => 'Renseigner parent_id et/ou eleve_id : un consentement doit être rattaché à une personne.',
            ], 422);
        }

        $tenantId = config('tenant.current_id');

        if ($parentId !== null && !DB::table('parents')->where('id', $parentId)->where('tenant_id', $tenantId)->exists()) {
            return response()->json(['success' => false, 'message' => 'Parent introuvable dans cet établissement.'], 404);
        }

        if ($eleveId !== null && !DB::table('eleves')->where('id', $eleveId)->where('tenant_id', $tenantId)->exists()) {
            return response()->json(['success' => false, 'message' => 'Élève introuvable dans cet établissement.'], 404);
        }

        $userId = auth('api')->id();

        DB::table('consentements_rgpd')->insert([
            'id'                => (string) Str::uuid(),
            'tenant_id'         => $tenantId,
            'user_id'           => $userId,
            'parent_id'         => $parentId,
            'eleve_id'          => $eleveId,
            'type_consentement' => $validated['type_consentement'],
            'accepte'           => (bool) $validated['accepte'],
            'version'           => $validated['version'] ?? '1.0',
            'ip_adresse'        => $request->ip(),
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        try {
            app(\App\Services\AuditChainService::class)->enregistrer(
                event: 'consentement_parental',
                payload: [
                    'type_consentement' => $validated['type_consentement'],
                    'accepte'           => (bool) $validated['accepte'],
                    'parent_id'         => $parentId,
                    'eleve_id'          => $eleveId,
                ],
                causerId: is_string($userId) ? $userId : null,
            );
        } catch (\Throwable) {
            // La chaîne d'audit ne doit pas bloquer l'enregistrement du
            // consentement : la ligne RGPD reste la source de vérité.
        }

        return response()->json([
            'success' => true,
            'message' => $validated['accepte']
                ? 'Consentement enregistré.'
                : 'Refus de consentement enregistré (l\'historique des consentements précédents est conservé).',
        ], 201);
    }

    /**
     * Historique des consentements du tenant, filtrable par élève ou parent.
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = config('tenant.current_id');

        $query = DB::table('consentements_rgpd')->where('tenant_id', $tenantId);

        $eleveId  = $request->query('eleve_id');
        $parentId = $request->query('parent_id');

        if (is_string($eleveId) && $eleveId !== '') {
            $query->where('eleve_id', $eleveId);
        }

        if (is_string($parentId) && $parentId !== '') {
            $query->where('parent_id', $parentId);
        }

        $consentements = $query->orderByDesc('created_at')->limit(500)->get();

        return response()->json(['success' => true, 'data' => $consentements]);
    }
}
