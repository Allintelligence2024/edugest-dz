<?php
// routes/api/extended.php
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// Extended — Transport, Cantine, Stock, Entretien,
// Pointage, Surveillance, Signalements, Billets, Examens
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\{
    SurveillanceController,
    SignalementController,
    ExamenController,
};

$protected = ['auth:api', 'resolve.tenant', 'tenant.verify', 'check.subscription', 'zero.trust'];
Route::middleware($protected)->group(function () {

    // ── Transport Scolaire (M09) ──
    Route::prefix('transport')->middleware('module:transport')->group(function () {
        Route::get('dashboard',                           [\App\Http\Controllers\Api\V1\TransportController::class, 'dashboard']);
        Route::get('circuits',                            [\App\Http\Controllers\Api\V1\TransportController::class, 'indexCircuits']);
        Route::post('circuits',                           [\App\Http\Controllers\Api\V1\TransportController::class, 'storeCircuit']);
        Route::get('circuits/{id}',                       [\App\Http\Controllers\Api\V1\TransportController::class, 'showCircuit']);
        Route::put('circuits/{id}',                       [\App\Http\Controllers\Api\V1\TransportController::class, 'updateCircuit']);
        Route::delete('circuits/{id}',                    [\App\Http\Controllers\Api\V1\TransportController::class, 'destroyCircuit']);
        Route::get('circuits/{id}/arrets',                [\App\Http\Controllers\Api\V1\TransportController::class, 'indexArrets']);
        Route::post('circuits/{id}/arrets',               [\App\Http\Controllers\Api\V1\TransportController::class, 'storeArret']);
        Route::put('arrets/{id}',                         [\App\Http\Controllers\Api\V1\TransportController::class, 'updateArret']);
        Route::delete('arrets/{id}',                      [\App\Http\Controllers\Api\V1\TransportController::class, 'destroyArret']);
        Route::post('inscrire',                           [\App\Http\Controllers\Api\V1\TransportController::class, 'inscrireEleve']);
        Route::delete('inscrire/{id}',                    [\App\Http\Controllers\Api\V1\TransportController::class, 'desinscrireEleve']);
        Route::get('eleve/{eleveId}',                     [\App\Http\Controllers\Api\V1\TransportController::class, 'circuitsEleve']);
        Route::post('pointage',                           [\App\Http\Controllers\Api\V1\TransportController::class, 'pointer']);
        Route::get('circuits/{id}/pointage',              [\App\Http\Controllers\Api\V1\TransportController::class, 'pointageDuJour']);
    });

    // ── Cantine / Restauration (M10) ──
    Route::prefix('cantine')->middleware('module:cantine')->group(function () {
        Route::get('dashboard',                       [\App\Http\Controllers\Api\V1\CantineController::class, 'dashboard']);
        Route::get('menus',                           [\App\Http\Controllers\Api\V1\CantineController::class, 'indexMenus']);
        Route::get('menus/semaine',                   [\App\Http\Controllers\Api\V1\CantineController::class, 'menuSemaine']);
        Route::post('menus',                          [\App\Http\Controllers\Api\V1\CantineController::class, 'storeMenu']);
        Route::put('menus/{id}',                      [\App\Http\Controllers\Api\V1\CantineController::class, 'updateMenu']);
        Route::delete('menus/{id}',                   [\App\Http\Controllers\Api\V1\CantineController::class, 'destroyMenu']);
        Route::get('inscriptions',                    [\App\Http\Controllers\Api\V1\CantineController::class, 'indexInscriptions']);
        Route::post('inscriptions',                   [\App\Http\Controllers\Api\V1\CantineController::class, 'inscrireEleve']);
        Route::put('inscriptions/{id}',               [\App\Http\Controllers\Api\V1\CantineController::class, 'updateInscription']);
        Route::delete('inscriptions/{id}',            [\App\Http\Controllers\Api\V1\CantineController::class, 'desinscrireEleve']);
        Route::post('pointage',                       [\App\Http\Controllers\Api\V1\CantineController::class, 'pointer']);
        Route::get('pointage/{date}',                 [\App\Http\Controllers\Api\V1\CantineController::class, 'pointageDate']);
        Route::get('stock',                           [\App\Http\Controllers\Api\V1\CantineController::class, 'indexStock']);
        Route::post('stock',                          [\App\Http\Controllers\Api\V1\CantineController::class, 'storeStock']);
        Route::post('stock/{id}/mouvement',           [\App\Http\Controllers\Api\V1\CantineController::class, 'mouvementStock']);
        Route::get('stock/alertes',                   [\App\Http\Controllers\Api\V1\CantineController::class, 'alertesStock']);
    });

    // ── Stock & Inventaire Mobilier (M11) ──
    Route::prefix('stock')->middleware('module:stock')->group(function () {
        Route::get('dashboard',                       [\App\Http\Controllers\Api\V1\StockInventaireController::class, 'dashboard']);
        Route::get('alertes',                         [\App\Http\Controllers\Api\V1\StockInventaireController::class, 'alertes']);

        // Articles
        Route::get('articles',                        [\App\Http\Controllers\Api\V1\StockInventaireController::class, 'index']);
        Route::post('articles',                       [\App\Http\Controllers\Api\V1\StockInventaireController::class, 'store']);
        Route::get('articles/qr/{qr_code}',           [\App\Http\Controllers\Api\V1\StockInventaireController::class, 'parQrCode']);
        Route::get('articles/{id}',                   [\App\Http\Controllers\Api\V1\StockInventaireController::class, 'show']);
        Route::put('articles/{id}',                   [\App\Http\Controllers\Api\V1\StockInventaireController::class, 'update']);
        Route::delete('articles/{id}',                [\App\Http\Controllers\Api\V1\StockInventaireController::class, 'destroy']);
        Route::post('articles/{id}/mouvement',        [\App\Http\Controllers\Api\V1\StockInventaireController::class, 'mouvement']);
        Route::get('articles/{id}/historique',        [\App\Http\Controllers\Api\V1\StockInventaireController::class, 'historique']);

        // Prêts
        Route::get('prets',                           [\App\Http\Controllers\Api\V1\StockInventaireController::class, 'indexPrets']);
        Route::post('prets',                          [\App\Http\Controllers\Api\V1\StockInventaireController::class, 'creerPret']);
        Route::put('prets/{id}/retour',               [\App\Http\Controllers\Api\V1\StockInventaireController::class, 'retourPret']);

        // Bons de commande
        Route::get('bons-commande',                   [\App\Http\Controllers\Api\V1\StockInventaireController::class, 'indexBons']);
        Route::post('bons-commande',                  [\App\Http\Controllers\Api\V1\StockInventaireController::class, 'creerBon']);
        Route::put('bons-commande/{id}/statut',       [\App\Http\Controllers\Api\V1\StockInventaireController::class, 'statutBon']);
        Route::get('bons-commande/{id}/pdf',          [\App\Http\Controllers\Api\V1\StockInventaireController::class, 'pdfBon']);

        // Rapport inventaire annuel
        Route::get('rapport-inventaire',              [\App\Http\Controllers\Api\V1\StockInventaireController::class, 'rapportInventaire']);
    });

    // ── Entretien Bâtiment (M14) ──
    Route::prefix('entretien')->middleware('module:entretien')->group(function () {
        Route::get('dashboard',                        [\App\Http\Controllers\Api\V1\EntretienController::class, 'dashboard']);

        // Locaux
        Route::get('locaux',                           [\App\Http\Controllers\Api\V1\EntretienController::class, 'indexLocaux']);
        Route::post('locaux',                          [\App\Http\Controllers\Api\V1\EntretienController::class, 'storeLocal']);
        Route::put('locaux/{id}',                      [\App\Http\Controllers\Api\V1\EntretienController::class, 'updateLocal']);
        Route::delete('locaux/{id}',                   [\App\Http\Controllers\Api\V1\EntretienController::class, 'destroyLocal']);

        // Prestataires
        Route::get('prestataires',                     [\App\Http\Controllers\Api\V1\EntretienController::class, 'indexPrestataires']);
        Route::post('prestataires',                    [\App\Http\Controllers\Api\V1\EntretienController::class, 'storePrestataire']);
        Route::put('prestataires/{id}',                [\App\Http\Controllers\Api\V1\EntretienController::class, 'updatePrestataire']);

        // Interventions
        Route::get('interventions',                    [\App\Http\Controllers\Api\V1\EntretienController::class, 'indexInterventions']);
        Route::post('interventions',                   [\App\Http\Controllers\Api\V1\EntretienController::class, 'signalerIntervention']);
        Route::get('interventions/{id}',               [\App\Http\Controllers\Api\V1\EntretienController::class, 'showIntervention']);
        Route::put('interventions/{id}/statut',        [\App\Http\Controllers\Api\V1\EntretienController::class, 'changerStatut']);
        Route::put('interventions/{id}/resoudre',      [\App\Http\Controllers\Api\V1\EntretienController::class, 'resoudreIntervention']);

        // Préventif
        Route::get('preventif',                        [\App\Http\Controllers\Api\V1\EntretienController::class, 'indexPreventif']);
        Route::post('preventif',                       [\App\Http\Controllers\Api\V1\EntretienController::class, 'planifierPreventif']);
        Route::put('preventif/{id}/realiser',          [\App\Http\Controllers\Api\V1\EntretienController::class, 'realiserPreventif']);
    });

    // ── Pointage par badge RFID/NFC ──
    Route::prefix('pointage')->middleware('module:pointage')->group(function () {
        Route::post('badge', [\App\Http\Controllers\Api\V1\PointageBadgeController::class, 'scan']);
        Route::get('enseignants',            [\App\Http\Controllers\Api\V1\PointageEnseignantController::class, 'index']);
        Route::post('enseignants',           [\App\Http\Controllers\Api\V1\PointageEnseignantController::class, 'store']);
        Route::get('enseignants/aujourd-hui', [\App\Http\Controllers\Api\V1\PointageEnseignantController::class, 'aujourdhui']);
        Route::post('enseignants/{id}/arrivee', [\App\Http\Controllers\Api\V1\PointageEnseignantController::class, 'arrivee']);
        Route::post('enseignants/{id}/depart', [\App\Http\Controllers\Api\V1\PointageEnseignantController::class, 'depart']);
        Route::get('enseignants/{id}/historique', [\App\Http\Controllers\Api\V1\PointageEnseignantController::class, 'historique']);
    });

    // ── Surveillance Dahua ──
    Route::prefix('surveillance')->middleware('module:surveillance')->group(function () {
        Route::get('/alertes',                  [SurveillanceController::class, 'indexAlertes']);
        Route::post('/alertes/{id}/traiter',    [SurveillanceController::class, 'traiterAlerte']);
        Route::get('/cameras',                  [SurveillanceController::class, 'indexCameras']);
        Route::post('/cameras',                 [SurveillanceController::class, 'enregistrerCamera']);
        Route::delete('/cameras/{id}',          [SurveillanceController::class, 'desactiverCamera']);
    });

    // ── Signalements comportement ──
    Route::post('signalements',                         [SignalementController::class, 'store']);
    Route::get('signalements',                          [SignalementController::class, 'index']);
    Route::get('signalements/mes-signalements',         [SignalementController::class, 'mesSIgnalements']);
    Route::get('signalements/eleve/{eleveId}',          [SignalementController::class, 'byEleve']);
    Route::post('signalements/{id}/traiter',            [SignalementController::class, 'traiter']);
    Route::get('signalements/parent/mon-enfant',        [SignalementController::class, 'monEnfantSignalements']);
    Route::get('notifications/parent',                  [SignalementController::class, 'notificationsParent']);
    Route::post('notifications/parent/{id}/lire',       [SignalementController::class, 'marquerLue']);
    Route::post('notifications/parent/tout-lire',       [SignalementController::class, 'toutMarquerLu']);

    // ── Billets (entrée / retard / sortie / convocation) ──
    Route::prefix('billets')->middleware('module:billets')->group(function () {
        Route::get('/',                    [\App\Http\Controllers\Api\V1\BilletController::class, 'index']);
        Route::post('/',                   [\App\Http\Controllers\Api\V1\BilletController::class, 'store']);
        Route::get('{id}/pdf',             [\App\Http\Controllers\Api\V1\BilletController::class, 'pdf']);
        Route::get('eleve/{eleveId}',      [\App\Http\Controllers\Api\V1\BilletController::class, 'parEleve']);
    });

    // ── Examens Officiels BEM/BAC ──
    Route::prefix('examens')->middleware('module:examens')->group(function () {
        Route::get('/',                       [ExamenController::class, 'indexSessions']);
        Route::post('/',                      [ExamenController::class, 'storeSession']);
        Route::get('/{id}',                   [ExamenController::class, 'showSession']);
        Route::put('/{id}',                   [ExamenController::class, 'updateSession']);

        Route::post('/{sessionId}/epreuves',          [ExamenController::class, 'storeEpreuve']);
        Route::delete('/epreuves/{id}',               [ExamenController::class, 'deleteEpreuve']);

        Route::post('/{sessionId}/salles',            [ExamenController::class, 'storeSalle']);

        Route::get('/{sessionId}/candidats',          [ExamenController::class, 'indexCandidats']);
        Route::post('/{sessionId}/candidats',         [ExamenController::class, 'storeCandidat']);
        Route::post('/{sessionId}/candidats/import-eleves', [ExamenController::class, 'importerElevesSysteme']);
        Route::post('/{sessionId}/candidats/import-csv',    [ExamenController::class, 'importerCSV']);
        Route::post('/candidats/{id}/presence',       [ExamenController::class, 'marquerPresence']);

        Route::post('/{sessionId}/surveillants',      [ExamenController::class, 'storeSurveillant']);
        Route::post('/{sessionId}/surveillants/import',[ExamenController::class, 'importerEnseignantsSurveillants']);

        Route::post('/{sessionId}/affecter-candidats',  [ExamenController::class, 'affecterCandidats']);
        Route::post('/{sessionId}/affecter-surveillants',[ExamenController::class, 'affecterSurveilants']);

        Route::get('/candidats/{id}/convocation',        [ExamenController::class, 'pdfConvocationCandidat']);
        Route::get('/{sessionId}/toutes-convocations',   [ExamenController::class, 'pdfToutesConvocations']);
        Route::get('/surveillants/{id}/convocation',     [ExamenController::class, 'pdfConvocationSurveillant']);
        Route::get('/salles/{salleId}/feuille-presence', [ExamenController::class, 'pdfFeuillePresence']);
        Route::get('/salles/{salleId}/plan',             [ExamenController::class, 'pdfPlanSalle']);

        // ── Export ONDEC ──
        Route::get('/{sessionId}/export-onec',          [ExamenController::class, 'exportOnec']);
    });

    // ── Signalements graves (élève → directeur — confidentiel) ──
    Route::prefix('signalements-graves')->group(function () {
        Route::get('/',                    [\App\Http\Controllers\Api\V1\SignalementGraveController::class, 'index']);
        Route::post('/',                   [\App\Http\Controllers\Api\V1\SignalementGraveController::class, 'store']);
        Route::patch('/{id}/traiter',      [\App\Http\Controllers\Api\V1\SignalementGraveController::class, 'traiter']);
    });
});

// ── Surveillance Dahua — Webhook PUBLIC ──
Route::post('surveillance/webhook', [SurveillanceController::class, 'recevoir'])
    ->middleware('throttle:60,1');
