<?php

return [
    'channels' => [
        'in_app' => true,
        'email' => false,
        'push' => false,
        'sms' => false,
        'whatsapp' => false,
    ],

    'types' => [
        'synchronization_delayed' => ['category' => 'synchronization', 'severity' => 'warning'],
        'device_offline' => ['category' => 'devices', 'severity' => 'warning'],
        'device_revoked' => ['category' => 'devices', 'severity' => 'danger'],
        'low_stock' => ['category' => 'inventory', 'severity' => 'warning'],
        'product_out_of_stock' => ['category' => 'inventory', 'severity' => 'danger'],
        'cashier_shortage' => ['category' => 'cashiers', 'severity' => 'danger'],
        'large_discount' => ['category' => 'sales', 'severity' => 'warning'],
        'sale_cancellation' => ['category' => 'sales', 'severity' => 'warning'],
        'high_return_volume' => ['category' => 'sales', 'severity' => 'warning'],
        'large_expense' => ['category' => 'expenses', 'severity' => 'warning'],
        'customer_debt_overdue' => ['category' => 'customers', 'severity' => 'warning'],
        'suspicious_login' => ['category' => 'security', 'severity' => 'danger'],
        'application_update_required' => ['category' => 'application', 'severity' => 'information'],
        'subscription_expiring' => ['category' => 'subscription', 'severity' => 'warning'],
    ],
];
