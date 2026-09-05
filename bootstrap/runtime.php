<?php

/**
 * Lightweight deployment/runtime preflight.
 *
 * This file intentionally uses only native PHP so it can run before Composer
 * and Laravel are bootstrapped. It keeps cloned deployments portable by:
 * - preparing Laravel writable runtime directories;
 * - keeping storage/logs/laravel.log writable when permissions allow it;
 * - discarding a config cache copied from another server/path;
 * - reporting the PHP version required by the installed Composer vendor set.
 */

$runtimeBasePath = dirname(__DIR__);

$normalizeRuntimePath = static function (string $path): string {
    $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

    return rtrim($normalized, DIRECTORY_SEPARATOR);
};

$runtimeBasePath = $normalizeRuntimePath(realpath($runtimeBasePath) ?: $runtimeBasePath);
$runtimeStoragePath = $runtimeBasePath . DIRECTORY_SEPARATOR . 'storage';
$runtimeLogDirectory = $runtimeStoragePath . DIRECTORY_SEPARATOR . 'logs';
$runtimeLogFile = $runtimeLogDirectory . DIRECTORY_SEPARATOR . 'laravel.log';
$runtimeBootstrapCache = $runtimeBasePath . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'cache';

$runtimeDirectories = [
    $runtimeLogDirectory,
    $runtimeStoragePath . DIRECTORY_SEPARATOR . 'framework',
    $runtimeStoragePath . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'cache',
    $runtimeStoragePath . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'data',
    $runtimeStoragePath . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'sessions',
    $runtimeStoragePath . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'views',
    $runtimeBootstrapCache,
];

foreach ($runtimeDirectories as $runtimeDirectory) {
    if (! is_dir($runtimeDirectory)) {
        @mkdir($runtimeDirectory, 0775, true);
    }

    if (is_dir($runtimeDirectory)) {
        @chmod($runtimeDirectory, 0775);
    }
}

if (! is_file($runtimeLogFile)) {
    @touch($runtimeLogFile);
}

if (is_file($runtimeLogFile)) {
    @chmod($runtimeLogFile, 0664);
}

if (is_writable($runtimeLogDirectory) || is_writable($runtimeLogFile)) {
    @ini_set('log_errors', '1');
    @ini_set('error_log', $runtimeLogFile);
}

$writeRuntimeLog = static function (string $message) use ($runtimeLogFile): void {
    $line = '[' . date('Y-m-d H:i:s') . '] deployment-runtime: ' . $message . PHP_EOL;
    @file_put_contents($runtimeLogFile, $line, FILE_APPEND | LOCK_EX);
};

/*
 * Composer's generated platform check is the source of truth for the vendor
 * folder currently shipped with this project. Detect its PHP minimum before
 * requiring vendor/autoload.php so an incompatible server gets a useful error
 * instead of an unexplained blank 500 response.
 */
$platformCheckFile = $runtimeBasePath . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'composer' . DIRECTORY_SEPARATOR . 'platform_check.php';
$requiredPhpVersionId = null;

if (is_file($platformCheckFile)) {
    $platformCheckContents = @file_get_contents($platformCheckFile);

    if (is_string($platformCheckContents)
        && preg_match('/PHP_VERSION_ID\s*>=\s*(\d+)/', $platformCheckContents, $phpMatch)) {
        $requiredPhpVersionId = (int) $phpMatch[1];
    }
}

if ($requiredPhpVersionId && PHP_VERSION_ID < $requiredPhpVersionId) {
    $major = intdiv($requiredPhpVersionId, 10000);
    $minor = intdiv($requiredPhpVersionId % 10000, 100);
    $patch = $requiredPhpVersionId % 100;
    $requiredPhpVersion = $major . '.' . $minor . '.' . $patch;

    $message = 'Installed Composer dependencies require PHP ' . $requiredPhpVersion
        . ' or newer; current PHP is ' . PHP_VERSION . '.';

    $writeRuntimeLog($message);

    if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }

    if (! headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
    }

    echo 'Server configuration error: PHP ' . $requiredPhpVersion . ' or newer is required.';
    exit;
}

/*
 * Laravel's config cache contains absolute paths (including laravel.log).
 * Copying a project after `config:cache` from server A to server B can therefore
 * point the new server at the old server's filesystem and old environment.
 * Detect that situation before Laravel boots and remove only the stale config
 * cache; Laravel will rebuild/read configuration from the current server.
 */
$configCacheFile = $runtimeBootstrapCache . DIRECTORY_SEPARATOR . 'config.php';
$envFile = $runtimeBasePath . DIRECTORY_SEPARATOR . '.env';
$deploymentMarkerFile = $runtimeStoragePath . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . '.deployment-runtime.json';
$currentHost = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
$currentHost = preg_replace('/:\d+$/', '', $currentHost) ?: '';
$currentHost = preg_replace('/^www\./', '', $currentHost) ?: $currentHost;
$configCacheIsStale = false;
$staleReasons = [];

$marker = null;
if (is_file($deploymentMarkerFile)) {
    $decodedMarker = json_decode((string) @file_get_contents($deploymentMarkerFile), true);
    $marker = is_array($decodedMarker) ? $decodedMarker : null;
}

if ($marker) {
    $previousBasePath = $normalizeRuntimePath((string) ($marker['base_path'] ?? ''));
    $previousHost = strtolower((string) ($marker['host'] ?? ''));
    $previousHost = preg_replace('/^www\./', '', $previousHost) ?: $previousHost;

    if ($previousBasePath !== '' && $previousBasePath !== $runtimeBasePath) {
        $configCacheIsStale = true;
        $staleReasons[] = 'deployment base path changed';
    }

    if ($currentHost !== '' && $previousHost !== '' && $currentHost !== $previousHost) {
        $configCacheIsStale = true;
        $staleReasons[] = 'deployment host changed';
    }
}

if (is_file($configCacheFile)) {
    if (is_file($envFile) && filemtime($envFile) !== false && filemtime($configCacheFile) !== false
        && filemtime($envFile) > filemtime($configCacheFile)) {
        $configCacheIsStale = true;
        $staleReasons[] = '.env is newer than config cache';
    }

    $cachedConfigContents = @file_get_contents($configCacheFile);

    if (is_string($cachedConfigContents)
        && preg_match("/'path'\\s*=>\\s*'([^']*storage\\/logs\\/laravel\\.log)'/", str_replace('\\\\', '/', $cachedConfigContents), $logPathMatch)) {
        $cachedLogPath = $normalizeRuntimePath((string) $logPathMatch[1]);
        $expectedLogPath = $normalizeRuntimePath($runtimeLogFile);

        if ($cachedLogPath !== '' && $cachedLogPath !== $expectedLogPath) {
            $configCacheIsStale = true;
            $staleReasons[] = 'cached absolute storage path belongs to another server';
        }
    }

    if ($configCacheIsStale && @unlink($configCacheFile)) {
        $writeRuntimeLog('Cleared stale bootstrap/cache/config.php (' . implode('; ', array_unique($staleReasons)) . ').');
    }
}

$markerHost = $currentHost !== ''
    ? $currentHost
    : (string) ($marker['host'] ?? '');

$markerPayload = json_encode([
    'base_path' => $runtimeBasePath,
    'host' => $markerHost,
    'php' => PHP_VERSION,
    'updated_at' => date(DATE_ATOM),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

if (is_string($markerPayload)) {
    @file_put_contents($deploymentMarkerFile, $markerPayload . PHP_EOL, LOCK_EX);
    @chmod($deploymentMarkerFile, 0664);
}
