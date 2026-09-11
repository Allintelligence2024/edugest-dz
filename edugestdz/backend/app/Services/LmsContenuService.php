<?php

namespace App\Services;

use App\Models\LmsChapitre;
use App\Models\LmsCours;
use App\Models\LmsLecon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class LmsContenuService
{
    /**
     * @return array{chapitre: LmsChapitre}
     */
    public function storeChapitre(string $coursId, array $data): array
    {
        $v = Validator::make($data, [
            'titre'      => 'required|string|max:200',
            'description'=> 'nullable|string',
            'ordre'      => 'integer|min:1',
        ])->validate();

        $ordre = $v['ordre'] ?? LmsChapitre::where('cours_id', $coursId)->max('ordre') + 1;

        /** @var array{titre: mixed, description?: mixed, ordre: mixed, cours_id: mixed} $attributes */
        $attributes = [...$v, 'cours_id' => $coursId, 'ordre' => $ordre];
        $chapitre = LmsChapitre::create($attributes);
        LmsCours::find($coursId)?->increment('nb_chapitres');

        return ['chapitre' => $chapitre];
    }

    /**
     * @return array{message: string}
     */
    public function deleteChapitre(string $id): array
    {
        $chapitre = LmsChapitre::findOrFail($id);
        LmsCours::find($chapitre->cours_id)?->decrement('nb_chapitres');
        $chapitre->delete();

        return ['message' => 'Chapitre supprimé'];
    }

    /**
     * @return array{lecon: LmsLecon}
     */
    public function storeLecon(string $chapitreId, array $data): array
    {
        $v = Validator::make($data, [
            'titre'         => 'required|string|max:200',
            'type'          => 'required|in:texte,video,pdf,audio,lien,quiz,devoir',
            'contenu'       => 'nullable|string',
            'ressource_url' => 'nullable|string|max:500',
            'ressource_nom' => 'nullable|string|max:200',
            'duree_minutes' => 'nullable|integer|min:1',
            'ordre'         => 'integer|min:1',
            'gratuite'      => 'boolean',
        ])->validate();

        $ordre = $v['ordre'] ?? LmsLecon::where('chapitre_id', $chapitreId)->max('ordre') + 1;

        /** @var array{titre: mixed, type: mixed, contenu?: mixed, ressource_url?: mixed, ressource_nom?: mixed, duree_minutes?: mixed, ordre: mixed, gratuite?: mixed, chapitre_id: mixed} $attributes */
        $attributes = [...$v, 'chapitre_id' => $chapitreId, 'ordre' => $ordre];

        return ['lecon' => LmsLecon::create($attributes)];
    }

    /**
     * @return array{lecon: LmsLecon}
     */
    public function uploadFichierLecon(string $id, Request $request): array
    {
        $request->validate(['fichier' => 'required|file|max:51200']);
        $lecon   = LmsLecon::findOrFail($id);
        $path    = $request->file('fichier')->store("lms/lecons/{$lecon->id}", 'public');
        $lecon->update([
            'ressource_url' => Storage::url($path),
            'ressource_nom' => $request->file('fichier')->getClientOriginalName(),
        ]);

        return ['lecon' => $lecon->fresh()];
    }
}
