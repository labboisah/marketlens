<?php

namespace App\Domain\Synchronization\Services\EntityProcessors;

use App\Domain\Purchases\Models\ReplicatedPurchase;
use App\Domain\Purchases\Models\ReplicatedPurchasePayment;
use App\Domain\Synchronization\Data\SyncRecordResult;
use App\Domain\Synchronization\Models\SyncBatch;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class PurchasePaymentProcessor extends SyncEntityProcessor
{
    public function entity(): string
    {
        return 'purchase_payment';
    }

    public function process(SyncBatch $batch, array $record): SyncRecordResult
    {
        $payload = $record['payload'] ?? [];

        if (is_array($payload) === false) {
            return new SyncRecordResult('rejected', $record, 'VALIDATION_FAILED', 'Purchase-payment payload must be an object.', false);
        }

        $validator = Validator::make($payload, [
            'purchase_uuid' => ['required', 'uuid'],
            'supplier_uuid' => ['nullable', 'uuid'],
            'supplier_name' => ['nullable', 'string', 'max:180'],
            'method' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', 'string', 'in:pending,completed,unverified,reversed,voided'],
            'amount_minor' => ['required', 'integer', 'min:0'],
            'reference' => ['nullable', 'string', 'max:160'],
            'paid_at' => ['required', 'date'],
        ]);

        if ($validator->fails()) {
            return new SyncRecordResult('rejected', $record, 'VALIDATION_FAILED', $validator->errors()->first(), false);
        }

        $safePayload = Arr::only($payload, array_keys($validator->getRules()));
        $purchase = ReplicatedPurchase::query()->where('business_id', $batch->business_id)->where('uuid', $safePayload['purchase_uuid'])->first();
        $record['payload'] = $safePayload;
        $result = parent::process($batch, $record);

        if ($result->status !== 'accepted' || $this->hasNewerVersion($batch, $record)) {
            return $result;
        }

        ReplicatedPurchasePayment::query()->updateOrCreate(
            ['business_id' => $batch->business_id, 'uuid' => $record['record_uuid']],
            [
                'branch_id' => $batch->branch_id,
                'replicated_purchase_id' => $purchase?->id,
                'purchase_uuid' => $safePayload['purchase_uuid'],
                'supplier_uuid' => $safePayload['supplier_uuid'] ?? $purchase?->supplier_uuid,
                'supplier_name' => $safePayload['supplier_name'] ?? $purchase?->supplier_name,
                'method' => $safePayload['method'] ?? null,
                'status' => $safePayload['status'] ?? 'completed',
                'amount_minor' => $safePayload['amount_minor'],
                'reference' => $safePayload['reference'] ?? null,
                'paid_at' => Carbon::parse($safePayload['paid_at']),
                'source_version' => $record['version'],
                'source_idempotency_key' => $record['idempotency_key'],
                'source_occurred_at' => Carbon::parse($record['occurred_at']),
            ],
        );

        return $result;
    }
}
