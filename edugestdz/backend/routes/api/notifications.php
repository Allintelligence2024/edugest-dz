<?php
// routes/api/notifications.php
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// Notifications — In-App, Push, Messages, Device Tokens,
// Campagnes, Absences Enseignants, Remplacements
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\{
    NotificationController,
    MessageController,
};

$protected = ['auth:api', 'resolve.tenant', 'tenant.verify', 'check.subscription', 'zero.trust'];
Route::middleware($protected)->group(function () {

    // ── Notifications ──
    Route::prefix('notifications')->group(function () {
        Route::get('/',                      [NotificationController::class, 'index']);
        Route::put('{id}/lire',              [NotificationController::class, 'marquerLu']);
        Route::put('tout-lire',              [NotificationController::class, 'toutLire']);
        Route::delete('{id}',               [NotificationController::class, 'destroy']);
        Route::post('envoyer',              [NotificationController::class, 'envoyer']);
    });

    // ── Notifications In-App (notifications_inapp) ──
    Route::prefix('notifications/in-app')->group(function () {
        Route::get('/',                      [\App\Http\Controllers\Api\V1\NotificationInAppController::class, 'index']);
        Route::patch('{id}/lu',              [\App\Http\Controllers\Api\V1\NotificationInAppController::class, 'marquerLue']);
    });

    // ── Remplacement Enseignant ──
    Route::prefix('remplacements')->group(function () {
        Route::get('seances-orphelines',     [\App\Http\Controllers\Api\V1\RemplacementController::class, 'seancesOrphelines']);
        Route::get('suggestions/{seanceId}', [\App\Http\Controllers\Api\V1\RemplacementController::class, 'suggestions']);
        Route::post('confirmer/{seanceId}',  [\App\Http\Controllers\Api\V1\RemplacementController::class, 'confirmer']);
    });

    // ── Device Tokens (Push Notifications) ──
    Route::prefix('device-tokens')->group(function () {
        Route::post('/',                     [\App\Http\Controllers\Api\V1\DeviceTokenController::class, 'register']);
        Route::delete('/',                   [\App\Http\Controllers\Api\V1\DeviceTokenController::class, 'unregister']);
        Route::get('/',                      [\App\Http\Controllers\Api\V1\DeviceTokenController::class, 'list']);
    });

    // ── Campagnes ──
    Route::apiResource('campagnes',          \App\Http\Controllers\Api\V1\CampagneController::class);
    Route::post('campagnes/{id}/envoyer',    [\App\Http\Controllers\Api\V1\CampagneController::class, 'envoyer']);

    // ── Messagerie ──
    Route::prefix('messages')->group(function () {
        Route::get('conversations',               [MessageController::class, 'conversations']);
        Route::post('conversations',              [MessageController::class, 'creerConversation']);
        Route::get('conversations/{id}',          [MessageController::class, 'conversation']);
        Route::post('conversations/{id}',         [MessageController::class, 'envoyer']);
        Route::put('conversations/{id}/lu',       [MessageController::class, 'marquerLu']);
    });
});

// ── Absences enseignants (no auth middleware) ──
Route::prefix('absences-enseignants')->group(function () {
    Route::get('/',                    [\App\Http\Controllers\Api\V1\AbsenceEnseignantController::class, 'index']);
    Route::post('/',                   [\App\Http\Controllers\Api\V1\AbsenceEnseignantController::class, 'signaler']);
    Route::post('/{id}/remplacer',     [\App\Http\Controllers\Api\V1\AbsenceEnseignantController::class, 'assigner']);
});
