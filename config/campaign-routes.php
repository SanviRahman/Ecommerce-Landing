<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Reserved Root Paths
    |--------------------------------------------------------------------------
    |
    | Custom campaign URLs use one root-level segment, for example:
    | https://example.com/shanto-gift-shop
    |
    | These paths are reserved by the application or public assets and cannot
    | be assigned to a campaign.
    |
    */
    'reserved' => [
        'admin',
        'api',
        'assets',
        'build',
        'campaign',
        'command',
        'css',
        'favicon.ico',
        'fonts',
        'frontend',
        'home',
        'images',
        'js',
        'login',
        'logout',
        'password',
        'register',
        'robots.txt',
        'sitemap.xml',
        'storage',
        'success',
        'track-order',
        'up',
        'vendor',
        'webhooks',
    ],

    /* One URL segment only. Lowercase letters, numbers, hyphen and underscore. */
    'pattern' => '[a-z0-9][a-z0-9_-]*',
];
