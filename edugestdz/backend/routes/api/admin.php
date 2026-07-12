<?php
// routes/api/admin.php
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// Admin — Super-admin, Modules, Onboarding, Personnel,
// Paramètres, RGPD, Audit-logs
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\{
    ExportRgpdController,
    ParametreController,
    ModuleController,
    OnboardingController,
};

// ── Super-Admin (sans tenant scope) — first set ──
Route::prefix('super-admin')->middleware(['auth:api', 'ip.allowlist', 'mfa', 'super_admin'])->group(function () {
    Route::get('tenants',                          [\App\Http\Controllers\Api\V1\SuperAdmin\SuperAdminController::class, 'indexTenants']);
    Route::get('stats',                            [\App\Http\Controllers\Api\V1\SuperAdmin\SuperAdminController::class, 'statsGlobales']);
    Route::post('tenants/{id}/suspendre',          [\App\Http\Controllers\Api\V1\SuperAdmin\SuperAdminController::class, 'suspendreTenant']);
    Route::post('marketplace/{tenantId}/verifier', [\App\Http\Controllers\Api\V1\SuperAdmin\SuperAdminController::class, 'verifierMarketplace']);
});

// ── Routes protégées par JWT ──
$protected = ['auth:api', 'resolve.tenant', 'tenant.verify', 'check.subscription', 'zero.trust'];
Route::middleware($protected)->group(function () {

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

    // ── Audit Logs ──
    Route::prefix('audit-logs')->group(function () {
        Route::get('/',                      [\App\Http\Controllers\Api\V1\AuditLogController::class, 'index']);
        Route::get('{id}',                   [\App\Http\Controllers\Api\V1\AuditLogController::class, 'show']);
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
});

// ── Gestion des Modules (accessible même hors module) ──
Route::prefix('modules')->middleware(['auth:api', 'resolve.tenant'])->group(function () {
    Route::get('/',                         [ModuleController::class, 'index']);
    Route::get('/actifs',                   [ModuleController::class, 'actifs']);
    Route::post('/bulk',                    [ModuleController::class, 'bulkUpdate']);
    Route::post('/{moduleKey}/activer',     [ModuleController::class, 'activer']);
    Route::post('/{moduleKey}/desactiver',  [ModuleController::class, 'desactiver']);
});

// ── Onboarding Wizard (accessible hors modules) ──
Route::prefix('onboarding')->middleware(['auth:api', 'resolve.tenant'])->group(function () {
    Route::get('/',                          [OnboardingController::class, 'statut']);
    Route::post('/avancer',                  [OnboardingController::class, 'avancer']);
    Route::post('/tester-notification',       [OnboardingController::class, 'testerNotification']);
    Route::post('/ignorer',                  [OnboardingController::class, 'ignorer']);
});

// ── Super-Admin second set (hors scope tenant) ──
Route::prefix('super-admin')->middleware(['auth:api', 'ip.allowlist', 'mfa', 'super_admin'])->group(function () {
    Route::post('tenants',                   [\App\Http\Controllers\Api\V1\SuperAdmin\TenantController::class, 'store']);
    Route::get('tenants/{id}',               [\App\Http\Controllers\Api\V1\SuperAdmin\TenantController::class, 'show']);
    Route::put('tenants/{id}',               [\App\Http\Controllers\Api\V1\SuperAdmin\TenantController::class, 'update']);
    Route::post('tenants/{id}/impersonate',  [\App\Http\Controllers\Api\V1\SuperAdmin\TenantController::class, 'impersonate']);
});
