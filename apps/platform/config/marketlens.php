<?php

return [
    'api_version' => 'v1',
    'sync_rate_limit' => env('SYNC_INGRESS_RATE_LIMIT_PER_MINUTE', 120),
    'currency' => [
        'default' => env('MARKETLENS_DEFAULT_CURRENCY', 'NGN'),
        'money_scale' => (int) env('MARKETLENS_MONEY_SCALE', 2),
        'quantity_scale' => (int) env('MARKETLENS_QUANTITY_SCALE', 6),
    ],
];
