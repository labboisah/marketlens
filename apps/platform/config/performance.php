<?php

return [
    'monitoring' => [
        'cache_ttl' => [
            'default' => (int) env('MARKETLENS_MONITORING_CACHE_TTL', 300),
            'dashboard' => (int) env('MARKETLENS_DASHBOARD_CACHE_TTL', 300),
            'sales-overview' => 180,
            'payment-overview' => 180,
            'inventory-overview' => 240,
            'expense-overview' => 180,
            'customer-debt-overview' => 240,
            'staff-overview' => 240,
            'sync-overview' => 60,
            'notification-counts' => 60,
        ],
        'slow_query' => [
            'enabled' => (bool) env('MARKETLENS_SLOW_QUERY_LOGGING', false),
            'threshold_ms' => (int) env('MARKETLENS_SLOW_QUERY_THRESHOLD_MS', 250),
            'sample_bindings' => (bool) env('MARKETLENS_SLOW_QUERY_SAMPLE_BINDINGS', false),
        ],
    ],
];
