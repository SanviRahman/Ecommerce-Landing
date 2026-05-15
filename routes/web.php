<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| User / Frontend Routes
|--------------------------------------------------------------------------
*/
require __DIR__ . '/user.php';

/*
|--------------------------------------------------------------------------
| Admin Auth routes under /admin prefix
|--------------------------------------------------------------------------
*/
Route::prefix('admin')
    ->name('admin.')
    ->middleware('lte_context:admin')
    ->group(function () {
        Auth::routes(['register' => false]);
        require __DIR__ . '/admin.php';
    });

/*
|--------------------------------------------------------------------------
| Admin Artisan Command Routes
|--------------------------------------------------------------------------
*/
require __DIR__ . '/command.php';