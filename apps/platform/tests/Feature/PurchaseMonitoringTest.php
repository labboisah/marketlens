<?php

use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Devices\Models\Device;
use App\Domain\Devices\Services\ShopAppActivationService;
use App\Domain\Purchases\Models\ReplicatedPurchase;
use App\Domain\Purchases\Models\ReplicatedPurchaseItem;
use App\Domain\Purchases\Models\ReplicatedPurchasePayment;
use App\Domain\Purchases\Models\ReplicatedSupplierBalance;
use App\Domain\Synchronization\Models\SyncRecord;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Enums\BranchStatus;
use App\Enums\BusinessStatus;
use App\Enums\SubscriptionStatus;
use App\Livewire\Platform\Purchases\PurchaseMonitoringPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

uses(RefreshDatabase::class);

function purchaseMonitoringFixture(array $features = ['Purchases and suppliers', 'Reports']): array
{
    $user = User::factory()->create();
    $plan = Plan::query()->create([
        'name' => 'Purchase Plan',
        'slug' => 'purchase-plan-'.Str::random(6),
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

    $business = Business::query()->create(['name' => 'Purchase Stores', 'status' => BusinessStatus::Active]);
    $branch = Branch::query()->create(['business_id' => $business->id, 'name' => 'Main Branch', 'code' => 'MAIN', 'status' => BranchStatus::Active]);
    $user->ownedBusinesses()->attach($business->id, ['role' => 'owner']);

    Subscription::query()->create(['business_id' => $business->id, 'plan_id' => $plan->id, 'status' => SubscriptionStatus::Active, 'starts_at' => now(), 'ends_at' => now()->addMonth()]);

    return [$user, $business, $branch];
}

function replicatedPurchaseFixture(Business $business, Branch $branch, array $overrides = []): ReplicatedPurchase
{
    $purchaseUuid = $overrides['uuid'] ?? (string) Str::uuid();
    $supplierUuid = $overrides['supplier_uuid'] ?? (string) Str::uuid();
    $purchase = ReplicatedPurchase::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'uuid' => $purchaseUuid,
        'purchase_number' => $overrides['purchase_number'] ?? 'PUR-001',
        'supplier_uuid' => $supplierUuid,
        'supplier_name' => $overrides['supplier_name'] ?? 'Amina Supplies',
        'purchased_at' => $overrides['purchased_at'] ?? now(),
        'status' => $overrides['status'] ?? 'received',
        'payment_status' => $overrides['payment_status'] ?? 'partial',
        'subtotal_minor' => 250000,
        'transport_cost_minor' => 10000,
        'additional_cost_minor' => 5000,
        'total_minor' => $overrides['total_minor'] ?? 265000,
        'amount_paid_minor' => $overrides['amount_paid_minor'] ?? 165000,
        'balance_minor' => $overrides['balance_minor'] ?? 100000,
        'source_version' => 1,
        'source_idempotency_key' => 'purchase-idem-'.$purchaseUuid,
        'source_occurred_at' => now(),
    ]);

    ReplicatedPurchaseItem::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'replicated_purchase_id' => $purchase->id,
        'uuid' => (string) Str::uuid(),
        'purchase_uuid' => $purchaseUuid,
        'product_uuid' => (string) Str::uuid(),
        'product_name' => $overrides['product_name'] ?? 'Rice 50kg',
        'sku' => 'RICE-50',
        'unit' => 'bag',
        'quantity' => 5,
        'unit_cost_minor' => $overrides['unit_cost_minor'] ?? 50000,
        'line_total_minor' => 250000,
        'source_version' => 1,
        'source_idempotency_key' => 'purchase-item-idem-'.Str::uuid(),
        'source_occurred_at' => now(),
    ]);

    ReplicatedPurchasePayment::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'replicated_purchase_id' => $purchase->id,
        'uuid' => (string) Str::uuid(),
        'purchase_uuid' => $purchaseUuid,
        'supplier_uuid' => $supplierUuid,
        'supplier_name' => $purchase->supplier_name,
        'method' => 'bank_transfer',
        'status' => 'completed',
        'amount_minor' => 165000,
        'reference' => 'SUP-PAY-001',
        'paid_at' => now(),
        'source_version' => 1,
        'source_idempotency_key' => 'purchase-payment-idem-'.Str::uuid(),
        'source_occurred_at' => now(),
    ]);

    ReplicatedSupplierBalance::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'uuid' => (string) Str::uuid(),
        'supplier_uuid' => $supplierUuid,
        'supplier_name' => $purchase->supplier_name,
        'phone' => '08034444444',
        'current_balance_minor' => 100000,
        'overdue_balance_minor' => 40000,
        'last_purchase_at' => now(),
        'last_payment_at' => now(),
        'status' => 'active',
        'last_synchronized_at' => now(),
        'source_version' => 1,
        'source_idempotency_key' => 'supplier-balance-idem-'.$supplierUuid,
        'source_occurred_at' => now(),
    ]);

    return $purchase;
}

function signedPurchaseSyncRequest(TestCase $test, string $credential, array $payload): TestResponse
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

it('stores replicated purchases items payments and supplier balances from synchronization records', function (): void {
    Queue::fake();
    [, $business, $branch] = purchaseMonitoringFixture();
    $deviceUser = User::factory()->create();
    $activation = app(ShopAppActivationService::class)->createActivation($business, $branch, $deviceUser, ['device_label' => 'Purchase Shop App', 'device_type' => 'shop_app', 'expires_at' => now()->addMinutes(30), 'application_version_id' => null]);
    $credential = app(ShopAppActivationService::class)->activate(['activation_code' => $activation['code'], 'device_identity' => 'purchase-machine', 'app_version' => '1.0.0'])['credential'];
    $device = Device::query()->where('business_id', $business->id)->firstOrFail();
    $purchaseUuid = (string) Str::uuid();
    $itemUuid = (string) Str::uuid();
    $paymentUuid = (string) Str::uuid();
    $supplierBalanceUuid = (string) Str::uuid();
    $supplierUuid = (string) Str::uuid();

    signedPurchaseSyncRequest($this, $credential, [
        'protocol_version' => '1.0',
        'batch_uuid' => (string) Str::uuid(),
        'business_uuid' => $business->uuid,
        'branch_uuid' => $branch->uuid,
        'device_uuid' => $device->uuid,
        'created_at' => now()->toISOString(),
        'records' => [
            ['entity' => 'purchase', 'record_uuid' => $purchaseUuid, 'operation' => 'create', 'version' => 1, 'idempotency_key' => 'purchase-'.$purchaseUuid, 'occurred_at' => now()->toISOString(), 'payload' => ['purchase_number' => 'PUR-SYNC-001', 'supplier_uuid' => $supplierUuid, 'supplier_name' => 'Sync Supplier', 'purchased_at' => now()->toISOString(), 'payment_status' => 'partial', 'subtotal_minor' => 250000, 'transport_cost_minor' => 10000, 'additional_cost_minor' => 5000, 'total_minor' => 265000, 'amount_paid_minor' => 165000, 'balance_minor' => 100000]],
            ['entity' => 'purchase_item', 'record_uuid' => $itemUuid, 'operation' => 'create', 'version' => 1, 'idempotency_key' => 'purchase-item-'.$itemUuid, 'occurred_at' => now()->toISOString(), 'payload' => ['purchase_uuid' => $purchaseUuid, 'product_name' => 'Beans 25kg', 'sku' => 'BEANS-25', 'unit' => 'bag', 'quantity' => 5, 'unit_cost_minor' => 50000, 'line_total_minor' => 250000]],
            ['entity' => 'purchase_payment', 'record_uuid' => $paymentUuid, 'operation' => 'create', 'version' => 1, 'idempotency_key' => 'purchase-payment-'.$paymentUuid, 'occurred_at' => now()->toISOString(), 'payload' => ['purchase_uuid' => $purchaseUuid, 'supplier_uuid' => $supplierUuid, 'supplier_name' => 'Sync Supplier', 'method' => 'bank_transfer', 'amount_minor' => 165000, 'reference' => 'SUP-SYNC-PAY', 'paid_at' => now()->toISOString()]],
            ['entity' => 'supplier_balance_summary', 'record_uuid' => $supplierBalanceUuid, 'operation' => 'summary_update', 'version' => 1, 'idempotency_key' => 'supplier-balance-'.$supplierBalanceUuid, 'occurred_at' => now()->toISOString(), 'payload' => ['supplier_uuid' => $supplierUuid, 'supplier_name' => 'Sync Supplier', 'phone' => '08035555555', 'current_balance_minor' => 100000, 'overdue_balance_minor' => 40000, 'last_purchase_at' => now()->toISOString(), 'last_payment_at' => now()->toISOString(), 'last_synchronized_at' => now()->toISOString()]],
        ],
    ])->assertOk()->assertJsonPath('data.status', 'accepted');

    expect(ReplicatedPurchase::query()->where('uuid', $purchaseUuid)->exists())->toBeTrue()
        ->and(ReplicatedPurchaseItem::query()->where('uuid', $itemUuid)->where('product_name', 'Beans 25kg')->exists())->toBeTrue()
        ->and(ReplicatedPurchasePayment::query()->where('uuid', $paymentUuid)->where('reference', 'SUP-SYNC-PAY')->exists())->toBeTrue()
        ->and(ReplicatedSupplierBalance::query()->where('uuid', $supplierBalanceUuid)->where('current_balance_minor', 100000)->exists())->toBeTrue()
        ->and(SyncRecord::query()->where('record_uuid', $purchaseUuid)->exists())->toBeTrue();
});

it('renders purchase monitoring reports details exports and read-only supplier balances', function (): void {
    [$user, $business, $branch] = purchaseMonitoringFixture();
    $purchase = replicatedPurchaseFixture($business, $branch);
    replicatedPurchaseFixture($business, $branch, ['purchase_number' => 'PUR-002', 'unit_cost_minor' => 60000, 'product_name' => 'Rice 50kg']);

    $this->actingAs($user)->withSession(['platform.business_id' => $business->id, 'platform.branch_id' => $branch->id])->get(route('platform.purchases'))
        ->assertOk()->assertSee('Supplier and purchase monitoring')->assertSee('PUR-001')->assertSee('Amina Supplies')->assertSee('Purchase-payment report')->assertDontSee('Edit purchase');

    Livewire::actingAs($user)->withQueryParams(['view' => 'details', 'purchaseNumber' => $purchase->uuid])->test(PurchaseMonitoringPage::class)->assertSee('Purchase details')->assertSee('Rice 50kg')->assertSee('Synchronization metadata')->assertSee('Transport and additional costs');
    Livewire::actingAs($user)->withQueryParams(['view' => 'suppliers'])->test(PurchaseMonitoringPage::class)->assertSee('Supplier balances')->assertSee('Amina Supplies');
    Livewire::actingAs($user)->withQueryParams(['view' => 'payments'])->test(PurchaseMonitoringPage::class)->assertSee('Purchase-payment report')->assertSee('SUP-PAY-001');
    Livewire::actingAs($user)->withQueryParams(['view' => 'trends'])->test(PurchaseMonitoringPage::class)->assertSee('Purchase trends');
    Livewire::actingAs($user)->withQueryParams(['view' => 'costs'])->test(PurchaseMonitoringPage::class)->assertSee('Product-cost changes')->assertSee('Rice 50kg');
    Livewire::actingAs($user)->withQueryParams(['view' => 'outstanding'])->test(PurchaseMonitoringPage::class)->assertSee('Outstanding supplier balances')->assertSee('Amina Supplies');
    Livewire::actingAs($user)->withQueryParams(['view' => 'branch'])->test(PurchaseMonitoringPage::class)->assertSee('Branch purchase comparison')->assertSee('Main Branch');
    Livewire::actingAs($user)->test(PurchaseMonitoringPage::class)->call('exportCsv')->assertFileDownloaded();
    Livewire::actingAs($user)->test(PurchaseMonitoringPage::class)->call('exportPdfFoundation')->assertFileDownloaded();
});

it('enforces purchase monitoring permissions and tenant isolation', function (): void {
    [$deniedUser, $deniedBusiness, $deniedBranch] = purchaseMonitoringFixture(['Sales']);
    replicatedPurchaseFixture($deniedBusiness, $deniedBranch, ['purchase_number' => 'DENIED-PUR']);

    $this->actingAs($deniedUser)->withSession(['platform.business_id' => $deniedBusiness->id, 'platform.branch_id' => $deniedBranch->id])->get(route('platform.purchases'))
        ->assertOk()->assertSee('Supplier and purchase monitoring is not available')->assertDontSee('DENIED-PUR');

    [$user, $business, $branch] = purchaseMonitoringFixture();
    replicatedPurchaseFixture($business, $branch, ['purchase_number' => 'VISIBLE-PUR']);
    $otherBusiness = Business::query()->create(['name' => 'Other Purchase Business', 'status' => BusinessStatus::Active]);
    $otherBranch = Branch::query()->create(['business_id' => $otherBusiness->id, 'name' => 'Other Branch', 'status' => BranchStatus::Active]);
    replicatedPurchaseFixture($otherBusiness, $otherBranch, ['purchase_number' => 'HIDDEN-PUR']);

    $this->actingAs($user)->withSession(['platform.business_id' => $business->id, 'platform.branch_id' => $branch->id])->get(route('platform.purchases'))
        ->assertOk()->assertSee('VISIBLE-PUR')->assertDontSee('HIDDEN-PUR');
});
