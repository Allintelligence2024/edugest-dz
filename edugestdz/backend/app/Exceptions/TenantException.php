<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class TenantException extends Exception
{
    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage() ?: 'Erreur liée au tenant',
            'code' => $this->getCode() ?: 'TENANT_ERROR',
        ], 403);
    }
}
