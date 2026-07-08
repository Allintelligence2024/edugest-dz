<?php

namespace App\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class HoneypotService
{
    private array $routesLeurres = [
        '/api/v1/phpinfo',
        '/api/v1/server-status',
        '/api/v1/actuator',
        '/api/v1/metrics',
        '/api/v1/.env',
        '/api/v1/admin',
        '/api/v1/debug',
        '/api/v1/backup',
        '/api/v1/config',
        '/api/v1/dump',
    ];

    public function getRoutesLeurres(): array
    {
        return $this->routesLeurres;
    }

    public function declencherRouteLeurre(): JsonResponse
    {
        Log::warning('Honeypot: route leurre declenchee', [
            'ip' => request()->ip(),
            'path' => request()->path(),
            'method' => request()->method(),
            'user_agent' => request()->userAgent(),
        ]);

        return response()->json([
            'message' => 'Not Found.',
        ], 404);
    }

    public function injecterCanaires(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value) && count($value) >= 5) {
                $canaryKey = '_canary_' . Str::random(8);
                $data[$key][$canaryKey] = [
                    'id' => Str::uuid()->toString(),
                    'type' => 'honeypot',
                    'watermark' => hash('sha256', $key . config('app.key')),
                    'created_at' => now()->toIso8601String(),
                ];
            }
        }

        return $data;
    }
}
