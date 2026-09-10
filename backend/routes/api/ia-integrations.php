<?php
// routes/api/ia-integrations.php
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// IA + Integrations — Diagnostic, Prediction, Analytics,
// Matching, WhatsApp, Google Classroom, LMS, Rapports
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\{
    RapportController,
    LmsController,
    AnalyticsDashboardController,
    DiagnosticController,
    PredictionController,
};

$protected = ['auth:api', 'resolve.tenant', 'tenant.verify', 'check.subscription', 'zero.trust'];
Route::middleware($protected)->group(function () {

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

    // ── Matching IA ──
    Route::prefix('matching')->group(function () {
        Route::get('suggestions', [\App\Http\Controllers\Api\V1\MatchingController::class, 'suggestions']);
    });

    // ── Diagnostic Niveau Élèves (Early Warning System) ──
    Route::prefix('diagnostic')->middleware('module:diagnostic')->group(function () {
        Route::get('/dashboard',                    [DiagnosticController::class, 'dashboard']);
        Route::get('/eleves',                       [DiagnosticController::class, 'indexDiagnostics']);
        Route::get('/eleves/{id}',                  [DiagnosticController::class, 'showDiagnostic']);
        Route::post('/eleves/{id}/analyser',        [DiagnosticController::class, 'analyserEleve']);
        Route::post('/analyser-tous',               [DiagnosticController::class, 'analyserTous']);
        Route::post('/rattrapages',                 [DiagnosticController::class, 'creerRattrapage']);
        Route::post('/convocations',                [DiagnosticController::class, 'envoyerConvocation']);
    });

    // ── IA Prediction Échec Scolaire ──
    Route::prefix('ia/prediction')->middleware('module:diagnostic')->group(function () {
        Route::get('/classement',                    [PredictionController::class, 'classementRisque']);
        Route::get('/tout',                          [PredictionController::class, 'predireTous']);
        Route::post('/tout',                         [PredictionController::class, 'predireTous']);
        Route::get('/eleve/{eleveId}',               [PredictionController::class, 'predireEleve']);
    });

    // ── Analytics Dashboard ──
    Route::prefix('analytics')->group(function () {
        Route::get('/dashboard',                     [AnalyticsDashboardController::class, 'dashboard']);
        Route::get('/finances',                      [AnalyticsDashboardController::class, 'finances']);
        Route::get('/pedagogique',                   [AnalyticsDashboardController::class, 'pedagogique']);
        Route::get('/rapport-pdf',                   [AnalyticsDashboardController::class, 'rapportPdf']);
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

// ── Google Classroom OAuth Callback (public) ──
Route::get('google/classroom/callback',  [\App\Http\Controllers\Api\V1\GoogleClassroomController::class, 'callback']);

// ── WhatsApp Webhook (Meta / public) ──
Route::prefix('whatsapp')->middleware('throttle:webhook')->group(function () {
    Route::get('webhook',                [\App\Http\Controllers\Api\V1\WhatsAppWebhookController::class, 'verify']);
    Route::post('webhook',               [\App\Http\Controllers\Api\V1\WhatsAppWebhookController::class, 'handle']);
});
