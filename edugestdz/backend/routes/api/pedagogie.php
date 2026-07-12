<?php
// routes/api/pedagogie.php
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// Pédagogie — Matières, Salles, Groupes, Cours, Séances,
// Planning, Présences, Absences, Évaluations, Notes,
// Bulletins, Devoirs, Feedbacks
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\{
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
};

$protected = ['auth:api', 'resolve.tenant', 'tenant.verify', 'check.subscription', 'zero.trust'];
Route::middleware($protected)->group(function () {

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

    // ── Devoirs ──
    Route::prefix('devoirs')->group(function () {
        Route::get('/',    [\App\Http\Controllers\Api\V1\DevoirController::class, 'index']);
        Route::post('/',   [\App\Http\Controllers\Api\V1\DevoirController::class, 'store']);
    });

    // ── Feedbacks pédagogiques (élève → directeur) ──
    Route::prefix('feedbacks-pedagogiques')->group(function () {
        Route::get('/',                    [\App\Http\Controllers\Api\V1\FeedbackPedagogiqueController::class, 'index']);
        Route::post('/',                   [\App\Http\Controllers\Api\V1\FeedbackPedagogiqueController::class, 'store']);
        Route::get('/resume/{ensId}',      [\App\Http\Controllers\Api\V1\FeedbackPedagogiqueController::class, 'resume']);
    });
});
