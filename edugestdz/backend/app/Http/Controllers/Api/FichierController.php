<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FichierController extends Controller
{
    public function show(Request $request, string $cheminB64): JsonResponse
    {
        $chemin = base64_decode($cheminB64, true);

        if ($chemin === false || !preg_match('#^tenants/([^/]+)/#', $chemin, $matches)) {
            abort(404);
        }

        $tenantId = $matches[1];
        $user = auth('api')->user();

        if (!$user) {
            abort(401);
        }

        $exp = $request->integer('exp', 0);

        if ($exp > 0 && now()->timestamp > $exp) {
            return response()->json([
                'success' => false,
                'message' => 'Le lien a expiré.',
                'code' => 'FICHIER_EXPIRE',
            ], 410);
        }

        $sig = $request->query('sig', '');
        $expected = hash_hmac('sha256', $chemin . $user->tenant_id, config('app.key'));

        if (!hash_equals($expected, $sig)) {
            abort(403, 'Signature invalide.');
        }

        if ($tenantId !== $user->tenant_id) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé : fichier d\'un autre centre.',
                'code' => 'FICHIER_AUTRE_TENANT',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Fichier accessible',
        ]);
    }
}
