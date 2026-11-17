<?php

namespace App\Domain\Synchronization\Services\EntityProcessors;

use App\Domain\Purchases\Models\ReplicatedPurchase;
use App\Domain\Synchronization\Data\SyncRecordResult;
use App\Domain\Synchronization\Models\SyncBatch;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class PurchaseProcessor extends SyncEntityProcessor
{
    public function entity(): string
    {
        return 'purchase';
    }

    public function process(SyncBatch $batch, array $record): SyncRecordResult
    {
        $payload = $record['payload'] ?? [];

        if (is_array($payload) === false) {
            return new SyncRecordResult('rejected', $record, 'VALIDATION_FAILED', 'Purchase payload must be an object.', false);
        }

        $validator = Validator::make($payload, [
            'purchase_number' => ['required', 'string', 'max:120'],
            'supplier_uuid' => ['nullable', 'uuid'],
            'supplier_name' => ['required', 'string', 'max:180'],
            'purchased_at' => ['required', 'date'],
            'status' => ['nullable', 'string', 'in:draft,ordered,received,cancelled,returned'],
            'payment_status' => ['nullable', 'string', 'in:paid,partial,unpaid,refunded,voided'],
            'subtotal_minor' => ['nullable', 'integer', 'min:0'],
            'transport_cost_minor' => ['nullable', 'integer', 'min:0'],
            'additional_cost_minor' => ['nullable', 'integer', 'min:0'],
            'total_minor' => ['required', 'integer', 'min:0'],
            'amount_paid_minor' => ['nullable', 'integer', 'min:0'],
            'balance_minor' => ['nullable', 'integer', 'min:0'],
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

        ReplicatedPurchase::query()->updateOrCreate(
            ['business_id' => $batch->business_id, 'uuid' => $record['record_uuid']],
            [
                'branch_id' => $batch->branch_id,
                'device_id' => $batch->device_id,
                'sync_batch_id' => $batch->id,
                'purchase_number' => $safePayload['purchase_number'],
                'supplier_uuid' => $safePayload['supplier_uuid'] ?? null,
                'supplier_name' => $safePayload['supplier_name'],
                'purchased_at' => Carbon::parse($safePayload['purchased_at']),
                'status' => $safePayload['status'] ?? 'received',
                'payment_status' => $safePayload['payment_status'] ?? 'unpaid',
                'subtotal_minor' => $safePayload['subtotal_minor'] ?? 0,
                'transport_cost_minor' => $safePayload['transport_cost_minor'] ?? 0,
                'additional_cost_minor' => $safePayload['additional_cost_minor'] ?? 0,
                'total_minor' => $safePayload['total_minor'],
                'amount_paid_minor' => $safePayload['amount_paid_minor'] ?? 0,
                'balance_minor' => $safePayload['balance_minor'] ?? max(0, $safePayload['total_minor'] - ($safePayload['amount_paid_minor'] ?? 0)),
                'source_version' => $record['version'],
                'source_idempotency_key' => $record['idempotency_key'],
                'source_occurred_at' => Carbon::parse($record['occurred_at']),
            ],
        );

        return $result;
    }
}
