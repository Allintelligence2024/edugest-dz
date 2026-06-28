<?php
namespace App\Services;

use App\Models\DeviceToken;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FirebaseService
{
    protected Client $http;
    protected string $projectId;
    protected ?string $credentialsPath;

    public function __construct()
    {
        $this->http = new Client(['timeout' => 10]);
        $this->projectId = config('services.firebase.project_id');
        $this->credentialsPath = config('services.firebase.credentials');
    }

    public function sendToDevice(string $token, array $notification, ?array $data = []): array
    {
        return $this->send([$token], $notification, $data);
    }

    public function sendToUser(string $userId, array $notification, ?array $data = []): array
    {
        $tokens = DeviceToken::where('user_id', $userId)->pluck('token')->toArray();
        if (empty($tokens)) {
            return ['success' => 0, 'failure' => 0, 'errors' => []];
        }
        return $this->send($tokens, $notification, $data);
    }

    public function sendToTenant(string $tenantId, array $notification, ?array $data = []): array
    {
        $tokens = DeviceToken::where('tenant_id', $tenantId)->pluck('token')->toArray();
        if (empty($tokens)) {
            return ['success' => 0, 'failure' => 0, 'errors' => []];
        }
        return $this->send($tokens, $notification, $data);
    }

    public function sendToMultiple(array $tokens, array $notification, ?array $data = []): array
    {
        return $this->send($tokens, $notification, $data);
    }

    protected function send(array $tokens, array $notification, ?array $data = []): array
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            Log::channel('stack')->error('Firebase: impossible d\'obtenir le token d\'accès');
            return ['success' => 0, 'failure' => count($tokens), 'errors' => ['auth_failed']];
        }

        $success = 0;
        $failure = 0;
        $errors = [];

        foreach ($tokens as $token) {
            $payload = [
                'message' => [
                    'token' => $token,
                    'notification' => [
                        'title' => $notification['title'] ?? '',
                        'body'  => $notification['body'] ?? '',
                    ],
                ],
            ];

            if (!empty($data)) {
                $payload['message']['data'] = array_map('strval', $data);
            }

            try {
                $response = $this->http->post(
                    "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send",
                    [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $accessToken,
                            'Content-Type'  => 'application/json',
                        ],
                        'json' => $payload,
                    ]
                );

                if ($response->getStatusCode() === 200) {
                    $success++;
                } else {
                    $failure++;
                    $errors[] = "HTTP {$response->getStatusCode()}";
                }
            } catch (GuzzleException $e) {
                $failure++;
                $code = $e->getCode();
                $errors[] = $e->getMessage();

                if ($code === 404) {
                    Log::channel('stack')->warning("Firebase: token invalide/supprimé — {$token}");
                    DeviceToken::where('token', $token)->delete();
                } else {
                    Log::channel('stack')->error("Firebase: échec d'envoi — {$e->getMessage()}");
                }
            }
        }

        return compact('success', 'failure', 'errors');
    }

    protected function getAccessToken(): ?string
    {
        return Cache::remember('firebase_access_token', 3300, function () {
            $credentialsPath = $this->credentialsPath;
            if (!$credentialsPath || !file_exists($credentialsPath)) {
                Log::channel('stack')->error('Firebase: fichier de credentials introuvable');
                return null;
            }

            $credentials = json_decode(file_get_contents($credentialsPath), true);
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
