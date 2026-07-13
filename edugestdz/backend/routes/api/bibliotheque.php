<?php

use App\Http\Controllers\Api\V1\BibliothequeController;
use Illuminate\Support\Facades\Route;

$protected = ['auth:api', 'resolve.tenant', 'tenant.verify', 'check.subscription', 'zero.trust'];
Route::middleware($protected)->prefix('bibliotheque')->group(function () {
    Route::get('/', [BibliothequeController::class, 'index']);
    Route::post('/', [BibliothequeController::class, 'store']);
    Route::get('/{livre}', [BibliothequeController::class, 'show']);

    Route::post('/scan', [BibliothequeController::class, 'scanner']);

    Route::post('/emprunter', [BibliothequeController::class, 'emprunter']);
    Route::post('/retourner/{emprunt}', [BibliothequeController::class, 'retourner']);
    Route::get('/mes-emprunts', [BibliothequeController::class, 'mesEmprunts']);
});
