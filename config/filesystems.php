<?php

$normalizePath = static function (string $path): string {
    return rtrim(
        str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path),
        DIRECTORY_SEPARATOR
    );
};

$configuredPublicRoot = trim((string) env('PUBLIC_DISK_ROOT', ''));
$documentRoot = trim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));

$detectedPublicRoot = $documentRoot !== '' && is_dir($documentRoot)
    ? $normalizePath($documentRoot . DIRECTORY_SEPARATOR . 'storage')
    : $normalizePath(public_path('storage'));

$publicDiskRoot = $configuredPublicRoot !== ''
    ? $normalizePath($configuredPublicRoot)
    : $detectedPublicRoot;

$publicDiskUrl = rtrim(
    (string) env(
        'PUBLIC_DISK_URL',
        rtrim((string) env('APP_URL', 'http://localhost'), '/') . '/storage'
    ),
    '/'
);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    */

    'default' => env('FILESYSTEM_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => $publicDiskRoot,
            'url' => $publicDiskUrl,
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Public media is stored directly in the web-accessible storage directory.
    | Local and production environments do not require a symbolic link.
    |
    */

    'links' => [],

];
