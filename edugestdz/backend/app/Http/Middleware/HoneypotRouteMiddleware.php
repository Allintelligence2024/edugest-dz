<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HoneypotRouteMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        Log::warning('Honeypot: route leurre déclenchée', [
            'ip' => $request->ip(),
            'path' => $request->path(),
            'method' => $request->method(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json(['message' => 'Not Found.'], 404);
    }
}
