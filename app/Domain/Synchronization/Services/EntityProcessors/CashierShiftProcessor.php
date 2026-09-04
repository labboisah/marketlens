<?php

namespace App\Domain\Synchronization\Services\EntityProcessors;

use App\Domain\Payments\Models\ReplicatedCashierShift;
use App\Domain\Synchronization\Data\SyncRecordResult;
use App\Domain\Synchronization\Models\SyncBatch;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class CashierShiftProcessor extends SyncEntityProcessor
{
    public function entity(): string
    {
        return 'cashier_shift';
    }

    /**
     * @param  array<string, mixed>  $record
     */
    public function process(SyncBatch $batch, array $record): SyncRecordResult
    {
        $payload = $record['payload'] ?? [];

        if (is_array($payload) === false) {
            return new SyncRecordResult('rejected', $record, 'VALIDATION_FAILED', 'Cashier-shift payload must be an object.', false);
        }

        $validator = Validator::make($payload, [
            'cashier_uuid' => ['nullable', 'uuid'],
            'cashier_name' => ['nullable', 'string', 'max:160'],
            'status' => ['required', 'string', 'in:open,closed,suspended,reviewed'],
            'opened_at' => ['nullable', 'date'],
            'closed_at' => ['nullable', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
            'last_activity_at' => ['nullable', 'date'],
        ]);

        if ($validator->fails()) {
            return new SyncRecordResult('rejected', $record, 'VALIDATION_FAILED', $validator->errors()->first(), false);
        }

        $safePayload = Arr::only($payload, array_keys($validator->getRules()));
        $record['payload'] = $safePayload;
        $result = parent::process($batch, $record);

        if ($result->status !== 'accepted') {
            return $result;
        }

        $openedAt = isset($safePayload['opened_at']) ? Carbon::parse($safePayload['opened_at']) : null;
        $closedAt = isset($safePayload['closed_at']) ? Carbon::parse($safePayload['closed_at']) : null;
        $duration = $safePayload['duration_minutes'] ?? ($openedAt && $closedAt ? $openedAt->diffInMinutes($closedAt) : 0);

        ReplicatedCashierShift::query()->updateOrCreate(
            [
                'business_id' => $batch->business_id,
                'uuid' => $record['record_uuid'],
            ],
            [
                'branch_id' => $batch->branch_id,
                'device_id' => $batch->device_id,
                'sync_batch_id' => $batch->id,
                'cashier_uuid' => $safePayload['cashier_uuid'] ?? null,
                'cashier_name' => $safePayload['cashier_name'] ?? null,
                'status' => $safePayload['status'],
                'opened_at' => $openedAt,
                'closed_at' => $closedAt,
                'duration_minutes' => $duration,
                'last_activity_at' => isset($safePayload['last_activity_at']) ? Carbon::parse($safePayload['last_activity_at']) : $closedAt,
                'source_version' => $record['version'],
                'source_idempotency_key' => $record['idempotency_key'],
                'source_occurred_at' => Carbon::parse($record['occurred_at']),
            ],
        );

        return $result;
    }
}
