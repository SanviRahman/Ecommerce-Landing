<?php

use App\Services\LandingMediaSectionOrganizerService;
use App\Services\PublicMediaStorageService;
use App\Services\ProductMediaSectionOrganizerService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

$redirectWithToast = function (string $type, string $message) {
    $source = trim((string) request()->input('source', ''));
    $returnTo = trim((string) request()->input('return_to', ''));

    $fallbackUrl = match ($source) {
        'api-orders' => route('admin.orders.api_orders'),
        'orders'     => route('admin.orders.all'),
        default      => route('admin.dashboard'),
    };

    $targetUrl = $fallbackUrl;

    /*
     * Orders pages are explicit redirect targets. A return_to value is only
     * accepted for those sources when it points back to that same orders
     * page. This keeps API Orders on API Orders while still preserving its
     * current query string/filter state.
     */
    $validRelativeReturn = $returnTo !== ''
        && str_starts_with($returnTo, '/')
        && ! str_starts_with($returnTo, '//')
        && ! str_contains($returnTo, '\\')
        && ! preg_match('/[\x00-\x1F\x7F]/', $returnTo)
        && parse_url($returnTo, PHP_URL_HOST) === null;

    if ($validRelativeReturn) {
        if (in_array($source, ['api-orders', 'orders'], true)) {
            $expectedPath = parse_url(
                $source === 'api-orders'
                    ? route('admin.orders.api_orders')
                    : route('admin.orders.all'),
                PHP_URL_PATH
            );

            $returnPath = parse_url($returnTo, PHP_URL_PATH);

            if ($returnPath === $expectedPath) {
                $targetUrl = url($returnTo);
            }
        } else {
            $targetUrl = url($returnTo);
        }
    }

    $separator = str_contains($targetUrl, '?') ? '&' : '?';

    return redirect()->to($targetUrl . $separator . http_build_query([
        'toast_type'    => $type,
        'toast_message' => $message,
    ]));
};

Route::prefix('command')
    ->name('command.')
    ->middleware(['auth', 'role:admin', 'lte_context:admin'])
    ->group(function () use ($redirectWithToast) {
        // Database migrations: read-only preview by GET, confirmed mutation by POST.
        // No raw command text or user-supplied Artisan options are accepted.
        Route::get('/database-migrations',
            [\App\Http\Controllers\Admin\DatabaseMigrationController::class, 'index'])
            ->name('database-migrations.index');

        Route::post('/database-migrations',
            [\App\Http\Controllers\Admin\DatabaseMigrationController::class, 'store'])
            ->middleware('throttle:3,15')
            ->name('database-migrations.store');

        Route::post('/clear-cache', function () use ($redirectWithToast) {
            Artisan::call('cache:clear');

            return $redirectWithToast('success', 'Cache cleared successfully.');
        })->name('clear-cache');

        Route::post('/clear-config', function () use ($redirectWithToast) {
            Artisan::call('config:clear');

            return $redirectWithToast('success', 'Config cleared successfully.');
        })->name('clear-config');

        Route::post('/clear-route', function () use ($redirectWithToast) {
            Artisan::call('route:clear');

            return $redirectWithToast('success', 'Route cache cleared successfully.');
        })->name('clear-route');

        Route::post('/clear-view', function () use ($redirectWithToast) {
            Artisan::call('view:clear');

            return $redirectWithToast('success', 'View cache cleared successfully.');
        })->name('clear-view');

        Route::post('/clear-events', function () use ($redirectWithToast) {
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

        Route::post('/optimize', function () use ($redirectWithToast) {
            Artisan::call('optimize');

            return $redirectWithToast('success', 'Application optimized successfully.');
        })->name('optimize');

        Route::post('/optimize-clear', function () use ($redirectWithToast) {
            Artisan::call('optimize:clear');

            return $redirectWithToast('success', 'Optimize cache cleared successfully.');
        })->name('optimize-clear');

        Route::post('/storage-link', function () use ($redirectWithToast) {
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

        Route::post('/sync-steadfast-statuses', function () use ($redirectWithToast) {
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

        Route::post('/sync-pathao-statuses', function () use ($redirectWithToast) {
            try {
                Artisan::call('courier:sync-pathao-statuses', [
                    '--limit' => 20,
                    '--delay' => 1000,
                    '--force' => true,
                ]);

                $output = trim(Artisan::output());
                $lines = preg_split('/\r\n|\r|\n/', $output) ?: [];
                $summary = trim((string) end($lines));

                $syncSucceeded = str_contains($summary, 'Failed: 0')
                    && str_contains($summary, 'Rate limited accounts: 0');

                return $redirectWithToast(
                    $syncSucceeded ? 'success' : 'error',
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


        Route::post('/separate-courier-cancelled-orders', function () use ($redirectWithToast) {
            try {
                Artisan::call('courier:separate-cancelled-orders', [
                    '--apply' => true,
                    '--limit' => 500,
                ]);

                $output = trim(Artisan::output());
                $lines = preg_split('/\r\n|\r|\n/', $output) ?: [];
                $summary = trim((string) end($lines));

                return $redirectWithToast(
                    str_contains($summary, 'Failed: 0') ? 'success' : 'error',
                    $summary ?: 'Courier-cancelled orders separated successfully.'
                );
            } catch (\Throwable $exception) {
                report($exception);

                return $redirectWithToast(
                    'error',
                    'Courier cancel separation failed: ' . $exception->getMessage()
                );
            }
        })->name('separate-courier-cancelled-orders');

        Route::post('/media-storage-doctor', function () use ($redirectWithToast) {
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
    });