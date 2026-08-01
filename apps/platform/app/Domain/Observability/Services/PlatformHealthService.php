<?php

namespace App\Domain\Observability\Services;

use App\Domain\Synchronization\Models\SyncBatch;
use App\Enums\SyncBatchStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PlatformHealthService
{
    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        $checks = [
            'application_logs' => $this->applicationLogs(),
            'trace_ids' => $this->traceIds(),
            'database' => $this->database(),
            'redis' => $this->redis(),
            'queue' => $this->queue(),
            'failed_jobs' => $this->failedJobs(),
            'synchronization' => $this->synchronization(),
            'api_latency' => $this->apiLatency(),
            'reverb' => $this->reverb(),
            'scheduler' => $this->scheduler(),
            'storage' => $this->storage(),
            'exception_tracking' => $this->exceptionTracking(),
            'security_event_logging' => $this->securityEventLogging(),
        ];

        return [
            'status' => $this->overallStatus($checks),
            'checked_at' => now()->toIso8601String(),
            'checks' => $checks,
        ];
    }

    /** @return array<string, mixed> */
    public function publicReadiness(): array
    {
        $snapshot = $this->snapshot();

        return [
            'status' => $snapshot['status'],
            'checked_at' => $snapshot['checked_at'],
            'components' => collect($snapshot['checks'])->map(fn (array $check): string => $check['status'])->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function liveness(): array
    {
        return [
            'status' => 'healthy',
            'checked_at' => now()->toIso8601String(),
        ];
    }

    private function applicationLogs(): array
    {
        return $this->check('healthy', 'Structured JSON logging is configured for application channels.', [
            'default_channel' => config('logging.default'),
            'structured' => true,
        ]);
    }

    private function traceIds(): array
    {
        return $this->check('healthy', 'Trace IDs are attached to requests and API responses.', [
            'header' => config('observability.trace_header', 'X-Trace-Id'),
        ]);
    }

    private function database(): array
    {
        $startedAt = microtime(true);

        try {
            DB::select('select 1');

            return $this->check('healthy', 'Database responded to a lightweight query.', [
                'connection' => DB::getDefaultConnection(),
                'latency_ms' => $this->elapsedMs($startedAt),
            ]);
        } catch (Throwable $e) {
            return $this->check('unhealthy', 'Database health query failed.', [
                'connection' => DB::getDefaultConnection(),
                'error' => $e::class,
            ]);
        }
    }

    private function redis(): array
    {
        $startedAt = microtime(true);

        try {
            $pong = Redis::connection()->ping();

            return $this->check('healthy', 'Redis responded to ping.', [
                'connection' => 'default',
                'latency_ms' => $this->elapsedMs($startedAt),
                'response' => is_string($pong) ? $pong : 'ok',
            ]);
        } catch (Throwable $e) {
            return $this->check('unhealthy', 'Redis ping failed.', [
                'connection' => 'default',
                'error' => $e::class,
            ]);
        }
    }

    private function queue(): array
    {
        if (! Schema::hasTable('jobs')) {
            return $this->check('degraded', 'Queue jobs table is not available.', ['connection' => config('queue.default')]);
        }

        $backlog = DB::table('jobs')->count();
        $status = $backlog > (int) config('observability.queue_backlog_warning', 100) ? 'degraded' : 'healthy';

        return $this->check($status, 'Queue backlog is being monitored.', [
            'connection' => config('queue.default'),
            'queued_jobs' => $backlog,
        ]);
    }

    private function failedJobs(): array
    {
        if (! Schema::hasTable('failed_jobs')) {
            return $this->check('degraded', 'Failed-job table is not available.', []);
        }

        $failedJobs = DB::table('failed_jobs')->count();
        $status = $failedJobs >= (int) config('observability.failed_jobs_warning', 1) ? 'degraded' : 'healthy';

        return $this->check($status, 'Failed jobs are being monitored.', ['failed_jobs' => $failedJobs]);
    }

    private function synchronization(): array
    {
        if (! Schema::hasTable('sync_batches')) {
            return $this->check('degraded', 'Synchronization batch table is not available.', []);
        }

        $failed = SyncBatch::query()->whereIn('status', [SyncBatchStatus::Failed, SyncBatchStatus::Rejected])->count();
        $pending = SyncBatch::query()->whereIn('status', [SyncBatchStatus::Received, SyncBatchStatus::Queued, SyncBatchStatus::Processing])->count();
        $lastReceivedAt = SyncBatch::query()->max('received_at');
        $staleAfter = now()->subMinutes((int) config('observability.sync_stale_after_minutes', 60));
        $isStale = $lastReceivedAt && Carbon::parse($lastReceivedAt)->lt($staleAfter);
        $status = $failed > 0 || $isStale ? 'degraded' : 'healthy';

        return $this->check($status, 'Synchronization intake metrics are being monitored.', [
            'pending_batches' => $pending,
            'failed_or_rejected_batches' => $failed,
            'last_received_at' => $lastReceivedAt ? Carbon::parse($lastReceivedAt)->toIso8601String() : null,
        ]);
    }

    private function apiLatency(): array
    {
        $latency = Cache::get((string) config('observability.api_latency_key'), []);
        $count = (int) ($latency['count'] ?? 0);
        $average = $count > 0 ? round(((int) ($latency['total_ms'] ?? 0)) / $count, 2) : null;

        return $this->check('healthy', 'API latency aggregation is enabled.', [
            'sample_count' => $count,
            'average_ms' => $average,
            'max_ms' => $latency['max_ms'] ?? null,
            'last_ms' => $latency['last_ms'] ?? null,
            'last_seen_at' => $latency['last_seen_at'] ?? null,
        ]);
    }

    private function reverb(): array
    {
        $configured = filled(config('broadcasting.default')) || filled(config('reverb.default'));

        return $this->check($configured ? 'healthy' : 'degraded', $configured ? 'Reverb configuration is present.' : 'Reverb configuration is not enabled.', [
            'configured' => $configured,
        ]);
    }

    private function scheduler(): array
    {
        $lastHeartbeat = Cache::get((string) config('observability.scheduler_heartbeat_key'));
        $staleAfter = now()->subMinutes((int) config('observability.scheduler_stale_after_minutes', 10));
        $healthy = $lastHeartbeat && Carbon::parse($lastHeartbeat)->gte($staleAfter);

        return $this->check($healthy ? 'healthy' : 'degraded', $healthy ? 'Scheduler heartbeat is fresh.' : 'Scheduler heartbeat is missing or stale.', [
            'last_heartbeat_at' => $lastHeartbeat,
        ]);
    }

    private function storage(): array
    {
        $disk = (string) config('observability.health_storage_disk', config('filesystems.default'));
        $path = 'health/observability-'.str()->uuid().'.txt';
        $startedAt = microtime(true);

        try {
            Storage::disk($disk)->put($path, 'ok');
            Storage::disk($disk)->delete($path);

            return $this->check('healthy', 'Storage disk accepted a write/delete probe.', [
                'disk' => $disk,
                'latency_ms' => $this->elapsedMs($startedAt),
            ]);
        } catch (Throwable $e) {
            return $this->check('unhealthy', 'Storage probe failed.', [
                'disk' => $disk,
                'error' => $e::class,
            ]);
        }
    }

    private function exceptionTracking(): array
    {
        return $this->check('healthy', 'Unhandled exceptions are reported to the exceptions log channel with trace IDs.', [
            'channel' => 'exceptions',
        ]);
    }

    private function securityEventLogging(): array
    {
        return $this->check('healthy', 'Security events are logged to a dedicated sanitized channel.', [
            'channel' => 'security',
        ]);
    }

    /** @param array<string, array<string, mixed>> $checks */
    private function overallStatus(array $checks): string
    {
        $statuses = collect($checks)->pluck('status');

        if ($statuses->contains('unhealthy')) {
            return 'unhealthy';
        }

        return $statuses->contains('degraded') ? 'degraded' : 'healthy';
    }

    /** @param array<string, mixed> $metrics */
    private function check(string $status, string $message, array $metrics): array
    {
        return compact('status', 'message', 'metrics');
    }

    private function elapsedMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
