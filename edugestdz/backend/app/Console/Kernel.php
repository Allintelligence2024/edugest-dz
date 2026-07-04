<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('edugest:sms-absents')
            ->weekdays()
            ->at('08:30')
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->command('edugest:generer-seances')
            ->weekly()
            ->sundays()
            ->at('23:00')
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->command('edugest:relances-impayes')
            ->dailyAt('09:00')
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->command('edugest:alertes-stock')
            ->dailyAt('07:00')
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->command('edugest:alertes-preventif')
            ->weekly()
            ->mondays()
            ->at('08:00')
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->command('model:prune')
            ->monthlyOn(1, '03:00');
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
