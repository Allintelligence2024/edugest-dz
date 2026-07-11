<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class ModuleDesactiveException extends Exception
{
    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => "Le module '{$this->getMessage()}' n'est pas activé pour votre établissement.",
            'code' => 'MODULE_DESACTIVE',
        ], 403);
    }
}
