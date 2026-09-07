<?php

namespace App\Domain\Synchronization\Services;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Devices\Models\Device;
use App\Domain\Synchronization\Jobs\ProcessSyncBatch;
use App\Domain\Synchronization\Models\SyncBatch;
use App\Domain\Synchronization\Models\SyncCheckpoint;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Enums\BranchStatus;
use App\Enums\BusinessStatus;
use App\Enums\DeviceStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\SyncBatchStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SyncBatchIntakeService
{
    private const PROTOCOL_VERSION = '1.0';

    private const MAX_DECOMPRESSED_BYTES = 1048576;

    public function __construct(private readonly SyncEntityProcessorRegistry $processors) {}

    /**
     * @return array{raw: string, payload: array<string, mixed>, payload_hash: string}
     */
    public function decodePayload(string $rawBody, ?string $encoding): array
    {
        $decoded = $rawBody;

        if ($encoding && strtolower($encoding) === 'gzip') {
            $decoded = gzdecode($rawBody);

            if ($decoded === false) {
                throw new BadRequestHttpException('Compressed synchronization payload could not be decoded.');
            }
        }

        if (strlen($decoded) > self::MAX_DECOMPRESSED_BYTES) {
            throw new HttpException(413, 'Synchronization payload exceeds the allowed size.');
        }

        $payload = json_decode($decoded, true);

        if (is_array($payload) === false) {
            throw new BadRequestHttpException('Synchronization payload must be valid JSON.');
        }

        return [
            'raw' => $decoded,
            'payload' => $payload,
            'payload_hash' => hash('sha256', $decoded),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function receive(Device $device, array $payload, string $payloadHash, string $traceId): array
    {
        $validator = Validator::make($payload, [
            'protocol_version' => ['required', 'string', 'in:'.self::PROTOCOL_VERSION],
            'batch_uuid' => ['required', 'uuid'],
            'business_uuid' => ['required', 'uuid'],
            'branch_uuid' => ['required', 'uuid'],
            'device_uuid' => ['required', 'uuid'],
            'created_at' => ['required', 'date'],
            'records' => ['required', 'array', 'min:1'],
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        if ((string) $payload['device_uuid'] !== (string) $device->uuid) {
            $this->auditFailure('wrong_device', $device, $traceId);
            throw new AccessDeniedHttpException('Synchronization device does not match the authenticated credential.');
        }

        $business = Business::query()->with('currentSubscription')->where('uuid', $payload['business_uuid'])->first();
        $branch = Branch::query()->where('uuid', $payload['branch_uuid'])->first();

        $this->assertScope($device, $business, $branch, $traceId);

        $duplicate = SyncBatch::query()->where('uuid', $payload['batch_uuid'])->first();

        if ($duplicate) {
            return $this->receipt($duplicate);
        }

        return DB::transaction(function () use ($device, $business, $branch, $payload, $payloadHash, $traceId): array {
            $batch = SyncBatch::query()->create([
                'business_id' => $business->id,
                'branch_id' => $branch->id,
                'device_id' => $device->id,
                'uuid' => $payload['batch_uuid'],
                'protocol_version' => $payload['protocol_version'],
                'status' => SyncBatchStatus::Received,
                'records_count' => count($payload['records']),
                'trace_id' => $traceId,
                'payload_hash' => $payloadHash,
                'received_at' => now(),
            ]);

            [$accepted, $rejected, $conflicts] = $this->processRecords($batch, $payload['records']);
            $status = $this->statusFor($accepted, $rejected, $conflicts);

            $batch->forceFill([
                'status' => $status,
                'accepted_records' => $accepted,
                'rejected_records' => $rejected,
                'conflicting_records' => $conflicts,
            ])->save();

            if ($accepted !== []) {
                $checkpoint = SyncCheckpoint::query()->firstOrNew([
                    'business_id' => $business->id,
                    'branch_id' => $branch->id,
                    'device_id' => $device->id,
                ]);

                $checkpoint->forceFill([
                    'last_batch_uuid' => $batch->uuid,
                    'last_successful_sync_at' => now(),
                    'accepted_records_count' => ((int) $checkpoint->accepted_records_count) + count($accepted),
                    'rejected_records_count' => ((int) $checkpoint->rejected_records_count) + count($rejected),
                ])->save();

                $branch->forceFill(['last_synchronized_at' => now()])->save();
                $device->forceFill(['last_synchronized_at' => now()])->save();
            }

            ProcessSyncBatch::dispatchSync($batch->id);

            return $this->receipt($batch->refresh());
        });
    }

    public function getBatchForDevice(Device $device, string $batchUuid): SyncBatch
    {
        return SyncBatch::query()
            ->where('device_id', $device->id)
            ->where('uuid', $batchUuid)
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function acknowledge(Device $device, array $payload): array
    {
        $validator = Validator::make($payload, [
            'batch_uuid' => ['required', 'uuid'],
            'accepted' => ['nullable', 'array'],
            'accepted.*.record_uuid' => ['required', 'uuid'],
            'accepted.*.idempotency_key' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $batch = $this->getBatchForDevice($device, (string) $payload['batch_uuid']);
        $batch->forceFill(['last_acknowledged_at' => now()])->save();

        return [
            'batch_uuid' => $batch->uuid,
            'acknowledged_at' => now()->toISOString(),
        ];
    }

    public function checkpoint(Device $device): array
    {
        $checkpoint = SyncCheckpoint::query()
            ->where('device_id', $device->id)
            ->first();

        return [
            'business_uuid' => $device->business->uuid,
            'branch_uuid' => $device->branch->uuid,
            'device_uuid' => $device->uuid,
            'last_batch_uuid' => $checkpoint?->last_batch_uuid,
            'last_successful_sync_at' => $checkpoint?->last_successful_sync_at?->toISOString(),
            'accepted_records_count' => $checkpoint?->accepted_records_count ?? 0,
            'rejected_records_count' => $checkpoint?->rejected_records_count ?? 0,
            'server_time' => now()->toISOString(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $records
     */
    private function processRecords(SyncBatch $batch, array $records): array
    {
        $accepted = [];
        $rejected = [];
        $conflicts = [];

        foreach ($records as $record) {
            if (is_array($record) === false || $this->processors->supports($record['entity'] ?? null) === false) {
                $rejected[] = [
                    'record_uuid' => $record['record_uuid'] ?? null,
                    'entity' => $record['entity'] ?? null,
                    'idempotency_key' => $record['idempotency_key'] ?? null,
                    'code' => 'UNSUPPORTED_ENTITY',
                    'message' => 'The synchronized entity is not supported by this Platform API version.',
                    'retryable' => false,
                ];

                continue;
            }

            $result = $this->processors->processorFor($record['entity'])->process($batch, $record);
            $target = $result->status === 'accepted' ? 'accepted' : ($result->status === 'conflict' ? 'conflicts' : 'rejected');
            ${$target}[] = $result->toProtocolItem();
        }

        return [$accepted, $rejected, $conflicts];
    }

    /**
     * @param  list<array<string, mixed>>  $accepted
     * @param  list<array<string, mixed>>  $rejected
     * @param  list<array<string, mixed>>  $conflicts
     */
    private function statusFor(array $accepted, array $rejected, array $conflicts): SyncBatchStatus
    {
        if ($accepted !== [] && ($rejected !== [] || $conflicts !== [])) {
            return SyncBatchStatus::Partial;
        }

        if ($accepted !== []) {
            return SyncBatchStatus::Accepted;
        }

        if ($conflicts !== []) {
            return SyncBatchStatus::Partial;
        }

        return SyncBatchStatus::Rejected;
    }

    private function assertScope(Device $device, ?Business $business, ?Branch $branch, string $traceId): void
    {
        if ($business === null || $business->id !== $device->business_id || $business->status !== BusinessStatus::Active) {
            $this->auditFailure('wrong_or_inactive_business', $device, $traceId);
            throw new AccessDeniedHttpException('Business is not authorized for this synchronization device.');
        }

        if (in_array($business->currentSubscription?->status, [SubscriptionStatus::Trialing, SubscriptionStatus::Active], true) === false) {
            $this->auditFailure('inactive_subscription', $device, $traceId);
            throw new AccessDeniedHttpException('Business subscription is not active for synchronization.');
        }

        if ($branch === null || $branch->id !== $device->branch_id || $branch->business_id !== $business->id || $branch->status !== BranchStatus::Active) {
            $this->auditFailure('wrong_or_inactive_branch', $device, $traceId);
            throw new AccessDeniedHttpException('Branch is not authorized for this synchronization device.');
        }

        if ($device->status !== DeviceStatus::Active || $device->revoked_at) {
            $this->auditFailure('inactive_device', $device, $traceId);
            throw new AccessDeniedHttpException('Device is not active for synchronization.');
        }
    }

    private function receipt(SyncBatch $batch): array
    {
        return [
            'batch_uuid' => $batch->uuid,
            'status' => $batch->status->value,
            'accepted' => $batch->accepted_records ?? [],
            'rejected' => $batch->rejected_records ?? [],
            'conflicts' => $batch->conflicting_records ?? [],
            'trace_id' => $batch->trace_id,
            'server_time' => now()->toISOString(),
        ];
    }

    private function auditFailure(string $reason, ?Device $device, string $traceId): void
    {
        AuditLog::query()->create([
            'business_id' => $device?->business_id,
            'branch_id' => $device?->branch_id,
            'action' => 'sync.intake_failed',
            'subject_type' => $device ? Device::class : null,
            'subject_id' => $device?->id,
            'metadata' => [
                'reason' => $reason,
                'trace_id' => $traceId,
            ],
        ]);
    }
}
