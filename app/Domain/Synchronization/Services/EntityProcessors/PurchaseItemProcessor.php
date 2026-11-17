<?php

namespace App\Domain\Synchronization\Services\EntityProcessors;

use App\Domain\Purchases\Models\ReplicatedPurchase;
use App\Domain\Purchases\Models\ReplicatedPurchaseItem;
use App\Domain\Synchronization\Data\SyncRecordResult;
use App\Domain\Synchronization\Models\SyncBatch;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class PurchaseItemProcessor extends SyncEntityProcessor
{
    public function entity(): string
    {
        return 'purchase_item';
    }

    public function process(SyncBatch $batch, array $record): SyncRecordResult
    {
        $payload = $record['payload'] ?? [];

        if (is_array($payload) === false) {
            return new SyncRecordResult('rejected', $record, 'VALIDATION_FAILED', 'Purchase-item payload must be an object.', false);
        }

        $validator = Validator::make($payload, [
            'purchase_uuid' => ['required', 'uuid'],
            'product_uuid' => ['nullable', 'uuid'],
            'product_name' => ['required', 'string', 'max:180'],
            'sku' => ['nullable', 'string', 'max:120'],
            'unit' => ['nullable', 'string', 'max:40'],
            'quantity' => ['required', 'numeric'],
            'unit_cost_minor' => ['required', 'integer', 'min:0'],
            'line_total_minor' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($validator->fails()) {
            return new SyncRecordResult('rejected', $record, 'VALIDATION_FAILED', $validator->errors()->first(), false);
        }

        $purchase = ReplicatedPurchase::query()->where('business_id', $batch->business_id)->where('uuid', $payload['purchase_uuid'])->first();

        if (! $purchase) {
            return new SyncRecordResult('rejected', $record, 'PURCHASE_NOT_FOUND', 'Purchase item references a purchase that has not been accepted.', true);
        }

        $safePayload = Arr::only($payload, array_keys($validator->getRules()));
        $record['payload'] = $safePayload;
        $result = parent::process($batch, $record);

        if ($result->status !== 'accepted' || $this->hasNewerVersion($batch, $record)) {
            return $result;
        }

        ReplicatedPurchaseItem::query()->updateOrCreate(
            ['business_id' => $batch->business_id, 'uuid' => $record['record_uuid']],
            [
                'branch_id' => $batch->branch_id,
                'replicated_purchase_id' => $purchase->id,
                'purchase_uuid' => $safePayload['purchase_uuid'],
                'product_uuid' => $safePayload['product_uuid'] ?? null,
                'product_name' => $safePayload['product_name'],
                'sku' => $safePayload['sku'] ?? null,
                'unit' => $safePayload['unit'] ?? null,
                'quantity' => $safePayload['quantity'],
                'unit_cost_minor' => $safePayload['unit_cost_minor'],
                'line_total_minor' => $safePayload['line_total_minor'] ?? (int) round((float) $safePayload['quantity'] * $safePayload['unit_cost_minor']),
                'source_version' => $record['version'],
                'source_idempotency_key' => $record['idempotency_key'],
                'source_occurred_at' => Carbon::parse($record['occurred_at']),
            ],
        );

        return $result;
    }
}
