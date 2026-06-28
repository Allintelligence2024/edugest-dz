<?php
namespace App\Http\Requests\Enseignant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateEnseignantRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $id = $this->route('enseignant');
        $enseignant = \App\Models\Enseignant::find($id);

        return [
            'nom'               => 'sometimes|string|min:2|max:100',
            'prenom'            => 'sometimes|string|min:2|max:100',
            'telephone'         => 'sometimes|string|min:9|max:20',
            'email'             => ['sometimes', 'email', 'max:150',
                Rule::unique('users')->ignore($enseignant?->user_id)],
            'wilaya_id'         => 'nullable|exists:wilayas,id',
            'adresse'           => 'nullable|string|max:500',
            'diplome'           => 'nullable|string|max:200',
            'specialite'        => 'nullable|string|max:200',
            'type_contrat'      => 'sometimes|in:CDI,CDD,vacataire,freelance,stagiaire',
            'salaire_base'      => 'nullable|numeric|min:0',
            'taux_horaire'      => 'nullable|numeric|min:0',
            'rib_bancaire'      => 'nullable|string|max:25',
            'statut'            => 'sometimes|in:actif,congé,suspendu,démissionné',
            'matieres'          => 'nullable|array',
            'matieres.*.matiere_id'         => 'required_with:matieres|uuid|exists:matieres,id',
            'matieres.*.niveau_scolaire'    => 'required_with:matieres|string',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'error'   => ['code' => 'VALIDATION_ERROR', 'details' => $validator->errors()]
        ], 422));
    }
}
