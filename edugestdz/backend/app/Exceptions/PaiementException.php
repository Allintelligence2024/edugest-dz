<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class PaiementException extends Exception
{
    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage() ?: 'Erreur de paiement',
            'code' => 'PAIEMENT_ERROR',
        ], 422);
    }
}
