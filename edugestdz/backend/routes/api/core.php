<?php
// routes/api/core.php
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// Core — Eleves, Parents, Enseignants, Contrats
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\{
    SearchController,
    EleveController,
    ParentController,
    EnseignantController,
    ContratController,
};

// Pentest Sprint 6 (C2) : jwt.blacklist rend enfin effectifs le verrouillage
// d'urgence (global_tokens_invalidated_at) et la révocation de jetons —
// le middleware est fail-open : sans incident, coût = 1 lecture cache.
$protected = ['auth:api', 'jwt.blacklist', 'resolve.tenant', 'tenant.verify', 'check.subscription', 'zero.trust'];

// ── RBAC (Sprint 2) ────────────────────────────────────────────────────────
// Les dossiers élèves/parents/enseignants sont administratifs : un parent ne
// doit pas pouvoir lister tous les élèves de l'établissement ni modifier des
// fiches. Les enseignants gardent un accès en LECTURE (besoin pédagogique).
$gestionEleves = 'role:admin,gestionnaire,secretariat';
$lectureEleves = 'role:admin,gestionnaire,secretariat,comptable,enseignant';
$lectureDossier = 'role:admin,gestionnaire,secretariat,comptable,enseignant,parent'; // dossier élève : parent admis, filiation imposée par verifierPerimetreEleve
$gestionRh     = 'role:admin,gestionnaire';

Route::middleware($protected)->group(function () use ($gestionEleves, $lectureEleves, $gestionRh) {

    // ── Recherche globale ──
    Route::get('search', SearchController::class);

    // ── Élèves ──
    Route::apiResource('eleves', EleveController::class)
        ->only(['index', 'show'])
        ->middleware($lectureEleves);
    Route::apiResource('eleves', EleveController::class)
        ->except(['index', 'show'])
        ->middleware($gestionEleves);

    Route::prefix('eleves')->group(function () use ($gestionEleves, $lectureEleves, $lectureDossier) {
        // Consultation du dossier pédagogique.
        Route::middleware($lectureDossier)->group(function () {
            Route::get('{id}/notes',        [EleveController::class, 'notes']);
            Route::get('{id}/presences',    [EleveController::class, 'presences']);
            Route::get('{id}/bulletins',    [EleveController::class, 'bulletins']);
            Route::get('{id}/statistiques', [EleveController::class, 'statistiques']);
        });

        // Données financières de l'élève : hors périmètre enseignant.
        Route::get('{id}/paiements',        [EleveController::class, 'paiements'])
            ->middleware('role:admin,gestionnaire,secretariat,comptable,parent');

        // Écriture et exports en masse : administratif uniquement.
        Route::middleware($gestionEleves)->group(function () {
            Route::post('{id}/photo',       [EleveController::class, 'uploadPhoto']);
            Route::post('{id}/inscription', [EleveController::class, 'inscrire']);
            Route::post('import',           [EleveController::class, 'import']);
            Route::get('export',            [EleveController::class, 'export']);
            Route::get('export/excel',      [EleveController::class, 'exportExcel'])->middleware('throttle:exports');
        });
    });

    // ── QR Code élève ──
    Route::get('eleves/{id}/qrcode', [\App\Http\Controllers\Api\V1\PresenceQRController::class, 'qrcode'])
        ->middleware($gestionEleves);

    // ── Parents ──
    Route::apiResource('parents', ParentController::class)
        ->only(['index', 'show'])
        ->middleware($lectureEleves);
    Route::apiResource('parents', ParentController::class)
        ->except(['index', 'show'])
        ->middleware($gestionEleves);

    // ── Enseignants ──
    Route::apiResource('enseignants', EnseignantController::class)
        ->only(['index', 'show'])
        ->middleware($lectureEleves);
    Route::apiResource('enseignants', EnseignantController::class)
        ->except(['index', 'show'])
        ->middleware($gestionRh);

    Route::prefix('enseignants')->group(function () use ($gestionRh) {
        // Un enseignant consulte son propre planning : lecture ouverte,
        // le filtrage par identité est assuré dans le contrôleur.
        Route::get('{id}/planning',         [EnseignantController::class, 'planning']);
        Route::get('{id}/statistiques',     [EnseignantController::class, 'statistiques']);
        Route::post('{id}/disponibilites',  [EnseignantController::class, 'setDisponibilites']);

        // Actions RH.
        Route::post('{id}/photo',           [EnseignantController::class, 'uploadPhoto'])->middleware($gestionRh);
        Route::post('{id}/toggle-statut',   [EnseignantController::class, 'toggleStatut'])->middleware($gestionRh);
    });

    // ── Contrats de travail : RH strict. ──
    Route::apiResource('contrats', ContratController::class)->middleware($gestionRh);
});
