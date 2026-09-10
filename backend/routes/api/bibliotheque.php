<?php

use App\Http\Controllers\Api\V1\BibliothequeController;
use Illuminate\Support\Facades\Route;

// Pentest Sprint 6 (C2) : jwt.blacklist rend enfin effectifs le verrouillage
// d'urgence (global_tokens_invalidated_at) et la révocation de jetons —
// le middleware est fail-open : sans incident, coût = 1 lecture cache.
$protected = ['auth:api', 'jwt.blacklist', 'resolve.tenant', 'tenant.verify', 'check.subscription', 'zero.trust'];
Route::middleware($protected)->prefix('bibliotheque')->group(function () {
    Route::get('/', [BibliothequeController::class, 'index']);
    Route::post('/', [BibliothequeController::class, 'store']);
    Route::get('/{livre}', [BibliothequeController::class, 'show']);

    Route::post('/scan', [BibliothequeController::class, 'scanner']);

    Route::post('/emprunter', [BibliothequeController::class, 'emprunter']);
    Route::post('/retourner/{emprunt}', [BibliothequeController::class, 'retourner']);
    Route::get('/mes-emprunts', [BibliothequeController::class, 'mesEmprunts']);
});
