<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        \App\Models\Eleve::observe(\App\Observers\EleveObserver::class);
        \App\Models\AbsenceJournaliere::observe(\App\Observers\AbsenceJournaliereObserver::class);
        \App\Models\Note::observe(\App\Observers\NoteObserver::class);
        \App\Models\Bulletin::observe(\App\Observers\BulletinObserver::class);
        \App\Models\ReservationMarketplace::observe(\App\Observers\ReservationMarketplaceObserver::class);

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinutes(15, 10)
                ->by($request->ip())
                ->response(function () {
                    return response()->json([
                        'success' => false,
                        'message' => 'Trop de tentatives de connexion. Réessayez dans 15 minutes.',
                    ], 429);
                });
        });

        RateLimiter::for('api', function (Request $request) {
            $tenantId = $request->header('X-Tenant-ID', $request->ip());
            return Limit::perMinute(100)
                ->by($tenantId)
                ->response(function () {
                    return response()->json([
                        'success' => false,
                        'message' => 'Limite de requêtes atteinte (100/min). Contactez le support si ce problème persiste.',
                    ], 429);
                });
        });

        RateLimiter::for('exports', function (Request $request) {
            return Limit::perMinute(10)
                ->by(optional($request->user())->id ?? $request->ip())
                ->response(function () {
                    return response()->json([
                        'success' => false,
                        'message' => "Trop d'exports. Attendez 1 minute.",
                    ], 429);
                });
        });

        RateLimiter::for('notifications', function (Request $request) {
            return Limit::perHour(20)
                ->by($request->header('X-Tenant-ID', $request->ip()));
        });

        RateLimiter::for('webhook', function (Request $request) {
            $twilioIPs = [
                '54.172.60.0/23', '54.244.51.0/24',
                '54.171.127.192/26', '54.65.63.192/26',
            ];
            return Limit::perMinute(30)->by($request->ip());
        });
    }
}
