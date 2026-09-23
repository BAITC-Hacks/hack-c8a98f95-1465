<?php

use Illuminate\Support\Facades\Route;

Route::get('/{path?}', function () {
    $index = public_path('build/index.html');
    abort_unless(is_file($index), 503, 'Build the frontend first: run start.ps1 or docker compose up --build.');

    return response()->file($index, ['Cache-Control' => 'no-cache']);
})->where('path', '(?!api(?:/|$)|build(?:/|$)|up(?:/|$)|.*\.).*');
