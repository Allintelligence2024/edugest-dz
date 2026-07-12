<?php
// routes/api/auth.php
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// Auth — Public login + Protected profile/2FA
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\{
    AuthController,
    TwoFactorController,
};

// ── Auth Public ──
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

// ── Auth Protected ──
$protected = ['auth:api', 'resolve.tenant', 'tenant.verify', 'check.subscription', 'zero.trust'];
Route::middleware($protected)->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('logout',          [AuthController::class, 'logout']);
        Route::post('refresh',         [AuthController::class, 'refresh']);
        Route::get('me',               [AuthController::class, 'me']);
        Route::put('me',               [AuthController::class, 'updateProfile']);
        Route::put('change-password',  [AuthController::class, 'changePassword']);
        Route::put('password',         [AuthController::class, 'changePassword']);
        Route::put('profile',          [AuthController::class, 'updateProfile']);

        // 2FA
        Route::prefix('2fa')->group(function () {
            Route::get('status',         [TwoFactorController::class, 'status']);
            Route::post('enable',        [TwoFactorController::class, 'enable']);
            Route::post('confirm',       [TwoFactorController::class, 'confirm']);
            Route::post('disable',       [TwoFactorController::class, 'disable']);
            Route::get('recovery-codes', [TwoFactorController::class, 'recoveryCodes']);
        });
    });
});
