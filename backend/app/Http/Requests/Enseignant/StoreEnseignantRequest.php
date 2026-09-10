<?php
namespace App\Http\Requests\Enseignant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreEnseignantRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'nom'               => 'required|string|min:2|max:100',
            'prenom'            => 'required|string|min:2|max:100',
            'nom_ar'            => 'nullable|string|max:100',
            'prenom_ar'         => 'nullable|string|max:100',
            'sexe'              => 'required|in:M,F',
            'date_naissance'    => 'nullable|date|before:today',
            'lieu_naissance'    => 'nullable|string|max:100',

            'telephone'         => 'required|string|min:9|max:20',
            'email'             => 'required|email|max:150|unique:users,email',
            'adresse'           => 'nullable|string|max:500',
            'wilaya_id'         => 'nullable|exists:wilayas,id',

            'diplome'           => 'nullable|string|max:200',
            'specialite'        => 'nullable|string|max:200',
            'experience_annees' => 'nullable|integer|min:0|max:50',
            'type_contrat'      => 'required|in:CDI,CDD,vacataire,freelance,stagiaire',
            'date_embauche'     => 'nullable|date',
            'salaire_base'      => 'nullable|numeric|min:0',
            'taux_horaire'      => 'nullable|numeric|min:0',

            'rib_bancaire'      => 'nullable|string|max:25',
            'banque'            => 'nullable|string|max:100',
            'num_cnas'          => 'nullable|string|max:20',

            'matieres'                      => 'nullable|array',
            'matieres.*.matiere_id'         => 'required_with:matieres|uuid|exists:matieres,id',
            'matieres.*.niveau_scolaire'    => 'required_with:matieres|string',
            'matieres.*.est_principal'      => 'nullable|boolean',

            'password'          => 'nullable|string|min:8',
        ];
    }

    public function messages(): array
    {
        return [
            'nom.required'           => 'Le nom est obligatoire',
            'prenom.required'        => 'Le prénom est obligatoire',
            'telephone.required'     => 'Le téléphone est obligatoire',
            'email.required'         => 'L\'email est obligatoire',
            'email.unique'           => 'Cet email est déjà utilisé',
            'type_contrat.required'  => 'Le type de contrat est obligatoire',
            'sexe.required'          => 'Le sexe est obligatoire',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'error'   => [
                'code'    => 'VALIDATION_ERROR',
                'message' => 'Données invalides',
                'details' => $validator->errors(),
            ]
        ], 422));
    }
}
