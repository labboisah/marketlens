<?php

return [
    'api_version' => 'v1',
    'sync_rate_limit' => env('SYNC_INGRESS_RATE_LIMIT_PER_MINUTE', 120),
    'currency' => [
        'default' => env('MARKETLENS_DEFAULT_CURRENCY', 'NGN'),
        'money_scale' => (int) env('MARKETLENS_MONEY_SCALE', 2),
        'quantity_scale' => (int) env('MARKETLENS_QUANTITY_SCALE', 6),
    ],
    'billing' => [
        'grace_days' => (int) env('MARKETLENS_SUBSCRIPTION_GRACE_DAYS', 7),
        'retention_days' => (int) env('MARKETLENS_SUBSCRIPTION_RETENTION_DAYS', 365),
        'invoice_due_days' => (int) env('MARKETLENS_BILLING_INVOICE_DUE_DAYS', 7),
        'default_limits' => [
            'branches' => (int) env('MARKETLENS_LIMIT_BRANCHES', 1),
            'shop_app_devices' => (int) env('MARKETLENS_LIMIT_SHOP_APP_DEVICES', 1),
            'client_app_devices' => (int) env('MARKETLENS_LIMIT_CLIENT_APP_DEVICES', 0),
            'monitoring_users' => (int) env('MARKETLENS_LIMIT_MONITORING_USERS', 1),
            'storage_mb' => (int) env('MARKETLENS_LIMIT_STORAGE_MB', 0),
            'report_exports_per_month' => (int) env('MARKETLENS_LIMIT_REPORT_EXPORTS_PER_MONTH', 10),
        ],
    ],
    'super_admin' => [
        'name' => env('MARKETLENS_SUPER_ADMIN_NAME', 'MarketLens Super Admin'),
        'email' => env('MARKETLENS_SUPER_ADMIN_EMAIL'),
        'phone' => env('MARKETLENS_SUPER_ADMIN_PHONE'),
        'password' => env('MARKETLENS_SUPER_ADMIN_PASSWORD'),
    ],
];
