<?php

use Illuminate\Support\Facades\{Route, DB};

Route::get('/', function () {
    return view('welcome');
});

// ONE-SHOT: Seed demo data — remove after use
Route::get('/seed-demo/{key}', function (string $key) {
    if ($key !== 'edugest-seed-2026') {
        abort(403, 'Invalid key');
    }

    try {
        DB::beginTransaction();

        $seeder = new \Database\Seeders\EcoleDemoSeeder();
        $seeder->setContainer(app())->setCommand(null);
        $seeder->run();

        DB::commit();

        return response()->json(['status' => 'ok', 'message' => 'Demo data seeded successfully']);
    } catch (\Throwable $e) {
        DB::rollBack();
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

// Swagger UI — développement uniquement
if (app()->environment(['local', 'staging'])) {
    Route::get('/api/documentation', function () {
        return view('l5-swagger::index');
    });
}
