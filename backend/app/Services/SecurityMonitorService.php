<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SecurityMonitorService
{
    private const SEUIL_BRUTE_FORCE       = 10;
    private const FENETRE_BRUTE_FORCE     = 900;
    private const SEUIL_VOLUME_ELEVES     = 100;
    private const HEURE_DEBUT_NORMALE     = 6;
    private const HEURE_FIN_NORMALE       = 22;

    public function loginEchoue(string $email, string $ip): void
    {
        $cacheKey = "login_failed:{$ip}:{$email}";
        $tentatives = (int) Cache::get($cacheKey, 0) + 1;
        Cache::put($cacheKey, $tentatives, self::FENETRE_BRUTE_FORCE);

        $this->enregistrerEvenement('login_failed', 'info', [
            'email'      => $email,
            'ip'         => $ip,
            'tentatives' => $tentatives,
        ]);

        if ($tentatives >= self::SEUIL_BRUTE_FORCE) {
            $this->alerter('brute_force', 'critical',
                "Brute force detecte sur {$email} depuis {$ip} — {$tentatives} tentatives",
                ['email' => $email, 'ip' => $ip, 'tentatives' => $tentatives]
            );
        }
    }

    public function estEnBruteForce(string $email, string $ip): bool
    {
        $cacheKey   = "login_failed:{$ip}:{$email}";
        $tentatives = (int) Cache::get($cacheKey, 0);
        return $tentatives >= self::SEUIL_BRUTE_FORCE;
    }

    public function verifierAccesHorsHoraires(string $userId, string $role, string $ip): void
    {
        if (!in_array($role, ['admin', 'super_admin'])) return;

        $heure = (int) now()->format('H');

        if ($heure < self::HEURE_DEBUT_NORMALE || $heure >= self::HEURE_FIN_NORMALE) {
            $this->alerter('after_hours_access', 'warning',
                "Acces hors horaires : admin {$userId} a {$heure}h depuis {$ip}",
                ['user_id' => $userId, 'heure' => $heure, 'ip' => $ip]
            );
        }
    }

    public function verifierVolumeRequete(string $userId, string $path, int $nbResultats): void
    {
        if ($nbResultats < self::SEUIL_VOLUME_ELEVES) return;

        $cacheKey   = "volume_check:{$userId}:" . now()->format('Y-m-d-H-i');
        $totalMin   = (int) Cache::get($cacheKey, 0) + $nbResultats;
        Cache::put($cacheKey, $totalMin, 60);

        if ($totalMin > self::SEUIL_VOLUME_ELEVES * 10) {
            $this->alerter('unusual_volume', 'critical',
                "Volume de donnees anormal : {$userId} a recupere {$totalMin} enregistrements en 1 min",
                ['user_id' => $userId, 'path' => $path, 'volume' => $totalMin]
            );
        }
    }

    public function enregistrerEvenement(
        string  $type,
        string  $severite,
        array   $details = [],
        ?string $userId   = null,
        ?string $tenantId = null
    ): void {
        try {
            DB::table('security_events')->insert([
                'id'          => Str::uuid(),
                'type'        => $type,
                'severite'    => $severite,
                'ip'          => request()->ip(),
                'user_agent'  => substr(request()->userAgent() ?? '', 0, 200),
                'user_id'     => $userId ?? auth('api')->id(),
                'tenant_id'   => $tenantId ?? config('tenant.current_id'),
                'path'        => request()->path(),
                'details'     => json_encode($details),
                'survenu_le'  => now(),
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('SecurityMonitor: impossible d\'enregistrer: ' . $e->getMessage());
        }
    }

    public function alerter(string $type, string $severite, string $message, array $details = []): void
    {
        $this->enregistrerEvenement($type, $severite, $details);

        Log::channel('stack')->log(
            $severite === 'critical' ? 'critical' : 'warning',
            "[SECURITY] {$message}",
            $details
        );

        $telegramToken = config('services.telegram.bot_token');
        $telegramChat  = config('services.telegram.chat_id');

        if ($telegramToken && $telegramChat) {
            try {
                $emoji  = match ($severite) {
                    'emergency', 'critical' => "\xF0\x9F\x9A\xA8",
                    'warning'              => "\xE2\x9A\xA0\xEF\xB8\x8F",
                    default                => "\xE2\x84\xB9\xEF\xB8\x8F",
                };

                Http::timeout(5)->post(
                    "https://api.telegram.org/bot{$telegramToken}/sendMessage",
                    [
                        'chat_id'    => $telegramChat,
                        'text'       => "{$emoji} *EduGest DZ — Alerte Securite*\n\n{$message}\n\n"
                                      . "IP: " . request()->ip() . "\n"
                                      . "Heure: " . now()->format('d/m/Y H:i:s'),
                        'parse_mode' => 'Markdown',
                    ]
                );
            } catch (\Throwable $e) {
                Log::warning('SecurityMonitor: Telegram alert failed: ' . $e->getMessage());
            }
        }

        if (in_array($severite, ['critical', 'emergency'])) {
            $adminEmail = config('app.security_alert_email');
            if ($adminEmail) {
                try {
                    \Illuminate\Support\Facades\Mail::raw(
                        "[SECURITE CRITIQUE] EduGest DZ\n\n{$message}\n\nDetails: " . json_encode($details, JSON_PRETTY_PRINT),
                        fn($m) => $m->to($adminEmail)->subject("[CRITIQUE] Alerte securite EduGest DZ")
                    );
                } catch (\Throwable) {}
            }
        }
    }
}
