<?php

declare(strict_types=1);

namespace App\Domain\Synchronization\Services\EntityProcessors;

use App\Domain\Synchronization\Data\SyncRecordResult;
use App\Domain\Synchronization\Models\SyncBatch;
use App\Domain\Tenancy\Models\Branch;
use Illuminate\Support\Facades\Validator;

class ProductionRecordProcessor extends OperationalRecordProcessor
{
    public function entity(): string
    {
        return 'production_record';
    }

    public function process(SyncBatch $batch, array $record): SyncRecordResult
    {
        $payload = $record['payload'] ?? [];
        $rules = [
            'uuid' => ['required', 'uuid'],
            'schema_version' => ['required', 'integer', 'in:1'],
            'branch_uuid' => ['required', 'uuid'],
            'kind' => ['required', 'in:material,machine,staff,recipe,rule,batch,expense,sale,return,allocation,settlement,transfer,loss'],
            'data' => ['required', 'array'],
        ];
        $validator = Validator::make($payload, $rules);
        $branchUuid = Branch::query()->withoutGlobalScopes()->where('business_id', $batch->business_id)->whereKey($batch->branch_id)->value('uuid');
        if ($validator->fails() || ($payload['branch_uuid'] ?? null) !== $branchUuid) {
            return new SyncRecordResult('rejected', $record, 'VALIDATION_FAILED', 'Invalid production contract or branch scope.', false);
        }

        $required = match ($payload['kind']) {
            'material' => ['productUuid', 'role', 'name'],
            'machine' => ['code', 'name', 'payee', 'active'],
            'staff' => ['userUuid', 'name', 'active'],
            'recipe' => ['name', 'productUuid', 'outputQuantity', 'materials'],
            'rule' => ['name', 'shares'],
            'batch' => ['reference', 'recipeUuid', 'ruleUuid', 'state', 'recipe', 'rule'],
            'expense' => ['batchUuid', 'amountMinor', 'description', 'reference', 'state'],
            'sale' => ['saleUuid', 'saleItemUuid', 'batchUuid', 'quantity', 'revenueMinor', 'costMinor', 'profitMinor'],
            'return' => ['saleUuid', 'batchUuid', 'reference', 'quantity', 'revenueMinor', 'costMinor'],
            'allocation' => ['batchUuid', 'reference', 'revenueMinor', 'costMinor', 'profitMinor', 'entries'],
            'settlement' => ['batchUuid', 'beneficiaryUuid', 'amountMinor', 'reference'],
            'transfer' => ['batchUuid', 'fromWarehouseUuid', 'toWarehouseUuid', 'quantity'],
            'loss' => ['batchUuid', 'reference', 'reason', 'amountMinor'],
        };
        foreach ($required as $key) {
            if (! array_key_exists($key, $payload['data'])) {
                return new SyncRecordResult('rejected', $record, 'VALIDATION_FAILED', 'Missing production field: '.$key, false);
            }
        }
        foreach ($payload['data'] as $key => $value) {
            if (str_ends_with($key, 'Minor') && (! is_int($value) || abs($value) > 9007199254740991)) {
                return new SyncRecordResult('rejected', $record, 'VALIDATION_FAILED', 'Production money must be integer minor units.', false);
            }
        }

        $dataRules = [];
        foreach ($payload['data'] as $key => $value) {
            if (str_ends_with($key, 'Uuid')) {
                $dataRules['data.'.$key] = ['required', 'uuid'];
            }
        }
        foreach (['name', 'code', 'payee', 'reference', 'description', 'reason'] as $key) {
            if (array_key_exists($key, $payload['data'])) {
                $dataRules['data.'.$key] = ['required', 'string', 'max:160'];
            }
        }
        foreach (['quantity', 'goodQuantity', 'plannedQuantity', 'outputQuantity', 'wasteQuantity'] as $key) {
            if (array_key_exists($key, $payload['data'])) {
                $dataRules['data.'.$key] = ['required', 'string', 'regex:/^\\d{1,9}(\\.\\d{1,3})?$/'];
            }
        }
        if (in_array($payload['kind'], ['machine', 'staff'], true)) {
            $dataRules['data.active'] = ['required', 'boolean'];
        }
        if ($payload['kind'] === 'material') {
            $dataRules['data.role'] = ['required', 'in:material,finished'];
        }
        if ($payload['kind'] === 'batch') {
            $dataRules['data.state'] = ['required', 'in:draft,in_progress,completed,cancelled,lost'];
            $dataRules['data.recipe'] = ['required', 'array'];
            $dataRules['data.rule'] = ['required', 'array'];
        }
        if ($payload['kind'] === 'expense') {
            $dataRules['data.state'] = ['required', 'in:pending,approved'];
        }
        if (in_array($payload['kind'], ['expense', 'settlement', 'loss'], true)) {
            $dataRules['data.amountMinor'] = ['required', 'integer', 'min:0'];
        }
        if (in_array($payload['kind'], ['sale', 'allocation'], true)) {
            $dataRules['data.revenueMinor'] = ['required', 'integer', 'min:0'];
            $dataRules['data.costMinor'] = ['required', 'integer', 'min:0'];
            if ($payload['data']['profitMinor'] !== $payload['data']['revenueMinor'] - $payload['data']['costMinor']) {
                return new SyncRecordResult('rejected', $record, 'VALIDATION_FAILED', 'Production profit does not reconcile.', false);
            }
        }

        if ($payload['kind'] === 'allocation') {
            $dataRules['data.entries'] = ['present', 'array', 'max:100'];
            $dataRules['data.entries.*.beneficiaryUuid'] = ['required', 'uuid'];
            $dataRules['data.entries.*.kind'] = ['required', 'in:machine,staff'];
            $dataRules['data.entries.*.amountMinor'] = ['required', 'integer'];
            $dataRules['data.entries.*.cumulativeMinor'] = ['required', 'integer', 'min:0'];
        }
        if ($payload['kind'] === 'rule') {
            $dataRules['data.shares'] = ['present', 'array', 'max:100'];
            $dataRules['data.shares.*.beneficiaryUuid'] = ['required', 'uuid', 'distinct'];
            $dataRules['data.shares.*.kind'] = ['required', 'in:machine,staff'];
            $dataRules['data.shares.*.percent'] = ['required', 'string', 'numeric', 'min:0', 'max:100'];
        }

        if (Validator::make($payload, $dataRules)->fails()) {
            return new SyncRecordResult('rejected', $record, 'VALIDATION_FAILED', 'Invalid production data types.', false);
        }

        return parent::process($batch, $record);
    }
}
