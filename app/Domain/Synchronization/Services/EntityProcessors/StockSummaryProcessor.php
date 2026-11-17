<?php

namespace App\Domain\Synchronization\Services\EntityProcessors;

use App\Domain\Inventory\Models\ReplicatedInventoryStock;
use App\Domain\Synchronization\Data\SyncRecordResult;
use App\Domain\Synchronization\Models\SyncBatch;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class StockSummaryProcessor extends SyncEntityProcessor
{
    public function entity(): string
    {
        return 'stock_summary';
    }

    public function process(SyncBatch $batch, array $record): SyncRecordResult
    {
        $payload = $record['payload'] ?? [];

        if (is_array($payload) === false) {
            return new SyncRecordResult('rejected', $record, 'VALIDATION_FAILED', 'Stock-summary payload must be an object.', false);
        }

        $validator = Validator::make($payload, [
            'product_uuid' => ['required', 'uuid'],
            'product_name' => ['required', 'string', 'max:180'],
            'sku' => ['nullable', 'string', 'max:120'],
            'barcode' => ['nullable', 'string', 'max:120'],
            'base_unit' => ['nullable', 'string', 'max:40'],
            'warehouse_uuid' => ['nullable', 'uuid'],
            'warehouse_name' => ['nullable', 'string', 'max:160'],
            'category_uuid' => ['nullable', 'uuid'],
            'category_name' => ['nullable', 'string', 'max:160'],
            'brand_uuid' => ['nullable', 'uuid'],
            'brand_name' => ['nullable', 'string', 'max:160'],
            'available_quantity' => ['required', 'numeric'],
            'reserved_quantity' => ['nullable', 'numeric'],
            'stock_value_minor' => ['nullable', 'integer', 'min:0'],
            'reorder_level' => ['nullable', 'numeric'],
            'overstock_level' => ['nullable', 'numeric'],
            'stock_status' => ['required', 'string', 'in:normal,low,out_of_stock,overstocked,expiring,damaged'],
            'expires_on' => ['nullable', 'date'],
            'last_movement_at' => ['nullable', 'date'],
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

        ReplicatedInventoryStock::query()->updateOrCreate(
            [
                'business_id' => $batch->business_id,
                'branch_id' => $batch->branch_id,
                'warehouse_uuid' => $safePayload['warehouse_uuid'] ?? null,
                'product_uuid' => $safePayload['product_uuid'],
            ],
            [
                'device_id' => $batch->device_id,
                'sync_batch_id' => $batch->id,
                'uuid' => $record['record_uuid'],
                'product_name' => $safePayload['product_name'],
                'sku' => $safePayload['sku'] ?? null,
                'barcode' => $safePayload['barcode'] ?? null,
                'base_unit' => $safePayload['base_unit'] ?? null,
                'warehouse_name' => $safePayload['warehouse_name'] ?? null,
                'category_uuid' => $safePayload['category_uuid'] ?? null,
                'category_name' => $safePayload['category_name'] ?? null,
                'brand_uuid' => $safePayload['brand_uuid'] ?? null,
                'brand_name' => $safePayload['brand_name'] ?? null,
                'available_quantity' => $safePayload['available_quantity'],
                'reserved_quantity' => $safePayload['reserved_quantity'] ?? 0,
                'stock_value_minor' => $safePayload['stock_value_minor'] ?? 0,
                'reorder_level' => $safePayload['reorder_level'] ?? 0,
                'overstock_level' => $safePayload['overstock_level'] ?? null,
                'stock_status' => $safePayload['stock_status'],
                'expires_on' => isset($safePayload['expires_on']) ? Carbon::parse($safePayload['expires_on'])->toDateString() : null,
                'last_movement_at' => isset($safePayload['last_movement_at']) ? Carbon::parse($safePayload['last_movement_at']) : null,
                'last_synchronized_at' => isset($safePayload['last_synchronized_at']) ? Carbon::parse($safePayload['last_synchronized_at']) : now(),
                'source_version' => $record['version'],
                'source_idempotency_key' => $record['idempotency_key'],
                'source_occurred_at' => Carbon::parse($record['occurred_at']),
            ],
        );

        return $result;
    }
}
