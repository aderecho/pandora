<?php

use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/docs', function () {
    return view('docs.swagger');
})->name('docs.ui');

Route::get('/docs/openapi.yaml', function () {
    return Response::file(base_path('openapi/openapi.yaml'), [
        'Content-Type' => 'application/yaml',
    ]);
})->name('docs.openapi');
