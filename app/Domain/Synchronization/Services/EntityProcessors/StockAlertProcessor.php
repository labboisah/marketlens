<?php

namespace App\Domain\Synchronization\Services\EntityProcessors;

use App\Domain\Inventory\Models\ReplicatedStockAlert;
use App\Domain\Synchronization\Data\SyncRecordResult;
use App\Domain\Synchronization\Models\SyncBatch;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class StockAlertProcessor extends SyncEntityProcessor
{
    public function entity(): string
    {
        return 'stock_alert';
    }

    public function process(SyncBatch $batch, array $record): SyncRecordResult
    {
        $payload = $record['payload'] ?? [];

        if (is_array($payload) === false) {
            return new SyncRecordResult('rejected', $record, 'VALIDATION_FAILED', 'Stock-alert payload must be an object.', false);
        }

        $validator = Validator::make($payload, [
            'product_uuid' => ['nullable', 'uuid'],
            'product_name' => ['required', 'string', 'max:180'],
            'alert_type' => ['required', 'string', 'in:low_stock,out_of_stock,overstocked,expiring,damaged,adjustment,transfer'],
            'severity' => ['nullable', 'string', 'in:information,warning,danger'],
            'status' => ['nullable', 'string', 'in:open,acknowledged,resolved'],
            'message' => ['nullable', 'string', 'max:500'],
            'occurred_at' => ['required', 'date'],
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

        ReplicatedStockAlert::query()->updateOrCreate(
            ['business_id' => $batch->business_id, 'uuid' => $record['record_uuid']],
            [
                'branch_id' => $batch->branch_id,
                'device_id' => $batch->device_id,
                'sync_batch_id' => $batch->id,
                'product_uuid' => $safePayload['product_uuid'] ?? null,
                'product_name' => $safePayload['product_name'],
                'alert_type' => $safePayload['alert_type'],
                'severity' => $safePayload['severity'] ?? 'warning',
                'status' => $safePayload['status'] ?? 'open',
                'message' => $safePayload['message'] ?? null,
                'occurred_at' => Carbon::parse($safePayload['occurred_at']),
                'source_version' => $record['version'],
                'source_idempotency_key' => $record['idempotency_key'],
                'source_occurred_at' => Carbon::parse($record['occurred_at']),
            ],
        );

        return $result;
    }
}
