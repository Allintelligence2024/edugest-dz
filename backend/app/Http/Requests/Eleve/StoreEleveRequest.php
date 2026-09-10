<?php
namespace App\Http\Requests\Eleve;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreEleveRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'nom'              => 'required|string|min:2|max:100',
            'prenom'           => 'required|string|min:2|max:100',
            'nom_ar'           => 'nullable|string|max:100',
            'prenom_ar'        => 'nullable|string|max:100',
            'date_naissance'   => 'required|date|before:today',
            'lieu_naissance'   => 'required|string|max:100',
            'sexe'             => 'required|in:M,F',
            'niveau_scolaire'  => 'required|string|max:50',
            'nationalite'      => 'nullable|string|max:50',
            'etablissement'    => 'nullable|string|max:200',
            'ecole_origine'    => 'nullable|string|max:200',
            'wilaya_id'        => 'nullable|exists:wilayas,id',
            'commune_id'       => 'nullable|exists:communes,id',
            'adresse'          => 'nullable|string|max:500',
            'telephone'        => 'nullable|string|max:20',
            'email'            => 'nullable|email|max:100',
            'notes'            => 'nullable|string',

            'parents'                   => 'nullable|array',
            'parents.*.nom'             => 'required_with:parents|string|max:100',
            'parents.*.prenom'          => 'required_with:parents|string|max:100',
            'parents.*.lien'            => 'required_with:parents|string|max:50',
            'parents.*.telephone_1'     => 'required_with:parents|string|max:20',
            'parents.*.telephone_2'     => 'nullable|string|max:20',
            'parents.*.email'           => 'nullable|email|max:100',
            'parents.*.profession'      => 'nullable|string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'nom.required'             => 'Le nom est obligatoire',
            'prenom.required'          => 'Le prénom est obligatoire',
            'date_naissance.required'  => 'La date de naissance est obligatoire',
            'niveau_scolaire.required' => 'Le niveau scolaire est obligatoire',
            'sexe.required'            => 'Le sexe est obligatoire',
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
