<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Artisan Command Routes
|--------------------------------------------------------------------------
| cPanel / shared hosting safe command routes.
|
| Storage Link Fix:
| - Laravel project যদি public_html/website2 folder থেকে run হয়,
|   public_path('storage') অনেক সময় website2/public/storage এ create হয়,
|   কিন্তু website URL থেকে file serve হয় public_html/storage থেকে।
| - তাই এখানে public_html/storage + public_path('storage') দুই জায়গাতেই
|   storage/app/public link/copy prepare করা হবে।
| - symlink permission denied হলেও 500 error দিবে না। fallback copy করবে।
*/

$redirectWithToast = function (string $type, string $message) {
    $previousUrl = url()->previous() ?: route('admin.dashboard');
    $separator = str_contains($previousUrl, '?') ? '&' : '?';

    return redirect()->to($previousUrl . $separator . http_build_query([
        'toast_type'    => $type,
        'toast_message' => $message,
    ]));
};

$normalizePath = function (?string $path): ?string {
    if (! $path) {
        return null;
    }

    $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

    return rtrim($path, DIRECTORY_SEPARATOR);
};

$uniquePaths = function (array $paths) use ($normalizePath): array {
    $clean = [];

    foreach ($paths as $path) {
        $path = $normalizePath($path);

        if (! $path) {
            continue;
        }

        $clean[$path] = $path;
    }

    return array_values($clean);
};

$getPublicHtmlPath = function () use ($normalizePath): ?string {
    /*
     * Priority 1: server document root. Live URL usually points here.
     */
    $documentRoot = $normalizePath($_SERVER['DOCUMENT_ROOT'] ?? null);

    if ($documentRoot && File::isDirectory($documentRoot)) {
        return $documentRoot;
    }

    /*
     * Priority 2: common cPanel layout.
     * Example:
     * base_path() = /home/username/public_html/website2
     * public_html  = /home/username/public_html
     */
    $parentOfProject = $normalizePath(dirname(base_path()));

    if ($parentOfProject && basename($parentOfProject) === 'public_html' && File::isDirectory($parentOfProject)) {
        return $parentOfProject;
    }

    /*
     * Priority 3: Laravel project outside public_html.
     * Example:
     * base_path() = /home/username/website2
     * public_html  = /home/username/public_html
     */
    $homePublicHtml = $normalizePath(dirname(base_path()) . DIRECTORY_SEPARATOR . 'public_html');

    if ($homePublicHtml && File::isDirectory($homePublicHtml)) {
        return $homePublicHtml;
    }

    /*
     * Fallback: Laravel public path.
     */
    return $normalizePath(public_path());
};

$removeBadStoragePath = function (string $linkPath): void {
    /*
     * Wrong/broken symlink হলে remove করা safe.
     */
    if (is_link($linkPath)) {
        @unlink($linkPath);
        return;
    }

    /*
     * storage নামে file থাকলে delete করা safe.
     */
    if (File::exists($linkPath) && ! File::isDirectory($linkPath)) {
        @File::delete($linkPath);
    }

    /*
     * Real directory হলে delete করা হবে না।
     * কারণ সেখানে আগে copy fallback দিয়ে file থাকতে পারে।
     */
};

$copyStorageFallback = function (string $target, string $linkPath): void {
    if (! File::isDirectory($linkPath)) {
        File::makeDirectory($linkPath, 0755, true);
    }

    /*
     * target empty হলেও directory ready থাকবে।
     * File::copyDirectory existing folder update/overwrite করে।
     */
    if (File::isDirectory($target)) {
        File::copyDirectory($target, $linkPath);
    }
};

$prepareStoragePath = function (string $target, string $linkPath) use ($removeBadStoragePath, $copyStorageFallback): array {
    $targetReal = realpath($target) ?: $target;

    try {
        if (! File::exists($target)) {
            File::makeDirectory($target, 0755, true);
        }

        if (is_link($linkPath)) {
            $currentReal = realpath($linkPath);

            if ($currentReal && $currentReal === $targetReal) {
                return [
                    'status'  => true,
                    'mode'    => 'exists',
                    'message' => 'Storage link already exists: ' . $linkPath,
                ];
            }
        }

        $removeBadStoragePath($linkPath);

        /*
         * Try real symlink first.
         */
        if (! file_exists($linkPath) && @symlink($target, $linkPath)) {
            return [
                'status'  => true,
                'mode'    => 'symlink',
                'message' => 'Storage symlink created: ' . $linkPath,
            ];
        }

        /*
         * Shared hosting fallback: copy storage/app/public into public storage.
         */
        $copyStorageFallback($target, $linkPath);

        return [
            'status'  => true,
            'mode'    => 'copy',
            'message' => 'Storage files copied: ' . $linkPath,
        ];
    } catch (Throwable $exception) {
        return [
            'status'  => false,
            'mode'    => 'failed',
            'message' => $linkPath . ' => ' . $exception->getMessage(),
        ];
    }
};

Route::prefix('command')
    ->name('command.')
    ->middleware(['auth', 'role:admin', 'lte_context:admin'])
    ->group(function () use (
        $redirectWithToast,
        $normalizePath,
        $uniquePaths,
        $getPublicHtmlPath,
        $prepareStoragePath
    ) {
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
            } catch (Throwable $exception) {
                return $redirectWithToast('error', 'Events cache clear failed: ' . $exception->getMessage());
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

        Route::get('/storage-link', function () use (
            $redirectWithToast,
            $normalizePath,
            $uniquePaths,
            $getPublicHtmlPath,
            $prepareStoragePath
        ) {
            $target = $normalizePath(storage_path('app/public'));
            $publicHtml = $getPublicHtmlPath();

            /*
             * Important for your cPanel structure:
             * public_html/storage must exist because website URL loads /storage/...
             * Also prepare public_path('storage') for normal Laravel behavior.
             */
            $candidateLinks = $uniquePaths([
                $publicHtml ? $publicHtml . DIRECTORY_SEPARATOR . 'storage' : null,
                base_path('..' . DIRECTORY_SEPARATOR . 'storage'),
                public_path('storage'),
            ]);

            $results = [];
            $successCount = 0;
            $copyCount = 0;
            $symlinkCount = 0;

            foreach ($candidateLinks as $linkPath) {
                if (! $target || $normalizePath($linkPath) === $normalizePath($target)) {
                    continue;
                }

                $result = $prepareStoragePath($target, $linkPath);
                $results[] = $result['message'];

                if ($result['status']) {
                    $successCount++;
                }

                if (($result['mode'] ?? null) === 'copy') {
                    $copyCount++;
                }

                if (($result['mode'] ?? null) === 'symlink' || ($result['mode'] ?? null) === 'exists') {
                    $symlinkCount++;
                }
            }

            if ($successCount > 0) {
                $mainMessage = $symlinkCount > 0
                    ? 'Storage link created successfully. public_html/storage is ready.'
                    : 'Symlink blocked, but storage files copied successfully. public_html/storage is ready.';

                return $redirectWithToast('success', $mainMessage);
            }

            return $redirectWithToast(
                'error',
                'Storage link failed. ' . implode(' | ', $results)
            );
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
