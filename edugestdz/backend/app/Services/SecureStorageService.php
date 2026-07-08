<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SecureStorageService
{
    private const DISK = 'local';

    public function stocker(
        UploadedFile $fichier,
        string       $type,
        ?string      $tenantId   = null
    ): string {
        $tenantId = $tenantId ?? config('tenant.current_id');
        $ext      = $fichier->getClientOriginalExtension();
        $uuid     = Str::uuid()->toString();
        $chemin   = "tenants/{$tenantId}/{$type}/{$uuid}.{$ext}";

        Storage::disk(self::DISK)->put($chemin, file_get_contents($fichier->getRealPath()));

        return $chemin;
    }

    public function stockerContenu(
        string  $contenu,
        string  $type,
        string  $extension = 'pdf',
        ?string $tenantId  = null
    ): string {
        $tenantId = $tenantId ?? config('tenant.current_id');
        $uuid     = Str::uuid()->toString();
        $chemin   = "tenants/{$tenantId}/{$type}/{$uuid}.{$extension}";

        Storage::disk(self::DISK)->put($chemin, $contenu);

        return $chemin;
    }

    public function urlSignee(string $chemin, int $minutes = 60): string
    {
        $tenantId = config('tenant.current_id');
        if ($tenantId && !str_contains($chemin, "tenants/{$tenantId}/")) {
            \Illuminate\Support\Facades\Log::critical('SECURE STORAGE: cross-tenant access attempt', [
                'chemin'    => $chemin,
                'tenant_id' => $tenantId,
                'user_id'   => auth('api')->id(),
                'ip'        => request()->ip(),
            ]);
            throw new \Illuminate\Auth\Access\AuthorizationException(
                'Accès refusé : fichier appartenant à un autre établissement.'
            );
        }

        return route('fichier.secure', [
            'chemin' => base64_encode($chemin),
            'sig'    => hash_hmac('sha256', $chemin . $tenantId, config('app.key')),
            'exp'    => now()->addMinutes($minutes)->timestamp,
        ]);
    }

    public function supprimer(string $chemin, ?string $tenantId = null): bool
    {
        $tenantId = $tenantId ?? config('tenant.current_id');

        if (!str_contains($chemin, "tenants/{$tenantId}/")) {
            \Illuminate\Support\Facades\Log::warning('SECURE STORAGE: tentative suppression cross-tenant', [
                'chemin' => $chemin, 'tenant' => $tenantId,
            ]);
            return false;
        }

        return Storage::disk(self::DISK)->delete($chemin);
    }
}
