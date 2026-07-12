<?php
// routes/api/finance.php
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// Finance — Tarifs, Factures, Paiements, Finance, Paies, Budget
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\{
    PaieController,
    TarifController,
    FactureController,
    PaiementController,
    FinanceController,
};

$protected = ['auth:api', 'resolve.tenant', 'tenant.verify', 'check.subscription', 'zero.trust'];
Route::middleware($protected)->group(function () {

    // ── Paies ──
    Route::prefix('paies')->group(function () {
        Route::get('/',                     [PaieController::class, 'index']);
        Route::post('calculer',             [PaieController::class, 'calculer']);
        Route::post('{id}/valider',         [PaieController::class, 'valider']);
        Route::post('{id}/payer',           [PaieController::class, 'payer']);
        Route::get('{id}/bulletin',         [PaieController::class, 'bulletin']);
    });

    // ── Tarifs ──
    Route::apiResource('tarifs', TarifController::class);

    // ── Factures ──
    Route::apiResource('factures', FactureController::class);
    Route::prefix('factures')->group(function () {
        Route::get('{id}/pdf',               [FactureController::class, 'pdf']);
        Route::post('{id}/envoyer',          [FactureController::class, 'envoyer']);
        Route::post('generer-mensuelle',      [FactureController::class, 'genererMensuelle']);
        Route::post('generer-toutes',         [FactureController::class, 'genererToutes']);
    });

    // ── Paiements ──
    Route::prefix('paiements')->group(function () {
        Route::get('caisse-jour',            [PaiementController::class, 'caisseJour']);
    });
    Route::apiResource('paiements', PaiementController::class);
    Route::prefix('paiements')->group(function () {
        Route::get('{id}/recu',              [PaiementController::class, 'recu']);
    });

    // ── Finance ──
    Route::prefix('finance')->group(function () {
        Route::get('tableau-bord',           [FinanceController::class, 'tableauBord']);
        Route::get('impayes',                [FinanceController::class, 'impayes']);
        Route::post('relances',              [FinanceController::class, 'envoyerRelances']);
        Route::get('bilan-mensuel',          [FinanceController::class, 'bilanMensuel']);
        Route::get('bilan-annuel',           [FinanceController::class, 'bilanAnnuel']);
    });

    // ── Budget Annuel & Comptabilité (M13) ──
    Route::prefix('budget')->middleware('module:budget')->group(function () {
        Route::get('dashboard',                   [\App\Http\Controllers\Api\V1\BudgetController::class, 'dashboard']);
        Route::get('categories',                  [\App\Http\Controllers\Api\V1\BudgetController::class, 'categories']);
        Route::get('bilan-mensuel',               [\App\Http\Controllers\Api\V1\BudgetController::class, 'bilanMensuel']);
        Route::get('bilan-annuel',                [\App\Http\Controllers\Api\V1\BudgetController::class, 'bilanAnnuel']);
        Route::get('depenses',                    [\App\Http\Controllers\Api\V1\BudgetController::class, 'indexDepenses']);
        Route::post('depenses',                   [\App\Http\Controllers\Api\V1\BudgetController::class, 'storeDepense']);
        Route::put('depenses/{id}',               [\App\Http\Controllers\Api\V1\BudgetController::class, 'updateDepense']);
        Route::delete('depenses/{id}',            [\App\Http\Controllers\Api\V1\BudgetController::class, 'destroyDepense']);
        Route::post('depenses/{id}/justificatif', [\App\Http\Controllers\Api\V1\BudgetController::class, 'uploadJustificatif']);
        Route::get('previsionnel',                [\App\Http\Controllers\Api\V1\BudgetController::class, 'previsionnel']);
        Route::post('previsionnel',               [\App\Http\Controllers\Api\V1\BudgetController::class, 'setPrevisionnel']);
    });

    // ── Paiement en ligne (Satim / CIB / Dahabia / BaridiMob) ──
    Route::prefix('paiements')->group(function () {
        Route::post('online/initier',        [\App\Http\Controllers\Api\V1\PaiementEnLigneController::class, 'initier']);
        Route::get('online/retour',          [\App\Http\Controllers\Api\V1\PaiementEnLigneController::class, 'retour']);
        Route::post('online/callback',       [\App\Http\Controllers\Api\V1\PaiementEnLigneController::class, 'callback']);
        Route::get('online/dashboard',       [\App\Http\Controllers\Api\V1\PaiementEnLigneController::class, 'dashboard']);
        Route::get('online/{id}/statut',     [\App\Http\Controllers\Api\V1\PaiementEnLigneController::class, 'verifierStatut']);
        Route::post('online/{id}/rembourser',[\App\Http\Controllers\Api\V1\PaiementEnLigneController::class, 'rembourser']);
    });
});
