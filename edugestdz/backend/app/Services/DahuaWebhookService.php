<?php

namespace App\Services;

use App\Models\CameraConfig;
use App\Models\AlerteSurveillance;
use App\Services\Sms\SmsService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DahuaWebhookService
{
    public function __construct(
        private SmsService      $sms,
        private FirebaseService $firebase,
    ) {}

    public function traiter(array $payload, string $ipSource): ?AlerteSurveillance
    {
        Log::info('Dahua webhook reçu', ['ip' => $ipSource, 'payload' => $payload]);

        $normalise = $this->normaliserPayload($payload);
        if (!$normalise) {
            Log::warning('Dahua webhook : payload non reconnu', $payload);
            return null;
        }

        $camera = CameraConfig::where('serial_no', $normalise['serial_no'])
            ->where('actif', true)
            ->first();

        if (!$camera) {
            Log::warning("Dahua webhook : Serial inconnu {$normalise['serial_no']}");
            return null;
        }

        $niveau = $this->determinerNiveau($normalise['type_alerte'], $camera);

        $alerte = AlerteSurveillance::create([
            'tenant_id'   => $camera->tenant_id,
            'camera_id'   => $camera->id,
            'serial_no'   => $normalise['serial_no'],
            'type_alerte' => $normalise['type_alerte'],
            'niveau'      => $niveau,
            'canal'       => $normalise['canal'],
            'payload'     => $payload,
            'survenu_le'  => $normalise['survenu_le'],
        ]);

        $this->notifier($alerte, $camera);

        return $alerte;
    }

    private function normaliserPayload(array $payload): ?array
    {
        if (isset($payload['IpAddress']) && isset($payload['Events'])) {
            $events = $payload['Events'];
            $event  = is_array($events) ? ($events[0] ?? $events) : [];

            return [
                'serial_no'   => $payload['SerialNo'] ?? $payload['IpAddress'],
                'type_alerte' => $event['Code']    ?? 'autre',
                'action'      => $event['Action']  ?? 'Start',
                'canal'       => (string) ($payload['ChannelID'] ?? $event['Index'] ?? '1'),
                'survenu_le'  => $this->parseDate($payload['LocaleTime'] ?? now()->toDateTimeString()),
            ];
        }

        if (isset($payload['eventType']) && isset($payload['deviceId'])) {
            return [
                'serial_no'   => $payload['deviceId'],
                'type_alerte' => $payload['eventType'],
                'action'      => $payload['action'] ?? 'Start',
                'canal'       => (string) ($payload['channelId'] ?? '1'),
                'survenu_le'  => $this->parseDate($payload['dateTime'] ?? now()->toDateTimeString()),
            ];
        }

        if (isset($payload['EventNotificationAlert'])) {
            $event = $payload['EventNotificationAlert'];
            return [
                'serial_no'   => $event['deviceID']  ?? 'unknown',
                'type_alerte' => $event['eventType'] ?? 'autre',
                'action'      => $event['eventState'] ?? 'active',
                'canal'       => (string) ($event['channelID'] ?? '1'),
                'survenu_le'  => $this->parseDate($event['dateTime'] ?? now()->toDateTimeString()),
            ];
        }

        if (isset($payload['code']) && isset($payload['serial'])) {
            return [
                'serial_no'   => $payload['serial'],
                'type_alerte' => $payload['code'],
                'action'      => $payload['action'] ?? 'Start',
                'canal'       => (string) ($payload['channel'] ?? '1'),
                'survenu_le'  => now(),
            ];
        }

        return null;
    }

    private function determinerNiveau(string $typeAlerte, CameraConfig $camera): string
    {
        $toujoursCritiques = [
            'AlarmLocal', 'CrossLineDetection', 'IntrusionDetection',
            'VideoLoss', 'VideoBlind', 'DiskError',
        ];

        if (in_array($typeAlerte, $toujoursCritiques)) {
            return 'critical';
        }

        if ($typeAlerte === 'VideoMotion' && $camera->estHorsHoraires()) {
            return 'critical';
        }

        return AlerteSurveillance::NIVEAUX_PAR_TYPE[$typeAlerte] ?? 'warning';
    }

    private function notifier(AlerteSurveillance $alerte, CameraConfig $camera): void
    {
        $libelleType = AlerteSurveillance::TYPES[$alerte->type_alerte]
            ?? $alerte->type_alerte;

        $heure    = $alerte->survenu_le->format('H:i');
        $lieu     = $camera->localisation ?? $camera->nom;
        $message  = "EduGest Securite : {$libelleType} detecte(e) — {$lieu} a {$heure}.";

        if ($alerte->niveau === 'critical') {
            $message = "ALERTE CRITIQUE EduGest : {$libelleType} — {$lieu} a {$heure}. Verifiez immediatement.";
        }

        $admins = \App\Models\User::where('tenant_id', $camera->tenant_id)
            ->whereHas('role', fn($q) => $q->where('nom', 'admin'))
            ->whereNotNull('telephone')
            ->get();

        $smsSent = false;
        foreach ($admins as $admin) {
            try {
                $this->sms->send($admin->telephone, $message);
                $smsSent = true;
            } catch (\Throwable $e) {
                Log::warning("SMS surveillance echoue admin {$admin->id}: " . $e->getMessage());
            }
        }

        $pushSent = false;
        foreach ($admins as $admin) {
            $pushed = $this->firebase->notifyUser(
                $admin->id,
                $alerte->niveau === 'critical' ? 'Alerte critique' : 'Alerte surveillance',
                $message,
                [
                    'type'      => 'surveillance',
                    'alerte_id' => $alerte->id,
                    'niveau'    => $alerte->niveau,
                ]
            );
            if ($pushed) $pushSent = true;
        }

        $alerte->update([
            'sms_envoye'  => $smsSent,
            'push_envoye' => $pushSent,
        ]);

        Log::info("Surveillance alerte {$alerte->id} notifiee", [
            'sms'   => $smsSent,
            'push'  => $pushSent,
            'niveau'=> $alerte->niveau,
        ]);
    }

    private function parseDate(string $dateStr): Carbon
    {
        try {
            return Carbon::parse($dateStr);
        } catch (\Throwable) {
            return now();
        }
    }
}
