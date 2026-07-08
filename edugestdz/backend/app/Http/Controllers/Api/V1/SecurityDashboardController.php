<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SecurityDashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $depuis24h = now()->subHours(24);
        $depuis7j  = now()->subDays(7);

        return response()->json([
            'success' => true,
            'data'    => [
                'evenements_24h'    => DB::table('security_events')
                    ->where('survenu_le', '>=', $depuis24h)
                    ->selectRaw('type, severite, COUNT(*) as total')
                    ->groupBy('type', 'severite')
                    ->get(),
                'critiques_24h'     => DB::table('security_events')
                    ->where('survenu_le', '>=', $depuis24h)
                    ->where('severite', 'critical')
                    ->count(),
                'brute_force_7j'    => DB::table('security_events')
                    ->where('survenu_le', '>=', $depuis7j)
                    ->where('type', 'brute_force')
                    ->count(),
                'ips_suspectes'     => DB::table('security_events')
                    ->where('survenu_le', '>=', $depuis7j)
                    ->whereIn('severite', ['critical', 'emergency'])
                    ->selectRaw('ip, COUNT(*) as total')
                    ->groupBy('ip')
                    ->orderByDesc('total')
                    ->limit(10)
                    ->get(),
                'derniers_evenements'=> DB::table('security_events')
                    ->orderByDesc('survenu_le')
                    ->limit(20)
                    ->get(),
                'jwt_blacklist_total'=> DB::table('jwt_blacklist')->count(),
                'admins_sans_mfa'   => User::whereHas('role', fn($q) => $q->whereIn('nom', ['admin','super_admin']))
                    ->whereNull('two_factor_secret')
                    ->count(),
            ],
        ]);
    }
}
