<?php

namespace App\Services;

use App\Models\LmsDevoir;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class LmsDevoirService
{
    /**
     * @return array{devoir: LmsDevoir}
     */
    public function soumettreDevoir(string $leconId, Request $request): array
    {
        $request->validate([
            'inscription_id'    => 'required|uuid|exists:lms_inscriptions,id',
            'fichier'           => 'nullable|file|max:20480',
            'commentaire_eleve' => 'nullable|string|max:1000',
        ]);

        $cheminFichier = null;
        $nomFichier    = null;

        if ($request->hasFile('fichier')) {
            $file          = $request->file('fichier');
            $cheminFichier = $file->store("lms/devoirs/{$leconId}", 'public');
            $nomFichier    = $file->getClientOriginalName();
        }

        $devoir = LmsDevoir::updateOrCreate(
            ['lecon_id' => $leconId, 'eleve_id' => auth('api')->id()],
            [
                'inscription_id'    => $request->inscription_id,
                'fichier_url'       => $cheminFichier ? Storage::url($cheminFichier) : null,
                'fichier_nom'       => $nomFichier,
                'commentaire_eleve' => $request->commentaire_eleve,
                'statut'            => 'soumis',
                'soumis_le'         => now(),
            ]
        );

        return ['devoir' => $devoir];
    }

    /**
     * @return array{devoir: LmsDevoir}
     */
    public function corrigerDevoir(string $devoirId, array $data): array
    {
        $v = Validator::make($data, [
            'note'                => 'required|numeric|min:0|max:20',
            'feedback_enseignant' => 'nullable|string|max:1000',
        ])->validate();

        $devoir = LmsDevoir::findOrFail($devoirId);

        /** @var array{note: mixed, feedback_enseignant?: mixed, statut: mixed, corrige_par: mixed, corrige_le: mixed} $attributes */
        $attributes = [
            ...$v,
            'statut'      => 'corrige',
            'corrige_par' => auth('api')->id(),
            'corrige_le'  => now(),
        ];
        $devoir->update($attributes);

        return ['devoir' => $devoir->fresh()];
    }
}
