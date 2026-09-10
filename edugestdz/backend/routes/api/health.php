<?php
// routes/api/health.php
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// Health Check — Full + Ping (P9 fix: moved inside v1)
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

use Illuminate\Support\Facades\Route;

// ── Health Check — utilise HealthController::check() ──
Route::get('/health', [\App\Http\Controllers\Api\HealthController::class, 'check'])->name('health');

// ── Health Ping — léger, sans auth, pour UptimeRobot (P9 fix) ──
Route::get('/health/ping', [\App\Http\Controllers\Api\HealthController::class, 'ping'])->name('health.ping');


