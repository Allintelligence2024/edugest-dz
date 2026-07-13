<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ScanLivreService
{
    private string $apiKey;
    private string $apiUrl = 'https://vision.googleapis.com/v1/images:annotate';

    public function __construct()
    {
        $this->apiKey = config('services.google.vision_api_key') ?? '';
    }

    public function estConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    public function analyserImage(string $imageData): array
    {
        if (!$this->estConfigured()) {
            return [
                'success' => false,
                'error'   => 'GOOGLE_VISION_API_KEY non configuré',
            ];
        }

        $payload = $this->buildPayload($imageData);

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post("{$this->apiUrl}?key={$this->apiKey}", $payload);

            if ($response->failed()) {
                Log::error('Google Vision API error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

                return [
                    'success' => false,
                    'error'   => "Erreur Vision API: {$response->status()}",
                ];
            }

            return $this->extraireResultats($response->json());
        } catch (\Exception $e) {
            Log::error('Vision API exception', ['message' => $e->getMessage()]);

            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    private function buildPayload(string $imageData): array
    {
        if (filter_var($imageData, FILTER_VALIDATE_URL)) {
            $image = ['source' => ['imageUri' => $imageData]];
        } else {
            $image = ['content' => $imageData];
        }

        return [
            'requests' => [
                [
                    'image'    => $image,
                    'features' => [
                        ['type' => 'TEXT_DETECTION', 'maxResults' => 10],
                    ],
                ],
            ],
        ];
    }

    private function extraireResultats(array $json): array
    {
        $annotations = $json['responses'][0]['fullTextAnnotation'] ?? null;

        if (!$annotations) {
            return [
                'success'   => true,
                'texte'     => '',
                'titre'     => null,
                'auteur'    => null,
                'isbn'      => null,
                'confiance' => 0,
            ];
        }

        $texte = $annotations['text'] ?? '';
        $confiance = $annotations['pages'][0]['property']['confidence'] ?? 0;

        $titre  = $this->extraireTitre($texte);
        $auteur = $this->extraireAuteur($texte);
        $isbn   = $this->extraireIsbn($texte);

        return [
            'success'   => true,
            'texte'     => $texte,
            'titre'     => $titre,
            'auteur'    => $auteur,
            'isbn'      => $isbn,
            'confiance' => round($confiance * 100, 1),
        ];
    }

    private function extraireTitre(string $texte): ?string
    {
        $lignes = array_filter(array_map('trim', explode("\n", $texte)));

        foreach ($lignes as $ligne) {
            if (strlen($ligne) < 3) continue;
            if (preg_match('/^\d{10,13}$/', $ligne)) continue;
            if (preg_match('/^(auteur|author|par|by)/i', $ligne)) continue;

            return $ligne;
        }

        return null;
    }

    private function extraireAuteur(string $texte): ?string
    {
        if (preg_match('/(?:auteur|author|par|by)\s*:\s*(.+)/i', $texte, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    private function extraireIsbn(string $texte): ?string
    {
        if (preg_match('/(?:ISBN[\s:-]*)?(\d[\d\s-]{9,16}\d)/i', $texte, $m)) {
            $isbn = preg_replace('/[\s-]/', '', $m[1]);
            if (strlen($isbn) === 10 || strlen($isbn) === 13) {
                return $isbn;
            }
        }

        return null;
    }
}
