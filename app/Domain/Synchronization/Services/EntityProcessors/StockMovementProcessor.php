<?php

namespace App\Domain\Synchronization\Services\EntityProcessors;

use App\Domain\Inventory\Models\ReplicatedInventoryMovement;
use App\Domain\Synchronization\Data\SyncRecordResult;
use App\Domain\Synchronization\Models\SyncBatch;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class StockMovementProcessor extends SyncEntityProcessor
{
    public function entity(): string
    {
        return 'stock_movement';
    }

    public function process(SyncBatch $batch, array $record): SyncRecordResult
    {
        $payload = $record['payload'] ?? [];

        if (is_array($payload) === false) {
            return new SyncRecordResult('rejected', $record, 'VALIDATION_FAILED', 'Stock-movement payload must be an object.', false);
        }

        $validator = Validator::make($payload, [
            'product_uuid' => ['required', 'uuid'],
            'product_name' => ['required', 'string', 'max:180'],
            'sku' => ['nullable', 'string', 'max:120'],
            'warehouse_uuid' => ['nullable', 'uuid'],
            'warehouse_name' => ['nullable', 'string', 'max:160'],
            'movement_type' => ['required', 'string', 'in:sale,purchase,adjustment,transfer_in,transfer_out,damage,expiry,opening_stock,return'],
            'quantity_delta' => ['required', 'numeric'],
            'value_minor' => ['nullable', 'integer', 'min:0'],
            'reference' => ['nullable', 'string', 'max:160'],
            'reason' => ['nullable', 'string', 'max:500'],
            'occurred_at' => ['required', 'date'],
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

        ReplicatedInventoryMovement::query()->updateOrCreate(
            ['business_id' => $batch->business_id, 'uuid' => $record['record_uuid']],
            [
                'branch_id' => $batch->branch_id,
                'device_id' => $batch->device_id,
                'sync_batch_id' => $batch->id,
                'product_uuid' => $safePayload['product_uuid'],
                'product_name' => $safePayload['product_name'],
                'sku' => $safePayload['sku'] ?? null,
                'warehouse_uuid' => $safePayload['warehouse_uuid'] ?? null,
                'warehouse_name' => $safePayload['warehouse_name'] ?? null,
                'movement_type' => $safePayload['movement_type'],
                'quantity_delta' => $safePayload['quantity_delta'],
                'value_minor' => $safePayload['value_minor'] ?? 0,
                'reference' => $safePayload['reference'] ?? null,
                'reason' => $safePayload['reason'] ?? null,
                'occurred_at' => Carbon::parse($safePayload['occurred_at']),
                'source_version' => $record['version'],
                'source_idempotency_key' => $record['idempotency_key'],
                'source_occurred_at' => Carbon::parse($record['occurred_at']),
            ],
        );

        return $result;
    }
}
