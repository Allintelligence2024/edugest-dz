<?php

namespace App\Providers;

use App\Policies\ElevePolicy;
use App\Policies\FacturePolicy;
use App\Models\Eleve;
use App\Models\Facture;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $pdo = DB::connection()->getPdo();
            $pdo->sqliteCreateFunction('gen_random_uuid', function () {
                return (string) Str::uuid();
            });
        }

        \App\Models\Eleve::observe(\App\Observers\EleveObserver::class);
        \App\Models\AbsenceJournaliere::observe(\App\Observers\AbsenceJournaliereObserver::class);
        \App\Models\Note::observe(\App\Observers\NoteObserver::class);
        \App\Models\Bulletin::observe(\App\Observers\BulletinObserver::class);
        \App\Models\ReservationMarketplace::observe(\App\Observers\ReservationMarketplaceObserver::class);
        \App\Models\AlerteSurveillance::observe(\App\Observers\AlerteSurveillanceObserver::class);

        \App\Models\AuditChain::observe(\App\Observers\AuditChainObserver::class);

        Gate::policy(Eleve::class, ElevePolicy::class);
        Gate::policy(Facture::class, FacturePolicy::class);

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
            $user = $request->user();
            $role = $user?->role?->nom;

            $limits = [
                'super_admin' => 300,
                'admin'       => 200,
                'enseignant'  => 120,
                'comptable'   => 100,
                'secretariat' => 100,
                'parent'      => 60,
            ];

            $baseLimit = $limits[$role] ?? 60;

            $hour = now()->hour;
            if ($hour >= 22 || $hour < 6) {
                $baseLimit = (int) ceil($baseLimit * 0.5);
            }

            $key = $user?->id ?? $request->ip();

            return Limit::perMinute($baseLimit)
                ->by($key)
                ->response(function () use ($baseLimit) {
                    return response()->json([
                        'success' => false,
                        'message' => "Limite de requêtes atteinte ({$baseLimit}/min). Réessayez plus tard.",
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
