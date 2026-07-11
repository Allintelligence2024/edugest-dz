<?php
// backend/routes/api.php
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// ci: trigger CI for PR #12
// EDUGEST DZ — Routes API v1
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\LmsController;
use App\Http\Controllers\Api\V1\OnboardingController;
use App\Http\Controllers\Api\V1\SecurityDashboardController;
use App\Http\Controllers\Api\V1\{
    AuthController,
    SearchController,
    ExportRgpdController,
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
    TwoFactorController,
    MarketplaceController,
    PointageEnseignantController,
    SurveillanceController,
    SignalementController,
    DiagnosticController,
    ExamenController,
    ModuleController,
    PredictionController,
    AnalyticsDashboardController,
};

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// VERSION 1 — Préfixe /api/v1
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    Route::prefix('v1')->group(function () {

        // ── Marketplace Public (existants) ──
        Route::prefix('marketplace')->group(function () {
            Route::get('offres',                     [\App\Http\Controllers\Api\V1\Marketplace\OffreController::class, 'recherche']);
            Route::get('offres/{id}',                [\App\Http\Controllers\Api\V1\Marketplace\OffreController::class, 'show']);
            Route::get('avis/enseignant/{id}',       [\App\Http\Controllers\Api\V1\Marketplace\AvisController::class, 'byEnseignant']);
        });

        // ── Marketplace Public (nouveau système profils centres) ──
        Route::prefix('marketplace')->group(function () {
            Route::get('stats',                      [MarketplaceController::class, 'stats']);
            Route::get('recherche',                  [MarketplaceController::class, 'recherche']);
            Route::get('featured',                   [MarketplaceController::class, 'featured']);
            Route::get('centres/{tenantId}',         [MarketplaceController::class, 'profil']);
        });

    // ────────────────────────────────────────────
    // 🔐 AUTH — Public (sans authentification)
    // ────────────────────────────────────────────
    Route::prefix('auth')->group(function () {
            Route::post('login',           [AuthController::class, 'login'])->middleware('throttle:auth');
            Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
            Route::post('reset-password',  [AuthController::class, 'resetPassword']);
            Route::post('2fa/challenge',   [TwoFactorController::class, 'challenge']);
            Route::post('2fa/complete',    [AuthController::class, 'complete2fa']);

            // Alias for frontend client.js compatibility
            Route::post('password/forgot', function (\Illuminate\Http\Request $request) {
                $request->validate(['email' => 'required|email']);
                try { \Illuminate\Support\Facades\Password::sendResetLink($request->only('email')); } catch (\Throwable) {}
                return response()->json(['success' => true, 'message' => 'Si ce compte existe, un email a été envoyé.']);
            });
            Route::post('password/reset',  [AuthController::class, 'resetPassword']);
        });

        // ────────────────────────────────────────────
        // 🔐 SUPER-ADMIN (sans tenant scope)
        // ────────────────────────────────────────────
        Route::prefix('super-admin')->middleware(['auth:api', 'ip.allowlist', 'mfa', 'super_admin'])->group(function () {
            Route::get('tenants',                          [\App\Http\Controllers\Api\V1\SuperAdmin\SuperAdminController::class, 'indexTenants']);
            Route::get('stats',                            [\App\Http\Controllers\Api\V1\SuperAdmin\SuperAdminController::class, 'statsGlobales']);
            Route::post('tenants/{id}/suspendre',          [\App\Http\Controllers\Api\V1\SuperAdmin\SuperAdminController::class, 'suspendreTenant']);
            Route::post('marketplace/{tenantId}/verifier', [\App\Http\Controllers\Api\V1\SuperAdmin\SuperAdminController::class, 'verifierMarketplace']);
        });

    // ────────────────────────────────────────────
    // 🔒 ROUTES PROTÉGÉES PAR JWT
    // ────────────────────────────────────────────
    Route::middleware(['auth:api', 'resolve.tenant', 'tenant.verify', 'check.subscription', 'zero.trust'])
         ->group(function () {

        // ── Recherche globale ──
        Route::get('search', SearchController::class);

        // ── Auth ──
        Route::prefix('auth')->group(function () {
            Route::post('logout',          [AuthController::class, 'logout']);
            Route::post('refresh',         [AuthController::class, 'refresh']);
            Route::get('me',               [AuthController::class, 'me']);
            Route::put('me',               [AuthController::class, 'updateProfile']);
            Route::put('change-password',  [AuthController::class, 'changePassword']);
            Route::put('password',         [AuthController::class, 'changePassword']);
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

        // ── Security Dashboard ──
        Route::get('security/dashboard', [SecurityDashboardController::class, 'index']);

        // ── Kill Switch (Niveau 6) ──
        Route::prefix('kill-switch')->group(function () {
            Route::post('initier',    [\App\Http\Controllers\Api\V1\KillSwitchController::class, 'initier']);
            Route::post('{voteId}/approuver', [\App\Http\Controllers\Api\V1\KillSwitchController::class, 'approuver']);
            Route::post('{voteId}/refuser',   [\App\Http\Controllers\Api\V1\KillSwitchController::class, 'refuser']);
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
            Route::get('export/excel',          [EleveController::class, 'exportExcel'])->middleware('throttle:exports');
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
            Route::get('export',                 [PlanningController::class, 'export'])->middleware('throttle:exports');
            Route::get('aujourd-hui',            [PlanningController::class, 'aujourdhui']);
            Route::get('ical',                   [PlanningController::class, 'exportICal'])->middleware('throttle:exports');
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
            Route::get('{id}/pdf',               [BulletinController::class, 'pdf'])->middleware('throttle:exports');
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

        // ── Trusted Devices (Niveau 4) ──
        Route::prefix('trusted-devices')->group(function () {
            Route::get('/',                      [\App\Http\Controllers\Api\V1\TrustedDeviceController::class, 'index']);
            Route::delete('{id}',                [\App\Http\Controllers\Api\V1\TrustedDeviceController::class, 'destroy']);
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
        Route::prefix('pointage')->middleware('module:pointage')->group(function () {
            Route::post('badge', [\App\Http\Controllers\Api\V1\PointageBadgeController::class, 'scan']);
            Route::get('enseignants',            [PointageEnseignantController::class, 'index']);
            Route::post('enseignants',           [PointageEnseignantController::class, 'store']);
            Route::get('enseignants/aujourd-hui', [PointageEnseignantController::class, 'aujourdhui']);
            Route::post('enseignants/{id}/arrivee', [PointageEnseignantController::class, 'arrivee']);
            Route::post('enseignants/{id}/depart', [PointageEnseignantController::class, 'depart']);
            Route::get('enseignants/{id}/historique', [PointageEnseignantController::class, 'historique']);
        });

        // ── Rapports ──
        Route::prefix('rapports')->group(function () {
            Route::get('presence',               [RapportController::class, 'presence']);
            Route::get('financier',              [RapportController::class, 'financier']);
            Route::get('pedagogique',            [RapportController::class, 'pedagogique']);
            Route::get('attestation/{eleveId}',  [RapportController::class, 'attestation']);
            Route::get('absences-pdf',           [RapportController::class, 'absencesPDF']);
            Route::get('absences-stats',         [RapportController::class, 'absencesStats']);
            Route::get('simulation-bem',         [RapportController::class, 'simulationBEM']);
            Route::get('simulation-bac',         [RapportController::class, 'simulationBAC']);
        });

        // ── Paramètres ──
        Route::prefix('parametres')->group(function () {
            Route::get('/',                      [ParametreController::class, 'index']);
            Route::patch('/',                    [ParametreController::class, 'update']);
            Route::post('/logo',                 [ParametreController::class, 'uploadLogo']);
            Route::post('/tester-smtp',          [ParametreController::class, 'testerSmtp']);
            Route::get('wilayas',                [ParametreController::class, 'wilayas']);
            Route::get('communes/{wilayaId}',    [ParametreController::class, 'communes']);
            Route::get('calendrier',             [ParametreController::class, 'calendrier']);
        });

        // ── RGPD / Loi 18-07 ──
        Route::prefix('rgpd')->group(function () {
            Route::get('/export-tenant',             [ExportRgpdController::class, 'exporterTenant']);
            Route::get('/export-eleve/{eleveId}',    [ExportRgpdController::class, 'exporterEleve']);
            Route::post('/demande-suppression',      [ExportRgpdController::class, 'demanderSuppression']);
            Route::post('/archiver-annee',           [ExportRgpdController::class, 'archiverAnnee']);
            Route::get('/demandes',                  [ExportRgpdController::class, 'listeDemandes']);
        });

        // ── Personnel Non-Enseignant (M12) ──
        Route::prefix('personnel')->middleware('module:personnel')->group(function () {
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

        // ── Matching IA ──
        Route::prefix('matching')->group(function () {
            Route::get('suggestions', [\App\Http\Controllers\Api\V1\MatchingController::class, 'suggestions']);
        });

        // ── Billets (entrée / retard / sortie / convocation) ──
        Route::prefix('billets')->middleware('module:billets')->group(function () {
            Route::get('/',                    [\App\Http\Controllers\Api\V1\BilletController::class, 'index']);
            Route::post('/',                   [\App\Http\Controllers\Api\V1\BilletController::class, 'store']);
            Route::get('{id}/pdf',             [\App\Http\Controllers\Api\V1\BilletController::class, 'pdf']);
            Route::get('eleve/{eleveId}',      [\App\Http\Controllers\Api\V1\BilletController::class, 'parEleve']);
        });

        // ── Marketplace Authenticated ──
        Route::prefix('marketplace')->middleware('module:marketplace')->group(function () {
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

        // ── Marketplace Nouveau Système (profils centres + offres cours) ──
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

        // ── Surveillance Dahua ──
        Route::prefix('surveillance')->middleware('module:surveillance')->group(function () {
            Route::get('/alertes',                  [SurveillanceController::class, 'indexAlertes']);
            Route::post('/alertes/{id}/traiter',    [SurveillanceController::class, 'traiterAlerte']);
            Route::get('/cameras',                  [SurveillanceController::class, 'indexCameras']);
            Route::post('/cameras',                 [SurveillanceController::class, 'enregistrerCamera']);
            Route::delete('/cameras/{id}',          [SurveillanceController::class, 'desactiverCamera']);
        });

        // ── Signalements comportement ─────────────────────────────────────
        Route::post('signalements',                         [SignalementController::class, 'store']);
        Route::get('signalements',                          [SignalementController::class, 'index']);
        Route::get('signalements/mes-signalements',         [SignalementController::class, 'mesSIgnalements']);
        Route::get('signalements/eleve/{eleveId}',          [SignalementController::class, 'byEleve']);
        Route::post('signalements/{id}/traiter',            [SignalementController::class, 'traiter']);
        Route::get('signalements/parent/mon-enfant',        [SignalementController::class, 'monEnfantSignalements']);
        Route::get('notifications/parent',                  [SignalementController::class, 'notificationsParent']);
        Route::post('notifications/parent/{id}/lire',       [SignalementController::class, 'marquerLue']);
        Route::post('notifications/parent/tout-lire',       [SignalementController::class, 'toutMarquerLu']);

        // ── Diagnostic Niveau Élèves (Early Warning System) ──────────────
        Route::prefix('diagnostic')->middleware('module:diagnostic')->group(function () {
            Route::get('/dashboard',                    [DiagnosticController::class, 'dashboard']);
            Route::get('/eleves',                       [DiagnosticController::class, 'indexDiagnostics']);
            Route::get('/eleves/{id}',                  [DiagnosticController::class, 'showDiagnostic']);
            Route::post('/eleves/{id}/analyser',        [DiagnosticController::class, 'analyserEleve']);
            Route::post('/analyser-tous',               [DiagnosticController::class, 'analyserTous']);
            Route::post('/rattrapages',                 [DiagnosticController::class, 'creerRattrapage']);
            Route::post('/convocations',                [DiagnosticController::class, 'envoyerConvocation']);
        });

        // ── IA Prediction Échec Scolaire ──────────────────────────────
        Route::prefix('ia/prediction')->middleware('module:diagnostic')->group(function () {
            Route::get('/classement',                    [PredictionController::class, 'classementRisque']);
            Route::get('/tout',                          [PredictionController::class, 'predireTous']);
            Route::post('/tout',                         [PredictionController::class, 'predireTous']);
            Route::get('/eleve/{eleveId}',               [PredictionController::class, 'predireEleve']);
        });

        // ── Analytics Dashboard ──────────────────────────────────────
        Route::prefix('analytics')->group(function () {
            Route::get('/dashboard',                     [AnalyticsDashboardController::class, 'dashboard']);
            Route::get('/finances',                      [AnalyticsDashboardController::class, 'finances']);
            Route::get('/pedagogique',                   [AnalyticsDashboardController::class, 'pedagogique']);
            Route::get('/rapport-pdf',                   [AnalyticsDashboardController::class, 'rapportPdf']);
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

        // ── WhatsApp Dashboard ──
        Route::prefix('whatsapp')->group(function () {
            Route::post('send',                  [\App\Http\Controllers\Api\V1\WhatsAppController::class, 'send']);
            Route::get('messages',               [\App\Http\Controllers\Api\V1\WhatsAppController::class, 'index']);
            Route::get('messages/{id}',          [\App\Http\Controllers\Api\V1\WhatsAppController::class, 'show']);
            Route::get('stats',                  [\App\Http\Controllers\Api\V1\WhatsAppController::class, 'stats']);
        });

        // ── Google Classroom ──
        Route::prefix('google')->group(function () {
            Route::post('classroom/auth',        [\App\Http\Controllers\Api\V1\GoogleClassroomController::class, 'auth']);
            Route::get('classroom/status',       [\App\Http\Controllers\Api\V1\GoogleClassroomController::class, 'status']);
            Route::delete('classroom/revoke',    [\App\Http\Controllers\Api\V1\GoogleClassroomController::class, 'revoke']);
            Route::get('classroom/courses',      [\App\Http\Controllers\Api\V1\GoogleClassroomController::class, 'courses']);
            Route::post('classroom/links',       [\App\Http\Controllers\Api\V1\GoogleClassroomController::class, 'link']);
            Route::get('classroom/links',        [\App\Http\Controllers\Api\V1\GoogleClassroomController::class, 'links']);
            Route::post('classroom/links/{id}/sync', [\App\Http\Controllers\Api\V1\GoogleClassroomController::class, 'sync']);
            Route::delete('classroom/links/{id}',    [\App\Http\Controllers\Api\V1\GoogleClassroomController::class, 'destroyLink']);
        });

        // ── LMS — Cours en ligne ──
        Route::prefix('lms')->middleware('module:lms')->group(function () {
            Route::get('/dashboard',                    [LmsController::class, 'dashboard']);
            Route::get('/cours',                        [LmsController::class, 'indexCours']);
            Route::post('/cours',                       [LmsController::class, 'storeCours']);
            Route::get('/cours/{id}',                   [LmsController::class, 'showCours']);
            Route::put('/cours/{id}',                   [LmsController::class, 'updateCours']);
            Route::post('/cours/{id}/publier',          [LmsController::class, 'publierCours']);
            Route::post('/cours/{coursId}/chapitres',   [LmsController::class, 'storeChapitre']);
            Route::put('/chapitres/{id}',               [LmsController::class, 'updateChapitre']);
            Route::delete('/chapitres/{id}',            [LmsController::class, 'deleteChapitre']);
            Route::post('/chapitres/{chapitreId}/lecons',[LmsController::class, 'storeLecon']);
            Route::put('/lecons/{id}',                  [LmsController::class, 'updateLecon']);
            Route::post('/lecons/{id}/upload',          [LmsController::class, 'uploadFichierLecon']);
            Route::post('/lecons/{leconId}/quiz',       [LmsController::class, 'storeQuiz']);
            Route::post('/quiz/{quizId}/questions',     [LmsController::class, 'storeQuestion']);
            Route::post('/quiz/{quizId}/passer',        [LmsController::class, 'passerQuiz']);
            Route::post('/inscrire',                    [LmsController::class, 'inscrire']);
            Route::get('/eleve/{eleveId}/inscriptions', [LmsController::class, 'inscriptionEleve']);
            Route::post('/inscription/{id}/lecon/{leconId}/complete', [LmsController::class, 'marquerLecon']);
            Route::get('/inscription/{id}/progression', [LmsController::class, 'progressionEleve']);
            Route::post('/lecons/{leconId}/devoir',     [LmsController::class, 'soumettreDevoir']);
            Route::post('/devoirs/{id}/corriger',       [LmsController::class, 'corrigerDevoir']);
            Route::get('/inscription/{id}/certificat',  [LmsController::class, 'telechargerCertificat']);
        });
    });

    // ────────────────────────────────────────────
    // 🧩 GESTION DES MODULES (accessible même hors module)
    // ────────────────────────────────────────────
    Route::prefix('modules')->middleware(['auth:api', 'resolve.tenant'])->group(function () {
        Route::get('/',                         [ModuleController::class, 'index']);
        Route::get('/actifs',                   [ModuleController::class, 'actifs']);
        Route::post('/bulk',                    [ModuleController::class, 'bulkUpdate']);
        Route::post('/{moduleKey}/activer',     [ModuleController::class, 'activer']);
        Route::post('/{moduleKey}/desactiver',  [ModuleController::class, 'desactiver']);
    });

    // ────────────────────────────────────────────
    // 🎯 ONBOARDING WIZARD (accessible hors modules)
    // ────────────────────────────────────────────
    Route::prefix('onboarding')->middleware(['auth:api', 'resolve.tenant'])->group(function () {
        Route::get('/',                          [OnboardingController::class, 'statut']);
        Route::post('/avancer',                  [OnboardingController::class, 'avancer']);
        Route::post('/tester-notification',       [OnboardingController::class, 'testerNotification']);
        Route::post('/ignorer',                  [OnboardingController::class, 'ignorer']);
    });

    // ────────────────────────────────────────────
    // 🔒 ROUTES SUPER-ADMIN (hors scope tenant)
    // ────────────────────────────────────────────
    Route::prefix('super-admin')->middleware(['auth:api', 'ip.allowlist', 'mfa', 'super_admin'])->group(function () {
        Route::post('tenants',                   [\App\Http\Controllers\Api\V1\SuperAdmin\TenantController::class, 'store']);
        Route::get('tenants/{id}',               [\App\Http\Controllers\Api\V1\SuperAdmin\TenantController::class, 'show']);
        Route::put('tenants/{id}',               [\App\Http\Controllers\Api\V1\SuperAdmin\TenantController::class, 'update']);
        Route::post('tenants/{id}/impersonate',  [\App\Http\Controllers\Api\V1\SuperAdmin\TenantController::class, 'impersonate']);
    });

    // ── Breach Response & Security Incidents ──
    Route::prefix('security/breach')->middleware(['auth:api', 'ip.allowlist'])->group(function () {
        Route::post('/verrouillage-urgence', [\App\Http\Controllers\Api\V1\BreachResponseController::class, 'verrouillageUrgence']);
        Route::post('/incidents',            [\App\Http\Controllers\Api\V1\BreachResponseController::class, 'declarerIncident']);
        Route::get('/incidents',             [\App\Http\Controllers\Api\V1\BreachResponseController::class, 'indexIncidents']);
        Route::delete('/verrouillage',       [\App\Http\Controllers\Api\V1\BreachResponseController::class, 'leverVerrouillage']);
    });

    // ── WhatsApp Webhook (Meta / public) ──
    Route::prefix('whatsapp')->middleware('throttle:webhook')->group(function () {
        Route::get('webhook',                [\App\Http\Controllers\Api\V1\WhatsAppWebhookController::class, 'verify']);
        Route::post('webhook',               [\App\Http\Controllers\Api\V1\WhatsAppWebhookController::class, 'handle']);
    });

    // ── Google Classroom OAuth Callback (public) ──
    Route::get('google/classroom/callback',  [\App\Http\Controllers\Api\V1\GoogleClassroomController::class, 'callback']);

    // ── Absences enseignants ──────────────────────────────────────────────
    Route::prefix('absences-enseignants')->group(function () {
        Route::get('/',                    [\App\Http\Controllers\Api\V1\AbsenceEnseignantController::class, 'index']);
        Route::post('/',                   [\App\Http\Controllers\Api\V1\AbsenceEnseignantController::class, 'signaler']);
        Route::post('/{id}/remplacer',     [\App\Http\Controllers\Api\V1\AbsenceEnseignantController::class, 'assigner']);
    });

    // ── Devoirs ───────────────────────────────────────────────────────────
    Route::prefix('devoirs')->group(function () {
        Route::get('/',    [\App\Http\Controllers\Api\V1\DevoirController::class, 'index']);
        Route::post('/',   [\App\Http\Controllers\Api\V1\DevoirController::class, 'store']);
    });

    // ── Feedbacks pédagogiques (élève → directeur) ────────────────────────
    Route::prefix('feedbacks-pedagogiques')->group(function () {
        Route::get('/',                    [\App\Http\Controllers\Api\V1\FeedbackPedagogiqueController::class, 'index']);
        Route::post('/',                   [\App\Http\Controllers\Api\V1\FeedbackPedagogiqueController::class, 'store']);
        Route::get('/resume/{ensId}',      [\App\Http\Controllers\Api\V1\FeedbackPedagogiqueController::class, 'resume']);
    });

    // ── Signalements graves (élève → directeur — confidentiel) ─────────────
    Route::prefix('signalements-graves')->group(function () {
        Route::get('/',                    [\App\Http\Controllers\Api\V1\SignalementGraveController::class, 'index']);
        Route::post('/',                   [\App\Http\Controllers\Api\V1\SignalementGraveController::class, 'store']);
        Route::patch('/{id}/traiter',      [\App\Http\Controllers\Api\V1\SignalementGraveController::class, 'traiter']);
    });

    // ══════════════════════════════════════════════════════════════════════
    // SURVEILLANCE DAHUA — Télésurveillance
    // ══════════════════════════════════════════════════════════════════════

    // Webhook PUBLIC — Dahua appelle cette URL (pas d'auth JWT)
    Route::post('surveillance/webhook', [SurveillanceController::class, 'recevoir'])
        ->middleware('throttle:60,1'); // 60 req/min
});

// ── Health Check amélioré — vérifie tous les services ──────────────────
Route::get('/health', function () {
    $checks  = [];
    $allOk   = true;
    $startTs = microtime(true);

    // 1. Base de données PostgreSQL
    try {
        $dbVersion = \DB::selectOne('SELECT version()')->version;
        $checks['database'] = [
            'status'  => 'ok',
            'driver'  => 'postgresql',
            'version' => substr($dbVersion, 0, 50),
        ];
    } catch (\Throwable $e) {
        $checks['database'] = ['status' => 'error', 'message' => $e->getMessage()];
        $allOk = false;
    }

    // 2. Redis
    try {
        $redisKey = 'health_check_' . now()->timestamp;
        \Cache::put($redisKey, 'ok', 5);
        $val = \Cache::get($redisKey);
        \Cache::forget($redisKey);
        $checks['redis'] = ['status' => $val === 'ok' ? 'ok' : 'error'];
        if ($val !== 'ok') $allOk = false;
    } catch (\Throwable $e) {
        $checks['redis'] = ['status' => 'error', 'message' => $e->getMessage()];
        $allOk = false;
    }

    // 3. Meilisearch (non-bloquant)
    try {
        $response = \Http::timeout(2)->get(config('scout.meilisearch.host', 'http://localhost:7700') . '/health');
        $checks['meilisearch'] = ['status' => $response->successful() ? 'ok' : 'degraded'];
    } catch (\Throwable) {
        $checks['meilisearch'] = ['status' => 'degraded', 'message' => 'Non disponible (non-critique)'];
    }

    // 4. Stockage fichiers
    try {
        $testFile = 'health_check_' . now()->timestamp . '.txt';
        \Storage::disk('local')->put($testFile, 'ok');
        $content = \Storage::disk('local')->get($testFile);
        \Storage::disk('local')->delete($testFile);
        $checks['storage'] = ['status' => $content === 'ok' ? 'ok' : 'error'];
        if ($content !== 'ok') $allOk = false;
    } catch (\Throwable $e) {
        $checks['storage'] = ['status' => 'error', 'message' => $e->getMessage()];
        $allOk = false;
    }

    // 5. Audit Chain intégrité (vérification rapide — dernier bloc seulement)
    try {
        $dernierBloc = \DB::table('audit_chain')->orderByDesc('bloc_numero')->first(['bloc_numero', 'hash_merkle']);
        $checks['audit_chain'] = [
            'status'      => 'ok',
            'total_blocs' => $dernierBloc?->bloc_numero ?? 0,
        ];
    } catch (\Throwable) {
        $checks['audit_chain'] = ['status' => 'degraded'];
    }

    // 6. Kill Switch status
    $killActive = \Cache::has('kill_switch_active');
    $checks['kill_switch'] = ['status' => $killActive ? 'ACTIVE' : 'inactive'];
    if ($killActive) $allOk = false;

    $responseTime = round((microtime(true) - $startTs) * 1000, 2);

    return response()->json([
        'status'        => $allOk ? 'healthy' : 'degraded',
        'timestamp'     => now()->toIso8601String(),
        'version'       => env('APP_VERSION', '1.0.0'),
        'environment'   => app()->environment(),
        'response_time' => $responseTime . 'ms',
        'checks'        => $checks,
    ], $allOk ? 200 : 503);
})->name('health');

// ── Health Ping — léger, sans auth, pour UptimeRobot ──────────────────
Route::get('/health/ping', [\App\Http\Controllers\Api\HealthController::class, 'ping'])->name('health.ping');

// ── Fichier (signé, authentifié) ──
Route::get('/fichier/{cheminB64}', [\App\Http\Controllers\Api\FichierController::class, 'show'])
    ->middleware('auth:api');

// ══════════════════════════════════════════════════════════════════════
// HONEYPOT ROUTES — Leurres pour détecter les scanners / attaquants
// ══════════════════════════════════════════════════════════════════════
Route::any('/v1/phpinfo', function () {
    return app(\App\Services\HoneypotService::class)->declencherRouteLeurre();
})->name('honeypot.phpinfo');

Route::any('/v1/server-status', function () {
    return app(\App\Services\HoneypotService::class)->declencherRouteLeurre();
})->name('honeypot.server-status');

Route::any('/v1/actuator', function () {
    return app(\App\Services\HoneypotService::class)->declencherRouteLeurre();
})->name('honeypot.actuator');

Route::any('/v1/metrics', function () {
    return app(\App\Services\HoneypotService::class)->declencherRouteLeurre();
})->name('honeypot.metrics');

Route::any('/v1/.git/config', function () {
    return app(\App\Services\HoneypotService::class)->declencherRouteLeurre();
})->name('honeypot.git-config');

Route::any('/v1/swagger.json', function () {
    return app(\App\Services\HoneypotService::class)->declencherRouteLeurre();
})->name('honeypot.swagger');

Route::any('/v1/graphql', function () {
    return app(\App\Services\HoneypotService::class)->declencherRouteLeurre();
})->name('honeypot.graphql');

Route::any('/v1/health/check', function () {
    return app(\App\Services\HoneypotService::class)->declencherRouteLeurre();
})->name('honeypot.health-check');

Route::any('/v1/ping', function () {
    return app(\App\Services\HoneypotService::class)->declencherRouteLeurre();
})->name('honeypot.ping');

Route::any('/v1/test', function () {
    return app(\App\Services\HoneypotService::class)->declencherRouteLeurre();
})->name('honeypot.test');

Route::any('/v1/api-docs', function () {
    return app(\App\Services\HoneypotService::class)->declencherRouteLeurre();
})->name('honeypot.api-docs');

Route::any('/v1/robots.txt', function () {
    return app(\App\Services\HoneypotService::class)->declencherRouteLeurre();
})->name('honeypot.robots');

Route::any('/v1/sitemap.xml', function () {
    return app(\App\Services\HoneypotService::class)->declencherRouteLeurre();
})->name('honeypot.sitemap');

Route::any('/v1/cron', function () {
    return app(\App\Services\HoneypotService::class)->declencherRouteLeurre();
})->name('honeypot.cron');

Route::any('/v1/deploy', function () {
    return app(\App\Services\HoneypotService::class)->declencherRouteLeurre();
})->name('honeypot.deploy');

Route::any('/v1/websocket', function () {
    return app(\App\Services\HoneypotService::class)->declencherRouteLeurre();
})->name('honeypot.websocket');
