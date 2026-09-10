<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InsiderThreatDetectorService
{
    private const SEUIL_BULK_EXPORT = 100;
    private const SEUIL_APRES_HEURES = 22;
    private const SEUIL_AVANT_HEURES = 6;
    private const SEUIL_VOLUME_DATA = 1000;
    private const SEUIL_IP_INCONNUE = 3;
    private const SEUIL_ECHEC_AUTH = 10;

    public function detecterExportMassif(User $user, int $nbLignes): bool
    {
        if ($nbLignes >= self::SEUIL_BULK_EXPORT) {
            $this->journaliser($user, 'BULK_EXPORT', [
                'nb_lignes' => $nbLignes,
                'seuil' => self::SEUIL_BULK_EXPORT,
            ]);
            return true;
        }

        return false;
    }

    public function detecterAccesHoraireAnormal(User $user): bool
    {
        $heure = now()->hour;

        if ($heure >= self::SEUIL_APRES_HEURES || $heure < self::SEUIL_AVANT_HEURES) {
            $this->journaliser($user, 'APRES_HEURES', [
                'heure' => $heure,
            ]);
            return true;
        }

        return false;
    }

    public function detecterVolumeAnormal(User $user): bool
    {
        $count = DB::table('audit_logs')
            ->where('causer_id', $user->id)
            ->where('created_at', '>=', now()->subMinutes(5))
            ->count();

        if ($count >= self::SEUIL_VOLUME_DATA) {
            $this->journaliser($user, 'VOLUME_ANORMAL', [
                'count' => $count,
                'seuil' => self::SEUIL_VOLUME_DATA,
            ]);
            return true;
        }

        return false;
    }

    public function detecterIPInconnue(User $user, string $ip): bool
    {
        $historique = DB::table('audit_logs')
            ->where('causer_id', $user->id)
            ->where('properties', 'like', "%{$ip}%")
            ->count();

        if ($historique < self::SEUIL_IP_INCONNUE) {
            $this->journaliser($user, 'IP_INCONNUE', [
                'ip' => $ip,
                'historique' => $historique,
            ]);
            return true;
        }

        return false;
    }

    public function detecterEchecsAuthMultiples(User $user, int $nbEchecs): bool
    {
        if ($nbEchecs >= self::SEUIL_ECHEC_AUTH) {
            $this->journaliser($user, 'ECHECS_AUTH_MULTIPLES', [
                'nb_echecs' => $nbEchecs,
                'seuil' => self::SEUIL_ECHEC_AUTH,
            ]);
            return true;
        }

        return false;
    }

    private function journaliser(User $user, string $type, array $details): void
    {
        Log::warning('InsiderThreat: ' . $type, [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'tenant_id' => $user->tenant_id,
            'type' => $type,
            'details' => $details,
        ]);

        try {
            activity()
                ->performedOn($user)
                ->causedBy($user)
                ->withProperties(['threat_type' => $type, 'details' => $details])
                ->log('Insider Threat: ' . $type);
        } catch (\Throwable $e) {
            Log::warning('InsiderThreat: impossible de journaliser dans activitylog', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
