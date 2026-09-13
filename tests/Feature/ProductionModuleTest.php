<?php

use App\Domain\Access\Models\BusinessMonitoringUser;
use App\Domain\Access\Models\MonitoringPermission;
use App\Domain\Access\Models\MonitoringRole;
use App\Domain\Access\Services\MonitoringUserManagementService;
use App\Domain\Devices\Models\Device;
use App\Domain\Production\ProductionMonitoring;
use App\Domain\Synchronization\Models\SyncBatch;
use App\Domain\Synchronization\Models\SyncRecord;
use App\Domain\Synchronization\Services\EntityProcessors\ProductionRecordProcessor;
use App\Domain\Synchronization\Services\SyncEntityProcessorRegistry;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Enums\AccountStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

function productionFixture(): array
{
    $owner = User::factory()->create(['account_status' => AccountStatus::Active]);
    $business = Business::query()->create(['name' => 'Production business']);
    $owner->ownedBusinesses()->attach($business->id, ['role' => 'owner']);
    $branch = Branch::query()->create(['business_id' => $business->id, 'name' => 'Main', 'status' => 'active']);
    $device = Device::query()->create(['business_id' => $business->id, 'branch_id' => $branch->id, 'name' => 'Production server', 'type' => 'shop_app', 'status' => 'active']);
    $batch = SyncBatch::query()->create(['business_id' => $business->id, 'branch_id' => $branch->id, 'device_id' => $device->id, 'protocol_version' => '1.0', 'status' => 'accepted', 'records_count' => 1, 'received_at' => now()]);

    return [$owner, $business, $branch, $batch];
}

function productionRecord(Branch $branch, string $kind = 'sale', array $data = []): array
{
    $uuid = (string) str()->uuid();

    return [
        'entity' => 'production_record', 'record_uuid' => $uuid, 'operation' => 'create',
        'version' => 1, 'idempotency_key' => $uuid, 'occurred_at' => now()->toISOString(),
        'payload' => ['uuid' => $uuid, 'schema_version' => 1, 'branch_uuid' => $branch->uuid, 'kind' => $kind, 'data' => $data ?: [
            'saleUuid' => (string) str()->uuid(), 'saleItemUuid' => (string) str()->uuid(), 'batchUuid' => (string) str()->uuid(), 'quantity' => '5.000', 'revenueMinor' => 50000, 'costMinor' => 10000, 'profitMinor' => 40000,
        ]],
    ];
}

it('accepts versioned production records and deduplicates retries', function () {
    [, , $branch, $batch] = productionFixture();
    $record = productionRecord($branch);
    expect(app(SyncEntityProcessorRegistry::class)->supports('production_record'))->toBeTrue();
    $processor = app(ProductionRecordProcessor::class);
    expect($processor->process($batch, $record)->status)->toBe('accepted');
    expect($processor->process($batch, $record)->status)->toBe('accepted');
    expect(SyncRecord::query()->count())->toBe(1);
});

it('rejects cross-branch production payloads, unsupported versions and credentials', function () {
    [, , $branch, $batch] = productionFixture();
    $processor = app(ProductionRecordProcessor::class);
    foreach ([
        ['branch_uuid' => (string) str()->uuid()],
        ['schema_version' => 2],
        ['kind' => 'unknown'],
        ['data' => ['password' => 'do-not-store']],
    ] as $changes) {
        $record = productionRecord($branch);
        $record['payload'] = array_replace($record['payload'], $changes);
        expect($processor->process($batch, $record)->status)->toBe('rejected');
    }
    expect(SyncRecord::query()->count())->toBe(0);
});

it('shows owners exact sales costs and rejects other tenants', function () {
    [$owner, $business, $branch, $batch] = productionFixture();
    app(ProductionRecordProcessor::class)->process($batch, productionRecord($branch));
    $report = app(ProductionMonitoring::class)->data($owner, $business, $branch->id);
    expect($report['profitMinor'])->toBe(40000);
    $other = User::factory()->create(['account_status' => AccountStatus::Active]);
    app(ProductionMonitoring::class)->data($other, $business, null);
})->throws(HttpException::class);

it('restricts branch totals and hides financial fields from operational viewers', function () {
    [, $business, $branch, $batch] = productionFixture();
    $otherBranch = Branch::query()->create(['business_id' => $business->id, 'name' => 'Restricted branch', 'status' => 'active']);
    $viewer = User::factory()->create(['account_status' => AccountStatus::Active]);
    app(MonitoringUserManagementService::class)->ensureDefaults();
    $role = MonitoringRole::query()->where('slug', 'read-only-viewer')->firstOrFail();
    $assignment = BusinessMonitoringUser::query()->create(['business_id' => $business->id, 'user_id' => $viewer->id, 'monitoring_role_id' => $role->id, 'status' => 'active', 'module_permissions' => ['view-production']]);
    $assignment->branches()->attach($branch->id);
    $record = productionRecord($branch, 'batch', ['reference' => 'B1', 'recipeUuid' => (string) str()->uuid(), 'ruleUuid' => (string) str()->uuid(), 'recipe' => ['name' => 'Test recipe'], 'rule' => ['shares' => []], 'state' => 'completed', 'totalCostMinor' => 20000, 'materials' => []]);
    app(ProductionRecordProcessor::class)->process($batch, $record);
    app(ProductionRecordProcessor::class)->process($batch, productionRecord($branch));
    $report = app(ProductionMonitoring::class)->data($viewer, $business, null);
    expect($report['finance'])->toBeFalse()->and($report['records'])->toHaveCount(1);
    expect($report['records']->first()['data'])->not->toHaveKeys(['rule', 'totalCostMinor']);
    app(ProductionMonitoring::class)->data($viewer, $business, $otherBranch->id);
})->throws(HttpException::class);

it('registers production monitoring permissions for owner assignment', function () {
    app(MonitoringUserManagementService::class)->ensureDefaults();
    expect(MonitoringPermission::query()->whereIn('slug', ['view-production', 'view-production-finance', 'export-production'])->count())->toBe(3);
});

it('reports approved shares and outstanding payouts separately from profit', function () {
    [$owner, $business, $branch, $batch] = productionFixture();
    $processor = app(ProductionRecordProcessor::class);
    $processor->process($batch, productionRecord($branch));
    $beneficiary = (string) str()->uuid();
    $allocation = productionRecord($branch, 'allocation', ['batchUuid' => (string) str()->uuid(), 'reference' => 'B1', 'revenueMinor' => 50000, 'costMinor' => 10000, 'profitMinor' => 40000, 'entries' => [['kind' => 'staff', 'beneficiaryUuid' => $beneficiary, 'amountMinor' => 12000, 'cumulativeMinor' => 12000]]]);
    expect($processor->process($batch, $allocation)->status)->toBe('accepted');
    $payment = productionRecord($branch, 'settlement', ['batchUuid' => (string) str()->uuid(), 'beneficiaryUuid' => $beneficiary, 'amountMinor' => 5000, 'reference' => 'PAY1']);
    expect($processor->process($batch, $payment)->status)->toBe('accepted');
    $report = app(ProductionMonitoring::class)->data($owner, $business, null);
    expect($report['approvedShareMinor'])->toBe(12000)->and($report['businessRemainderMinor'])->toBe(28000)->and($report['outstandingShareMinor'])->toBe(7000);
});
