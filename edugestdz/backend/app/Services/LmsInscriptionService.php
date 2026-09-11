<?php

namespace App\Services;

use App\Models\LmsInscription;
use Illuminate\Support\Facades\Validator;

class LmsInscriptionService
{
    public function __construct(private LmsService $lms) {}

    /**
     * @return array{inscription: LmsInscription}
     */
    public function inscrire(array $data): array
    {
        $v = Validator::make($data, [
            'cours_id' => 'required|uuid|exists:lms_cours,id',
            'eleve_id' => 'required|uuid|exists:eleves,id',
        ])->validate();

        return ['inscription' => $this->lms->inscrireEleve($v['cours_id'], $v['eleve_id'])];
    }

    /**
     * @return array{inscriptions: mixed}
     */
    public function inscriptionEleve(string $eleveId): array
    {
        $inscriptions = LmsInscription::where('eleve_id', $eleveId)
            ->with('cours:id,titre,matiere,image_url,nb_lecons,seuil_completion')
            ->orderByDesc('updated_at')
            ->get();

        return ['inscriptions' => $inscriptions];
    }

    /**
     * @return array{result: array, message: string}
     */
    public function marquerLecon(string $inscriptionId, string $leconId, array $data): array
    {
        $v = Validator::make($data, ['temps_secondes' => 'integer|min:0'])->validate();

        $result = $this->lms->marquerLeconComplete($inscriptionId, $leconId, $v['temps_secondes'] ?? 0);

        return [
            'result'  => $result,
            'message' => $result['cours_complete']
                ? '🎓 Cours terminé ! Certificat généré.'
                : "Progression : {$result['progression_pct']}%",
        ];
    }

    /**
     * @return array{inscription: LmsInscription, chapitres: mixed, progression_pct: mixed, certificat_url: mixed}
     */
    public function progressionEleve(string $inscriptionId): array
    {
        $inscription = LmsInscription::with([
            'cours.chapitres.lecons',
            'progressions',
        ])->findOrFail($inscriptionId);

        $completees = $inscription->progressions->where('completee', true)->pluck('lecon_id')->toArray();

        $chapitres = $inscription->cours->chapitres->map(fn($ch) => [
            'id'     => $ch->id,
            'titre'  => $ch->titre,
            'lecons' => $ch->lecons->map(fn($l) => [
                'id'        => $l->id,
                'titre'     => $l->titre,
                'type'      => $l->type,
                'completee' => in_array($l->id, $completees),
                'duree'     => $l->duree_minutes,
            ]),
        ]);

        return [
            'inscription'    => $inscription,
            'chapitres'      => $chapitres,
            'progression_pct'=> $inscription->progression_pct,
            'certificat_url' => $inscription->certificat_url,
        ];
    }
}
