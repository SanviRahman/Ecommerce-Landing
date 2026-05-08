<?php

return [
    'base_url' => env('STEADFAST_BASE_URL', 'https://portal.packzy.com/api/v1'),

    'api_key' => env('STEADFAST_API_KEY'),

    'secret_key' => env('STEADFAST_SECRET_KEY'),

    'timeout' => env('STEADFAST_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Auto Update Local Order Status
    |--------------------------------------------------------------------------
    | true করলে Steadfast status delivered/cancelled হলে local order_status
    | automatically delivered/cancelled হবে.
    */
    'auto_update_order_status' => env('STEADFAST_AUTO_UPDATE_ORDER_STATUS', false),
];