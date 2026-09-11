<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SqlInjectionDetectorMiddleware
{
    private array $exclusions = [
        '/api/health',
        '/api/v1/auth/login',
    ];

    private array $patterns = [
        '/\bUNION\b.*\bSELECT\b/i',
        '/\bSELECT\b.*\bFROM\b/i',
        '/\bINSERT\b.*\bINTO\b/i',
        '/\bDROP\b.*\bTABLE\b/i',
        '/\bDELETE\b.*\bFROM\b/i',
        '/\bALTER\b.*\bTABLE\b/i',
        '/\bEXEC\b.*\(/i',
        '/\bOR\b.*\b1=1\b/i',
        '/\bOR\b.*\b1=0\b/i',
        '/\bOR\b.*\'.\'=\'/i',
        '/\bWAITFOR\b.*\bDELAY\b/i',
        '/\bPG_SLEEP\b/i',
        '/\bSLEEP\b.*\(/i',
        '/\bBENCHMARK\b.*\(/i',
        '/\bINTO\s+OUTFILE\b/i',
        '/\bINTO\s+DUMPFILE\b/i',
        '/\bLOAD_FILE\b/i',
        '/\/\*/',
        '/\bNULL\b/i',
        '/\bHAVING\b/i',
    ];

    public function handle(Request $request, Closure $next)
    {
        foreach ($this->exclusions as $exclusion) {
            if ($request->is($exclusion)) {
                return $next($request);
            }
        }

        $inputs = $request->all();

        foreach ($inputs as $key => $value) {
            if (is_string($value) && $this->contientInjection($value)) {
                Log::warning('SQL Injection detectee', [
                    'ip' => $request->ip(),
                    'path' => $request->path(),
                    'field' => $key,
                    'value' => substr($value, 0, 200),
                    'user_id' => $request->user()?->id,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Requete invalide.',
                    'code' => 'INVALID_REQUEST',
                ], 400);
            }
        }

        return $next($request);
    }

    private function contientInjection(string $value): bool
    {
        foreach ($this->patterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }

        return false;
    }
}
