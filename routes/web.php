<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return file_get_contents(public_path('docs.html'));
});

Route::get('/docs', function () {
    return file_get_contents(public_path('docs.html'));
});

Route::get('/api-docs.json', function () {
    return response()->file(public_path('api-docs.json'), [
        'Content-Type' => 'application/json',
    ]);
});
