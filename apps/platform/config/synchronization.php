<?php

return [
    'protocol_version' => env('MARKETLENS_SYNC_PROTOCOL_VERSION', '1.0'),

    'monitoring' => [
        'online_minutes' => (int) env('MARKETLENS_SYNC_ONLINE_MINUTES', 5),
        'recent_sync_minutes' => (int) env('MARKETLENS_SYNC_RECENT_MINUTES', 30),
        'delayed_minutes' => (int) env('MARKETLENS_SYNC_DELAYED_MINUTES', 120),
        'offline_minutes' => (int) env('MARKETLENS_SYNC_OFFLINE_MINUTES', 360),
        'minimum_shop_app_version' => env('MARKETLENS_MINIMUM_SHOP_APP_VERSION'),
    ],
];
