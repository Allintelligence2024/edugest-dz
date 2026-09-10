<?php

namespace App\Services;

use Carbon\Carbon;

class NotificationTimingService
{
    private const HEURE_DEBUT          = 7;
    private const HEURE_FIN            = 20;
    private const HEURE_NUIT_DEBUT     = 22;
    private const HEURE_NUIT_FIN       = 6;

    public function estEnPlageHoraire(?Carbon $moment = null): bool
    {
        $moment = $moment ?? now()->setTimezone('Africa/Algiers');
        $heure  = (int) $moment->format('H');

        return $heure >= self::HEURE_DEBUT && $heure < self::HEURE_FIN;
    }

    public function estEnHeuresNuit(?Carbon $moment = null): bool
    {
        $moment = $moment ?? now()->setTimezone('Africa/Algiers');
        $heure  = (int) $moment->format('H');

        return $heure >= self::HEURE_NUIT_DEBUT || $heure < self::HEURE_NUIT_FIN;
    }

    public function doitEnvoyerPush(bool $urgence = false, ?Carbon $moment = null): bool
    {
        if ($urgence) return true;

        return $this->estEnPlageHoraire($moment);
    }

    public function doitEnvoyerSMS(bool $urgence = false, ?Carbon $moment = null): bool
    {
        if ($urgence) return true;

        if ($this->estEnHeuresNuit($moment)) return false;

        return $this->estEnPlageHoraire($moment);
    }

    public function doitEnvoyerEmail(bool $urgence = false, ?Carbon $moment = null): bool
    {
        if ($this->estEnHeuresNuit($moment)) return false;

        return true;
    }

    public function getDelaiAvantEnvoi(?Carbon $moment = null): int
    {
        $moment = $moment ?? now()->setTimezone('Africa/Algiers');
        $heure  = (int) $moment->format('H');

        if ($heure >= self::HEURE_DEBUT && $heure < self::HEURE_FIN) {
            return 0;
        }

        if ($heure >= self::HEURE_NUIT_DEBUT) {
            $debut = $moment->copy()->addDay()->setTime(self::HEURE_DEBUT, 0);
            return (int) $moment->diffInMinutes($debut);
        }

        if ($heure < self::HEURE_DEBUT) {
            $debut = $moment->copy()->setTime(self::HEURE_DEBUT, 0);
            return (int) $moment->diffInMinutes($debut);
        }

        return 0;
    }

    public function getPlageHoraireLabel(): string
    {
        return self::HEURE_DEBUT . 'h — ' . self::HEURE_FIN . 'h';
    }
}
