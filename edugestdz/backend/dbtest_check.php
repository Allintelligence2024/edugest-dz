<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$pdo = DB::connection()->getPdo();

// Check paies statut CHECK constraint
$stmt = $pdo->query("SELECT conname, pg_get_constraintdef(oid) FROM pg_constraint WHERE conrelid = 'paies'::regclass AND contype = 'c'");
echo "paies CHECK:\n";
foreach ($stmt as $row) {
    echo "  {$row['conname']}: {$row['pg_get_constraintdef']}\n";
}

// Check seances unique constraint
$stmt = $pdo->query("SELECT conname, pg_get_constraintdef(oid) FROM pg_constraint WHERE conrelid = 'seances'::regclass AND contype = 'u'");
echo "seances UNIQUE:\n";
foreach ($stmt as $row) {
    echo "  {$row['conname']}: {$row['pg_get_constraintdef']}\n";
}

// Check evaluations CHECK constraints
$stmt = $pdo->query("SELECT conname, pg_get_constraintdef(oid) FROM pg_constraint WHERE conrelid = 'evaluations'::regclass AND contype = 'c'");
echo "evaluations CHECK:\n";
foreach ($stmt as $row) {
    echo "  {$row['conname']}: {$row['pg_get_constraintdef']}\n";
}

$stmt = $pdo->query("SELECT column_name, udt_name FROM information_schema.columns WHERE table_name = 'evaluations'");
echo "evaluations columns:\n";
foreach ($stmt as $row) {
    echo "  {$row['column_name']}: {$row['udt_name']}\n";
}
