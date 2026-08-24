<?php

return [
    'timeout' => (int) env('STEADFAST_TIMEOUT', 30),

    'connect_timeout' => (int) env('STEADFAST_CONNECT_TIMEOUT', 10),

    'force_ipv4' => filter_var(env('STEADFAST_FORCE_IPV4', true), FILTER_VALIDATE_BOOL),

    'verify_ssl' => filter_var(env('STEADFAST_VERIFY_SSL', true), FILTER_VALIDATE_BOOL),

    'auto_update_order_status' => filter_var(
        env('STEADFAST_AUTO_UPDATE_ORDER_STATUS', false),
        FILTER_VALIDATE_BOOL
    ),
];
