<?php

return [
    'api_url' => env('KERNELBRIDGE_API_URL', 'https://kernelbridge.test/api/v1'),
    'api_token' => env('KERNELBRIDGE_API_TOKEN', ''),
    'product_code' => env('KERNELBRIDGE_PRODUCT_CODE', 'MARKETLENS'),
    'platform_link_redirect_route' => env('KERNELBRIDGE_LICENSE_REDIRECT_AFTER_PLATFORM_LINK', 'platform.dashboard'),
    'enforce_server_grants' => env('KERNELBRIDGE_ENFORCE_SERVER_GRANTS', true),
    'webhook_secret' => env('KERNELBRIDGE_WEBHOOK_SECRET', ''),
    'webhook_tolerance_seconds' => (int) env('KERNELBRIDGE_WEBHOOK_TOLERANCE_SECONDS', 300),

    'activation_policy' => [
        'model' => 'business_claim_then_scoped_server_credentials',
        'business_claim_counts_against_installation_limit' => false,
        'business_claim_endpoint' => '/api/v1/licenses/redeem',
        'business_claim_is_idempotent' => true,
        'server_installation_counts_against_installation_limit' => true,
        'server_installation_grant_endpoint' => '/api/v1/licenses/installation-grants',
        'installation_limit_key' => 'max_server_installations',
        'require_scoped_server_credentials' => true,
        'statement' => 'The Platform claims a KernelBridge license for one MarketLens business through the idempotent business-claim endpoint without consuming an installation slot. Each Business Server installation receives a scoped grant that reserves one max_server_installations slot.',
    ],
];
