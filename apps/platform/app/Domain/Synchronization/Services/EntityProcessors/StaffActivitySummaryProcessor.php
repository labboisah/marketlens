<?php

namespace App\Domain\Synchronization\Services\EntityProcessors;

use App\Domain\Reporting\Models\StaffActivitySummary;
use App\Domain\Synchronization\Data\SyncRecordResult;
use App\Domain\Synchronization\Models\SyncBatch;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class StaffActivitySummaryProcessor extends SyncEntityProcessor
{
    public function entity(): string
    {
        return 'staff_activity_summary';
    }

    /**
     * @param  array<string, mixed>  $record
     */
    public function process(SyncBatch $batch, array $record): SyncRecordResult
    {
        $payload = $record['payload'] ?? [];

        if (is_array($payload) === false) {
            return new SyncRecordResult('rejected', $record, 'VALIDATION_FAILED', 'Staff-activity payload must be an object.', false);
        }

        $validator = Validator::make($payload, [
            'operational_user_uuid' => ['required', 'uuid'],
            'summary_date' => ['required', 'date'],
            'display_name' => ['required', 'string', 'max:160'],
            'role_name' => ['nullable', 'string', 'max:120'],
            'sales_count' => ['nullable', 'integer', 'min:0'],
            'gross_sales_minor' => ['nullable', 'integer', 'min:0'],
            'voided_sales_count' => ['nullable', 'integer', 'min:0'],
            'returns_count' => ['nullable', 'integer', 'min:0'],
            'cashier_variance_minor' => ['nullable', 'integer'],
            'purchases_recorded_count' => ['nullable', 'integer', 'min:0'],
            'stock_counts_count' => ['nullable', 'integer', 'min:0'],
            'stock_adjustments_count' => ['nullable', 'integer', 'min:0'],
            'damaged_stock_records_count' => ['nullable', 'integer', 'min:0'],
            'transfers_processed_count' => ['nullable', 'integer', 'min:0'],
            'discounts_approved_count' => ['nullable', 'integer', 'min:0'],
            'expenses_approved_count' => ['nullable', 'integer', 'min:0'],
            'returns_approved_count' => ['nullable', 'integer', 'min:0'],
            'stock_adjustments_approved_count' => ['nullable', 'integer', 'min:0'],
            'cashier_settlements_reviewed_count' => ['nullable', 'integer', 'min:0'],
            'outside_normal_hours_count' => ['nullable', 'integer', 'min:0'],
            'excessive_discount_count' => ['nullable', 'integer', 'min:0'],
            'high_cancellation_count' => ['nullable', 'integer', 'min:0'],
            'high_return_count' => ['nullable', 'integer', 'min:0'],
            'repeated_stock_adjustment_count' => ['nullable', 'integer', 'min:0'],
            'last_activity_at' => ['nullable', 'date'],
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

        StaffActivitySummary::query()->updateOrCreate(
            [
                'business_id' => $batch->business_id,
                'branch_id' => $batch->branch_id,
                'operational_user_uuid' => $safePayload['operational_user_uuid'],
                'summary_date' => Carbon::parse($safePayload['summary_date'])->toDateString(),
            ],
            [
                'display_name' => $safePayload['display_name'],
                'role_name' => $safePayload['role_name'] ?? null,
                'sales_count' => $safePayload['sales_count'] ?? 0,
                'gross_sales_minor' => $safePayload['gross_sales_minor'] ?? 0,
                'voided_sales_count' => $safePayload['voided_sales_count'] ?? 0,
                'returns_count' => $safePayload['returns_count'] ?? 0,
                'cashier_variance_minor' => $safePayload['cashier_variance_minor'] ?? 0,
                'purchases_recorded_count' => $safePayload['purchases_recorded_count'] ?? 0,
                'stock_counts_count' => $safePayload['stock_counts_count'] ?? 0,
                'stock_adjustments_count' => $safePayload['stock_adjustments_count'] ?? 0,
                'damaged_stock_records_count' => $safePayload['damaged_stock_records_count'] ?? 0,
                'transfers_processed_count' => $safePayload['transfers_processed_count'] ?? 0,
                'discounts_approved_count' => $safePayload['discounts_approved_count'] ?? 0,
                'expenses_approved_count' => $safePayload['expenses_approved_count'] ?? 0,
                'returns_approved_count' => $safePayload['returns_approved_count'] ?? 0,
                'stock_adjustments_approved_count' => $safePayload['stock_adjustments_approved_count'] ?? 0,
                'cashier_settlements_reviewed_count' => $safePayload['cashier_settlements_reviewed_count'] ?? 0,
                'outside_normal_hours_count' => $safePayload['outside_normal_hours_count'] ?? 0,
                'excessive_discount_count' => $safePayload['excessive_discount_count'] ?? 0,
                'high_cancellation_count' => $safePayload['high_cancellation_count'] ?? 0,
                'high_return_count' => $safePayload['high_return_count'] ?? 0,
                'repeated_stock_adjustment_count' => $safePayload['repeated_stock_adjustment_count'] ?? 0,
                'last_activity_at' => isset($safePayload['last_activity_at']) ? Carbon::parse($safePayload['last_activity_at']) : null,
            ],
        );

        return $result;
    }
}
