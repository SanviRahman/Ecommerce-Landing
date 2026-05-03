<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Admin Auth routes under /admin prefix
Route::prefix('admin')
    ->name('admin.')
    ->middleware('lte_context:admin')
    ->group(function () {
        Auth::routes(['register' => false]);
        require __DIR__ . '/admin.php';
    });