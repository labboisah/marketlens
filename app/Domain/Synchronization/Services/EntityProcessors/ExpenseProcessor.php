<?php

namespace App\Domain\Synchronization\Services\EntityProcessors;

use App\Domain\Expenses\Models\ReplicatedExpense;
use App\Domain\Synchronization\Data\SyncRecordResult;
use App\Domain\Synchronization\Models\SyncBatch;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class ExpenseProcessor extends SyncEntityProcessor
{
    public function entity(): string
    {
        return 'expense';
    }

    public function process(SyncBatch $batch, array $record): SyncRecordResult
    {
        $payload = $record['payload'] ?? [];

        if (is_array($payload) === false) {
            return new SyncRecordResult('rejected', $record, 'VALIDATION_FAILED', 'Expense payload must be an object.', false);
        }

        $validator = Validator::make($payload, [
            'expense_number' => ['required', 'string', 'max:120'],
            'category_uuid' => ['nullable', 'uuid'],
            'category_name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'amount_minor' => ['required', 'integer', 'min:0'],
            'payment_method' => ['nullable', 'string', 'in:cash,bank_transfer,pos_terminal,mobile_money,credit,mixed,other'],
            'creator_uuid' => ['nullable', 'uuid'],
            'creator_name' => ['nullable', 'string', 'max:160'],
            'approver_uuid' => ['nullable', 'uuid'],
            'approver_name' => ['nullable', 'string', 'max:160'],
            'status' => ['required', 'string', 'in:approved,pending,rejected,voided'],
            'expense_date' => ['required', 'date'],
            'receipt_attachment_path' => ['nullable', 'string', 'max:500'],
            'is_recurring' => ['nullable', 'boolean'],
            'recurring_period' => ['nullable', 'string', 'in:daily,weekly,monthly,quarterly,yearly,custom'],
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

        ReplicatedExpense::query()->updateOrCreate(
            ['business_id' => $batch->business_id, 'uuid' => $record['record_uuid']],
            [
                'branch_id' => $batch->branch_id,
                'device_id' => $batch->device_id,
                'sync_batch_id' => $batch->id,
                'expense_number' => $safePayload['expense_number'],
                'category_uuid' => $safePayload['category_uuid'] ?? null,
                'category_name' => $safePayload['category_name'],
                'description' => $safePayload['description'] ?? null,
                'amount_minor' => $safePayload['amount_minor'],
                'payment_method' => $safePayload['payment_method'] ?? null,
                'creator_uuid' => $safePayload['creator_uuid'] ?? null,
                'creator_name' => $safePayload['creator_name'] ?? null,
                'approver_uuid' => $safePayload['approver_uuid'] ?? null,
                'approver_name' => $safePayload['approver_name'] ?? null,
                'status' => $safePayload['status'],
                'expense_date' => Carbon::parse($safePayload['expense_date']),
                'receipt_attachment_path' => $safePayload['receipt_attachment_path'] ?? null,
                'is_recurring' => $safePayload['is_recurring'] ?? false,
                'recurring_period' => $safePayload['recurring_period'] ?? null,
                'source_version' => $record['version'],
                'source_idempotency_key' => $record['idempotency_key'],
                'source_occurred_at' => Carbon::parse($record['occurred_at']),
            ],
        );

        return $result;
    }
}
