<?php

use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Devices\Models\Device;
use App\Domain\Devices\Services\ShopAppActivationService;
use App\Domain\Inventory\Models\ReplicatedInventoryMovement;
use App\Domain\Inventory\Models\ReplicatedInventoryStock;
use App\Domain\Inventory\Models\ReplicatedStockAlert;
use App\Domain\Synchronization\Models\SyncRecord;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Enums\BranchStatus;
use App\Enums\BusinessStatus;
use App\Enums\SubscriptionStatus;
use App\Livewire\Platform\Inventory\InventoryMonitoringPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

uses(RefreshDatabase::class);

function inventoryMonitoringFixture(array $features = ['Inventory', 'Advanced stock reports']): array
{
    $user = User::factory()->create();
    $plan = Plan::query()->create([
        'name' => 'Inventory Plan',
        'slug' => 'inventory-plan-'.Str::random(6),
        'billing_interval' => 'monthly',
        'price_minor' => 20000,
        'currency' => 'NGN',
        'trial_days' => 14,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    foreach ($features as $index => $feature) {
        $plan->features()->create(['name' => $feature, 'sort_order' => $index + 1]);
    }

    $business = Business::query()->create(['name' => 'Inventory Stores', 'status' => BusinessStatus::Active]);
    $branch = Branch::query()->create([
        'business_id' => $business->id,
        'name' => 'Main Branch',
        'code' => 'MAIN',
        'status' => BranchStatus::Active,
    ]);
    $user->ownedBusinesses()->attach($business->id, ['role' => 'owner']);

    Subscription::query()->create([
        'business_id' => $business->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active,
        'starts_at' => now(),
        'ends_at' => now()->addMonth(),
    ]);

    return [$user, $business, $branch];
}

function inventoryStockFixture(Business $business, Branch $branch, array $overrides = []): ReplicatedInventoryStock
{
    $productUuid = $overrides['product_uuid'] ?? (string) Str::uuid();

    $stock = ReplicatedInventoryStock::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'uuid' => $overrides['uuid'] ?? (string) Str::uuid(),
        'product_uuid' => $productUuid,
        'product_name' => $overrides['product_name'] ?? 'Golden Rice 50kg',
        'sku' => $overrides['sku'] ?? 'RICE-50',
        'barcode' => $overrides['barcode'] ?? '600100200300',
        'base_unit' => $overrides['base_unit'] ?? 'bag',
        'warehouse_uuid' => $overrides['warehouse_uuid'] ?? (string) Str::uuid(),
        'warehouse_name' => $overrides['warehouse_name'] ?? 'Main Warehouse',
        'category_uuid' => $overrides['category_uuid'] ?? (string) Str::uuid(),
        'category_name' => $overrides['category_name'] ?? 'Foodstuff',
        'brand_uuid' => $overrides['brand_uuid'] ?? (string) Str::uuid(),
        'brand_name' => $overrides['brand_name'] ?? 'Golden Brand',
        'available_quantity' => $overrides['available_quantity'] ?? 4,
        'reserved_quantity' => $overrides['reserved_quantity'] ?? 1,
        'stock_value_minor' => $overrides['stock_value_minor'] ?? 250000,
        'reorder_level' => $overrides['reorder_level'] ?? 5,
        'overstock_level' => $overrides['overstock_level'] ?? 100,
        'stock_status' => $overrides['stock_status'] ?? 'low',
        'expires_on' => $overrides['expires_on'] ?? now()->addDays(20)->toDateString(),
        'last_movement_at' => $overrides['last_movement_at'] ?? now(),
        'last_synchronized_at' => $overrides['last_synchronized_at'] ?? now(),
        'source_version' => 1,
        'source_idempotency_key' => $overrides['source_idempotency_key'] ?? 'stock-idem-'.$productUuid,
        'source_occurred_at' => now(),
    ]);

    ReplicatedInventoryMovement::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'uuid' => (string) Str::uuid(),
        'product_uuid' => $productUuid,
        'product_name' => $stock->product_name,
        'sku' => $stock->sku,
        'warehouse_uuid' => $stock->warehouse_uuid,
        'warehouse_name' => $stock->warehouse_name,
        'movement_type' => $overrides['movement_type'] ?? 'adjustment',
        'quantity_delta' => $overrides['quantity_delta'] ?? -2,
        'value_minor' => $overrides['value_minor'] ?? 100000,
        'reference' => $overrides['reference'] ?? 'ADJ-001',
        'reason' => $overrides['reason'] ?? 'Physical count correction',
        'occurred_at' => $overrides['occurred_at'] ?? now(),
        'source_version' => 1,
        'source_idempotency_key' => 'movement-idem-'.Str::uuid(),
        'source_occurred_at' => now(),
    ]);

    ReplicatedStockAlert::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'uuid' => (string) Str::uuid(),
        'product_uuid' => $productUuid,
        'product_name' => $stock->product_name,
        'alert_type' => $overrides['alert_type'] ?? 'damaged',
        'severity' => $overrides['severity'] ?? 'danger',
        'status' => 'open',
        'message' => $overrides['message'] ?? 'Damaged cartons found during stock count',
        'occurred_at' => now(),
        'source_version' => 1,
        'source_idempotency_key' => 'alert-idem-'.Str::uuid(),
        'source_occurred_at' => now(),
    ]);

    return $stock;
}

function signedInventorySyncRequest(TestCase $test, string $credential, array $payload): TestResponse
{
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $timestamp = now()->toISOString();

    return $test->call('POST', route('api.v1.sync.batches.store'), [], [], [], [
        'HTTP_ACCEPT' => 'application/json',
        'CONTENT_TYPE' => 'application/json',
        'HTTP_AUTHORIZATION' => 'Bearer '.$credential,
        'HTTP_X_ML_TIMESTAMP' => $timestamp,
        'HTTP_X_ML_SIGNATURE' => hash_hmac('sha256', $timestamp.'.'.$body, $credential),
    ], $body);
}

it('stores replicated inventory stocks alerts and movements from synchronization records', function (): void {
    Queue::fake();
    [, $business, $branch] = inventoryMonitoringFixture();
    $deviceUser = User::factory()->create();
    $activation = app(ShopAppActivationService::class)->createActivation($business, $branch, $deviceUser, [
        'device_label' => 'Inventory Business Server',
        'device_type' => 'shop_app',
        'expires_at' => now()->addMinutes(30),
        'application_version_id' => null,
    ]);
    $credential = app(ShopAppActivationService::class)->activate([
        'activation_code' => $activation['code'],
        'device_identity' => 'inventory-machine',
        'app_version' => '1.0.0',
    ])['credential'];
    $device = Device::query()->where('business_id', $business->id)->firstOrFail();
    $productUuid = (string) Str::uuid();
    $stockUuid = (string) Str::uuid();
    $alertUuid = (string) Str::uuid();
    $movementUuid = (string) Str::uuid();

    signedInventorySyncRequest($this, $credential, [
        'protocol_version' => '1.0',
        'batch_uuid' => (string) Str::uuid(),
        'business_uuid' => $business->uuid,
        'branch_uuid' => $branch->uuid,
        'device_uuid' => $device->uuid,
        'created_at' => now()->toISOString(),
        'records' => [
            [
                'entity' => 'stock_summary',
                'record_uuid' => $stockUuid,
                'operation' => 'summary_update',
                'version' => 1,
                'idempotency_key' => 'stock-'.$stockUuid,
                'occurred_at' => now()->toISOString(),
                'payload' => [
                    'product_uuid' => $productUuid,
                    'product_name' => 'Sync Golden Rice',
                    'sku' => 'SYNC-RICE',
                    'barcode' => '900100200300',
                    'base_unit' => 'bag',
                    'warehouse_name' => 'Main Warehouse',
                    'category_name' => 'Foodstuff',
                    'brand_name' => 'Golden Brand',
                    'available_quantity' => 4,
                    'reserved_quantity' => 1,
                    'stock_value_minor' => 250000,
                    'reorder_level' => 5,
                    'overstock_level' => 100,
                    'stock_status' => 'low',
                    'expires_on' => now()->addDays(10)->toDateString(),
                    'last_movement_at' => now()->toISOString(),
                    'last_synchronized_at' => now()->toISOString(),
                ],
            ],
            [
                'entity' => 'stock_alert',
                'record_uuid' => $alertUuid,
                'operation' => 'create',
                'version' => 1,
                'idempotency_key' => 'alert-'.$alertUuid,
                'occurred_at' => now()->toISOString(),
                'payload' => [
                    'product_uuid' => $productUuid,
                    'product_name' => 'Sync Golden Rice',
                    'alert_type' => 'low_stock',
                    'severity' => 'warning',
                    'status' => 'open',
                    'message' => 'Below reorder level',
                    'occurred_at' => now()->toISOString(),
                ],
            ],
            [
                'entity' => 'stock_movement',
                'record_uuid' => $movementUuid,
                'operation' => 'create',
                'version' => 1,
                'idempotency_key' => 'movement-'.$movementUuid,
                'occurred_at' => now()->toISOString(),
                'payload' => [
                    'product_uuid' => $productUuid,
                    'product_name' => 'Sync Golden Rice',
                    'sku' => 'SYNC-RICE',
                    'warehouse_name' => 'Main Warehouse',
                    'movement_type' => 'adjustment',
                    'quantity_delta' => -2,
                    'value_minor' => 100000,
                    'reference' => 'ADJ-SYNC-001',
                    'reason' => 'Count correction',
                    'occurred_at' => now()->toISOString(),
                ],
            ],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'accepted')
        ->assertJsonCount(3, 'data.accepted');

    expect(ReplicatedInventoryStock::query()->where('uuid', $stockUuid)->where('product_name', 'Sync Golden Rice')->exists())->toBeTrue()
        ->and(ReplicatedStockAlert::query()->where('uuid', $alertUuid)->where('alert_type', 'low_stock')->exists())->toBeTrue()
        ->and(ReplicatedInventoryMovement::query()->where('uuid', $movementUuid)->where('reference', 'ADJ-SYNC-001')->exists())->toBeTrue()
        ->and(SyncRecord::query()->where('record_uuid', $stockUuid)->exists())->toBeTrue();
});

it('renders read-only inventory monitoring pages filters details and movement summaries', function (): void {
    [$user, $business, $branch] = inventoryMonitoringFixture();
    $stock = inventoryStockFixture($business, $branch);
    inventoryStockFixture($business, $branch, [
        'product_name' => 'Transfer Cement',
        'sku' => 'CEMENT-001',
        'stock_status' => 'normal',
        'movement_type' => 'transfer_out',
        'reference' => 'TRF-001',
    ]);
    inventoryStockFixture($business, $branch, [
        'product_name' => 'Expiring Milk',
        'sku' => 'MILK-EXP',
        'stock_status' => 'normal',
        'expires_on' => now()->addDays(7)->toDateString(),
    ]);

    $this->actingAs($user)
        ->withSession(['platform.business_id' => $business->id, 'platform.branch_id' => $branch->id])
        ->get(route('platform.inventory'))
        ->assertOk()
        ->assertSee('Inventory monitoring')
        ->assertSee('Current stock by branch')
        ->assertSee('Golden Rice 50kg')
        ->assertSee('Business Server remains authoritative')
        ->assertDontSee('Edit stock')
        ->assertDontSee('Correct stock');

    Livewire::actingAs($user)
        ->withQueryParams(['view' => 'details', 'product' => $stock->product_uuid])
        ->test(InventoryMonitoringPage::class)
        ->assertSee('Product stock details')
        ->assertSee('Golden Rice 50kg')
        ->assertSee('RICE-50')
        ->assertSee('600100200300')
        ->assertSee('Available quantity')
        ->assertSee('Reserved quantity')
        ->assertSee('Recent movement summary')
        ->assertSee('ADJ-001');

    Livewire::actingAs($user)->withQueryParams(['view' => 'low'])->test(InventoryMonitoringPage::class)->assertSee('Golden Rice 50kg');
    Livewire::actingAs($user)->withQueryParams(['view' => 'expiring'])->test(InventoryMonitoringPage::class)->assertSee('Expiring Milk');
    Livewire::actingAs($user)->withQueryParams(['view' => 'adjustments'])->test(InventoryMonitoringPage::class)->assertSee('ADJ-001');
    Livewire::actingAs($user)->withQueryParams(['view' => 'transfers'])->test(InventoryMonitoringPage::class)->assertSee('TRF-001');
    Livewire::actingAs($user)->withQueryParams(['view' => 'damaged'])->test(InventoryMonitoringPage::class)->assertSee('Damaged-stock summaries')->assertSee('Damaged cartons found');
    Livewire::actingAs($user)->withQueryParams(['view' => 'value'])->test(InventoryMonitoringPage::class)->assertSee('Stock-value summary')->assertSee('Main Branch');
    Livewire::actingAs($user)->withQueryParams(['view' => 'fast'])->test(InventoryMonitoringPage::class)->assertSee('Fast-moving products')->assertSee('Golden Rice 50kg');
    Livewire::actingAs($user)->withQueryParams(['view' => 'slow'])->test(InventoryMonitoringPage::class)->assertSee('Slow-moving products')->assertSee('Golden Rice 50kg');
});

it('labels delayed synchronized inventory data when branch stock is stale', function (): void {
    [$user, $business, $branch] = inventoryMonitoringFixture();
    inventoryStockFixture($business, $branch, ['last_synchronized_at' => now()->subHours(3)]);

    $this->actingAs($user)
        ->withSession(['platform.business_id' => $business->id, 'platform.branch_id' => $branch->id])
        ->get(route('platform.inventory'))
        ->assertOk()
        ->assertSee('Delayed inventory data');
});

it('enforces inventory permissions and tenant isolation', function (): void {
    [$deniedUser, $deniedBusiness, $deniedBranch] = inventoryMonitoringFixture(['Sales']);
    inventoryStockFixture($deniedBusiness, $deniedBranch, ['product_name' => 'DENIED-STOCK']);

    $this->actingAs($deniedUser)
        ->withSession(['platform.business_id' => $deniedBusiness->id, 'platform.branch_id' => $deniedBranch->id])
        ->get(route('platform.inventory'))
        ->assertOk()
        ->assertSee('Inventory monitoring is not available')
        ->assertDontSee('DENIED-STOCK');

    [$user, $business, $branch] = inventoryMonitoringFixture();
    inventoryStockFixture($business, $branch, ['product_name' => 'VISIBLE-STOCK']);
    $otherBusiness = Business::query()->create(['name' => 'Other Inventory Business', 'status' => BusinessStatus::Active]);
    $otherBranch = Branch::query()->create(['business_id' => $otherBusiness->id, 'name' => 'Other Branch', 'status' => BranchStatus::Active]);
    inventoryStockFixture($otherBusiness, $otherBranch, ['product_name' => 'HIDDEN-STOCK']);

    $this->actingAs($user)
        ->withSession(['platform.business_id' => $business->id, 'platform.branch_id' => $branch->id])
        ->get(route('platform.inventory'))
        ->assertOk()
        ->assertSee('VISIBLE-STOCK')
        ->assertDontSee('HIDDEN-STOCK');
});
