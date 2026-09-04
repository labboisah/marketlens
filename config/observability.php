<?php

return [
    'trace_header' => 'X-Trace-Id',
    'health_cache_ttl_seconds' => 120,
    'scheduler_heartbeat_key' => 'observability:scheduler:last_heartbeat_at',
    'api_latency_key' => 'observability:api_latency',
    'scheduler_stale_after_minutes' => 10,
    'sync_stale_after_minutes' => 60,
    'queue_backlog_warning' => 100,
    'failed_jobs_warning' => 1,
    'health_storage_disk' => env('OBSERVABILITY_HEALTH_DISK', env('FILESYSTEM_DISK', 'local')),
];
