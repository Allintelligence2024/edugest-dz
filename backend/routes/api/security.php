<?php
// routes/api/security.php
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// Security — Dashboard, Kill-Switch, Trusted Devices,
// Breach Response
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\SecurityDashboardController;

// ── Security Dashboard (inside protected group) ──
$protected = ['auth:api', 'resolve.tenant', 'tenant.verify', 'check.subscription', 'zero.trust'];
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
});

// ── Breach Response & Security Incidents (own middleware) ──
Route::prefix('security/breach')->middleware(['auth:api', 'ip.allowlist'])->group(function () {
    Route::post('/verrouillage-urgence', [\App\Http\Controllers\Api\V1\BreachResponseController::class, 'verrouillageUrgence']);
    Route::post('/incidents',            [\App\Http\Controllers\Api\V1\BreachResponseController::class, 'declarerIncident']);
    Route::get('/incidents',             [\App\Http\Controllers\Api\V1\BreachResponseController::class, 'indexIncidents']);
    Route::delete('/verrouillage',       [\App\Http\Controllers\Api\V1\BreachResponseController::class, 'leverVerrouillage']);
});
