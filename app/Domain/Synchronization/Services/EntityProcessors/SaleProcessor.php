<?php

namespace App\Domain\Synchronization\Services\EntityProcessors;

use App\Domain\Sales\Models\ReplicatedSale;
use App\Domain\Synchronization\Data\SyncRecordResult;
use App\Domain\Synchronization\Models\SyncBatch;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class SaleProcessor extends SyncEntityProcessor
{
    public function entity(): string
    {
        return 'sale';
    }

    /**
     * @param  array<string, mixed>  $record
     */
    public function process(SyncBatch $batch, array $record): SyncRecordResult
    {
        $payload = $record['payload'] ?? [];

        if (is_array($payload) === false) {
            return new SyncRecordResult('rejected', $record, 'VALIDATION_FAILED', 'Sale payload must be an object.', false);
        }

        $validator = Validator::make($payload, [
            'sale_number' => ['required', 'string', 'max:120'],
            'transaction_number' => ['nullable', 'string', 'max:120'],
            'cashier_uuid' => ['nullable', 'uuid'],
            'cashier_name' => ['nullable', 'string', 'max:160'],
            'customer_uuid' => ['nullable', 'uuid'],
            'customer_name' => ['nullable', 'string', 'max:160'],
            'sale_type' => ['nullable', 'string', 'max:80'],
            'status' => ['required', 'string', 'in:completed,cancelled,returned,partially_returned,voided'],
            'payment_status' => ['required', 'string', 'in:paid,partial,unpaid,refunded,voided'],
            'sold_at' => ['required', 'date'],
            'subtotal_minor' => ['nullable', 'integer', 'min:0'],
            'discount_minor' => ['nullable', 'integer', 'min:0'],
            'tax_minor' => ['nullable', 'integer', 'min:0'],
            'total_minor' => ['required', 'integer', 'min:0'],
            'cost_minor' => ['nullable', 'integer', 'min:0'],
            'gross_profit_minor' => ['nullable', 'integer'],
            'cancellation' => ['nullable', 'array'],
            'return_summary' => ['nullable', 'array'],
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

        ReplicatedSale::query()->updateOrCreate(
            [
                'business_id' => $batch->business_id,
                'uuid' => $record['record_uuid'],
            ],
            [
                'branch_id' => $batch->branch_id,
                'device_id' => $batch->device_id,
                'sync_batch_id' => $batch->id,
                'sale_number' => $safePayload['sale_number'],
                'transaction_number' => $safePayload['transaction_number'] ?? null,
                'cashier_uuid' => $safePayload['cashier_uuid'] ?? null,
                'cashier_name' => $safePayload['cashier_name'] ?? null,
                'customer_uuid' => $safePayload['customer_uuid'] ?? null,
                'customer_name' => $safePayload['customer_name'] ?? null,
                'sale_type' => $safePayload['sale_type'] ?? 'retail',
                'status' => $safePayload['status'],
                'payment_status' => $safePayload['payment_status'],
                'sold_at' => Carbon::parse($safePayload['sold_at']),
                'subtotal_minor' => $safePayload['subtotal_minor'] ?? $safePayload['total_minor'],
                'discount_minor' => $safePayload['discount_minor'] ?? 0,
                'tax_minor' => $safePayload['tax_minor'] ?? 0,
                'total_minor' => $safePayload['total_minor'],
                'cost_minor' => $safePayload['cost_minor'] ?? 0,
                'gross_profit_minor' => $safePayload['gross_profit_minor'] ?? (($safePayload['total_minor'] ?? 0) - ($safePayload['cost_minor'] ?? 0)),
                'cancellation' => $safePayload['cancellation'] ?? null,
                'return_summary' => $safePayload['return_summary'] ?? null,
                'source_version' => $record['version'],
                'source_idempotency_key' => $record['idempotency_key'],
                'source_occurred_at' => Carbon::parse($record['occurred_at']),
            ],
        );

        return $result;
    }
}
