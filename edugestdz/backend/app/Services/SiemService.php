<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SiemService
{
    public function evaluerRegle(string $regle, Request $request, ?User $user): array
    {
        $cacheKey = 'siem_evaluated:' . $regle . ':' . now()->format('Y-m-d-H-i');

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $resultat = match ($regle) {
            'tentative_intrusion' => $this->evaluerTentativeIntrusion($request),
            'volume_anormal' => $this->evaluerVolumeAnormal(),
            'ip_suspecte' => $this->evaluerIpSuspecte($request),
            'horaire_anormal' => $this->evaluerHoraireAnormal($request, $user),
            'multiples_echecs' => $this->evaluerMultiplesEchecs($request),
            default => ['regle' => $regle, 'raison' => 'regle inconnue'],
        };

        Cache::put($cacheKey, $resultat, 60);

        if (($resultat['alerte'] ?? false) === true) {
            Log::warning('SIEM: regle declenchee', [
                'regle' => $regle,
                'resultat' => $resultat,
            ]);
        }

        return $resultat;
    }

    private function evaluerTentativeIntrusion(Request $request): array
    {
        $path = $request->path();
        $pathsSuspects = ['api/v1/phpinfo', 'api/v1/server-status', 'api/v1/actuator', 'api/v1/metrics', 'api/v1/.env', 'api/v1/admin'];

        if (in_array($path, $pathsSuspects)) {
            return [
                'alerte' => true,
                'regle' => 'tentative_intrusion',
                'severite' => 8,
                'raison' => 'Acces a une route suspecte',
                'path' => $path,
            ];
        }

        return ['alerte' => false, 'regle' => 'tentative_intrusion'];
    }

    private function evaluerVolumeAnormal(): array
    {
        $count = DB::table('audit_logs')
            ->where('created_at', '>=', now()->subMinutes(5))
            ->count();

        if ($count > 500) {
            return [
                'alerte' => true,
                'regle' => 'volume_anormal',
                'severite' => 7,
                'raison' => "Volume eleve d'audit_logs: {$count} en 5 min",
                'count' => $count,
            ];
        }

        return ['alerte' => false, 'regle' => 'volume_anormal'];
    }

    private function evaluerIpSuspecte(Request $request): array
    {
        $ip = $request->ip();

        $suspectes = Cache::get('siem:ip_suspectes', []);

        if (in_array($ip, $suspectes)) {
            return [
                'alerte' => true,
                'regle' => 'ip_suspecte',
                'severite' => 6,
                'raison' => 'IP suspecte detectee',
                'ip' => $ip,
            ];
        }

        return ['alerte' => false, 'regle' => 'ip_suspecte'];
    }

    private function evaluerHoraireAnormal(Request $request, ?User $user): array
    {
        $heure = now()->hour;

        if ($heure < 6 || $heure > 22) {
            return [
                'alerte' => true,
                'regle' => 'horaire_anormal',
                'severite' => 4,
                'raison' => "Acces en dehors des heures ouvrees ({$heure}h)",
                'heure' => $heure,
                'user_id' => $user?->id,
            ];
        }

        return ['alerte' => false, 'regle' => 'horaire_anormal'];
    }

    private function evaluerMultiplesEchecs(Request $request): array
    {
        $ip = $request->ip();

        $key = 'siem:echecs:' . $ip;
        $echecs = (int) Cache::get($key, 0);

        if ($echecs >= 10) {
            return [
                'alerte' => true,
                'regle' => 'multiples_echecs',
                'severite' => 7,
                'raison' => "Tentatives echouees: {$echecs} depuis {$ip}",
                'ip' => $ip,
                'echecs' => $echecs,
            ];
        }

        return ['alerte' => false, 'regle' => 'multiples_echecs'];
    }
}
