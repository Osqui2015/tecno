<?php

use Illuminate\Support\Facades\Route;

// SPA catch-all: cualquier ruta no-API se sirve con Vue
Route::get('/{any?}', function () {
    return view('app');
})->where('any', '^(?!api).*$');
