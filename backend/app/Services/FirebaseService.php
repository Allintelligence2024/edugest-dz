<?php

namespace App\Services;

use App\Models\DeviceToken;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FirebaseService
{
    private ?string $serverKey = null;
    private string $fcmUrl = 'https://fcm.googleapis.com/fcm/send';
    private Client $http;
    private ?string $projectId = null;
    private ?string $credentialsPath = null;

    public function __construct()
    {
        $this->serverKey = config('services.firebase.server_key') ?? '';
        $this->http = new Client(['timeout' => 10]);
        $this->projectId = config('services.firebase.project_id');
        $this->credentialsPath = config('services.firebase.credentials');
    }

    public function sendNotification(string|array $tokens, string $title, string $body, array $data = []): bool
    {
        if (empty($this->serverKey)) {
            Log::warning('FirebaseService: FIREBASE_SERVER_KEY manquant');
            return false;
        }

        $tokens = (array) $tokens;
        if (empty($tokens)) return false;

        $payload = [
            'registration_ids' => $tokens,
            'notification' => [
                'title' => $title,
                'body'  => $body,
                'sound' => 'default',
                'badge' => 1,
            ],
            'data'     => array_merge($data, ['click_action' => 'FLUTTER_NOTIFICATION_CLICK']),
            'priority' => 'high',
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'key=' . $this->serverKey,
                'Content-Type'  => 'application/json',
            ])->post($this->fcmUrl, $payload);

            $result = $response->json();
            $success = ($result['success'] ?? 0) > 0;

            if (!$success) {
                Log::warning('Firebase push échoué', $result ?? []);
            }

            return $success;
        } catch (\Throwable $e) {
            Log::error('FirebaseService exception: ' . $e->getMessage());
            return false;
        }
    }

    public function notifyUser(int|string $userId, string $title, string $body, array $data = []): bool
    {
        $tokens = DeviceToken::where('user_id', $userId)
            ->pluck('token')
            ->toArray();

        if (empty($tokens)) return false;
        return $this->sendNotification($tokens, $title, $body, $data);
    }

    public function notifyParentsEleve(string $eleveId, string $title, string $body, array $data = []): void
    {
        $eleve = \App\Models\Eleve::with('parents:id')->find($eleveId);
        if (!$eleve) return;

        foreach ($eleve->parents as $parent) {
            $this->notifyUser($parent->id, $title, $body, $data);
        }
    }

    public function sendToDevice(string $token, array $notification, ?array $data = []): array
    {
        $success = $this->sendNotification(
            $token,
            $notification['title'] ?? '',
            $notification['body'] ?? '',
            $data ?? []
        );
        return ['success' => (int)$success, 'failure' => (int)(!$success), 'errors' => $success ? [] : ['legacy_adapter']];
    }

    public function sendToUser(string $userId, array $notification, ?array $data = []): array
    {
        $success = $this->notifyUser(
            $userId,
            $notification['title'] ?? '',
            $notification['body'] ?? '',
            $data ?? []
        );
        return ['success' => (int)$success, 'failure' => (int)(!$success), 'errors' => $success ? [] : ['legacy_adapter']];
    }

    public function sendToTenant(string $tenantId, array $notification, ?array $data = []): array
    {
        $tokens = DeviceToken::where('tenant_id', $tenantId)->pluck('token')->toArray();
        if (empty($tokens)) {
            return ['success' => 0, 'failure' => 0, 'errors' => []];
        }
        $success = $this->sendNotification($tokens,
            $notification['title'] ?? '',
            $notification['body'] ?? '',
            $data ?? []
        );
        return ['success' => (int)$success, 'failure' => (int)(!$success), 'errors' => $success ? [] : ['legacy_adapter']];
    }

    public function sendToMultiple(array $tokens, array $notification, ?array $data = []): array
    {
        $success = $this->sendNotification(
            $tokens,
            $notification['title'] ?? '',
            $notification['body'] ?? '',
            $data ?? []
        );
        return ['success' => (int)$success, 'failure' => (int)(!$success), 'errors' => $success ? [] : ['legacy_adapter']];
    }

    public function send(array $tokens, array $notification, ?array $data = []): array
    {
        return $this->sendToMultiple($tokens, $notification, $data);
    }

    public function getAccessToken(): ?string
    {
        return Cache::remember('firebase_access_token', 3300, function () {
            if (!$this->credentialsPath || !file_exists($this->credentialsPath)) {
                Log::channel('stack')->error('Firebase: fichier de credentials introuvable');
                return null;
            }

            $credentials = json_decode(file_get_contents($this->credentialsPath), true);
            if (!$credentials || !isset($credentials['client_email'])) {
                Log::channel('stack')->error('Firebase: credentials JSON invalide');
                return null;
            }

            $clientEmail = $credentials['client_email'];
            $privateKey = $credentials['private_key'];

            $now = time();
            $header = [
                'alg' => 'RS256',
                'typ' => 'JWT',
                'kid' => $credentials['private_key_id'] ?? null,
            ];
            $claims = [
                'iss'   => $clientEmail,
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud'   => 'https://oauth2.googleapis.com/token',
                'exp'   => $now + 3600,
                'iat'   => $now,
            ];

            $base64UrlHeader = $this->base64UrlEncode(json_encode($header));
            $base64UrlClaims = $this->base64UrlEncode(json_encode($claims));
            $signatureInput = "{$base64UrlHeader}.{$base64UrlClaims}";

            openssl_sign($signatureInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);
            $base64UrlSignature = $this->base64UrlEncode($signature);

            $jwt = "{$base64UrlHeader}.{$base64UrlClaims}.{$base64UrlSignature}";

            try {
                $response = $this->http->post('https://oauth2.googleapis.com/token', [
                    'form_params' => [
                        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                        'assertion'  => $jwt,
                    ],
                ]);

                $body = json_decode($response->getBody(), true);
                return $body['access_token'] ?? null;
            } catch (GuzzleException $e) {
                Log::channel('stack')->error("Firebase: échec OAuth2 — {$e->getMessage()}");
                return null;
            }
        });
    }

    protected function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
