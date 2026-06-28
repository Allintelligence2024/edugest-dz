<?php
namespace App\Http\Requests\Eleve;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateEleveRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'nom'             => 'sometimes|string|min:2|max:100',
            'prenom'          => 'sometimes|string|min:2|max:100',
            'nom_ar'          => 'nullable|string|max:100',
            'prenom_ar'       => 'nullable|string|max:100',
            'date_naissance'  => 'sometimes|date|before:today',
            'lieu_naissance'  => 'sometimes|string|max:100',
            'sexe'            => 'sometimes|in:M,F',
            'niveau_scolaire' => 'sometimes|string|max:50',
            'etablissement'   => 'nullable|string|max:200',
            'wilaya_id'       => 'nullable|exists:wilayas,id',
            'commune_id'      => 'nullable|exists:communes,id',
            'adresse'         => 'nullable|string|max:500',
            'telephone'       => 'nullable|string|max:20',
            'email'           => 'nullable|email|max:100',
            'statut'          => 'sometimes|in:actif,inactif,suspendu',
            'notes'           => 'nullable|string',
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
