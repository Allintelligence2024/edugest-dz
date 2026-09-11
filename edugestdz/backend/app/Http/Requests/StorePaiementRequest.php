<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaiementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'eleve_id' => 'required|uuid|exists:eleves,id',
            'montant' => 'required|numeric|min:0',
            'mode_paiement' => 'required|string|in:especes,cheque,virement,carte,baridimob',
            'date_paiement' => 'required|date',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
