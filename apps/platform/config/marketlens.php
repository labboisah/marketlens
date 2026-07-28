<?php

return [
    'api_version' => 'v1',
    'sync_rate_limit' => env('SYNC_INGRESS_RATE_LIMIT_PER_MINUTE', 120),
];
