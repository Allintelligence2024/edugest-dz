<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SecureStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SecureFichierController extends Controller
{
    public function __construct(private SecureStorageService $storage) {}

    public function servir(Request $request, string $cheminBase64)
    {
        $chemin   = base64_decode($cheminBase64);
        $sig      = $request->query('sig', '');
        $exp      = (int) $request->query('exp', 0);
        $tenantId = config('tenant.current_id');

        if ($exp < now()->timestamp) {
            return response()->json(['message' => 'Lien expiré. Générez un nouveau lien.'], 410);
        }

        $expectedSig = hash_hmac('sha256', $chemin . $tenantId, config('app.key'));
        if (!hash_equals($expectedSig, $sig)) {
            \Illuminate\Support\Facades\Log::warning('SECURE FILE: signature invalide', [
                'chemin' => $chemin, 'ip' => $request->ip(),
            ]);
            return response()->json(['message' => 'Lien invalide.'], 403);
        }

        if ($tenantId && !str_contains($chemin, "tenants/{$tenantId}/")) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        if (!Storage::disk('local')->exists($chemin)) {
            return response()->json(['message' => 'Fichier non trouvé.'], 404);
        }

        $contenu = Storage::disk('local')->get($chemin);
        $ext     = pathinfo($chemin, PATHINFO_EXTENSION);
        $mime    = match ($ext) {
            'pdf'  => 'application/pdf',
            'jpg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            default=> 'application/octet-stream',
        };

        return response($contenu, 200, [
            'Content-Type'        => $mime,
            'Content-Disposition' => 'inline; filename="' . basename($chemin) . '"',
            'Cache-Control'       => 'no-store, no-cache, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
