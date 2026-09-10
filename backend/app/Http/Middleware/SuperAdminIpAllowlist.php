<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SuperAdminIpAllowlist
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth('api')->user();

        if (!$user || $user->role?->nom !== 'super_admin') {
            return $next($request);
        }

        $allowedIpsEnv = config('app.super_admin_allowed_ips', '');

        if (empty($allowedIpsEnv)) {
            return $next($request);
        }

        $allowedIps = array_map('trim', explode(',', $allowedIpsEnv));
        $clientIp   = $request->ip();

        $autorise = false;
        foreach ($allowedIps as $allowedIp) {
            if ($this->ipCorrespond($clientIp, $allowedIp)) {
                $autorise = true;
                break;
            }
        }

        if (!$autorise) {
            Log::critical('SUPER_ADMIN IP BLOCKED', [
                'user_id'     => $user->id,
                'client_ip'   => $clientIp,
                'allowed_ips' => $allowedIps,
                'path'        => $request->path(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Accès Super-Admin refusé depuis cette adresse IP.',
                'code'    => 'IP_NOT_ALLOWED',
                'ip'      => $clientIp,
            ], 403);
        }

        return $next($request);
    }

    private function ipCorrespond(string $ip, string $pattern): bool
    {
        if ($ip === $pattern) return true;
        if ($pattern === '*') return true;

        if (str_contains($pattern, '*')) {
            $regex = '/^' . str_replace('.', '\.', str_replace('*', '\d+', $pattern)) . '$/';
            return (bool) preg_match($regex, $ip);
        }

        if (str_contains($pattern, '/')) {
            return $this->ipInCidr($ip, $pattern);
        }

        return false;
    }

    private function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $mask] = explode('/', $cidr);
        $ipLong     = ip2long($ip);
        $subnetLong = ip2long($subnet);
        $maskLong   = ~((1 << (32 - (int) $mask)) - 1);
        return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
    }
}
