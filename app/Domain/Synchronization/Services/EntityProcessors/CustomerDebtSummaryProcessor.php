<?php

namespace App\Domain\Synchronization\Services\EntityProcessors;

use App\Domain\Customers\Models\ReplicatedCustomerDebtPayment;
use App\Domain\Customers\Models\ReplicatedCustomerDebtSummary;
use App\Domain\Synchronization\Data\SyncRecordResult;
use App\Domain\Synchronization\Models\SyncBatch;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class CustomerDebtSummaryProcessor extends SyncEntityProcessor
{
    public function entity(): string
    {
        return 'customer_debt_summary';
    }

    public function process(SyncBatch $batch, array $record): SyncRecordResult
    {
        $payload = $record['payload'] ?? [];

        if (is_array($payload) === false) {
            return new SyncRecordResult('rejected', $record, 'VALIDATION_FAILED', 'Customer-debt payload must be an object.', false);
        }

        $validator = Validator::make($payload, [
            'customer_uuid' => ['required', 'uuid'],
            'customer_name' => ['required', 'string', 'max:180'],
            'phone' => ['nullable', 'string', 'max:80'],
            'credit_limit_minor' => ['nullable', 'integer', 'min:0'],
            'current_balance_minor' => ['required', 'integer', 'min:0'],
            'credit_sales_minor' => ['nullable', 'integer', 'min:0'],
            'overdue_amount_minor' => ['nullable', 'integer', 'min:0'],
            'debt_status' => ['required', 'string', 'in:current,overdue,blocked,settled'],
            'last_payment_at' => ['nullable', 'date'],
            'last_payment_minor' => ['nullable', 'integer', 'min:0'],
            'last_payment_method' => ['nullable', 'string', 'max:80'],
            'oldest_unpaid_sale_at' => ['nullable', 'date'],
            'oldest_unpaid_sale_number' => ['nullable', 'string', 'max:120'],
            'age_current_minor' => ['nullable', 'integer', 'min:0'],
            'age_1_30_minor' => ['nullable', 'integer', 'min:0'],
            'age_31_60_minor' => ['nullable', 'integer', 'min:0'],
            'age_61_90_minor' => ['nullable', 'integer', 'min:0'],
            'age_over_90_minor' => ['nullable', 'integer', 'min:0'],
            'last_synchronized_at' => ['nullable', 'date'],
            'payment_history' => ['nullable', 'array', 'max:20'],
            'payment_history.*.uuid' => ['required_with:payment_history', 'uuid'],
            'payment_history.*.amount_minor' => ['required_with:payment_history', 'integer', 'min:0'],
            'payment_history.*.payment_method' => ['nullable', 'string', 'max:80'],
            'payment_history.*.reference' => ['nullable', 'string', 'max:160'],
            'payment_history.*.paid_at' => ['required_with:payment_history', 'date'],
        ]);

        if ($validator->fails()) {
            return new SyncRecordResult('rejected', $record, 'VALIDATION_FAILED', $validator->errors()->first(), false);
        }

        $safePayload = Arr::only($payload, array_keys($validator->getRules()));
        $safePayload['payment_history'] = $payload['payment_history'] ?? [];
        $record['payload'] = $safePayload;
        $result = parent::process($batch, $record);

        if ($result->status !== 'accepted' || $this->hasNewerVersion($batch, $record)) {
            return $result;
        }

        $summary = ReplicatedCustomerDebtSummary::query()->updateOrCreate(
            ['business_id' => $batch->business_id, 'customer_uuid' => $safePayload['customer_uuid']],
            [
                'branch_id' => $batch->branch_id,
                'device_id' => $batch->device_id,
                'sync_batch_id' => $batch->id,
                'uuid' => $record['record_uuid'],
                'customer_name' => $safePayload['customer_name'],
                'phone' => $safePayload['phone'] ?? null,
                'credit_limit_minor' => $safePayload['credit_limit_minor'] ?? 0,
                'current_balance_minor' => $safePayload['current_balance_minor'],
                'credit_sales_minor' => $safePayload['credit_sales_minor'] ?? 0,
                'overdue_amount_minor' => $safePayload['overdue_amount_minor'] ?? 0,
                'debt_status' => $safePayload['debt_status'],
                'last_payment_at' => isset($safePayload['last_payment_at']) ? Carbon::parse($safePayload['last_payment_at']) : null,
                'last_payment_minor' => $safePayload['last_payment_minor'] ?? 0,
                'last_payment_method' => $safePayload['last_payment_method'] ?? null,
                'oldest_unpaid_sale_at' => isset($safePayload['oldest_unpaid_sale_at']) ? Carbon::parse($safePayload['oldest_unpaid_sale_at']) : null,
                'oldest_unpaid_sale_number' => $safePayload['oldest_unpaid_sale_number'] ?? null,
                'age_current_minor' => $safePayload['age_current_minor'] ?? 0,
                'age_1_30_minor' => $safePayload['age_1_30_minor'] ?? 0,
                'age_31_60_minor' => $safePayload['age_31_60_minor'] ?? 0,
                'age_61_90_minor' => $safePayload['age_61_90_minor'] ?? 0,
                'age_over_90_minor' => $safePayload['age_over_90_minor'] ?? 0,
                'last_synchronized_at' => isset($safePayload['last_synchronized_at']) ? Carbon::parse($safePayload['last_synchronized_at']) : now(),
                'source_version' => $record['version'],
                'source_idempotency_key' => $record['idempotency_key'],
                'source_occurred_at' => Carbon::parse($record['occurred_at']),
            ],
        );

        foreach ($safePayload['payment_history'] as $payment) {
            ReplicatedCustomerDebtPayment::query()->updateOrCreate(
                ['business_id' => $batch->business_id, 'uuid' => $payment['uuid']],
                [
                    'branch_id' => $batch->branch_id,
                    'customer_debt_summary_id' => $summary->id,
                    'customer_uuid' => $safePayload['customer_uuid'],
                    'customer_name' => $safePayload['customer_name'],
                    'amount_minor' => $payment['amount_minor'],
                    'payment_method' => $payment['payment_method'] ?? null,
                    'reference' => $payment['reference'] ?? null,
                    'paid_at' => Carbon::parse($payment['paid_at']),
                ],
            );
        }

        return $result;
    }
}
