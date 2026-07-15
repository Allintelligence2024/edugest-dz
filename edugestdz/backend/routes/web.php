<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Swagger UI — développement uniquement
if (app()->environment(['local', 'staging'])) {
    Route::get('/api/documentation', function () {
        return view('l5-swagger::index');
    });
}
