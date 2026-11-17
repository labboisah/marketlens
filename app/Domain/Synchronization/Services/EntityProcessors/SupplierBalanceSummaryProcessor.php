<?php

namespace App\Domain\Synchronization\Services\EntityProcessors;

use App\Domain\Purchases\Models\ReplicatedSupplierBalance;
use App\Domain\Synchronization\Data\SyncRecordResult;
use App\Domain\Synchronization\Models\SyncBatch;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class SupplierBalanceSummaryProcessor extends SyncEntityProcessor
{
    public function entity(): string
    {
        return 'supplier_balance_summary';
    }

    public function process(SyncBatch $batch, array $record): SyncRecordResult
    {
        $payload = $record['payload'] ?? [];

        if (is_array($payload) === false) {
            return new SyncRecordResult('rejected', $record, 'VALIDATION_FAILED', 'Supplier-balance payload must be an object.', false);
        }

        $validator = Validator::make($payload, [
            'supplier_uuid' => ['required', 'uuid'],
            'supplier_name' => ['required', 'string', 'max:180'],
            'phone' => ['nullable', 'string', 'max:80'],
            'current_balance_minor' => ['required', 'integer', 'min:0'],
            'overdue_balance_minor' => ['nullable', 'integer', 'min:0'],
            'last_purchase_at' => ['nullable', 'date'],
            'last_payment_at' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:80'],
            'last_synchronized_at' => ['nullable', 'date'],
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

        ReplicatedSupplierBalance::query()->updateOrCreate(
            ['business_id' => $batch->business_id, 'supplier_uuid' => $safePayload['supplier_uuid']],
            [
                'branch_id' => $batch->branch_id,
                'device_id' => $batch->device_id,
                'sync_batch_id' => $batch->id,
                'uuid' => $record['record_uuid'],
                'supplier_name' => $safePayload['supplier_name'],
                'phone' => $safePayload['phone'] ?? null,
                'current_balance_minor' => $safePayload['current_balance_minor'],
                'overdue_balance_minor' => $safePayload['overdue_balance_minor'] ?? 0,
                'last_purchase_at' => isset($safePayload['last_purchase_at']) ? Carbon::parse($safePayload['last_purchase_at']) : null,
                'last_payment_at' => isset($safePayload['last_payment_at']) ? Carbon::parse($safePayload['last_payment_at']) : null,
                'status' => $safePayload['status'] ?? 'active',
                'last_synchronized_at' => isset($safePayload['last_synchronized_at']) ? Carbon::parse($safePayload['last_synchronized_at']) : now(),
                'source_version' => $record['version'],
                'source_idempotency_key' => $record['idempotency_key'],
                'source_occurred_at' => Carbon::parse($record['occurred_at']),
            ],
        );

        return $result;
    }
}
