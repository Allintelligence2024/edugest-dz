<?php

namespace App\Services;

use App\Models\LmsQuestion;
use App\Models\LmsQuiz;
use App\Models\LmsTentativeQuiz;
use Illuminate\Support\Facades\Validator;

class LmsQuizService
{
    public function __construct(private LmsService $lms) {}

    /**
     * @return array{quiz: LmsQuiz}
     */
    public function storeQuiz(string $leconId, array $data): array
    {
        $v = Validator::make($data, [
            'titre'               => 'required|string|max:200',
            'duree_minutes'       => 'integer|min:1',
            'seuil_reussite'      => 'integer|min:1|max:100',
            'nb_tentatives_max'   => 'integer|min:1|max:10',
            'correction_immediate'=> 'boolean',
            'ordre_aleatoire'     => 'boolean',
        ])->validate();

        /** @var array{titre: mixed, duree_minutes?: mixed, seuil_reussite?: mixed, nb_tentatives_max?: mixed, correction_immediate?: mixed, ordre_aleatoire?: mixed, lecon_id: mixed} $attributes */
        $attributes = [...$v, 'lecon_id' => $leconId];

        return ['quiz' => LmsQuiz::create($attributes)];
    }

    /**
     * @return array{question: LmsQuestion}
     */
    public function storeQuestion(string $quizId, array $data): array
    {
        $v = Validator::make($data, [
            'type'        => 'required|in:qcm,vrai_faux,reponse_courte',
            'enonce'      => 'required|string',
            'options'     => 'required|array',
            'explication' => 'nullable|string',
            'points'      => 'integer|min:1',
            'ordre'       => 'integer|min:1',
        ])->validate();

        $ordre = $v['ordre'] ?? LmsQuestion::where('quiz_id', $quizId)->max('ordre') + 1;

        /** @var array{type: mixed, enonce: mixed, options: mixed, explication?: mixed, points?: mixed, ordre: mixed, quiz_id: mixed} $attributes */
        $attributes = [...$v, 'quiz_id' => $quizId, 'ordre' => $ordre];
        $question = LmsQuestion::create($attributes);
        LmsQuiz::find($quizId)?->increment('nb_questions');

        return ['question' => $question];
    }

    /**
     * @return array{tentative: LmsTentativeQuiz, message: string}|array{error: string}
     */
    public function passerQuiz(string $quizId, array $data): array
    {
        $v = Validator::make($data, [
            'inscription_id'  => 'required|uuid|exists:lms_inscriptions,id',
            'reponses'        => 'required|array',
            'duree_secondes'  => 'integer|min:0',
        ])->validate();

        try {
            $tentative = $this->lms->soumettreTentativeQuiz(
                $quizId,
                auth('api')->id(),
                $v['inscription_id'],
                $v['reponses'],
                $v['duree_secondes'] ?? 0
            );

            return [
                'tentative' => $tentative,
                'message'   => $tentative->reussi ? '✅ Quiz réussi !' : '❌ Quiz non réussi — réessayez',
            ];
        } catch (\RuntimeException $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
