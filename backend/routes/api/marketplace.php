<?php
// routes/api/marketplace.php
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// Marketplace — Public + Authenticated
// P8 fix: merged two separate public marketplace groups
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\MarketplaceController;
use App\Http\Controllers\Api\V1\Marketplace\{
    OffreController,
    AvisController,
    ReservationController,
};

// ── Marketplace Public (merged P8 fix) ──
Route::prefix('marketplace')->group(function () {
    Route::get('offres',                     [OffreController::class, 'recherche']);
    Route::get('offres/{id}',                [OffreController::class, 'show']);
    Route::get('avis/enseignant/{id}',       [AvisController::class, 'byEnseignant']);
    Route::get('stats',                      [MarketplaceController::class, 'stats']);
    Route::get('recherche',                  [MarketplaceController::class, 'recherche']);
    Route::get('featured',                   [MarketplaceController::class, 'featured']);
    Route::get('centres/{tenantId}',         [MarketplaceController::class, 'profil']);
});

// ── Marketplace Authenticated ──
$protected = ['auth:api', 'resolve.tenant', 'tenant.verify', 'check.subscription', 'zero.trust'];
Route::middleware($protected)->group(function () {
    Route::prefix('marketplace')->middleware('module:marketplace')->group(function () {
        Route::post('offres',                [OffreController::class, 'store']);
        Route::put('offres/{id}',            [OffreController::class, 'update']);
        Route::delete('offres/{id}',         [OffreController::class, 'destroy']);
        Route::get('mes-offres',             [OffreController::class, 'mesOffres']);
        Route::post('reservations',          [ReservationController::class, 'store']);
        Route::post('reservations/{id}/payer',[ReservationController::class, 'payer']);
        Route::get('mes-reservations',       [ReservationController::class, 'mesReservations']);
        Route::post('reservations/{id}/annuler',[ReservationController::class, 'annuler']);
        Route::post('reservations/{id}/terminer',[ReservationController::class, 'terminer']);
        Route::post('avis',                  [AvisController::class, 'store']);
    });

    Route::prefix('marketplace')->middleware('module:marketplace')->group(function () {
        Route::get('mon-profil',                         [MarketplaceController::class, 'monProfil']);
        Route::put('mon-profil',                         [MarketplaceController::class, 'updateProfil']);
        Route::get('offres-cours',                       [MarketplaceController::class, 'indexOffres']);
        Route::post('offres-cours',                      [MarketplaceController::class, 'storeOffre']);
        Route::get('reservations-recues',                [MarketplaceController::class, 'indexReservationsCentre']);
        Route::post('reservations-recues/{id}/confirmer',[MarketplaceController::class, 'confirmerReservation']);
        Route::post('reservations-recues/{id}/annuler',  [MarketplaceController::class, 'annulerReservationCentre']);
        Route::post('reserver',                          [MarketplaceController::class, 'reserver']);
        Route::get('parent/reservations',                [MarketplaceController::class, 'mesReservations']);
        Route::post('avis-centre',                       [MarketplaceController::class, 'soumettreAvis']);
        Route::post('favoris/{tenantId}',                [MarketplaceController::class, 'toggleFavori']);
    });
});
