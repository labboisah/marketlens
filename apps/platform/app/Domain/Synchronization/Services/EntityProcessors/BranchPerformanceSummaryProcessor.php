<?php

namespace App\Domain\Synchronization\Services\EntityProcessors;

use App\Domain\Reporting\Models\DashboardBranchSummary;
use App\Domain\Synchronization\Data\SyncRecordResult;
use App\Domain\Synchronization\Models\SyncBatch;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class BranchPerformanceSummaryProcessor extends SyncEntityProcessor
{
    public function entity(): string
    {
        return 'branch_performance_summary';
    }

    /**
     * @param  array<string, mixed>  $record
     */
    public function process(SyncBatch $batch, array $record): SyncRecordResult
    {
        $payload = $record['payload'] ?? [];

        if (is_array($payload) === false) {
            return new SyncRecordResult('rejected', $record, 'VALIDATION_FAILED', 'Branch-performance payload must be an object.', false);
        }

        $validator = Validator::make($payload, [
            'summary_date' => ['required', 'date'],
            'gross_sales_minor' => ['nullable', 'integer', 'min:0'],
            'net_sales_minor' => ['nullable', 'integer', 'min:0'],
            'transaction_count' => ['nullable', 'integer', 'min:0'],
            'completed_sales_count' => ['nullable', 'integer', 'min:0'],
            'gross_profit_minor' => ['nullable', 'integer', 'min:0'],
            'expenses_minor' => ['nullable', 'integer', 'min:0'],
            'estimated_net_profit_minor' => ['nullable', 'integer'],
            'stock_value_minor' => ['nullable', 'integer', 'min:0'],
            'customer_debt_minor' => ['nullable', 'integer', 'min:0'],
            'supplier_balance_minor' => ['nullable', 'integer', 'min:0'],
            'cashier_variance_minor' => ['nullable', 'integer'],
            'low_stock_count' => ['nullable', 'integer', 'min:0'],
            'active_client_apps_count' => ['nullable', 'integer', 'min:0'],
            'last_successful_sync_at' => ['nullable', 'date'],
            'summarized_at' => ['nullable', 'date'],
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

        DashboardBranchSummary::query()->updateOrCreate(
            [
                'business_id' => $batch->business_id,
                'branch_id' => $batch->branch_id,
                'summary_date' => Carbon::parse($safePayload['summary_date'])->toDateString(),
            ],
            [
                'gross_sales_minor' => $safePayload['gross_sales_minor'] ?? 0,
                'net_sales_minor' => $safePayload['net_sales_minor'] ?? 0,
                'completed_sales_count' => $safePayload['completed_sales_count'] ?? $safePayload['transaction_count'] ?? 0,
                'gross_profit_minor' => $safePayload['gross_profit_minor'] ?? 0,
                'expenses_minor' => $safePayload['expenses_minor'] ?? 0,
                'estimated_net_profit_minor' => $safePayload['estimated_net_profit_minor'] ?? 0,
                'stock_value_minor' => $safePayload['stock_value_minor'] ?? 0,
                'customer_debt_minor' => $safePayload['customer_debt_minor'] ?? 0,
                'supplier_balance_minor' => $safePayload['supplier_balance_minor'] ?? 0,
                'cashier_variance_minor' => $safePayload['cashier_variance_minor'] ?? 0,
                'low_stock_count' => $safePayload['low_stock_count'] ?? 0,
                'active_client_apps_count' => $safePayload['active_client_apps_count'] ?? 0,
                'last_successful_sync_at' => isset($safePayload['last_successful_sync_at']) ? Carbon::parse($safePayload['last_successful_sync_at']) : now(),
                'summarized_at' => isset($safePayload['summarized_at']) ? Carbon::parse($safePayload['summarized_at']) : now(),
            ],
        );

        return $result;
    }
}
