<?php
// routes/api/honeypot.php
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// Honeypot Routes — Leurres pour détecter les scanners
// Ces routes sont EN DEHORS du prefix v1
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

use Illuminate\Support\Facades\Route;

Route::any('/v1/phpinfo', function () {
    return app(\App\Services\HoneypotService::class)->declencherRouteLeurre();
})->name('honeypot.phpinfo');

Route::any('/v1/server-status', function () {
    return app(\App\Services\HoneypotService::class)->declencherRouteLeurre();
})->name('honeypot.server-status');

Route::any('/v1/actuator', function () {
    return app(\App\Services\HoneypotService::class)->declencherRouteLeurre();
})->name('honeypot.actuator');

Route::any('/v1/metrics', function () {
    return app(\App\Services\HoneypotService::class)->declencherRouteLeurre();
})->name('honeypot.metrics');

Route::any('/v1/.git/config', function () {
    return app(\App\Services\HoneypotService::class)->declencherRouteLeurre();
})->name('honeypot.git-config');

Route::any('/v1/swagger.json', function () {
    return app(\App\Services\HoneypotService::class)->declencherRouteLeurre();
})->name('honeypot.swagger');

Route::any('/v1/graphql', function () {
    return app(\App\Services\HoneypotService::class)->declencherRouteLeurre();
})->name('honeypot.graphql');

Route::any('/v1/health/check', function () {
    return app(\App\Services\HoneypotService::class)->declencherRouteLeurre();
})->name('honeypot.health-check');

Route::any('/v1/ping', function () {
    return app(\App\Services\HoneypotService::class)->declencherRouteLeurre();
})->name('honeypot.ping');

Route::any('/v1/test', function () {
    return app(\App\Services\HoneypotService::class)->declencherRouteLeurre();
})->name('honeypot.test');

Route::any('/v1/api-docs', function () {
    return app(\App\Services\HoneypotService::class)->declencherRouteLeurre();
})->name('honeypot.api-docs');

Route::any('/v1/robots.txt', function () {
    return app(\App\Services\HoneypotService::class)->declencherRouteLeurre();
})->name('honeypot.robots');

Route::any('/v1/sitemap.xml', function () {
    return app(\App\Services\HoneypotService::class)->declencherRouteLeurre();
})->name('honeypot.sitemap');

Route::any('/v1/cron', function () {
    return app(\App\Services\HoneypotService::class)->declencherRouteLeurre();
})->name('honeypot.cron');

Route::any('/v1/deploy', function () {
    return app(\App\Services\HoneypotService::class)->declencherRouteLeurre();
})->name('honeypot.deploy');

Route::any('/v1/websocket', function () {
    return app(\App\Services\HoneypotService::class)->declencherRouteLeurre();
})->name('honeypot.websocket');
