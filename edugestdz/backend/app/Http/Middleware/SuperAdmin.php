<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SuperAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();

        if (!$user || $user->role !== 'super_admin') {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'FORBIDDEN', 'message' => 'Accès réservé aux super administrateurs'],
            ], 403);
        }

        return $next($request);
    }
}
