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
    QrCodeController,
};

// Pentest Sprint 6 (C2) : jwt.blacklist rend enfin effectifs le verrouillage
// d'urgence (global_tokens_invalidated_at) et la révocation de jetons —
// le middleware est fail-open : sans incident, coût = 1 lecture cache.
$protected = ['auth:api', 'jwt.blacklist', 'resolve.tenant', 'tenant.verify', 'check.subscription', 'zero.trust'];

// ── RBAC (Sprint 2) ────────────────────────────────────────────────────────
// Le pédagogique est le domaine des enseignants : ils SAISISSENT notes et
// présences. En revanche la structure (matières, salles, groupes, cours) et
// la génération de bulletins relèvent de l'administration.
$structure  = 'role:admin,gestionnaire,secretariat';   // référentiels
$pedagoEcr  = 'role:admin,gestionnaire,enseignant';    // saisie pédagogique
$pedagoLect = 'role:admin,gestionnaire,secretariat,enseignant';

Route::middleware($protected)->group(function () use ($structure, $pedagoEcr, $pedagoLect) {

    // ── Matières ──
    // Référentiels : lecture ouverte à tous les authentifiés, écriture admin.
    Route::apiResource('matieres', MatiereController::class)->only(['index', 'show']);
    Route::apiResource('matieres', MatiereController::class)->except(['index', 'show'])->middleware($structure);

    // ── Salles ──
    Route::apiResource('salles', SalleController::class)->only(['index', 'show']);
    Route::apiResource('salles', SalleController::class)->except(['index', 'show'])->middleware($structure);
    Route::get('salles/{id}/disponibilites', [SalleController::class, 'disponibilites']);

    // ── Groupes ──
    Route::apiResource('groupes', GroupeController::class)->only(['index', 'show'])->middleware($pedagoLect);
    Route::apiResource('groupes', GroupeController::class)->except(['index', 'show'])->middleware($structure);
    Route::prefix('groupes')->group(function () use ($structure, $pedagoLect) {
        Route::get('{id}/eleves',            [GroupeController::class, 'eleves'])->middleware($pedagoLect);
        Route::post('{id}/eleves',           [GroupeController::class, 'addEleve'])->middleware($structure);
        Route::delete('{id}/eleves/{eleveId}',[GroupeController::class, 'removeEleve'])->middleware($structure);
    });

    // ── Cours ──
    Route::apiResource('cours', CoursController::class)->only(['index', 'show'])->middleware($pedagoLect);
    Route::apiResource('cours', CoursController::class)->except(['index', 'show'])->middleware($structure);

    // ── Séances ──
    Route::apiResource('seances', SeanceController::class)->only(['index', 'show'])->middleware($pedagoLect);
    Route::apiResource('seances', SeanceController::class)->except(['index', 'show'])->middleware($structure);
    // Cycle de vie d'une séance : piloté par l'enseignant qui la donne.
    Route::prefix('seances')->middleware($pedagoEcr)->group(function () {
        Route::post('{id}/demarrer',         [SeanceController::class, 'demarrer']);
        Route::post('{id}/terminer',         [SeanceController::class, 'terminer']);
        Route::post('{id}/annuler',          [SeanceController::class, 'annuler']);
        Route::post('{id}/reporter',         [SeanceController::class, 'reporter']);
    });

    // ── Planning ──
    Route::prefix('planning')->group(function () use ($structure) {
        Route::get('/',                      [PlanningController::class, 'index']);
        Route::get('conflits',               [PlanningController::class, 'conflits'])->middleware($structure);
        Route::post('generer',               [PlanningController::class, 'generer'])->middleware($structure);
        Route::get('export',                 [PlanningController::class, 'export'])->middleware('throttle:exports');
        Route::get('aujourd-hui',            [PlanningController::class, 'aujourdhui']);
        Route::get('ical',                   [PlanningController::class, 'exportICal'])->middleware('throttle:exports');
    });

    // ── Présences ──
    // Saisie des présences : cœur du métier enseignant.
    Route::prefix('presences')->middleware($pedagoEcr)->group(function () {
        Route::get('seance/{seanceId}',      [PresenceController::class, 'parSeance']);
        Route::post('seance/{seanceId}',     [PresenceController::class, 'saisir']);
        Route::put('{id}',                   [PresenceController::class, 'update']);
        Route::get('rapport',                [PresenceController::class, 'rapport']);
        Route::post('scan',                  [\App\Http\Controllers\Api\V1\PresenceQRController::class, 'scan']);
    });

    // ── Absences journalières élèves ──
    Route::prefix('absences')->middleware($pedagoLect)->group(function () {
        Route::get('/',                          [\App\Http\Controllers\Api\V1\AbsenceController::class, 'index']);
        Route::post('/{eleveId}',                [\App\Http\Controllers\Api\V1\AbsenceController::class, 'marquerPresent']);
        Route::put('/{id}/justifier',            [\App\Http\Controllers\Api\V1\AbsenceController::class, 'justifier']);
        Route::get('/rapport',                   [\App\Http\Controllers\Api\V1\AbsenceController::class, 'rapport']);
        Route::post('/badges/assigner',          [\App\Http\Controllers\Api\V1\AbsenceController::class, 'assignerBadge']);

        // ── Absences géographiques (carte chaleur) ──
        Route::prefix('geographie')->group(function () {
            Route::get('/par-wilaya',            [\App\Http\Controllers\Api\V1\AbsencesGeographiquesController::class, 'parWilaya']);
            Route::get('/taux-absentisme',       [\App\Http\Controllers\Api\V1\AbsencesGeographiquesController::class, 'tauxAbsentisme']);
            Route::get('/wilaya/{wilayaId}',     [\App\Http\Controllers\Api\V1\AbsencesGeographiquesController::class, 'parWilayaDetail']);
            Route::get('/resume',                [\App\Http\Controllers\Api\V1\AbsencesGeographiquesController::class, 'resume']);
        });
    });

    // ── Évaluations ──
    Route::apiResource('evaluations', EvaluationController::class)->middleware($pedagoEcr);
    Route::prefix('evaluations')->middleware($pedagoEcr)->group(function () {
        Route::get('{id}/notes',             [EvaluationController::class, 'notes']);
        Route::post('{id}/notes',            [EvaluationController::class, 'saisirNotes']);
    });

    // ── Notes ──
    // Modification d'une note : jamais accessible aux parents/élèves.
    Route::put('notes/{id}',                 [NoteController::class, 'update'])->middleware($pedagoEcr);

    // ── Bulletins ──
    Route::prefix('bulletins')->group(function () use ($structure, $pedagoLect) {
        // Lecture : un parent consulte le bulletin de son enfant (filtrage
        // par filiation assuré dans le contrôleur).
        Route::get('/',                      [BulletinController::class, 'index']);
        Route::get('{id}',                   [BulletinController::class, 'show']);
        Route::get('{id}/pdf',               [BulletinController::class, 'pdf'])->middleware('throttle:exports');

        // Génération et diffusion : administration.
        Route::post('generer',               [BulletinController::class, 'generer'])->middleware($structure);
        Route::post('{id}/envoyer',          [BulletinController::class, 'envoyer'])->middleware($structure);
    });

    // ── Devoirs ──
    Route::prefix('devoirs')->group(function () use ($pedagoEcr) {
        Route::get('/',    [\App\Http\Controllers\Api\V1\DevoirController::class, 'index']);
        Route::post('/',   [\App\Http\Controllers\Api\V1\DevoirController::class, 'store'])->middleware($pedagoEcr);
    });

    // ── Feedbacks pédagogiques (élève → directeur) ──
    Route::prefix('feedbacks-pedagogiques')->group(function () {
        Route::get('/',                    [\App\Http\Controllers\Api\V1\FeedbackPedagogiqueController::class, 'index']);
        Route::post('/',                   [\App\Http\Controllers\Api\V1\FeedbackPedagogiqueController::class, 'store']);
        Route::get('/resume/{ensId}',      [\App\Http\Controllers\Api\V1\FeedbackPedagogiqueController::class, 'resume']);
    });

    // ── QR Code Présence ──
    Route::prefix('qr-code')->middleware($pedagoEcr)->group(function () {
        Route::post('session/demarrer',    [QrCodeController::class, 'demarrerSession']);
        Route::post('session/fermer',      [QrCodeController::class, 'fermerSession']);
        Route::post('scanner',             [QrCodeController::class, 'scanner']);
        Route::get('session/{seanceId}/statut', [QrCodeController::class, 'statutSession']);
    });
});
