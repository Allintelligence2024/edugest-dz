<?php

/**
 * Point d'entrée serverless Vercel pour le backend Laravel.
 *
 * Vercel route TOUTES les requêtes /api/* vers ce fichier (voir vercel.json).
 * On délègue ensuite au front controller Laravel standard.
 *
 * ⚠️ Contexte serverless — contraintes structurantes :
 *   - Le filesystem est en lecture seule SAUF /tmp (éphémère, non partagé).
 *     storage/ est donc symlinké vers /tmp au démarrage.
 *   - Aucun worker de queue ni scheduler ne tourne : QUEUE_CONNECTION doit
 *     rester 'sync' ou pointer vers un service externe. Le scheduler est
 *     remplacé par les Vercel Cron (voir vercel.json).
 *   - Les uploads doivent aller vers un stockage objet (S3/Vercel Blob),
 *     jamais sur le disque local.
 */

// ── storage/ doit être inscriptible : on le bascule sur /tmp ──
$tmpStorage = '/tmp/storage';

foreach ([
    '/framework/views',
    '/framework/cache/data',
    '/framework/sessions',
    '/logs',
    '/app/public',
] as $dir) {
    if (!is_dir($tmpStorage . $dir)) {
        @mkdir($tmpStorage . $dir, 0755, true);
    }
}

// Laravel lira ce chemin via bootstrap/app.php (useStoragePath).
putenv("LARAVEL_STORAGE_PATH={$tmpStorage}");
$_ENV['LARAVEL_STORAGE_PATH'] = $tmpStorage;
$_SERVER['LARAVEL_STORAGE_PATH'] = $tmpStorage;

// Les vues compilées peuvent aussi être pré-générées au build ; sinon /tmp.
if (!getenv('VIEW_COMPILED_PATH')) {
    putenv("VIEW_COMPILED_PATH={$tmpStorage}/framework/views");
    $_ENV['VIEW_COMPILED_PATH'] = $tmpStorage . '/framework/views';
}

require __DIR__ . '/../public/index.php';
