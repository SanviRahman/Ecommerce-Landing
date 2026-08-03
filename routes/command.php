<?php

use App\Services\LandingMediaSectionOrganizerService;
use App\Services\PublicMediaStorageService;
use App\Services\ProductMediaSectionOrganizerService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

$redirectWithToast = function (string $type, string $message) {
    $previousUrl = url()->previous() ?: route('admin.dashboard');
    $separator = str_contains($previousUrl, '?') ? '&' : '?';

    return redirect()->to($previousUrl . $separator . http_build_query([
        'toast_type'    => $type,
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

        Route::get('/clear-events', function () use ($redirectWithToast) {
            try {
                Artisan::call('event:clear');

                return $redirectWithToast('success', 'Events cache cleared successfully.');
            } catch (\Throwable $exception) {
                report($exception);

                return $redirectWithToast(
                    'error',
                    'Events cache clear failed: ' . $exception->getMessage()
                );
            }
        })->name('clear-events');

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
            try {
                $result = app(PublicMediaStorageService::class)->prepare(true);
                $productResult = app(
                    ProductMediaSectionOrganizerService::class
                )->organize();
                $landingResult = app(
                    LandingMediaSectionOrganizerService::class
                )->organize();

                Artisan::call('optimize:clear');

                $message = sprintf(
                    'Media storage prepared successfully. Root: %s. Migrated: %d files. Legacy files removed: %d. Product media organized: %d. Landing media organized: %d. Missing product files: %d. Missing landing files: %d.',
                    $result['target'],
                    $result['copied_files'],
                    $result['removed_files'],
                    $productResult['organized_media'],
                    $landingResult['organized_media'],
                    $productResult['missing_files'],
                    $landingResult['missing_files']
                );

                if (! $result['writable']) {
                    return $redirectWithToast(
                        'error',
                        $message . ' The media directory is not writable.'
                    );
                }

                return $redirectWithToast('success', $message);
            } catch (\Throwable $exception) {
                report($exception);

                return $redirectWithToast(
                    'error',
                    'Media storage preparation failed: ' . $exception->getMessage()
                );
            }
        })->name('storage-link');

        Route::get('/sync-steadfast-statuses', function () use ($redirectWithToast) {
            try {
                Artisan::call('courier:sync-steadfast-statuses', [
                    '--limit' => 100,
                    '--force' => true,
                ]);

                $output = trim(Artisan::output());
                $lines = preg_split('/\r\n|\r|\n/', $output) ?: [];
                $summary = trim((string) end($lines));

                return $redirectWithToast(
                    str_contains($summary, 'Failed: 0') ? 'success' : 'error',
                    $summary ?: 'SteadFast courier statuses synced.'
                );
            } catch (\Throwable $exception) {
                report($exception);

                return $redirectWithToast(
                    'error',
                    'SteadFast status sync failed: ' . $exception->getMessage()
                );
            }
        })->name('sync-steadfast-statuses');

        Route::get('/sync-pathao-statuses', function () use ($redirectWithToast) {
            try {
                Artisan::call('courier:sync-pathao-statuses', [
                    '--limit' => 100,
                    '--force' => true,
                ]);

                $output = trim(Artisan::output());
                $lines = preg_split('/\r\n|\r|\n/', $output) ?: [];
                $summary = trim((string) end($lines));

                return $redirectWithToast(
                    str_contains($summary, 'Failed: 0') ? 'success' : 'error',
                    $summary ?: 'Pathao courier statuses synced.'
                );
            } catch (\Throwable $exception) {
                report($exception);

                return $redirectWithToast(
                    'error',
                    'Pathao status sync failed: ' . $exception->getMessage()
                );
            }
        })->name('sync-pathao-statuses');

        Route::get('/media-storage-doctor', function () use ($redirectWithToast) {
            try {
                Artisan::call('media:storage-doctor', [
                    '--limit' => 20,
                ]);

                $output = trim(Artisan::output());
                $message = str_contains($output, 'Single public media root is ready.')
                    ? 'Media storage check passed. Admin and public images use one physical folder.'
                    : 'Media storage needs attention. Run Prepare Media Storage first.';

                return $redirectWithToast(
                    str_contains($output, 'Single public media root is ready.') ? 'success' : 'error',
                    $message
                );
            } catch (\Throwable $exception) {
                report($exception);

                return $redirectWithToast(
                    'error',
                    'Media storage check failed: ' . $exception->getMessage()
                );
            }
        })->name('media-storage-doctor');

        Route::get('/migrate-fresh', function () use ($redirectWithToast) {
            if (! app()->environment('local')) {
                return $redirectWithToast(
                    'error',
                    'Fresh migrate is allowed only in local environment.'
                );
            }

            Artisan::call('migrate:fresh', [
                '--force' => true,
            ]);

            return $redirectWithToast('success', 'Database fresh migrated successfully.');
        })->name('migrate-fresh');

        Route::get('/migrate-fresh-seed', function () use ($redirectWithToast) {
            if (! app()->environment('local')) {
                return $redirectWithToast(
                    'error',
                    'Fresh migrate seed is allowed only in local environment.'
                );
            }

            Artisan::call('migrate:fresh', [
                '--seed'  => true,
                '--force' => true,
            ]);

            return $redirectWithToast(
                'success',
                'Database fresh migrated and seeded successfully.'
            );
        })->name('migrate-fresh-seed');
    });