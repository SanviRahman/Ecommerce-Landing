<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Artisan Command Routes
|--------------------------------------------------------------------------
| Note:
| - Storage link command updated for Windows/XAMPP + shared hosting safety.
| - If symlink permission denied, it will not throw 500 error.
| - It will fallback by copying storage/app/public files to public/storage.
*/

$redirectWithToast = function (string $type, string $message) {
    $previousUrl = url()->previous() ?: route('admin.dashboard');

    $separator = str_contains($previousUrl, '?') ? '&' : '?';

    return redirect()->to($previousUrl . $separator . http_build_query([
        'toast_type'    => $type,
        'toast_message' => $message,
    ]));
};

$clearPublicStoragePath = function (string $linkPath): void {
    if (is_link($linkPath)) {
        @unlink($linkPath);
        return;
    }

    /*
     * public/storage যদি real directory হয়, সেটা delete করা risky.
     * তাই delete না করে fallback copyDirectory দিয়ে overwrite/update করা হবে।
     */
};

Route::prefix('command')
    ->name('command.')
    ->middleware(['auth', 'role:admin', 'lte_context:admin'])
    ->group(function () use ($redirectWithToast, $clearPublicStoragePath) {
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

        Route::get('/storage-link', function () use ($redirectWithToast, $clearPublicStoragePath) {
            $target = storage_path('app/public');
            $linkPath = public_path('storage');

            try {
                if (! File::exists($target)) {
                    File::makeDirectory($target, 0755, true);
                }

                /*
                 * Already correct symlink থাকলে success return.
                 */
                if (is_link($linkPath) && realpath($linkPath) === realpath($target)) {
                    return $redirectWithToast('success', 'Storage link already exists.');
                }

                /*
                 * Broken/wrong symlink থাকলে remove.
                 * Real directory হলে delete করা হবে না, fallback copy update করবে।
                 */
                $clearPublicStoragePath($linkPath);

                /*
                 * Normal Laravel path: public/storage -> storage/app/public
                 * @symlink ব্যবহার করা হয়েছে যেন permission denied হলে 500 না দেয়।
                 */
                if (! file_exists($linkPath) && @symlink($target, $linkPath)) {
                    return $redirectWithToast('success', 'Storage symbolic link created successfully.');
                }

                /*
                 * Windows/XAMPP/shared hosting fallback:
                 * symlink permission না থাকলে public/storage directory বানিয়ে files copy করবে।
                 * এতে admin panel থেকে 500 error আসবে না, images show করবে।
                 */
                if (! File::isDirectory($linkPath)) {
                    File::makeDirectory($linkPath, 0755, true);
                }

                File::copyDirectory($target, $linkPath);

                return $redirectWithToast(
                    'success',
                    'Storage symlink permission denied, but files copied to public/storage successfully.'
                );
            } catch (Throwable $e) {
                return $redirectWithToast(
                    'error',
                    'Storage link failed: ' . $e->getMessage()
                );
            }
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
                '--seed'  => true,
                '--force' => true,
            ]);

            return $redirectWithToast('success', 'Database fresh migrated and seeded successfully.');
        })->name('migrate-fresh-seed');
    });
