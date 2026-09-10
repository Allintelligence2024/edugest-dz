<?php
// routes/api/security.php
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// Security — Dashboard, Kill-Switch, Trusted Devices,
// Breach Response
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\SecurityDashboardController;

// ── Security Dashboard (inside protected group) ──
// Pentest Sprint 6 (C2) : jwt.blacklist rend enfin effectifs le verrouillage
// d'urgence (global_tokens_invalidated_at) et la révocation de jetons —
// le middleware est fail-open : sans incident, coût = 1 lecture cache.
$protected = ['auth:api', 'jwt.blacklist', 'resolve.tenant', 'tenant.verify', 'check.subscription', 'zero.trust'];
Route::middleware($protected)->group(function () {

    // ── Security Dashboard ──
    // Pentest Sprint 6 : sans contrôle de rôle, n'importe quel compte
    // authentifié (parent, élève) pouvait lire le dashboard de sécurité.
    Route::get('security/dashboard', [SecurityDashboardController::class, 'index'])
        ->middleware('role:admin');

    // ── Kill Switch (Niveau 6) ──
    // Pentest Sprint 6 : ces routes coupaient le service pour TOUS les
    // tenants (503 global) alors qu'elles n'exigeaient qu'être authentifié —
    // deux comptes quelconques (initiateur + approbateur distincts)
    // suffisaient. Réservées aux admins (le super_admin traverse role:).
    Route::prefix('kill-switch')->middleware('role:admin')->group(function () {
        Route::post('initier',    [\App\Http\Controllers\Api\V1\KillSwitchController::class, 'initier']);
        Route::post('{voteId}/approuver', [\App\Http\Controllers\Api\V1\KillSwitchController::class, 'approuver']);
        Route::post('{voteId}/refuser',   [\App\Http\Controllers\Api\V1\KillSwitchController::class, 'refuser']);
    });

    // ── Trusted Devices (Niveau 4) ──
    Route::prefix('trusted-devices')->group(function () {
        Route::get('/',                      [\App\Http\Controllers\Api\V1\TrustedDeviceController::class, 'index']);
        Route::delete('{id}',                [\App\Http\Controllers\Api\V1\TrustedDeviceController::class, 'destroy']);
    });

    // ── Zero-Trust : vérification du challenge (Sprint 6, pentest C3) ──
    // Code reçu par e-mail (hors-bande) après un 428 ZERO_TRUST_CHALLENGE.
    // Throttle « auth » : le code est court, on limite la force brute.
    Route::post('security/zero-trust/verify', [\App\Http\Controllers\Api\V1\TrustedDeviceController::class, 'verifierChallenge'])
        ->middleware('throttle:auth');
});

// ── Breach Response & Security Incidents (own middleware) ──
Route::prefix('security/breach')->middleware(['auth:api', 'jwt.blacklist', 'ip.allowlist'])->group(function () {
    Route::post('/verrouillage-urgence', [\App\Http\Controllers\Api\V1\BreachResponseController::class, 'verrouillageUrgence']);
    Route::post('/incidents',            [\App\Http\Controllers\Api\V1\BreachResponseController::class, 'declarerIncident']);
    Route::get('/incidents',             [\App\Http\Controllers\Api\V1\BreachResponseController::class, 'indexIncidents']);
    Route::delete('/verrouillage',       [\App\Http\Controllers\Api\V1\BreachResponseController::class, 'leverVerrouillage']);
});
