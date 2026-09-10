<?php
namespace App\Services;

use App\Models\{Eleve, Enseignant};
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MatchingService
{
    const WEIGHTS = [
        'matiere' => 0.25,
        'niveau' => 0.20,
        'disponibilites' => 0.15,
        'wilaya' => 0.10,
        'tarif' => 0.10,
        'experience' => 0.10,
        'note' => 0.10,
    ];

    private const NIVEAUX_ORDRE = [
        '1AP', '2AP', '3AP', '4AP', '5AP',
        '1AM', '2AM', '3AM', '4AM',
        '1AS', '2AS', '3AS',
        'universitaire', 'autre',
    ];

    public function getSuggestions(string $eleveId, int $limit = 10): array
    {
        $eleve = Eleve::with([
            'inscriptions.groupe.matiere',
            'groupes.matiere',
            'wilaya',
        ])->findOrFail($eleveId);

        $enseignants = Enseignant::with([
            'matieres',
            'wilaya',
            'contrats',
        ])->where('statut', 'actif')->get();

        $suggestions = collect();

        foreach ($enseignants as $enseignant) {
            $result = $this->calculateScore($eleve, $enseignant);
            $suggestions->push([
                'enseignant' => $enseignant,
                'score'      => $result['total'],
                'raisons'    => $result['raisons'],
                'details'    => $result['details'],
            ]);
        }

        $suggestions = $suggestions->sortByDesc('score')->values()->take($limit);

        $llmUsed = false;
        if (config('services.openai.key')) {
            try {
                $suggestions = $this->reorderWithLLM($suggestions, $eleve);
                $llmUsed = true;
            } catch (\Exception $e) {
                Log::warning('Matching LLM reorder failed: ' . $e->getMessage());
            }
        }

        return [
            'data'    => $suggestions->toArray(),
            'llm_used' => $llmUsed,
        ];
    }

    public function calculateScore(Eleve $eleve, Enseignant $enseignant): array
    {
        $matiereScore     = $this->calculateMatiereScore($eleve, $enseignant);
        $niveauScore      = $this->calculateNiveauScore($eleve, $enseignant);
        $dispoScore       = $this->calculateDispoScore($eleve, $enseignant);
        $wilayaScore      = $this->calculateWilayaScore($eleve, $enseignant);
        $tarifScore       = $this->calculateTarifScore($eleve, $enseignant);
        $experienceScore  = $this->calculateExperienceScore($enseignant);
        $noteScore        = $this->calculateNoteScore($enseignant);

        $details = compact(
            'matiereScore', 'niveauScore', 'dispoScore',
            'wilayaScore', 'tarifScore', 'experienceScore', 'noteScore'
        );

        $dimensions = [
            'matiere'        => $matiereScore,
            'niveau'         => $niveauScore,
            'disponibilites' => $dispoScore,
            'wilaya'         => $wilayaScore,
            'tarif'          => $tarifScore,
            'experience'     => $experienceScore,
            'note'           => $noteScore,
        ];

        $total   = 0;
        $raisons = [];

        foreach (self::WEIGHTS as $key => $weight) {
            $score = $dimensions[$key];
            $total += $score * $weight;
            if ($score >= 0.8) {
                $raisons[] = $this->generateRaison($key, $score, $eleve, $enseignant);
            }
        }

        return [
            'total'   => round($total, 4),
            'details' => $details,
            'raisons' => $raisons,
        ];
    }

    // ──────────────────────────────────────────────
    //  Calculs par dimension
    // ──────────────────────────────────────────────

    private function calculateMatiereScore(Eleve $eleve, Enseignant $enseignant): float
    {
        $eleveMatiereIds = $eleve->groupes->pluck('matiere_id')->unique()->toArray();
        if (empty($eleveMatiereIds)) {
            return 0.3;
        }

        $enseignantMatiereIds = $enseignant->matieres->pluck('id')->toArray();
        if (empty($enseignantMatiereIds)) {
            return 0;
        }

        $primaryMatiereId = $eleveMatiereIds[0];

        if (in_array($primaryMatiereId, $enseignantMatiereIds)) {
            return 1.0;
        }

        $common = array_intersect($eleveMatiereIds, $enseignantMatiereIds);
        if (!empty($common)) {
            return 0.5;
        }

        return 0;
    }

    private function calculateNiveauScore(Eleve $eleve, Enseignant $enseignant): float
    {
        $eleveNiveau = $eleve->niveau_scolaire;
        if (!$eleveNiveau) {
            return 0.3;
        }

        $enseignantNiveaux = $enseignant->matieres
            ->pluck('pivot.niveau_scolaire')
            ->unique()
            ->toArray();

        if (empty($enseignantNiveaux)) {
            return 0.3;
        }

        if (in_array($eleveNiveau, $enseignantNiveaux)) {
            return 1.0;
        }

        $eleveIdx = array_search($eleveNiveau, self::NIVEAUX_ORDRE);
        if ($eleveIdx === false) {
            return 0.3;
        }

        foreach ($enseignantNiveaux as $niveau) {
            $ensIdx = array_search($niveau, self::NIVEAUX_ORDRE);
            if ($ensIdx !== false && abs($eleveIdx - $ensIdx) === 1) {
                return 0.7;
            }
        }

        return 0.3;
    }

    private function calculateDispoScore(Eleve $eleve, Enseignant $enseignant): float
    {
        $eleveDispos = $eleve->disponibilites ?? null;
        $ensDispos   = $enseignant->disponibilites ?? [];

        if (empty($eleveDispos) && empty($ensDispos)) {
            return 0.75;
        }

        if (empty($eleveDispos)) {
            return 0.6;
        }

        if (empty($ensDispos)) {
            return 0.3;
        }

        $eleveDispos = is_string($eleveDispos) ? json_decode($eleveDispos, true) : $eleveDispos;
        $ensDispos   = is_string($ensDispos) ? json_decode($ensDispos, true) : $ensDispos;

        $overlap = 0;
        $total   = 0;

        foreach ((array) $eleveDispos as $ed) {
            $jourE = $ed['jour'] ?? $ed['day'] ?? null;
            if (!$jourE) {
                continue;
            }
            $total++;
            foreach ((array) $ensDispos as $ad) {
                $jourA = $ad['jour'] ?? $ad['day'] ?? null;
                if ($jourA && strtolower($jourA) === strtolower($jourE)) {
                    $overlap++;
                    break;
                }
            }
        }

        if ($total === 0) {
            return 0.5;
        }

        return round($overlap / $total, 2);
    }

    private function calculateWilayaScore(Eleve $eleve, Enseignant $enseignant): float
    {
        $eleveWilayaId = $eleve->wilaya_id;
        $ensWilayaId   = $enseignant->wilaya_id;

        if (!$eleveWilayaId || !$ensWilayaId) {
            return 0.5;
        }

        if ((int) $eleveWilayaId === (int) $ensWilayaId) {
            return 1.0;
        }

        $eleveRegion = $this->getWilayaRegion((int) $eleveWilayaId);
        $ensRegion   = $this->getWilayaRegion((int) $ensWilayaId);

        if ($eleveRegion === $ensRegion) {
            return 0.5;
        }

        return 0.2;
    }

    private function calculateTarifScore(Eleve $eleve, Enseignant $enseignant): float
    {
        $budget = $eleve->budget_mensuel ?? null;
        $tarif  = $enseignant->taux_horaire ?? 0;

        if ($tarif <= 0) {
            return 0.5;
        }

        if ($budget === null) {
            return 0.75;
        }

        if ($tarif <= $budget) {
            return 1.0;
        }

        $ratio = $tarif / $budget;
        if ($ratio >= 2.0) {
            return 0;
        }

        return round(1.0 - (($ratio - 1.0) / 1.0), 2);
    }

    private function calculateExperienceScore(Enseignant $enseignant): float
    {
        $annees = (int) ($enseignant->experience_annees ?? 0);
        return round(min(1.0, $annees / 10), 2);
    }

    private function calculateNoteScore(Enseignant $enseignant): float
    {
        $note = $enseignant->note_interne ?? null;
        if ($note && is_numeric($note)) {
            return round(min(1.0, max(0, (float) $note / 5)), 2);
        }

        return 0.7;
    }

    // ──────────────────────────────────────────────
    //  Raisons
    // ──────────────────────────────────────────────

    private function generateRaison(string $dimension, float $score, Eleve $eleve, Enseignant $enseignant): string
    {
        return match ($dimension) {
            'matiere' => $this->raisonMatiere($eleve, $enseignant),
            'niveau'  => $this->raisonNiveau($eleve, $enseignant),
            'disponibilites' => 'Disponibilités compatibles',
            'wilaya'  => $this->raisonWilaya($eleve, $enseignant),
            'tarif'   => $this->raisonTarif($enseignant),
            'experience' => sprintf('%d années d\'expérience', $enseignant->experience_annees ?? 0),
            'note'    => 'Bonne évaluation interne',
            default   => '',
        };
    }

    private function raisonMatiere(Eleve $eleve, Enseignant $enseignant): string
    {
        $eleveMatieres = $eleve->groupes->pluck('matiere.nom_fr')->unique()->filter()->values();
        $ensMatieres   = $enseignant->matieres->pluck('nom_fr')->filter()->values();

        $common = $eleveMatieres->intersect($ensMatieres);
        if ($common->isNotEmpty()) {
            return 'Même matière : ' . $common->implode(', ');
        }

        return 'Matière compatible';
    }

    private function raisonNiveau(Eleve $eleve, Enseignant $enseignant): string
    {
        $niveau = $eleve->niveau_scolaire;
        $ensNiveaux = $enseignant->matieres->pluck('pivot.niveau_scolaire')->unique()->filter();

        if ($ensNiveaux->contains($niveau)) {
            return "Même niveau : $niveau";
        }

        return "Niveau proche";
    }

    private function raisonWilaya(Eleve $eleve, Enseignant $enseignant): string
    {
        $wilayaNom = $enseignant->wilaya?->nom_fr;
        if (!$wilayaNom) {
            return 'Même wilaya';
        }
        return "Même wilaya : $wilayaNom";
    }

    private function raisonTarif(Enseignant $enseignant): string
    {
        $tarif = $enseignant->taux_horaire ?? 0;
        return "Tarif compatible : {$tarif} DZD/séance";
    }

    // ──────────────────────────────────────────────
    //  LLM Reorder
    // ──────────────────────────────────────────────

    public function reorderWithLLM($suggestions, Eleve $eleve)
    {
        $apiKey = config('services.openai.key');
        $model  = config('services.openai.model', 'gpt-4o-mini');
        $timeout = config('services.openai.timeout', 15);

        $eleveDesc = sprintf(
            "Niveau: %s | Wilaya: %s | Budget: %s DZD",
            $eleve->niveau_scolaire ?? 'Non spécifié',
            $eleve->wilaya?->nom_fr ?? 'Non spécifiée',
            $eleve->budget_mensuel ?? 'Non défini'
        );

        $profsDesc = '';
        foreach ($suggestions as $i => $s) {
            $ens = $s['enseignant'];
            $profsDesc .= sprintf(
                "%d. %s %s (score: %.2f, tarif: %d DZD, wilaya: %s, raisons: %s)\n",
                $i + 1,
                $ens->prenom ?? '',
                $ens->nom ?? '',
                $s['score'],
                $ens->taux_horaire ?? 0,
                $ens->wilaya?->nom_fr ?? 'N/A',
                implode('; ', $s['raisons'] ?? [])
            );
        }

        $prompt = <<<PROMPT
Tu es un expert en matching éducatif pour une plateforme algérienne de cours particuliers (EduGest DZ).

Voici la liste des professeurs disponibles pour un élève. Classe-les par pertinence en justifiant brièvement.

ÉLÈVE :
{$eleveDesc}

PROFESSEURS :
{$profsDesc}

Réponds UNIQUEMENT avec une liste numérotée dans le même ordre que les professeurs ci-dessus, suivie d'une brève justification d'une phrase par professeur en français.
Chaque ligne doit être au format: "NouvelIndex. Justification en français"
PROMPT;

        $response = Http::timeout($timeout)
            ->withToken($apiKey)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model'       => $model,
                'messages'    => [
                    ['role' => 'system', 'content' => 'Tu es un assistant de matching éducatif. Réponds en français.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.3,
                'max_tokens'  => 1024,
            ]);

        if (!$response->successful()) {
            Log::warning('LLM API returned error', ['status' => $response->status(), 'body' => $response->body()]);
            return $suggestions;
        }

        $content = $response->json('choices.0.message.content');
        if (!$content) {
            return $suggestions;
        }

        return $this->parseLLMResponse($content, $suggestions);
    }

    private function parseLLMResponse(string $content, $suggestions)
    {
        $lines = explode("\n", trim($content));
        $indices = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (preg_match('/^(\d+)\./', $line, $m)) {
                $indices[] = (int) $m[1] - 1;
            }
        }

        if (count($indices) !== count($suggestions) || count(array_unique($indices)) !== count($suggestions)) {
            $indices = range(0, count($suggestions) - 1);
        }

        $reordered = collect();
        $justifications = $this->extractJustifications($content);

        foreach ($indices as $newIdx => $oldIdx) {
            if (isset($suggestions[$oldIdx])) {
                $item = $suggestions[$oldIdx];
                $item['justification_llm'] = $justifications[$oldIdx] ?? '';
                $reordered->push($item);
            }
        }

        return $reordered;
    }

    private function extractJustifications(string $content): array
    {
        $justifications = [];
        $lines = explode("\n", trim($content));

        foreach ($lines as $line) {
            $line = trim($line);
            if (preg_match('/^\d+\.\s*(.+)/', $line, $m)) {
                $justifications[] = trim($m[1]);
            }
        }

        return $justifications;
    }

    // ──────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────

    private function getWilayaRegion(int $wilayaId): string
    {
        return match (true) {
            $wilayaId >= 1  && $wilayaId <= 9  => 'nord_centre',
            $wilayaId >= 10 && $wilayaId <= 15 => 'nord_est',
            $wilayaId >= 16 && $wilayaId <= 22 => 'nord_ouest',
            $wilayaId >= 23 && $wilayaId <= 29 => 'hauts_plateaux_est',
            $wilayaId >= 30 && $wilayaId <= 33 => 'hauts_plateaux_ouest',
            $wilayaId >= 34 && $wilayaId <= 39 => 'sud_est',
            $wilayaId >= 40 && $wilayaId <= 48 => 'sud_ouest',
            default => 'autre',
        };
    }

}
