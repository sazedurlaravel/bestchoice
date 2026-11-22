<?php

return [
    'sslcommerz' => [
        'store_id' => env('SSLCOMMERZ_STORE_ID'),
        'store_password' => env('SSLCOMMERZ_STORE_PASSWORD'),
        'base_url' => env('SSLCOMMERZ_SANDBOX', true) ? 'https://sandbox.sslcommerz.com' : 'https://securepay.sslcommerz.com',
    ],
    'steadfast' => [
        'base_url' => env('STEADFAST_BASE_URL', 'https://portal.packzy.com/api/v1'),
        'timeout' => (int) env('STEADFAST_TIMEOUT', 30),
        'connect_timeout' => (int) env('STEADFAST_CONNECT_TIMEOUT', 10),
    ],
];
