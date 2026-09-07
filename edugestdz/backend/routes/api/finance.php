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
    PlanFractionnementController,
};

$protected = ['auth:api', 'resolve.tenant', 'tenant.verify', 'check.subscription', 'zero.trust'];

// ── RBAC (Sprint 2) ────────────────────────────────────────────────────────
// Le module finance manipule salaires, factures et encaissements : il est
// réservé à la direction et à la comptabilité. Avant ce garde-fou, tout
// utilisateur authentifié — y compris un compte « parent » — pouvait lire
// les paies du personnel et le bilan de l'établissement.
$financeRoles = 'role:admin,gestionnaire,comptable';
$paieRoles    = 'role:admin,comptable';   // la paie est encore plus restreinte

Route::middleware($protected)->group(function () use ($financeRoles, $paieRoles) {

    // ── Paies (données salariales : accès le plus restreint) ──
    Route::prefix('paies')->middleware($paieRoles)->group(function () {
        Route::get('/',                     [PaieController::class, 'index']);
        Route::post('calculer',             [PaieController::class, 'calculer']);
        Route::post('{id}/valider',         [PaieController::class, 'valider']);
        Route::post('{id}/payer',           [PaieController::class, 'payer']);
        Route::get('{id}/bulletin',         [PaieController::class, 'bulletin']);
    });

    // ── Tarifs : lecture large (un parent doit voir la grille tarifaire),
    //    écriture réservée à la direction. ──
    Route::apiResource('tarifs', TarifController::class)->only(['index', 'show']);
    Route::apiResource('tarifs', TarifController::class)
        ->except(['index', 'show'])
        ->middleware($financeRoles);

    // ── Factures ──
    Route::apiResource('factures', FactureController::class)->middleware($financeRoles);
    Route::prefix('factures')->middleware($financeRoles)->group(function () {
        Route::get('{id}/pdf',               [FactureController::class, 'pdf']);
        Route::post('{id}/envoyer',          [FactureController::class, 'envoyer']);
        Route::post('generer-mensuelle',      [FactureController::class, 'genererMensuelle']);
        Route::post('generer-toutes',         [FactureController::class, 'genererToutes']);
    });

    // ── Paiements ──
    Route::prefix('paiements')->middleware($financeRoles)->group(function () {
        Route::get('caisse-jour',            [PaiementController::class, 'caisseJour']);
    });
    Route::apiResource('paiements', PaiementController::class)->middleware($financeRoles);
    Route::prefix('paiements')->middleware($financeRoles)->group(function () {
        Route::get('{id}/recu',              [PaiementController::class, 'recu']);
    });

    // ── Finance (tableaux de bord, impayés, bilans) ──
    Route::prefix('finance')->middleware($financeRoles)->group(function () {
        Route::get('tableau-bord',           [FinanceController::class, 'tableauBord']);
        Route::get('impayes',                [FinanceController::class, 'impayes']);
        Route::post('relances',              [FinanceController::class, 'envoyerRelances']);
        Route::get('bilan-mensuel',          [FinanceController::class, 'bilanMensuel']);
        Route::get('bilan-annuel',           [FinanceController::class, 'bilanAnnuel']);
    });

    // ── Budget Annuel & Comptabilité (M13) ──
    Route::prefix('budget')->middleware(['module:budget', $financeRoles])->group(function () {
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
    Route::prefix('paiements')->group(function () use ($financeRoles) {
        // Accessible aux parents : régler la scolarité de son enfant.
        Route::post('online/initier',        [\App\Http\Controllers\Api\V1\PaiementEnLigneController::class, 'initier']);
        Route::get('online/retour',          [\App\Http\Controllers\Api\V1\PaiementEnLigneController::class, 'retour']);
        Route::post('online/callback',       [\App\Http\Controllers\Api\V1\PaiementEnLigneController::class, 'callback']);
        Route::get('online/{id}/statut',     [\App\Http\Controllers\Api\V1\PaiementEnLigneController::class, 'verifierStatut']);

        // Réservé à la finance : vue globale et remboursements.
        Route::get('online/dashboard',       [\App\Http\Controllers\Api\V1\PaiementEnLigneController::class, 'dashboard'])
            ->middleware($financeRoles);
        Route::post('online/{id}/rembourser',[\App\Http\Controllers\Api\V1\PaiementEnLigneController::class, 'rembourser'])
            ->middleware($financeRoles);
    });

    // ── Plans de fractionnement (échéanciers de paiement) ──
    Route::prefix('plans-fractionnement')->middleware($financeRoles)->group(function () {
        Route::get('/',                     [PlanFractionnementController::class, 'index']);
        Route::post('/',                    [PlanFractionnementController::class, 'store']);
        Route::get('/{id}',                 [PlanFractionnementController::class, 'show']);
        Route::post('/affecter',            [PlanFractionnementController::class, 'affecter']);
        Route::post('/{id}/annuler',        [PlanFractionnementController::class, 'annuler']);
    });
});
