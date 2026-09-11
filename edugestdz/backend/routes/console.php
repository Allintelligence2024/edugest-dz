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

// Génération automatique des factures le 1er de chaque mois à 6h00 (heure Alger)
Schedule::command('factures:generer-mensuel')
    ->monthlyOn(1, '06:00')
    ->timezone('Africa/Algiers')
    ->withoutOverlapping()
    ->runInBackground();
