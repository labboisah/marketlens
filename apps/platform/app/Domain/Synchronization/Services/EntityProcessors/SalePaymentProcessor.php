<?php

namespace App\Domain\Synchronization\Services\EntityProcessors;

use App\Domain\Sales\Models\ReplicatedSale;
use App\Domain\Sales\Models\ReplicatedSalePayment;
use App\Domain\Synchronization\Data\SyncRecordResult;
use App\Domain\Synchronization\Models\SyncBatch;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class SalePaymentProcessor extends SyncEntityProcessor
{
    public function entity(): string
    {
        return 'sale_payment';
    }

    /**
     * @param  array<string, mixed>  $record
     */
    public function process(SyncBatch $batch, array $record): SyncRecordResult
    {
        $payload = $record['payload'] ?? [];

        if (is_array($payload) === false) {
            return new SyncRecordResult('rejected', $record, 'VALIDATION_FAILED', 'Sale-payment payload must be an object.', false);
        }

        $validator = Validator::make($payload, [
            'sale_uuid' => ['required', 'uuid'],
            'method' => ['required', 'string', 'max:80'],
            'status' => ['required', 'string', 'in:pending,completed,failed,refunded,voided'],
            'amount_minor' => ['required', 'integer', 'min:0'],
            'reference' => ['nullable', 'string', 'max:160'],
            'paid_at' => ['nullable', 'date'],
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

        $sale = ReplicatedSale::query()
            ->where('business_id', $batch->business_id)
            ->where('uuid', $safePayload['sale_uuid'])
            ->first();

        ReplicatedSalePayment::query()->updateOrCreate(
            [
                'business_id' => $batch->business_id,
                'uuid' => $record['record_uuid'],
            ],
            [
                'branch_id' => $batch->branch_id,
                'replicated_sale_id' => $sale?->id,
                'sync_batch_id' => $batch->id,
                'sale_uuid' => $safePayload['sale_uuid'],
                'method' => $safePayload['method'],
                'status' => $safePayload['status'],
                'amount_minor' => $safePayload['amount_minor'],
                'reference' => $safePayload['reference'] ?? null,
                'paid_at' => isset($safePayload['paid_at']) ? Carbon::parse($safePayload['paid_at']) : null,
                'source_version' => $record['version'],
                'source_idempotency_key' => $record['idempotency_key'],
                'source_occurred_at' => Carbon::parse($record['occurred_at']),
            ],
        );

        return $result;
    }
}
