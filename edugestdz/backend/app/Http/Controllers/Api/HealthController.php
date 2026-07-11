<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class HealthController extends Controller
{
    public function check(): JsonResponse
    {
        $services = [];
        $allOk    = true;

        try {
            DB::select('SELECT 1');
            $services['postgresql'] = ['status' => 'ok', 'latency_ms' => $this->measureLatency(fn() => DB::select('SELECT 1'))];
        } catch (\Throwable $e) {
            $services['postgresql'] = ['status' => 'error', 'error' => $e->getMessage()];
            $allOk = false;
        }

        try {
            $key = 'health_check_' . uniqid();
            Cache::put($key, 'ok', 5);
            $val = Cache::get($key);
            Cache::forget($key);
            $services['redis'] = ['status' => $val === 'ok' ? 'ok' : 'degraded'];
        } catch (\Throwable $e) {
            $services['redis'] = ['status' => 'error', 'error' => $e->getMessage()];
            $allOk = false;
        }

        try {
            $path = storage_path('app/health_test.tmp');
            file_put_contents($path, 'ok');
            $ok = file_get_contents($path) === 'ok';
            unlink($path);
            $services['storage'] = ['status' => $ok ? 'ok' : 'degraded'];
        } catch (\Throwable $e) {
            $services['storage'] = ['status' => 'error', 'error' => $e->getMessage()];
            $allOk = false;
        }

        $services['queue'] = ['status' => 'ok', 'driver' => config('queue.default')];

        try {
            $client = app(\Laravel\Scout\Engines\MeilisearchEngine::class);
            $services['meilisearch'] = ['status' => 'ok'];
        } catch (\Throwable) {
            $services['meilisearch'] = ['status' => 'unavailable'];
        }

        $httpStatus = $allOk ? 200 : 503;

        return response()->json([
            'status'      => $allOk ? 'ok' : 'degraded',
            'version'     => config('app.version', '1.0.0'),
            'environment' => app()->environment(),
            'timestamp'   => now()->toIso8601String(),
            'services'    => $services,
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
            'status'    => 'ok',
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
