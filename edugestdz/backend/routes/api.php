<?php
// backend/routes/api.php
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// EDUGEST DZ — Routes API v1
// Refactored: sub-files in routes/api/*.php
// Le préfixe 'v1' est défini UNE SEULE FOIS ici.
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

use Illuminate\Support\Facades\Route;

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// VERSION 1 — Préfixe /api/v1
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Route::prefix('v1')->group(function () {

    // ── Marketplace (Public + Authenticated, P8 fix: merged) ──
    require __DIR__ . '/api/marketplace.php';

    // ── Auth (Public login + Protected profile/2FA) ──
    require __DIR__ . '/api/auth.php';

    // ── Core — Eleves, Parents, Enseignants, Contrats ──
    require __DIR__ . '/api/core.php';

    // ── Pédagogie — Matières, Salles, Groupes, Cours, Séances, Planning, etc. ──
    require __DIR__ . '/api/pedagogie.php';

    // ── Finance — Tarifs, Factures, Paiements, Paies, Budget ──
    require __DIR__ . '/api/finance.php';

    // ── Admin — Super-admin, Modules, Onboarding, Personnel, Paramètres, RGPD ──
    require __DIR__ . '/api/admin.php';

    // ── Security — Dashboard, Kill-Switch, Trusted Devices, Breach ──
    require __DIR__ . '/api/security.php';

    // ── Notifications — In-App, Push, Messages, Device Tokens, Campagnes ──
    require __DIR__ . '/api/notifications.php';

    // ── Extended — Transport, Cantine, Stock, Entretien, Pointage, Surveillance, etc. ──
    require __DIR__ . '/api/extended.php';

    // ── IA + Integrations — Diagnostic, Prediction, Analytics, WhatsApp, Google, LMS ──
    require __DIR__ . '/api/ia-integrations.php';

    // ── Health Check + Ping (P9 fix: moved inside v1) ──
    require __DIR__ . '/api/health.php';
});

// ── Fichier (signé, authentifié) — hors v1 ──
Route::get('/fichier/{cheminB64}', [\App\Http\Controllers\Api\FichierController::class, 'show'])
    ->middleware('auth:api');

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// HONEYPOT — Leurres EN DEHORS du prefix v1
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
require __DIR__ . '/api/honeypot.php';
