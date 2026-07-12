<?php
// routes/api/core.php
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// Core — Eleves, Parents, Enseignants, Contrats
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\{
    SearchController,
    EleveController,
    ParentController,
    EnseignantController,
    ContratController,
};

$protected = ['auth:api', 'resolve.tenant', 'tenant.verify', 'check.subscription', 'zero.trust'];
Route::middleware($protected)->group(function () {

    // ── Recherche globale ──
    Route::get('search', SearchController::class);

    // ── Élèves ──
    Route::apiResource('eleves', EleveController::class);
    Route::prefix('eleves')->group(function () {
        Route::post('{id}/photo',           [EleveController::class, 'uploadPhoto']);
        Route::get('{id}/notes',            [EleveController::class, 'notes']);
        Route::get('{id}/presences',        [EleveController::class, 'presences']);
        Route::get('{id}/paiements',        [EleveController::class, 'paiements']);
        Route::get('{id}/bulletins',        [EleveController::class, 'bulletins']);
        Route::get('{id}/statistiques',     [EleveController::class, 'statistiques']);
        Route::post('{id}/inscription',     [EleveController::class, 'inscrire']);
        Route::post('import',               [EleveController::class, 'import']);
        Route::get('export',                [EleveController::class, 'export']);
        Route::get('export/excel',          [EleveController::class, 'exportExcel'])->middleware('throttle:exports');
    });

    // ── QR Code élève ──
    Route::get('eleves/{id}/qrcode', [\App\Http\Controllers\Api\V1\PresenceQRController::class, 'qrcode']);

    // ── Parents ──
    Route::apiResource('parents', ParentController::class);

    // ── Enseignants ──
    Route::apiResource('enseignants', EnseignantController::class);
    Route::prefix('enseignants')->group(function () {
        Route::get('{id}/planning',         [EnseignantController::class, 'planning']);
        Route::get('{id}/statistiques',     [EnseignantController::class, 'statistiques']);
        Route::post('{id}/disponibilites',  [EnseignantController::class, 'setDisponibilites']);
        Route::post('{id}/photo',           [EnseignantController::class, 'uploadPhoto']);
        Route::post('{id}/toggle-statut',   [EnseignantController::class, 'toggleStatut']);
    });

    // ── Contrats ──
    Route::apiResource('contrats', ContratController::class);
});
