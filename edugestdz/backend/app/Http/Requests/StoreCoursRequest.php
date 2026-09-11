<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreCoursRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'enseignant_id' => 'required|uuid|exists:enseignants,id',
            'groupe_id'     => 'required|uuid|exists:groupes,id',
            'salle_id'      => 'nullable|uuid|exists:salles,id',
            'jour_semaine'  => 'required|integer|between:0,6',
            'heure_debut'   => 'required|date_format:H:i',
            'heure_fin'     => 'required|date_format:H:i|after:heure_debut',
            'recurrence'    => 'required|in:unique,hebdo,bimensuel,mensuel',
            'date_debut'    => 'required|date',
            'date_fin'      => 'nullable|date|after:date_debut',
            'tarif_seance'  => 'nullable|numeric|min:0|max:99999',
            'forcer'        => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'enseignant_id.required' => 'L\'enseignant est obligatoire',
            'groupe_id.required'     => 'Le groupe est obligatoire',
            'jour_semaine.required'  => 'Le jour est obligatoire',
            'heure_debut.required'   => 'L\'heure de début est obligatoire',
            'heure_fin.after'        => 'L\'heure de fin doit être après l\'heure de début',
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
