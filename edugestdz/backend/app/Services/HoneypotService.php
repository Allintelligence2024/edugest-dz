<?php

namespace App\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class HoneypotService
{
    /**
     * Chemins des leurres, préfixés /api.
     *
     * Lus depuis config('security.honeypot.routes'), qui est la source
     * unique : le fichier de routes enregistre exactement la même liste.
     * Avant cette centralisation, ce service portait sa propre copie en dur
     * et les deux avaient divergé de six entrées.
     *
     * @return list<string>
     */
    public function getRoutesLeurres(): array
    {
        return array_values(array_map(
            static fn (string $chemin): string => '/api' . $chemin,
            config('security.honeypot.routes', [])
        ));
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
