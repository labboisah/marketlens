<?php

use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Devices\Models\Device;
use App\Domain\Devices\Services\ShopAppActivationService;
use App\Domain\Payments\Models\ReplicatedCashierSettlement;
use App\Domain\Sales\Models\ReplicatedSale;
use App\Domain\Sales\Models\ReplicatedSalePayment;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Enums\BranchStatus;
use App\Enums\BusinessStatus;
use App\Enums\SubscriptionStatus;
use App\Livewire\Platform\Payments\PaymentMonitoringPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function paymentMonitoringFixture(array $features = ['Sales', 'Cashier settlement']): array
{
    $user = User::factory()->create();
    $plan = Plan::query()->create([
        'name' => 'Payment Plan',
        'slug' => 'payment-plan-'.Str::random(6),
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

    $business = Business::query()->create(['name' => 'Payment Stores', 'status' => BusinessStatus::Active]);
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

function paymentSaleFixture(Business $business, Branch $branch, array $paymentOverrides = [], array $saleOverrides = []): ReplicatedSalePayment
{
    $saleUuid = (string) Str::uuid();
    $sale = ReplicatedSale::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'uuid' => $saleUuid,
        'sale_number' => $saleOverrides['sale_number'] ?? 'PAY-SALE-001',
        'transaction_number' => $saleOverrides['transaction_number'] ?? 'PAY-TXN-001',
        'cashier_uuid' => $saleOverrides['cashier_uuid'] ?? (string) Str::uuid(),
        'cashier_name' => $saleOverrides['cashier_name'] ?? 'Ada Cashier',
        'customer_name' => 'Walk In',
        'sale_type' => 'retail',
        'status' => 'completed',
        'payment_status' => 'paid',
        'sold_at' => now(),
        'total_minor' => $paymentOverrides['amount_minor'] ?? 100000,
        'cost_minor' => 60000,
        'gross_profit_minor' => 40000,
        'source_version' => 1,
        'source_idempotency_key' => 'pay-sale-'.$saleUuid,
        'source_occurred_at' => now(),
    ]);

    return ReplicatedSalePayment::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'replicated_sale_id' => $sale->id,
        'uuid' => (string) Str::uuid(),
        'sale_uuid' => $saleUuid,
        'method' => $paymentOverrides['method'] ?? 'cash',
        'status' => $paymentOverrides['status'] ?? 'completed',
        'amount_minor' => $paymentOverrides['amount_minor'] ?? 100000,
        'reference' => $paymentOverrides['reference'] ?? 'PAY-REF-001',
        'paid_at' => $paymentOverrides['paid_at'] ?? now(),
        'source_version' => 1,
        'source_idempotency_key' => 'pay-idem-'.Str::uuid(),
        'source_occurred_at' => now(),
    ]);
}

function settlementFixture(Business $business, Branch $branch, array $overrides = []): ReplicatedCashierSettlement
{
    $uuid = (string) Str::uuid();

    return ReplicatedCashierSettlement::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'uuid' => $uuid,
        'shift_uuid' => (string) Str::uuid(),
        'cashier_uuid' => $overrides['cashier_uuid'] ?? (string) Str::uuid(),
        'cashier_name' => $overrides['cashier_name'] ?? 'Ada Cashier',
        'expected_cash_minor' => $overrides['expected_cash_minor'] ?? 120000,
        'actual_cash_minor' => $overrides['actual_cash_minor'] ?? 110000,
        'shortage_minor' => $overrides['shortage_minor'] ?? 10000,
        'surplus_minor' => $overrides['surplus_minor'] ?? 0,
        'cash_expenses_minor' => $overrides['cash_expenses_minor'] ?? 5000,
        'refunds_minor' => $overrides['refunds_minor'] ?? 0,
        'unverified_transfer_minor' => $overrides['unverified_transfer_minor'] ?? 25000,
        'payment_mismatch_count' => $overrides['payment_mismatch_count'] ?? 1,
        'reversal_count' => $overrides['reversal_count'] ?? 2,
        'status' => $overrides['status'] ?? 'shortage',
        'settled_at' => $overrides['settled_at'] ?? now(),
        'source_version' => 1,
        'source_idempotency_key' => 'settlement-'.$uuid,
        'source_occurred_at' => now(),
    ]);
}

it('stores replicated cashier settlements from synchronized records', function (): void {
    Queue::fake();
    [, $business, $branch] = paymentMonitoringFixture();
    $deviceUser = User::factory()->create();
    $activation = app(ShopAppActivationService::class)->createActivation($business, $branch, $deviceUser, [
        'device_label' => 'Payment Shop App',
        'device_type' => 'shop_app',
        'expires_at' => now()->addMinutes(30),
        'application_version_id' => null,
    ]);
    $credential = app(ShopAppActivationService::class)->activate([
        'activation_code' => $activation['code'],
        'device_identity' => 'payment-machine',
        'app_version' => '1.0.0',
    ])['credential'];
    $device = Device::query()->where('business_id', $business->id)->firstOrFail();
    $settlementUuid = (string) Str::uuid();
    $body = json_encode([
        'protocol_version' => '1.0',
        'batch_uuid' => (string) Str::uuid(),
        'business_uuid' => $business->uuid,
        'branch_uuid' => $branch->uuid,
        'device_uuid' => $device->uuid,
        'created_at' => now()->toISOString(),
        'records' => [[
            'entity' => 'cashier_settlement',
            'record_uuid' => $settlementUuid,
            'operation' => 'settle',
            'version' => 1,
            'idempotency_key' => 'settlement-'.$settlementUuid,
            'occurred_at' => now()->toISOString(),
            'payload' => [
                'cashier_name' => 'Sync Cashier',
                'expected_cash_minor' => 150000,
                'actual_cash_minor' => 140000,
                'shortage_minor' => 10000,
                'cash_expenses_minor' => 3000,
                'unverified_transfer_minor' => 45000,
                'payment_mismatch_count' => 2,
                'reversal_count' => 3,
                'status' => 'shortage',
                'settled_at' => now()->toISOString(),
            ],
        ]],
    ], JSON_THROW_ON_ERROR);
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

    expect(ReplicatedCashierSettlement::query()->where('uuid', $settlementUuid)->where('shortage_minor', 10000)->exists())->toBeTrue();
});

it('renders payment monitoring pages breakdowns and read-only exceptions', function (): void {
    [$user, $business, $branch] = paymentMonitoringFixture();
    paymentSaleFixture($business, $branch, ['method' => 'cash', 'status' => 'completed', 'amount_minor' => 100000]);
    paymentSaleFixture($business, $branch, ['method' => 'bank_transfer', 'status' => 'unverified', 'amount_minor' => 85000, 'reference' => 'TRF-001']);
    paymentSaleFixture($business, $branch, ['method' => 'pos_terminal', 'status' => 'completed', 'amount_minor' => 70000]);
    paymentSaleFixture($business, $branch, ['method' => 'credit', 'status' => 'pending', 'amount_minor' => 40000]);
    paymentSaleFixture($business, $branch, ['method' => 'mobile_money', 'status' => 'refunded', 'amount_minor' => 60000]);
    paymentSaleFixture($business, $branch, ['method' => 'mixed', 'status' => 'reversed', 'amount_minor' => 30000]);
    settlementFixture($business, $branch);

    $this->actingAs($user)
        ->withSession(['platform.business_id' => $business->id, 'platform.branch_id' => $branch->id])
        ->get(route('platform.payments'))
        ->assertOk()
        ->assertSee('Payment monitoring')
        ->assertSee('Expected amounts')
        ->assertSee('Actual submitted cash')
        ->assertSee('Unverified transfer value')
        ->assertDontSee('Correct payment')
        ->assertDontSee('Edit payment');

    Livewire::actingAs($user)->withQueryParams(['view' => 'methods'])->test(PaymentMonitoringPage::class)->assertSee('Payment-method breakdown')->assertSee('Bank Transfer');
    Livewire::actingAs($user)->withQueryParams(['view' => 'unverified_transfers'])->test(PaymentMonitoringPage::class)->assertSee('TRF-001')->assertSee('Unverified');
    Livewire::actingAs($user)->withQueryParams(['view' => 'cash'])->test(PaymentMonitoringPage::class)->assertSee('Cash collections')->assertSee('Cash');
    Livewire::actingAs($user)->withQueryParams(['view' => 'pos'])->test(PaymentMonitoringPage::class)->assertSee('POS-terminal collections')->assertSee('Pos Terminal');
    Livewire::actingAs($user)->withQueryParams(['view' => 'credit'])->test(PaymentMonitoringPage::class)->assertSee('Credit sales')->assertSee('Credit');
    Livewire::actingAs($user)->withQueryParams(['view' => 'branch'])->test(PaymentMonitoringPage::class)->assertSee('Branch payment comparison')->assertSee('Main Branch');
    Livewire::actingAs($user)->withQueryParams(['view' => 'cashier'])->test(PaymentMonitoringPage::class)->assertSee('Cashier payment comparison')->assertSee('Ada Cashier');
    Livewire::actingAs($user)->withQueryParams(['view' => 'exceptions'])->test(PaymentMonitoringPage::class)->assertSee('Cash shortages')->assertSee('Unusually high refunds')->assertSee('Payment mismatches')->assertSee('Repeated payment reversals');
});

it('enforces payment monitoring permissions and tenant isolation', function (): void {
    [$deniedUser, $deniedBusiness, $deniedBranch] = paymentMonitoringFixture(['Inventory']);
    paymentSaleFixture($deniedBusiness, $deniedBranch, ['reference' => 'DENIED-PAY']);

    $this->actingAs($deniedUser)
        ->withSession(['platform.business_id' => $deniedBusiness->id, 'platform.branch_id' => $deniedBranch->id])
        ->get(route('platform.payments'))
        ->assertOk()
        ->assertSee('Payment monitoring is not available')
        ->assertDontSee('DENIED-PAY');

    [$user, $business, $branch] = paymentMonitoringFixture();
    paymentSaleFixture($business, $branch, ['reference' => 'VISIBLE-PAY']);
    $otherBusiness = Business::query()->create(['name' => 'Other Payment Business', 'status' => BusinessStatus::Active]);
    $otherBranch = Branch::query()->create(['business_id' => $otherBusiness->id, 'name' => 'Other Branch', 'status' => BranchStatus::Active]);
    paymentSaleFixture($otherBusiness, $otherBranch, ['reference' => 'HIDDEN-PAY']);

    $this->actingAs($user)
        ->withSession(['platform.business_id' => $business->id, 'platform.branch_id' => $branch->id])
        ->get(route('platform.payments'))
        ->assertOk()
        ->assertSee('VISIBLE-PAY')
        ->assertDontSee('HIDDEN-PAY');
});

it('provides payment csv export and pdf export foundation', function (): void {
    [$user, $business, $branch] = paymentMonitoringFixture();
    paymentSaleFixture($business, $branch);

    Livewire::actingAs($user)
        ->test(PaymentMonitoringPage::class)
        ->call('exportCsv')
        ->assertFileDownloaded();

    Livewire::actingAs($user)
        ->test(PaymentMonitoringPage::class)
        ->call('exportPdfFoundation')
        ->assertFileDownloaded();
});
