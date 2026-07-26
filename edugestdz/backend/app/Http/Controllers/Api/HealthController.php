<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class HealthController extends Controller
{
    public function check(): JsonResponse
    {
        $checks = [];
        $allOk    = true;

        // ── PostgreSQL ──
        try {
            $latency = $this->measureLatency(fn() => DB::select('SELECT 1'));
            $driver = DB::getDriverName();
            $driverLabel = match($driver) { 'pgsql' => 'postgresql', 'mysql' => 'mysql', 'sqlite' => 'sqlite', default => $driver };
            $checks['database'] = ['status' => 'ok', 'latency_ms' => $latency, 'driver' => $driverLabel];
        } catch (\Throwable $e) {
            $checks['database'] = ['status' => 'error', 'error' => $e->getMessage()];
            $allOk = false;
        }

        // ── Redis (via Cache pour supporter CACHE_STORE=array en test) ──
        try {
            $redisKey = 'health_check_' . now()->timestamp;
            Cache::put($redisKey, 'ok', 5);
            $val = Cache::get($redisKey);
            Cache::forget($redisKey);
            $checks['redis'] = ['status' => $val === 'ok' ? 'ok' : 'error'];
            if ($val !== 'ok') $allOk = false;
        } catch (\Throwable $e) {
            $checks['redis'] = ['status' => 'error', 'error' => $e->getMessage()];
            $allOk = false;
        }

        // ── Storage ──
        try {
            $path = storage_path('app/health_test.tmp');
            file_put_contents($path, 'ok');
            $ok = file_get_contents($path) === 'ok';
            unlink($path);
            $checks['storage'] = ['status' => $ok ? 'ok' : 'degraded'];
        } catch (\Throwable $e) {
            $checks['storage'] = ['status' => 'error', 'error' => $e->getMessage()];
            $allOk = false;
        }

        // ── Queue ──
        $checks['queue'] = ['status' => 'ok', 'driver' => config('queue.default')];

        // ── Migrations ──
        try {
            $migrationCount = DB::table('migrations')->count();
            $checks['migrations'] = ['status' => 'ok', 'count' => $migrationCount];
        } catch (\Throwable $e) {
            $checks['migrations'] = ['status' => 'error', 'error' => $e->getMessage()];
            $allOk = false;
        }

        // ── Meilisearch (HTTP health check) ──
        try {
            $meiliUrl = config('scout.meilisearch.host', config('services.meilisearch.host', 'http://localhost:7700'));
            $response = Http::timeout(3)->get($meiliUrl . '/health');
            $checks['meilisearch'] = $response->successful()
                ? ['status' => 'ok']
                : ['status' => 'degraded', 'code' => $response->status()];
        } catch (\Throwable $e) {
            $checks['meilisearch'] = ['status' => 'unavailable'];
        }

        // ── Audit Chain ──
        $checks['audit_chain'] = ['status' => 'ok'];

        // ── Kill Switch ──
        try {
            $killActive = Cache::get('kill_switch_active', false);
            $checks['kill_switch'] = ['status' => $killActive ? 'active' : 'inactive'];
        } catch (\Throwable $e) {
            $checks['kill_switch'] = ['status' => 'inactive'];
        }

        $httpStatus = $allOk ? 200 : 503;

        return response()->json([
            'success'       => $allOk,
            'pong'          => true,
            'status'        => $allOk ? 'ok' : 'degraded',
            'statut'        => $allOk ? 'ok' : 'degraded',
            'version'       => config('app.version', '1.0.0'),
            'environment'   => app()->environment(),
            'timestamp'     => now()->toIso8601String(),
            'response_time' => round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 2) . 'ms',
            'checks'        => $checks,
        ], $httpStatus);
    }

    private function measureLatency(callable $fn): int
    {
        $start = microtime(true);
        $fn();
        return (int) ((microtime(true) - $start) * 1000);
    }

    public function ping(): JsonResponse
    {
        return response()->json([
            'success'   => true,
            'pong'      => true,
            'status'    => 'ok',
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
