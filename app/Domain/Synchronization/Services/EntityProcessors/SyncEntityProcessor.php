<?php

namespace App\Domain\Synchronization\Services\EntityProcessors;

use App\Domain\Synchronization\Data\SyncRecordResult;
use App\Domain\Synchronization\Models\SyncBatch;
use App\Domain\Synchronization\Models\SyncRecord;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

abstract class SyncEntityProcessor
{
    abstract public function entity(): string;

    /**
     * @param  array<string, mixed>  $record
     */
    public function process(SyncBatch $batch, array $record): SyncRecordResult
    {
        $validator = Validator::make($record, [
            'entity' => ['required', 'string'],
            'record_uuid' => ['required', 'uuid'],
            'operation' => ['required', 'string', 'in:create,update,void,return,summary_update,settle,alert'],
            'version' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'string', 'max:200'],
            'occurred_at' => ['required', 'date'],
            'payload' => ['required', 'array'],
        ]);

        if ($validator->fails()) {
            return new SyncRecordResult('rejected', $record, 'VALIDATION_FAILED', $validator->errors()->first(), false);
        }

        $payloadHash = hash('sha256', json_encode($record['payload'], JSON_THROW_ON_ERROR));
        $existing = SyncRecord::query()
            ->where('business_id', $batch->business_id)
            ->where('idempotency_key', $record['idempotency_key'])
            ->first();

        if ($existing) {
            if ($existing->payload_hash !== $payloadHash) {
                return new SyncRecordResult('rejected', $record, 'IDEMPOTENCY_CONFLICT', 'The idempotency key was already used for a different payload.', false);
            }

            return new SyncRecordResult($existing->status, $record, $existing->error_code, $existing->error_message, $existing->retryable);
        }

        try {
            SyncRecord::query()->create([
                'sync_batch_id' => $batch->id,
                'business_id' => $batch->business_id,
                'branch_id' => $batch->branch_id,
                'device_id' => $batch->device_id,
                'record_uuid' => $record['record_uuid'],
                'entity' => $record['entity'],
                'operation' => $record['operation'],
                'version' => $record['version'],
                'idempotency_key' => $record['idempotency_key'],
                'payload_hash' => $payloadHash,
                'status' => 'accepted',
                'payload' => $record['payload'],
                'occurred_at' => Carbon::parse($record['occurred_at']),
                'acknowledged_at' => now(),
            ]);
        } catch (QueryException) {
            return new SyncRecordResult('rejected', $record, 'DUPLICATE_RECORD', 'This synchronized record version was already accepted.', false);
        }

        return new SyncRecordResult('accepted', $record);
    }
    /** Older retries remain acknowledged without replacing a newer monitoring projection. */
    protected function hasNewerVersion(SyncBatch $batch, array $record): bool
    {
        return SyncRecord::query()
            ->withoutGlobalScopes()
            ->where('business_id', $batch->business_id)
            ->where('branch_id', $batch->branch_id)
            ->where('entity', $record['entity'])
            ->where('record_uuid', $record['record_uuid'])
            ->where('status', 'accepted')
            ->where('version', '>', $record['version'])
            ->exists();
    }

}
