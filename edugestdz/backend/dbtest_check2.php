<?php
$_ENV['APP_ENV'] = 'testing';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
$app->make('config')->set('database.default', 'pgsql');

$pdo = DB::connection()->getPdo();
$pdo->exec("SET search_path TO public");

// List all CHECK constraints for paies
$stmt = $pdo->query("
    SELECT con.conname, pg_get_constraintdef(con.oid) as def
    FROM pg_catalog.pg_constraint con
    JOIN pg_catalog.pg_class rel ON rel.oid = con.conrelid
    WHERE rel.relname = 'paies'
    AND con.contype = 'c'
");
echo "paies CHECK constraints:\n";
foreach ($stmt as $row) {
    echo "  {$row['conname']}: {$row['def']}\n";
}

// List all CHECK constraints for seances
$stmt = $pdo->query("
    SELECT con.conname, pg_get_constraintdef(con.oid) as def
    FROM pg_catalog.pg_constraint con
    JOIN pg_catalog.pg_class rel ON rel.oid = con.conrelid
    WHERE rel.relname = 'seances'
    AND con.contype = 'c'
");
echo "seances CHECK constraints:\n";
foreach ($stmt as $row) {
    echo "  {$row['conname']}: {$row['def']}\n";
}
