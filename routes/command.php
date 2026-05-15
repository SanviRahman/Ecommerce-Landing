<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

$redirectWithToast = function (string $type, string $message) {
    $previousUrl = url()->previous();

    $separator = str_contains($previousUrl, '?') ? '&' : '?';

    return redirect()->to($previousUrl . $separator . http_build_query([
        'toast_type' => $type,
        'toast_message' => $message,
    ]));
};

Route::prefix('command')
    ->name('command.')
    ->middleware(['auth', 'role:admin', 'lte_context:admin'])
    ->group(function () use ($redirectWithToast) {
        Route::get('/clear-cache', function () use ($redirectWithToast) {
            Artisan::call('cache:clear');

            return $redirectWithToast('success', 'Cache cleared successfully.');
        })->name('clear-cache');

        Route::get('/clear-config', function () use ($redirectWithToast) {
            Artisan::call('config:clear');

            return $redirectWithToast('success', 'Config cleared successfully.');
        })->name('clear-config');

        Route::get('/clear-route', function () use ($redirectWithToast) {
            Artisan::call('route:clear');

            return $redirectWithToast('success', 'Route cache cleared successfully.');
        })->name('clear-route');

        Route::get('/clear-view', function () use ($redirectWithToast) {
            Artisan::call('view:clear');

            return $redirectWithToast('success', 'View cache cleared successfully.');
        })->name('clear-view');

        Route::get('/optimize', function () use ($redirectWithToast) {
            Artisan::call('optimize');

            return $redirectWithToast('success', 'Application optimized successfully.');
        })->name('optimize');

        Route::get('/optimize-clear', function () use ($redirectWithToast) {
            Artisan::call('optimize:clear');

            return $redirectWithToast('success', 'Optimize cache cleared successfully.');
        })->name('optimize-clear');

        Route::get('/migrate', function () use ($redirectWithToast) {
            Artisan::call('migrate', [
                '--force' => true,
            ]);

            return $redirectWithToast('success', 'Database migrated successfully.');
        })->name('migrate');

        Route::get('/seed', function () use ($redirectWithToast) {
            Artisan::call('db:seed', [
                '--force' => true,
            ]);

            return $redirectWithToast('success', 'Database seeded successfully.');
        })->name('seed');

        Route::get('/storage-link', function () use ($redirectWithToast) {
            Artisan::call('storage:link');

            return $redirectWithToast('success', 'Storage link created successfully.');
        })->name('storage-link');

        Route::get('/migrate-fresh', function () use ($redirectWithToast) {
            if (! app()->environment('local')) {
                return $redirectWithToast('error', 'Fresh migrate is allowed only in local environment.');
            }

            Artisan::call('migrate:fresh', [
                '--force' => true,
            ]);

            return $redirectWithToast('success', 'Database fresh migrated successfully.');
        })->name('migrate-fresh');

        Route::get('/migrate-fresh-seed', function () use ($redirectWithToast) {
            if (! app()->environment('local')) {
                return $redirectWithToast('error', 'Fresh migrate seed is allowed only in local environment.');
            }

            Artisan::call('migrate:fresh', [
                '--seed' => true,
                '--force' => true,
            ]);

            return $redirectWithToast('success', 'Database fresh migrated and seeded successfully.');
        })->name('migrate-fresh-seed');
    });