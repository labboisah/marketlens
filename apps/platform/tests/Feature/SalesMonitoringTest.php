<?php

use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Devices\Models\Device;
use App\Domain\Devices\Services\ShopAppActivationService;
use App\Domain\Sales\Models\ReplicatedSale;
use App\Domain\Sales\Models\ReplicatedSaleItem;
use App\Domain\Sales\Models\ReplicatedSalePayment;
use App\Domain\Synchronization\Models\SyncRecord;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Enums\BranchStatus;
use App\Enums\BusinessStatus;
use App\Enums\SubscriptionStatus;
use App\Livewire\Platform\Sales\SalesMonitoringPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function salesMonitoringFixture(array $features = ['Sales', 'Reports']): array
{
    $user = User::factory()->create();
    $plan = Plan::query()->create([
        'name' => 'Sales Plan',
        'slug' => 'sales-plan-'.Str::random(6),
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

    $business = Business::query()->create(['name' => 'Sales Stores', 'status' => BusinessStatus::Active]);
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

function replicatedSaleFixture(Business $business, Branch $branch, array $overrides = []): ReplicatedSale
{
    $saleUuid = $overrides['uuid'] ?? (string) Str::uuid();
    $sale = ReplicatedSale::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'uuid' => $saleUuid,
        'sale_number' => $overrides['sale_number'] ?? 'SALE-001',
        'transaction_number' => $overrides['transaction_number'] ?? 'TXN-001',
        'cashier_uuid' => $overrides['cashier_uuid'] ?? (string) Str::uuid(),
        'cashier_name' => $overrides['cashier_name'] ?? 'Ada Cashier',
        'customer_uuid' => $overrides['customer_uuid'] ?? (string) Str::uuid(),
        'customer_name' => $overrides['customer_name'] ?? 'Walk In',
        'sale_type' => $overrides['sale_type'] ?? 'retail',
        'status' => $overrides['status'] ?? 'completed',
        'payment_status' => $overrides['payment_status'] ?? 'paid',
        'sold_at' => $overrides['sold_at'] ?? now(),
        'subtotal_minor' => 120000,
        'discount_minor' => 5000,
        'tax_minor' => 0,
        'total_minor' => $overrides['total_minor'] ?? 115000,
        'cost_minor' => 70000,
        'gross_profit_minor' => $overrides['gross_profit_minor'] ?? 45000,
        'cancellation' => $overrides['cancellation'] ?? null,
        'return_summary' => $overrides['return_summary'] ?? null,
        'source_version' => 1,
        'source_idempotency_key' => 'sale-idem-'.$saleUuid,
        'source_occurred_at' => now(),
    ]);

    ReplicatedSaleItem::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'replicated_sale_id' => $sale->id,
        'uuid' => (string) Str::uuid(),
        'sale_uuid' => $saleUuid,
        'product_uuid' => (string) Str::uuid(),
        'product_name' => 'Rice 50kg',
        'sku' => 'RICE-50',
        'category_uuid' => (string) Str::uuid(),
        'category_name' => 'Foodstuff',
        'quantity' => 2,
        'unit' => 'bag',
        'unit_price_minor' => 60000,
        'discount_minor' => 5000,
        'tax_minor' => 0,
        'line_total_minor' => 115000,
        'cost_minor' => 70000,
        'gross_profit_minor' => 45000,
        'source_version' => 1,
        'source_idempotency_key' => 'item-idem-'.$saleUuid,
        'source_occurred_at' => now(),
    ]);

    ReplicatedSalePayment::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'replicated_sale_id' => $sale->id,
        'uuid' => (string) Str::uuid(),
        'sale_uuid' => $saleUuid,
        'method' => 'cash',
        'status' => 'completed',
        'amount_minor' => 115000,
        'reference' => 'CASH-001',
        'paid_at' => now(),
        'source_version' => 1,
        'source_idempotency_key' => 'payment-idem-'.$saleUuid,
        'source_occurred_at' => now(),
    ]);

    return $sale;
}

it('stores replicated sale item and payment records from synchronized sales', function (): void {
    Queue::fake();
    [, $business, $branch] = salesMonitoringFixture();
    $deviceUser = User::factory()->create();

    $activation = app(ShopAppActivationService::class)->createActivation($business, $branch, $deviceUser, [
        'device_label' => 'Sales Business Server',
        'device_type' => 'shop_app',
        'expires_at' => now()->addMinutes(30),
        'application_version_id' => null,
    ]);
    $credential = app(ShopAppActivationService::class)->activate([
        'activation_code' => $activation['code'],
        'device_identity' => 'sales-machine',
        'app_version' => '1.0.0',
    ])['credential'];
    $device = Device::query()->where('business_id', $business->id)->firstOrFail();
    $saleUuid = (string) Str::uuid();
    $itemUuid = (string) Str::uuid();
    $paymentUuid = (string) Str::uuid();
    $payload = [
        'protocol_version' => '1.0',
        'batch_uuid' => (string) Str::uuid(),
        'business_uuid' => $business->uuid,
        'branch_uuid' => $branch->uuid,
        'device_uuid' => $device->uuid,
        'created_at' => now()->toISOString(),
        'records' => [
            [
                'entity' => 'sale',
                'record_uuid' => $saleUuid,
                'operation' => 'create',
                'version' => 1,
                'idempotency_key' => 'sale-'.$saleUuid,
                'occurred_at' => now()->toISOString(),
                'payload' => [
                    'sale_number' => 'SALE-SYNC-001',
                    'transaction_number' => 'TXN-SYNC-001',
                    'cashier_name' => 'Sync Cashier',
                    'customer_name' => 'Sync Customer',
                    'sale_type' => 'retail',
                    'status' => 'completed',
                    'payment_status' => 'paid',
                    'sold_at' => now()->toISOString(),
                    'total_minor' => 250000,
                    'cost_minor' => 180000,
                ],
            ],
            [
                'entity' => 'sale_item',
                'record_uuid' => $itemUuid,
                'operation' => 'create',
                'version' => 1,
                'idempotency_key' => 'item-'.$itemUuid,
                'occurred_at' => now()->toISOString(),
                'payload' => [
                    'sale_uuid' => $saleUuid,
                    'product_name' => 'Beans 25kg',
                    'category_name' => 'Foodstuff',
                    'quantity' => 1,
                    'unit' => 'bag',
                    'unit_price_minor' => 250000,
                    'line_total_minor' => 250000,
                    'cost_minor' => 180000,
                ],
            ],
            [
                'entity' => 'sale_payment',
                'record_uuid' => $paymentUuid,
                'operation' => 'create',
                'version' => 1,
                'idempotency_key' => 'payment-'.$paymentUuid,
                'occurred_at' => now()->toISOString(),
                'payload' => [
                    'sale_uuid' => $saleUuid,
                    'method' => 'transfer',
                    'status' => 'completed',
                    'amount_minor' => 250000,
                    'reference' => 'TRF-001',
                    'paid_at' => now()->toISOString(),
                ],
            ],
        ],
    ];
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $timestamp = now()->toISOString();

    $this->call('POST', route('api.v1.sync.batches.store'), [], [], [], [
        'HTTP_ACCEPT' => 'application/json',
        'CONTENT_TYPE' => 'application/json',
        'HTTP_AUTHORIZATION' => 'Bearer '.$credential,
        'HTTP_X_ML_TIMESTAMP' => $timestamp,
        'HTTP_X_ML_SIGNATURE' => hash_hmac('sha256', $timestamp.'.'.$body, $credential),
    ], $body)
        ->assertOk()
        ->assertJsonPath('data.status', 'accepted');

    expect(ReplicatedSale::query()->where('uuid', $saleUuid)->where('total_minor', 250000)->exists())->toBeTrue()
        ->and(ReplicatedSaleItem::query()->where('uuid', $itemUuid)->where('product_name', 'Beans 25kg')->exists())->toBeTrue()
        ->and(ReplicatedSalePayment::query()->where('uuid', $paymentUuid)->where('method', 'transfer')->exists())->toBeTrue()
        ->and(SyncRecord::query()->where('record_uuid', $saleUuid)->exists())->toBeTrue();
});

it('renders read-only sales monitoring pages reports and sale details', function (): void {
    [$user, $business, $branch] = salesMonitoringFixture();
    $sale = replicatedSaleFixture($business, $branch);

    $this->actingAs($user)
        ->withSession(['platform.business_id' => $business->id, 'platform.branch_id' => $branch->id])
        ->get(route('platform.sales'))
        ->assertOk()
        ->assertSee('Sales monitoring')
        ->assertSee('SALE-001')
        ->assertSee('Product-sales report')
        ->assertDontSee('Edit sale')
        ->assertDontSee('Delete sale');

    Livewire::actingAs($user)
        ->withQueryParams(['view' => 'details', 'transactionNumber' => $sale->uuid])
        ->test(SalesMonitoringPage::class)
        ->assertSee('Sale details')
        ->assertSee('Rice 50kg')
        ->assertSee('Payments')
        ->assertSee('Synchronization metadata')
        ->assertSee('Source device');

    Livewire::actingAs($user)
        ->withQueryParams(['view' => 'product'])
        ->test(SalesMonitoringPage::class)
        ->assertSee('Rice 50kg');

    Livewire::actingAs($user)
        ->withQueryParams(['view' => 'category'])
        ->test(SalesMonitoringPage::class)
        ->assertSee('Foodstuff');

    Livewire::actingAs($user)
        ->withQueryParams(['view' => 'cashier'])
        ->test(SalesMonitoringPage::class)
        ->assertSee('Ada Cashier');
});

it('shows cancelled and returned sales reports from replicated status records', function (): void {
    [$user, $business, $branch] = salesMonitoringFixture();
    replicatedSaleFixture($business, $branch, [
        'sale_number' => 'SALE-CANCELLED',
        'transaction_number' => 'TXN-CANCELLED',
        'status' => 'cancelled',
        'cancellation' => ['reason' => 'Customer changed mind'],
    ]);
    replicatedSaleFixture($business, $branch, [
        'sale_number' => 'SALE-RETURNED',
        'transaction_number' => 'TXN-RETURNED',
        'status' => 'returned',
        'return_summary' => ['reason' => 'Damaged item'],
    ]);

    Livewire::actingAs($user)
        ->withQueryParams(['view' => 'cancelled'])
        ->test(SalesMonitoringPage::class)
        ->assertSee('SALE-CANCELLED')
        ->assertDontSee('SALE-RETURNED');

    Livewire::actingAs($user)
        ->withQueryParams(['view' => 'returned'])
        ->test(SalesMonitoringPage::class)
        ->assertSee('SALE-RETURNED')
        ->assertDontSee('SALE-CANCELLED');
});

it('enforces subscription permissions and tenant isolation for sales monitoring', function (): void {
    [$user, $business, $branch] = salesMonitoringFixture(['Inventory']);
    replicatedSaleFixture($business, $branch, ['sale_number' => 'DENIED-SALE']);

    $this->actingAs($user)
        ->withSession(['platform.business_id' => $business->id, 'platform.branch_id' => $branch->id])
        ->get(route('platform.sales'))
        ->assertOk()
        ->assertSee('Sales monitoring is not available')
        ->assertDontSee('DENIED-SALE');

    [$allowedUser, $allowedBusiness, $allowedBranch] = salesMonitoringFixture();
    replicatedSaleFixture($allowedBusiness, $allowedBranch, ['sale_number' => 'VISIBLE-SALE']);
    $otherBusiness = Business::query()->create(['name' => 'Other Business', 'status' => BusinessStatus::Active]);
    $otherBranch = Branch::query()->create(['business_id' => $otherBusiness->id, 'name' => 'Other Branch', 'status' => BranchStatus::Active]);
    replicatedSaleFixture($otherBusiness, $otherBranch, ['sale_number' => 'HIDDEN-SALE']);

    $this->actingAs($allowedUser)
        ->withSession(['platform.business_id' => $allowedBusiness->id, 'platform.branch_id' => $allowedBranch->id])
        ->get(route('platform.sales'))
        ->assertOk()
        ->assertSee('VISIBLE-SALE')
        ->assertDontSee('HIDDEN-SALE');
});

it('provides csv export and pdf export foundation for sales monitoring', function (): void {
    [$user, $business, $branch] = salesMonitoringFixture();
    replicatedSaleFixture($business, $branch);

    Livewire::actingAs($user)
        ->test(SalesMonitoringPage::class)
        ->call('exportCsv')
        ->assertFileDownloaded();

    Livewire::actingAs($user)
        ->test(SalesMonitoringPage::class)
        ->call('exportPdfFoundation')
        ->assertFileDownloaded();
});
