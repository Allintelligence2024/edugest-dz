<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotificationInAppService
{
    private const HEURE_DEBUT = 7;
    private const HEURE_FIN   = 20;

    public function creer(
        ?string $userId,
        string  $type,
        string  $titre,
        string  $corps,
        array   $meta     = [],
        ?string $tenantId = null,
        ?string $eleveId  = null,
        bool    $urgence  = false,
    ): void {
        if (!$userId && !$eleveId) return;

        $tenantId = $tenantId ?? config('tenant.current_id');

        try {
            DB::table('notifications_inapp')->insert([
                'id'         => (string) Str::uuid(),
                'tenant_id'  => $tenantId,
                'user_id'    => $userId,
                'type'       => $type,
                'titre'      => $titre,
                'corps'      => $corps,
                'lien'       => $meta['action_url'] ?? null,
                'action_url' => $meta['action_url'] ?? null,
                'icone'      => $meta['icone'] ?? null,
                'lu'         => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable) {
            return;
        }

        if ($userId && ($urgence || $this->dansPlageHoraire())) {
            try {
                app(FirebaseService::class)->notifyUser(
                    $userId, $titre, $corps,
                    array_merge($meta, ['type' => $type])
                );
            } catch (\Throwable) {
            }
        }
    }

    public function creerPourRole(
        string $tenantId,
        string $role,
        string $type,
        string $titre,
        string $corps,
        array  $meta   = [],
        bool   $urgence = false,
    ): void {
        $users = \App\Models\User::where('tenant_id', $tenantId)
            ->whereHas('role', fn($q) => $q->where('nom', $role))
            ->pluck('id');

        foreach ($users as $userId) {
            $this->creer($userId, $type, $titre, $corps, $meta, $tenantId, null, $urgence);
        }
    }

    private function dansPlageHoraire(): bool
    {
        $heure = (int) now()->setTimezone('Africa/Algiers')->format('H');
        return $heure >= self::HEURE_DEBUT && $heure < self::HEURE_FIN;
    }
}
