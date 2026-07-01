<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QueryMonitor
{
    public function handle(Request $request, Closure $next)
    {
        if (app()->environment('production')) {
            return $next($request);
        }

        $queries   = [];
        $startTime = microtime(true);

        DB::listen(function ($query) use (&$queries) {
            $queries[] = [
                'sql'  => $query->sql,
                'time' => $query->time,
            ];
        });

        $response  = $next($request);
        $totalTime = round((microtime(true) - $startTime) * 1000, 1);
        $nbQueries = count($queries);
        $slowQueries = collect($queries)->filter(fn($q) => $q['time'] > 100);

        if ($nbQueries > 20 || $totalTime > 500) {
            Log::warning('[QueryMonitor] Performance alert', [
                'route'       => $request->path(),
                'nb_queries'  => $nbQueries,
                'total_ms'    => $totalTime,
                'slow_queries'=> $slowQueries->count(),
            ]);
        }

        $response->headers->set('X-Query-Count', $nbQueries);
        $response->headers->set('X-Response-Time', $totalTime . 'ms');

        return $response;
    }
}
