<?php

namespace App\Domain\Synchronization\Services;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Devices\Models\Device;
use App\Domain\Devices\Models\DeviceHeartbeatLog;
use App\Domain\Synchronization\Jobs\ProcessSyncBatch;
use App\Domain\Synchronization\Models\SyncBatch;
use App\Domain\Synchronization\Models\SyncRecord;
use App\Domain\Tenancy\Models\Branch;
use App\Enums\DeviceStatus;
use App\Enums\SyncBatchStatus;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SynchronizationMonitoringService
{
    /**
     * @return array<string, mixed>
     */
    public function overview(int $businessId): array
    {
        $devices = $this->shopDevices($businessId)->get();
        $classifications = $devices->map(fn (Device $device): string => $this->classifyDevice($device));

        return [
            'metrics' => [
                'active_devices' => $devices->where('status', DeviceStatus::Active)->count(),
                'online_devices' => $classifications->filter(fn (string $status): bool => $status === 'Online')->count(),
                'recently_synchronized_devices' => $classifications->filter(fn (string $status): bool => in_array($status, ['Online', 'Synced recently'], true))->count(),
                'delayed_devices' => $classifications->filter(fn (string $status): bool => $status === 'Delayed')->count(),
                'offline_devices' => $classifications->filter(fn (string $status): bool => $status === 'Offline')->count(),
                'pending_batches' => $this->batchCount($businessId, [SyncBatchStatus::Received, SyncBatchStatus::Queued]),
                'processing_batches' => $this->batchCount($businessId, [SyncBatchStatus::Processing]),
                'failed_batches' => $this->batchCount($businessId, [SyncBatchStatus::Failed, SyncBatchStatus::Rejected]),
                'conflicting_records' => $this->conflictQuery($businessId)->count(),
                'average_sync_delay_minutes' => $this->averageSyncDelayMinutes($businessId),
            ],
            'last_sync_by_branch' => $this->lastSyncByBranch($businessId),
            'thresholds' => config('synchronization.monitoring'),
        ];
    }

    public function devices(int $businessId): EloquentCollection
    {
        return $this->shopDevices($businessId)
            ->with('branch')
            ->latest('last_seen_at')
            ->get()
            ->each(fn (Device $device) => $device->setAttribute('sync_classification', $this->classifyDevice($device)));
    }

    public function batches(int $businessId): LengthAwarePaginator
    {
        return SyncBatch::query()
            ->with(['device', 'branch'])
            ->where('business_id', $businessId)
            ->latest('received_at')
            ->paginate(15);
    }

    public function batch(int $businessId, string $batchUuid): ?SyncBatch
    {
        return SyncBatch::query()
            ->with(['device', 'branch'])
            ->where('business_id', $businessId)
            ->where('uuid', $batchUuid)
            ->first();
    }

    public function failedRecords(int $businessId): LengthAwarePaginator
    {
        return SyncRecord::query()
            ->with(['batch', 'device'])
            ->where('business_id', $businessId)
            ->whereIn('status', ['rejected', 'failed'])
            ->latest()
            ->paginate(15);
    }

    public function conflictRecords(int $businessId): LengthAwarePaginator
    {
        return $this->conflictQuery($businessId)
            ->with(['batch', 'device'])
            ->latest()
            ->paginate(15);
    }

    public function heartbeatHistory(int $businessId): LengthAwarePaginator
    {
        return DeviceHeartbeatLog::query()
            ->with(['device', 'branch'])
            ->where('business_id', $businessId)
            ->latest('reported_at')
            ->paginate(15);
    }

    public function protocolUsage(int $businessId): Collection
    {
        return SyncBatch::query()
            ->where('business_id', $businessId)
            ->selectRaw('protocol_version, count(*) as batches_count')
            ->groupBy('protocol_version')
            ->orderByDesc('batches_count')
            ->get();
    }

    public function applicationVersionDistribution(int $businessId): Collection
    {
        return $this->shopDevices($businessId)
            ->selectRaw("coalesce(app_version, 'Unknown') as app_version, count(*) as devices_count")
            ->groupBy('app_version')
            ->orderByDesc('devices_count')
            ->get();
    }

    public function retryBatch(int $businessId, string $batchUuid, User $actor): void
    {
        $batch = $this->batch($businessId, $batchUuid);

        if (! $batch || ! in_array($batch->status, [SyncBatchStatus::Failed, SyncBatchStatus::Rejected, SyncBatchStatus::Partial], true)) {
            throw ValidationException::withMessages(['batch' => 'Only failed, rejected, or partial batches can be retried.']);
        }

        $batch->forceFill([
            'status' => SyncBatchStatus::Queued,
            'retry_requested_at' => now(),
            'retry_requested_by' => $actor->id,
        ])->save();

        ProcessSyncBatch::dispatch($batch->id);
        $this->audit($actor, $businessId, $batch->branch_id, 'sync.batch_retry_requested', SyncBatch::class, $batch->id, ['batch_uuid' => $batch->uuid]);
    }

    public function markConflictReviewed(int $businessId, int $recordId, User $actor): void
    {
        $record = SyncRecord::query()
            ->where('business_id', $businessId)
            ->where('status', 'conflict')
            ->findOrFail($recordId);

        $record->forceFill([
            'reviewed_at' => now(),
            'reviewed_by' => $actor->id,
        ])->save();

        $this->audit($actor, $businessId, $record->branch_id, 'sync.conflict_reviewed', SyncRecord::class, $record->id, [
            'record_uuid' => $record->record_uuid,
            'entity' => $record->entity,
        ]);
    }

    public function revokeDevice(int $businessId, int $deviceId, User $actor): void
    {
        $device = $this->shopDevices($businessId)->findOrFail($deviceId);

        $device->forceFill([
            'status' => DeviceStatus::Revoked,
            'credential_hash' => null,
            'revoked_at' => now(),
        ])->save();

        $this->audit($actor, $businessId, $device->branch_id, 'sync.device_revoked', Device::class, $device->id, ['device_uuid' => $device->uuid]);
    }

    public function requireApplicationUpdate(int $businessId, int $deviceId, User $actor): void
    {
        $device = $this->shopDevices($businessId)->findOrFail($deviceId);
        $requiredVersion = config('synchronization.monitoring.minimum_shop_app_version') ?: $device->app_version;

        $device->forceFill([
            'requires_update_at' => now(),
            'required_version' => $requiredVersion,
            'update_required_by' => $actor->id,
        ])->save();

        $this->audit($actor, $businessId, $device->branch_id, 'sync.device_update_required', Device::class, $device->id, [
            'device_uuid' => $device->uuid,
            'required_version' => $requiredVersion,
        ]);
    }

    public function redactedErrorReport(int $businessId): string
    {
        $rows = SyncRecord::query()
            ->with(['batch', 'device'])
            ->where('business_id', $businessId)
            ->whereIn('status', ['rejected', 'failed', 'conflict'])
            ->latest()
            ->limit(250)
            ->get();

        $lines = ['record_uuid,entity,status,error_code,retryable,batch_uuid,device_uuid,occurred_at,error_summary'];

        foreach ($rows as $row) {
            $lines[] = implode(',', [
                $this->csv($row->record_uuid),
                $this->csv($row->entity),
                $this->csv($row->status),
                $this->csv($row->error_code),
                $row->retryable ? 'true' : 'false',
                $this->csv($row->batch?->uuid),
                $this->csv($row->device?->uuid),
                $this->csv($row->occurred_at?->toISOString()),
                $this->csv(str((string) $row->error_message)->limit(180)->toString()),
            ]);
        }

        return implode("\n", $lines)."\n";
    }

    public function classifyDevice(Device $device): string
    {
        if ($device->status === DeviceStatus::Revoked || $device->revoked_at) {
            return 'Revoked';
        }

        if ($device->requires_update_at) {
            return 'Requires update';
        }

        $now = now();

        if ($device->last_seen_at && $device->last_seen_at->greaterThanOrEqualTo($now->copy()->subMinutes((int) config('synchronization.monitoring.online_minutes')))) {
            return 'Online';
        }

        if ($device->last_synchronized_at && $device->last_synchronized_at->greaterThanOrEqualTo($now->copy()->subMinutes((int) config('synchronization.monitoring.recent_sync_minutes')))) {
            return 'Synced recently';
        }

        if ($device->last_synchronized_at && $device->last_synchronized_at->greaterThanOrEqualTo($now->copy()->subMinutes((int) config('synchronization.monitoring.delayed_minutes')))) {
            return 'Delayed';
        }

        if ($device->last_seen_at && $device->last_seen_at->greaterThanOrEqualTo($now->copy()->subMinutes((int) config('synchronization.monitoring.offline_minutes')))) {
            return 'Delayed';
        }

        return 'Offline';
    }

    private function shopDevices(int $businessId)
    {
        return Device::query()
            ->where('business_id', $businessId)
            ->where('type', 'shop_app');
    }

    /**
     * @param  list<SyncBatchStatus>  $statuses
     */
    private function batchCount(int $businessId, array $statuses): int
    {
        return SyncBatch::query()
            ->where('business_id', $businessId)
            ->whereIn('status', collect($statuses)->map->value->all())
            ->count();
    }

    private function conflictQuery(int $businessId)
    {
        return SyncRecord::query()
            ->where('business_id', $businessId)
            ->where('status', 'conflict');
    }

    private function averageSyncDelayMinutes(int $businessId): ?int
    {
        $batches = SyncBatch::query()
            ->where('business_id', $businessId)
            ->whereNotNull('received_at')
            ->whereNotNull('processed_at')
            ->latest('processed_at')
            ->limit(100)
            ->get(['received_at', 'processed_at']);

        if ($batches->isEmpty()) {
            return null;
        }

        return (int) round($batches->avg(fn (SyncBatch $batch): int => Carbon::parse($batch->received_at)->diffInMinutes($batch->processed_at)));
    }

    private function lastSyncByBranch(int $businessId): Collection
    {
        return Branch::query()
            ->where('business_id', $businessId)
            ->orderBy('name')
            ->get()
            ->map(fn (Branch $branch): array => [
                'name' => $branch->name,
                'code' => $branch->code,
                'last_synchronized_at' => $branch->last_synchronized_at,
                'status' => $branch->last_synchronized_at && $branch->last_synchronized_at->greaterThanOrEqualTo(now()->subMinutes((int) config('synchronization.monitoring.recent_sync_minutes')))
                    ? 'Fresh'
                    : 'Stale',
            ]);
    }

    private function audit(User $actor, int $businessId, ?int $branchId, string $action, string $subjectType, int $subjectId, array $metadata = []): void
    {
        AuditLog::query()->create([
            'business_id' => $businessId,
            'branch_id' => $branchId,
            'actor_id' => $actor->id,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'metadata' => $metadata,
        ]);
    }

    private function csv(mixed $value): string
    {
        $text = (string) ($value ?? '');

        return '"'.str_replace('"', '""', $text).'"';
    }
}
