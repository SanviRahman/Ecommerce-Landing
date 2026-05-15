<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::prefix('command')
    ->name('command.')
    ->middleware(['auth', 'role:admin', 'lte_context:admin'])
    ->group(function () {
        Route::get('/clear-cache', function () {
            Artisan::call('cache:clear');

            return back()->with('success', 'Cache cleared successfully. ' . Artisan::output());
        })->name('clear-cache');

        Route::get('/clear-config', function () {
            Artisan::call('config:clear');

            return back()->with('success', 'Config cleared successfully. ' . Artisan::output());
        })->name('clear-config');

        Route::get('/clear-route', function () {
            Artisan::call('route:clear');

            return back()->with('success', 'Route cache cleared successfully. ' . Artisan::output());
        })->name('clear-route');

        Route::get('/clear-view', function () {
            Artisan::call('view:clear');

            return back()->with('success', 'View cache cleared successfully. ' . Artisan::output());
        })->name('clear-view');

        Route::get('/optimize', function () {
            Artisan::call('optimize');

            return back()->with('success', 'Application optimized successfully. ' . Artisan::output());
        })->name('optimize');

        Route::get('/optimize-clear', function () {
            Artisan::call('optimize:clear');

            return back()->with('success', 'Optimize cache cleared successfully. ' . Artisan::output());
        })->name('optimize-clear');

        Route::get('/migrate', function () {
            Artisan::call('migrate', [
                '--force' => true,
            ]);

            return back()->with('success', 'Database migrated successfully. ' . Artisan::output());
        })->name('migrate');

        Route::get('/seed', function () {
            Artisan::call('db:seed', [
                '--force' => true,
            ]);

            return back()->with('success', 'Database seeded successfully. ' . Artisan::output());
        })->name('seed');

        Route::get('/storage-link', function () {
            Artisan::call('storage:link');

            return back()->with('success', 'Storage link created successfully. ' . Artisan::output());
        })->name('storage-link');

        Route::get('/migrate-fresh', function () {
            if (! app()->environment('local')) {
                return back()->with('error', 'Fresh migrate is allowed only in local environment.');
            }

            Artisan::call('migrate:fresh', [
                '--force' => true,
            ]);

            return back()->with('success', 'Database fresh migrated successfully. ' . Artisan::output());
        })->name('migrate-fresh');

        Route::get('/migrate-fresh-seed', function () {
            if (! app()->environment('local')) {
                return back()->with('error', 'Fresh migrate seed is allowed only in local environment.');
            }

            Artisan::call('migrate:fresh', [
                '--seed' => true,
                '--force' => true,
            ]);

            return back()->with('success', 'Database fresh migrated and seeded successfully. ' . Artisan::output());
        })->name('migrate-fresh-seed');
    });