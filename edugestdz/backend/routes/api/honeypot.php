<?php
// routes/api/honeypot.php
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// Honeypot — routes leurres pour détecter les scanners.
//
// Les chemins ne sont plus écrits ici : ils viennent de
// config('security.honeypot.routes'), source unique partagée avec
// HoneypotService. Voir config/security.php pour le détail et l'historique
// de la divergence qui a motivé cette centralisation.
//
// Ces routes sont enregistrées EN DEHORS du préfixe v1 (le chemin le porte
// déjà) et en DERNIER, après toutes les routes métier : un leurre ne peut
// donc jamais masquer une route réelle.
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

use App\Services\HoneypotService;
use Illuminate\Support\Facades\Route;

if (! config('security.honeypot.actif', true)) {
    return;
}

foreach (config('security.honeypot.routes', []) as $nom => $chemin) {
    Route::any($chemin, static function () {
        return app(HoneypotService::class)->declencherRouteLeurre();
    })->name("honeypot.{$nom}");
}
