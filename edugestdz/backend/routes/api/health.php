<?php
// routes/api/health.php
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// Health Check — Full + Ping (P9 fix: moved inside v1)
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

use Illuminate\Support\Facades\Route;

// ── Health Check amélioré — vérifie tous les services ──
Route::get('/health', function () {
    $checks  = [];
    $allOk   = true;
    $startTs = microtime(true);

    // 1. Base de données PostgreSQL
    try {
        $dbVersion = \DB::selectOne('SELECT version()')->version;
        $checks['database'] = [
            'status'  => 'ok',
            'driver'  => 'postgresql',
            'version' => substr($dbVersion, 0, 50),
        ];
    } catch (\Throwable $e) {
        $checks['database'] = ['status' => 'error', 'message' => $e->getMessage()];
        $allOk = false;
    }

    // 2. Redis
    try {
        $redisKey = 'health_check_' . now()->timestamp;
        \Cache::put($redisKey, 'ok', 5);
        $val = \Cache::get($redisKey);
        \Cache::forget($redisKey);
        $checks['redis'] = ['status' => $val === 'ok' ? 'ok' : 'error'];
        if ($val !== 'ok') $allOk = false;
    } catch (\Throwable $e) {
        $checks['redis'] = ['status' => 'error', 'message' => $e->getMessage()];
        $allOk = false;
    }

    // 3. Meilisearch (non-bloquant)
    try {
        $response = \Http::timeout(2)->get(config('scout.meilisearch.host', 'http://localhost:7700') . '/health');
        $checks['meilisearch'] = ['status' => $response->successful() ? 'ok' : 'degraded'];
    } catch (\Throwable) {
        $checks['meilisearch'] = ['status' => 'degraded', 'message' => 'Non disponible (non-critique)'];
    }

    // 4. Stockage fichiers
    try {
        $testFile = 'health_check_' . now()->timestamp . '.txt';
        \Storage::disk('local')->put($testFile, 'ok');
        $content = \Storage::disk('local')->get($testFile);
        \Storage::disk('local')->delete($testFile);
        $checks['storage'] = ['status' => $content === 'ok' ? 'ok' : 'error'];
        if ($content !== 'ok') $allOk = false;
    } catch (\Throwable $e) {
        $checks['storage'] = ['status' => 'error', 'message' => $e->getMessage()];
        $allOk = false;
    }

    // 5. Audit Chain intégrité (vérification rapide — dernier bloc seulement)
    try {
        $dernierBloc = \DB::table('audit_chain')->orderByDesc('bloc_numero')->first(['bloc_numero', 'hash_merkle']);
        $checks['audit_chain'] = [
            'status'      => 'ok',
            'total_blocs' => $dernierBloc?->bloc_numero ?? 0,
        ];
    } catch (\Throwable) {
        $checks['audit_chain'] = ['status' => 'degraded'];
    }

    // 6. Kill Switch status
    $killActive = \Cache::has('kill_switch_active');
    $checks['kill_switch'] = ['status' => $killActive ? 'ACTIVE' : 'inactive'];
    if ($killActive) $allOk = false;

    $responseTime = round((microtime(true) - $startTs) * 1000, 2);

    return response()->json([
        'status'        => $allOk ? 'healthy' : 'degraded',
        'timestamp'     => now()->toIso8601String(),
        'version'       => env('APP_VERSION', '1.0.0'),
        'environment'   => app()->environment(),
        'response_time' => $responseTime . 'ms',
        'checks'        => $checks,
    ], $allOk ? 200 : 503);
})->name('health');

// ── Health Ping — léger, sans auth, pour UptimeRobot (P9 fix) ──
Route::get('/health/ping', [\App\Http\Controllers\Api\HealthController::class, 'ping'])->name('health.ping');
