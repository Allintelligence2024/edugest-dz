<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('absences:verifier-matin')
    ->dailyAt('08:30')
    ->timezone('Africa/Algiers')
    ->withoutOverlapping();
