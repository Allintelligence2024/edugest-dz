<?php
// backend/routes/api.php
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// EDUGEST DZ — Routes API v1
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\{
    AuthController,
    EleveController,
    ParentController,
    InscriptionController,
    EnseignantController,
    ContratController,
    PaieController,
    MatiereController,
    SalleController,
    GroupeController,
    CoursController,
    SeanceController,
    PlanningController,
    PresenceController,
    EvaluationController,
    NoteController,
    BulletinController,
    TarifController,
    FactureController,
    PaiementController,
    FinanceController,
    NotificationController,
    MessageController,
    RapportController,
    ParametreController,
    TwoFactorController
};

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// VERSION 1 — Préfixe /api/v1
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    Route::prefix('v1')->group(function () {

        // ── Marketplace Public ──
        Route::prefix('marketplace')->group(function () {
            Route::get('offres',                     [\App\Http\Controllers\Api\V1\Marketplace\OffreController::class, 'recherche']);
            Route::get('offres/{id}',                [\App\Http\Controllers\Api\V1\Marketplace\OffreController::class, 'show']);
            Route::get('avis/enseignant/{id}',       [\App\Http\Controllers\Api\V1\Marketplace\AvisController::class, 'byEnseignant']);
        });

    // ────────────────────────────────────────────
    // 🔐 AUTH — Public (sans authentification)
    // ────────────────────────────────────────────
    Route::prefix('auth')->group(function () {
            Route::post('login',           [AuthController::class, 'login']);
            Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
            Route::post('reset-password',  [AuthController::class, 'resetPassword']);
            Route::post('2fa/challenge',   [TwoFactorController::class, 'challenge']);
            Route::post('2fa/complete',    [AuthController::class, 'complete2fa']);
        });

    // ────────────────────────────────────────────
    // 🔒 ROUTES PROTÉGÉES PAR JWT
    // ────────────────────────────────────────────
    Route::middleware(['auth:api', 'resolve.tenant', 'check.subscription'])
         ->group(function () {

        // ── Auth ──
        Route::prefix('auth')->group(function () {
            Route::post('logout',          [AuthController::class, 'logout']);
            Route::post('refresh',         [AuthController::class, 'refresh']);
            Route::get('me',               [AuthController::class, 'me']);
            Route::put('change-password',  [AuthController::class, 'changePassword']);
            Route::put('profile',          [AuthController::class, 'updateProfile']);

            // ── 2FA ──
            Route::prefix('2fa')->group(function () {
                Route::get('status',         [TwoFactorController::class, 'status']);
                Route::post('enable',        [TwoFactorController::class, 'enable']);
                Route::post('confirm',       [TwoFactorController::class, 'confirm']);
                Route::post('disable',       [TwoFactorController::class, 'disable']);
                Route::get('recovery-codes', [TwoFactorController::class, 'recoveryCodes']);
            });
        });

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
        });

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

        // ── Paies ──
        Route::prefix('paies')->group(function () {
            Route::get('/',                     [PaieController::class, 'index']);
            Route::post('calculer',             [PaieController::class, 'calculer']);
            Route::post('{id}/valider',         [PaieController::class, 'valider']);
            Route::post('{id}/payer',           [PaieController::class, 'payer']);
            Route::get('{id}/bulletin',         [PaieController::class, 'bulletin']);
        });

        // ── Matières ──
        Route::apiResource('matieres', MatiereController::class);

        // ── Salles ──
        Route::apiResource('salles', SalleController::class);
        Route::get('salles/{id}/disponibilites', [SalleController::class, 'disponibilites']);

        // ── Groupes ──
        Route::apiResource('groupes', GroupeController::class);
        Route::prefix('groupes')->group(function () {
            Route::get('{id}/eleves',            [GroupeController::class, 'eleves']);
            Route::post('{id}/eleves',           [GroupeController::class, 'addEleve']);
            Route::delete('{id}/eleves/{eleveId}',[GroupeController::class, 'removeEleve']);
        });

        // ── Cours ──
        Route::apiResource('cours', CoursController::class);

        // ── Séances ──
        Route::apiResource('seances', SeanceController::class);
        Route::prefix('seances')->group(function () {
            Route::post('{id}/demarrer',         [SeanceController::class, 'demarrer']);
            Route::post('{id}/terminer',         [SeanceController::class, 'terminer']);
            Route::post('{id}/annuler',          [SeanceController::class, 'annuler']);
            Route::post('{id}/reporter',         [SeanceController::class, 'reporter']);
        });

        // ── Planning ──
        Route::prefix('planning')->group(function () {
            Route::get('/',                      [PlanningController::class, 'index']);
            Route::get('conflits',               [PlanningController::class, 'conflits']);
            Route::post('generer',               [PlanningController::class, 'generer']);
            Route::get('export',                 [PlanningController::class, 'export']);
        });

        // ── Présences ──
        Route::prefix('presences')->group(function () {
            Route::get('seance/{seanceId}',      [PresenceController::class, 'parSeance']);
            Route::post('seance/{seanceId}',     [PresenceController::class, 'saisir']);
            Route::put('{id}',                   [PresenceController::class, 'update']);
            Route::get('rapport',                [PresenceController::class, 'rapport']);
            Route::post('scan',                  [\App\Http\Controllers\Api\V1\PresenceQRController::class, 'scan']);
        });

        // ── Absences journalières élèves ──
        Route::prefix('absences')->group(function () {
            Route::get('/',                          [\App\Http\Controllers\Api\V1\AbsenceController::class, 'index']);
            Route::post('/{eleveId}',                [\App\Http\Controllers\Api\V1\AbsenceController::class, 'marquerPresent']);
            Route::put('/{id}/justifier',            [\App\Http\Controllers\Api\V1\AbsenceController::class, 'justifier']);
            Route::get('/rapport',                   [\App\Http\Controllers\Api\V1\AbsenceController::class, 'rapport']);
            Route::post('/badges/assigner',          [\App\Http\Controllers\Api\V1\AbsenceController::class, 'assignerBadge']);
        });

        // ── QR Code élève ──
        Route::get('eleves/{id}/qrcode',         [\App\Http\Controllers\Api\V1\PresenceQRController::class, 'qrcode']);

        // ── Évaluations ──
        Route::apiResource('evaluations', EvaluationController::class);
        Route::prefix('evaluations')->group(function () {
            Route::get('{id}/notes',             [EvaluationController::class, 'notes']);
            Route::post('{id}/notes',            [EvaluationController::class, 'saisirNotes']);
        });

        // ── Notes ──
        Route::put('notes/{id}',                 [NoteController::class, 'update']);

        // ── Bulletins ──
        Route::prefix('bulletins')->group(function () {
            Route::get('/',                      [BulletinController::class, 'index']);
            Route::post('generer',               [BulletinController::class, 'generer']);
            Route::get('{id}',                   [BulletinController::class, 'show']);
            Route::get('{id}/pdf',               [BulletinController::class, 'pdf']);
            Route::post('{id}/envoyer',          [BulletinController::class, 'envoyer']);
        });

        // ── Tarifs ──
        Route::apiResource('tarifs', TarifController::class);

        // ── Factures ──
        Route::apiResource('factures', FactureController::class);
        Route::prefix('factures')->group(function () {
            Route::get('{id}/pdf',               [FactureController::class, 'pdf']);
            Route::post('{id}/envoyer',          [FactureController::class, 'envoyer']);
            // Génération mensuelle
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

        // ── Budget Annuel & Comptabilite (M13) ──
        Route::prefix('budget')->group(function () {
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

        // ── Notifications ──
        Route::prefix('notifications')->group(function () {
            Route::get('/',                      [NotificationController::class, 'index']);
            Route::put('{id}/lire',              [NotificationController::class, 'marquerLu']);
            Route::put('tout-lire',              [NotificationController::class, 'toutLire']);
            Route::delete('{id}',               [NotificationController::class, 'destroy']);
            Route::post('envoyer',              [NotificationController::class, 'envoyer']);
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

        // ── Audit Logs ──
        Route::prefix('audit-logs')->group(function () {
            Route::get('/',                      [\App\Http\Controllers\Api\V1\AuditLogController::class, 'index']);
            Route::get('{id}',                   [\App\Http\Controllers\Api\V1\AuditLogController::class, 'show']);
        });

        // ── Messagerie ──
        Route::prefix('messages')->group(function () {
            Route::get('conversations',               [MessageController::class, 'conversations']);
            Route::post('conversations',              [MessageController::class, 'creerConversation']);
            Route::get('conversations/{id}',          [MessageController::class, 'conversation']);
            Route::post('conversations/{id}',         [MessageController::class, 'envoyer']);
            Route::put('conversations/{id}/lu',       [MessageController::class, 'marquerLu']);
        });

        // ── Pointage par badge RFID/NFC ──
        Route::prefix('pointage')->group(function () {
            Route::post('badge', [\App\Http\Controllers\Api\V1\PointageBadgeController::class, 'scan']);
            Route::get('enseignants/aujourd-hui', [\App\Http\Controllers\Api\V1\PointageEnseignantController::class, 'aujourdhui']);
            Route::post('enseignants/{id}/arrivee', [\App\Http\Controllers\Api\V1\PointageEnseignantController::class, 'arrivee']);
            Route::post('enseignants/{id}/depart', [\App\Http\Controllers\Api\V1\PointageEnseignantController::class, 'depart']);
            Route::get('enseignants/{id}/historique', [\App\Http\Controllers\Api\V1\PointageEnseignantController::class, 'historique']);
        });

        // ── Rapports ──
        Route::prefix('rapports')->group(function () {
            Route::get('presence',               [RapportController::class, 'presence']);
            Route::get('financier',              [RapportController::class, 'financier']);
            Route::get('pedagogique',            [RapportController::class, 'pedagogique']);
            Route::get('attestation/{eleveId}',  [RapportController::class, 'attestation']);
        });

        // ── Paramètres ──
        Route::prefix('parametres')->group(function () {
            Route::get('/',                      [ParametreController::class, 'index']);
            Route::put('/',                      [ParametreController::class, 'update']);
            Route::get('wilayas',                [ParametreController::class, 'wilayas']);
            Route::get('communes/{wilayaId}',    [ParametreController::class, 'communes']);
            Route::get('calendrier',             [ParametreController::class, 'calendrier']);
        });

        // ── Personnel Non-Enseignant (M12) ──
        Route::prefix('personnel')->group(function () {
            Route::get('tableau-bord',           [\App\Http\Controllers\Api\V1\PersonnelController::class, 'tableauBord']);
            Route::get('/',                       [\App\Http\Controllers\Api\V1\PersonnelController::class, 'index']);
            Route::post('/',                      [\App\Http\Controllers\Api\V1\PersonnelController::class, 'store']);
            Route::get('{id}',                    [\App\Http\Controllers\Api\V1\PersonnelController::class, 'show']);
            Route::put('{id}',                    [\App\Http\Controllers\Api\V1\PersonnelController::class, 'update']);
            Route::delete('{id}',                 [\App\Http\Controllers\Api\V1\PersonnelController::class, 'destroy']);

            // Congés
            Route::get('{id}/conges',             [\App\Http\Controllers\Api\V1\PersonnelController::class, 'conges']);
            Route::post('{id}/conges',            [\App\Http\Controllers\Api\V1\PersonnelController::class, 'demanderConge']);
            Route::put('conges/{congeId}/statut', [\App\Http\Controllers\Api\V1\PersonnelController::class, 'statuerConge']);

            // Pointage
            Route::post('{id}/pointer/arrivee',   [\App\Http\Controllers\Api\V1\PointagePersonnelController::class, 'arrivee']);
            Route::post('{id}/pointer/depart',    [\App\Http\Controllers\Api\V1\PointagePersonnelController::class, 'depart']);
            Route::get('{id}/pointer/historique', [\App\Http\Controllers\Api\V1\PointagePersonnelController::class, 'historique']);

            // Paies personnel non-enseignant
            Route::get('paies',                    [\App\Http\Controllers\Api\V1\PaiePersonnelController::class, 'index']);
            Route::post('paies/calculer',          [\App\Http\Controllers\Api\V1\PaiePersonnelController::class, 'calculer']);
            Route::post('paies/calculer-tous',     [\App\Http\Controllers\Api\V1\PaiePersonnelController::class, 'calculerTous']);
            Route::put('paies/{id}/valider',       [\App\Http\Controllers\Api\V1\PaiePersonnelController::class, 'valider']);
            Route::put('paies/{id}/payer',         [\App\Http\Controllers\Api\V1\PaiePersonnelController::class, 'payer']);
            Route::get('paies/{id}/pdf',           [\App\Http\Controllers\Api\V1\PaiePersonnelController::class, 'pdf']);
        });

        // ── Transport Scolaire (M09) ──
        Route::prefix('transport')->group(function () {
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
        Route::prefix('cantine')->group(function () {
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

        // ── Matching IA ──
        Route::prefix('matching')->group(function () {
            Route::get('suggestions', [\App\Http\Controllers\Api\V1\MatchingController::class, 'suggestions']);
        });

        // ── Billets (entrée / retard / sortie / convocation) ──
        Route::prefix('billets')->group(function () {
            Route::get('/',                    [\App\Http\Controllers\Api\V1\BilletController::class, 'index']);
            Route::post('/',                   [\App\Http\Controllers\Api\V1\BilletController::class, 'store']);
            Route::get('{id}/pdf',             [\App\Http\Controllers\Api\V1\BilletController::class, 'pdf']);
            Route::get('eleve/{eleveId}',      [\App\Http\Controllers\Api\V1\BilletController::class, 'parEleve']);
        });

        // ── Marketplace Authenticated ──
        Route::prefix('marketplace')->group(function () {
            Route::post('offres',                [\App\Http\Controllers\Api\V1\Marketplace\OffreController::class, 'store']);
            Route::put('offres/{id}',            [\App\Http\Controllers\Api\V1\Marketplace\OffreController::class, 'update']);
            Route::delete('offres/{id}',         [\App\Http\Controllers\Api\V1\Marketplace\OffreController::class, 'destroy']);
            Route::get('mes-offres',             [\App\Http\Controllers\Api\V1\Marketplace\OffreController::class, 'mesOffres']);
            Route::post('reservations',          [\App\Http\Controllers\Api\V1\Marketplace\ReservationController::class, 'store']);
            Route::post('reservations/{id}/payer',[\App\Http\Controllers\Api\V1\Marketplace\ReservationController::class, 'payer']);
            Route::get('mes-reservations',       [\App\Http\Controllers\Api\V1\Marketplace\ReservationController::class, 'mesReservations']);
            Route::post('reservations/{id}/annuler',[\App\Http\Controllers\Api\V1\Marketplace\ReservationController::class, 'annuler']);
            Route::post('reservations/{id}/terminer',[\App\Http\Controllers\Api\V1\Marketplace\ReservationController::class, 'terminer']);
            Route::post('avis',                  [\App\Http\Controllers\Api\V1\Marketplace\AvisController::class, 'store']);
        });

        // ── Paiement en ligne (Satim / CIB / Dahabia / BaridiMob) ──
        Route::prefix('paiements')->group(function () {
            Route::post('online/initier',        [\App\Http\Controllers\Api\V1\PaiementEnLigneController::class, 'initier']);
            Route::get('online/retour',          [\App\Http\Controllers\Api\V1\PaiementEnLigneController::class, 'retour']);
            Route::post('online/callback',       [\App\Http\Controllers\Api\V1\PaiementEnLigneController::class, 'callback']);
        });
    });

    // ────────────────────────────────────────────
    // 🔒 ROUTES SUPER-ADMIN (hors scope tenant)
    // ────────────────────────────────────────────
    Route::prefix('super-admin')->middleware(['auth:api', 'super_admin'])->group(function () {
        Route::get('tenants',                    [\App\Http\Controllers\Api\V1\SuperAdmin\TenantController::class, 'index']);
        Route::post('tenants',                   [\App\Http\Controllers\Api\V1\SuperAdmin\TenantController::class, 'store']);
        Route::get('tenants/{id}',               [\App\Http\Controllers\Api\V1\SuperAdmin\TenantController::class, 'show']);
        Route::put('tenants/{id}',               [\App\Http\Controllers\Api\V1\SuperAdmin\TenantController::class, 'update']);
        Route::get('stats',                      [\App\Http\Controllers\Api\V1\SuperAdmin\TenantController::class, 'stats']);
        Route::post('tenants/{id}/impersonate',  [\App\Http\Controllers\Api\V1\SuperAdmin\TenantController::class, 'impersonate']);
    });

    // ── WhatsApp Webhook (public) ──
    Route::prefix('whatsapp')->group(function () {
        Route::get('webhook',                [\App\Http\Controllers\Api\V1\WhatsAppWebhookController::class, 'verify']);
        Route::post('webhook',               [\App\Http\Controllers\Api\V1\WhatsAppWebhookController::class, 'handle']);
    });
});
