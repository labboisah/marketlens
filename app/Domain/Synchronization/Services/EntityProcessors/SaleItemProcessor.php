<?php

namespace App\Domain\Synchronization\Services\EntityProcessors;

use App\Domain\Sales\Models\ReplicatedSale;
use App\Domain\Sales\Models\ReplicatedSaleItem;
use App\Domain\Synchronization\Data\SyncRecordResult;
use App\Domain\Synchronization\Models\SyncBatch;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class SaleItemProcessor extends SyncEntityProcessor
{
    public function entity(): string
    {
        return 'sale_item';
    }

    /**
     * @param  array<string, mixed>  $record
     */
    public function process(SyncBatch $batch, array $record): SyncRecordResult
    {
        $payload = $record['payload'] ?? [];

        if (is_array($payload) === false) {
            return new SyncRecordResult('rejected', $record, 'VALIDATION_FAILED', 'Sale-item payload must be an object.', false);
        }

        $validator = Validator::make($payload, [
            'sale_uuid' => ['required', 'uuid'],
            'product_uuid' => ['nullable', 'uuid'],
            'product_name' => ['required', 'string', 'max:180'],
            'sku' => ['nullable', 'string', 'max:120'],
            'category_uuid' => ['nullable', 'uuid'],
            'category_name' => ['nullable', 'string', 'max:160'],
            'quantity' => ['required', 'numeric'],
            'unit' => ['nullable', 'string', 'max:40'],
            'unit_price_minor' => ['required', 'integer', 'min:0'],
            'discount_minor' => ['nullable', 'integer', 'min:0'],
            'tax_minor' => ['nullable', 'integer', 'min:0'],
            'line_total_minor' => ['required', 'integer', 'min:0'],
            'cost_minor' => ['nullable', 'integer', 'min:0'],
            'gross_profit_minor' => ['nullable', 'integer'],
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

        $sale = ReplicatedSale::query()
            ->where('business_id', $batch->business_id)
            ->where('uuid', $safePayload['sale_uuid'])
            ->first();

        ReplicatedSaleItem::query()->updateOrCreate(
            [
                'business_id' => $batch->business_id,
                'uuid' => $record['record_uuid'],
            ],
            [
                'branch_id' => $batch->branch_id,
                'replicated_sale_id' => $sale?->id,
                'sync_batch_id' => $batch->id,
                'sale_uuid' => $safePayload['sale_uuid'],
                'product_uuid' => $safePayload['product_uuid'] ?? null,
                'product_name' => $safePayload['product_name'],
                'sku' => $safePayload['sku'] ?? null,
                'category_uuid' => $safePayload['category_uuid'] ?? null,
                'category_name' => $safePayload['category_name'] ?? null,
                'quantity' => $safePayload['quantity'],
                'unit' => $safePayload['unit'] ?? null,
                'unit_price_minor' => $safePayload['unit_price_minor'],
                'discount_minor' => $safePayload['discount_minor'] ?? 0,
                'tax_minor' => $safePayload['tax_minor'] ?? 0,
                'line_total_minor' => $safePayload['line_total_minor'],
                'cost_minor' => $safePayload['cost_minor'] ?? 0,
                'gross_profit_minor' => $safePayload['gross_profit_minor'] ?? (($safePayload['line_total_minor'] ?? 0) - ($safePayload['cost_minor'] ?? 0)),
                'source_version' => $record['version'],
                'source_idempotency_key' => $record['idempotency_key'],
                'source_occurred_at' => Carbon::parse($record['occurred_at']),
            ],
        );

        return $result;
    }
}
