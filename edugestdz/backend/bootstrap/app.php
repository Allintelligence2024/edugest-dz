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
        $middleware->alias([
            'resolve.tenant'    => \App\Http\Middleware\ResolveTenant::class,
            'check.subscription' => \App\Http\Middleware\CheckSubscription::class,
            'super_admin'       => \App\Http\Middleware\SuperAdmin::class,
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

        $schedule->command('queue:prune-failed --hours=720')
                 ->weekly()
                 ->sundays()
                 ->at('03:00');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
