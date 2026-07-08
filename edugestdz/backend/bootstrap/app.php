<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->throttleApi();

        $middleware->api(prepend: [
            \App\Http\Middleware\LicenceCheck::class,
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\QueryMonitor::class,
        ]);

        $middleware->alias([
            'resolve.tenant'    => \App\Http\Middleware\ResolveTenant::class,
            'tenant'            => \App\Http\Middleware\ResolveTenant::class,
            'check.subscription' => \App\Http\Middleware\CheckSubscription::class,
            'super_admin'       => \App\Http\Middleware\SuperAdmin::class,
            'module'            => \App\Http\Middleware\ModuleCheck::class,
            'mfa'               => \App\Http\Middleware\MfaRequired::class,
            'ip.allowlist'      => \App\Http\Middleware\SuperAdminIpAllowlist::class,
            'tenant.verify'     => \App\Http\Middleware\TenantIsolationVerifier::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('edugest:relances-paiement')
                 ->dailyAt('08:00')
                 ->timezone('Africa/Algiers')
                 ->withoutOverlapping();

        $schedule->command('edugest:generer-seances')
                 ->weeklyOn(6, '22:00')
                 ->timezone('Africa/Algiers')
                 ->withoutOverlapping();

        $schedule->command('edugest:calculer-paies')
                 ->monthlyOn(1, '06:00')
                 ->timezone('Africa/Algiers');

        $schedule->command('edugest:sms-absents')
                 ->weekdays()
                 ->at('08:30')
                 ->timezone('Africa/Algiers')
                 ->withoutOverlapping()
                 ->runInBackground();

        $schedule->command('edugest:relances-impayes')
                 ->dailyAt('09:00')
                 ->timezone('Africa/Algiers')
                 ->withoutOverlapping()
                 ->runInBackground();

        $schedule->command('edugest:alertes-stock')
                 ->dailyAt('07:00')
                 ->timezone('Africa/Algiers')
                 ->withoutOverlapping()
                 ->runInBackground();

        $schedule->command('edugest:alertes-preventif')
                 ->weekly()
                 ->mondays()
                 ->at('08:00')
                 ->timezone('Africa/Algiers')
                 ->withoutOverlapping()
                 ->runInBackground();

        $schedule->command('queue:prune-failed --hours=720')
                 ->weekly()
                 ->sundays()
                 ->at('03:00');

        $schedule->command('edugest:audit-export')
                 ->dailyAt('02:00')
                 ->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
