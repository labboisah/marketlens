<?php

namespace App\Domain\Synchronization\Services\EntityProcessors;

use App\Domain\Payments\Models\ReplicatedCashierSettlement;
use App\Domain\Synchronization\Data\SyncRecordResult;
use App\Domain\Synchronization\Models\SyncBatch;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class CashierSettlementProcessor extends SyncEntityProcessor
{
    public function entity(): string
    {
        return 'cashier_settlement';
    }

    /**
     * @param  array<string, mixed>  $record
     */
    public function process(SyncBatch $batch, array $record): SyncRecordResult
    {
        $payload = $record['payload'] ?? [];

        if (is_array($payload) === false) {
            return new SyncRecordResult('rejected', $record, 'VALIDATION_FAILED', 'Cashier-settlement payload must be an object.', false);
        }

        $validator = Validator::make($payload, [
            'shift_uuid' => ['nullable', 'uuid'],
            'cashier_uuid' => ['nullable', 'uuid'],
            'cashier_name' => ['nullable', 'string', 'max:160'],
            'expected_cash_minor' => ['nullable', 'integer', 'min:0'],
            'actual_cash_minor' => ['nullable', 'integer', 'min:0'],
            'shortage_minor' => ['nullable', 'integer'],
            'surplus_minor' => ['nullable', 'integer'],
            'cash_expenses_minor' => ['nullable', 'integer', 'min:0'],
            'refunds_minor' => ['nullable', 'integer', 'min:0'],
            'unverified_transfer_minor' => ['nullable', 'integer', 'min:0'],
            'payment_mismatch_count' => ['nullable', 'integer', 'min:0'],
            'reversal_count' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', 'string', 'in:pending,submitted,balanced,shortage,surplus,reviewed'],
            'settled_at' => ['nullable', 'date'],
        ]);

        if ($validator->fails()) {
            return new SyncRecordResult('rejected', $record, 'VALIDATION_FAILED', $validator->errors()->first(), false);
        }

        $safePayload = Arr::only($payload, array_keys($validator->getRules()));
        $record['payload'] = $safePayload;
        $result = parent::process($batch, $record);

        if ($result->status !== 'accepted' || $this->hasNewerVersion($batch, $record)) {
            return $result;
        }

        ReplicatedCashierSettlement::query()->updateOrCreate(
            [
                'business_id' => $batch->business_id,
                'uuid' => $record['record_uuid'],
            ],
            [
                'branch_id' => $batch->branch_id,
                'device_id' => $batch->device_id,
                'sync_batch_id' => $batch->id,
                'shift_uuid' => $safePayload['shift_uuid'] ?? null,
                'cashier_uuid' => $safePayload['cashier_uuid'] ?? null,
                'cashier_name' => $safePayload['cashier_name'] ?? null,
                'expected_cash_minor' => $safePayload['expected_cash_minor'] ?? 0,
                'actual_cash_minor' => $safePayload['actual_cash_minor'] ?? 0,
                'shortage_minor' => $safePayload['shortage_minor'] ?? 0,
                'surplus_minor' => $safePayload['surplus_minor'] ?? 0,
                'cash_expenses_minor' => $safePayload['cash_expenses_minor'] ?? 0,
                'refunds_minor' => $safePayload['refunds_minor'] ?? 0,
                'unverified_transfer_minor' => $safePayload['unverified_transfer_minor'] ?? 0,
                'payment_mismatch_count' => $safePayload['payment_mismatch_count'] ?? 0,
                'reversal_count' => $safePayload['reversal_count'] ?? 0,
                'status' => $safePayload['status'],
                'settled_at' => isset($safePayload['settled_at']) ? Carbon::parse($safePayload['settled_at']) : null,
                'source_version' => $record['version'],
                'source_idempotency_key' => $record['idempotency_key'],
                'source_occurred_at' => Carbon::parse($record['occurred_at']),
            ],
        );

        return $result;
    }
}
