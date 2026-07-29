<?php

use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Customers\Models\ReplicatedCustomerDebtPayment;
use App\Domain\Customers\Models\ReplicatedCustomerDebtSummary;
use App\Domain\Devices\Models\Device;
use App\Domain\Devices\Services\ShopAppActivationService;
use App\Domain\Synchronization\Models\SyncRecord;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Enums\BranchStatus;
use App\Enums\BusinessStatus;
use App\Enums\SubscriptionStatus;
use App\Livewire\Platform\Customers\CustomerDebtMonitoringPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

uses(RefreshDatabase::class);

function customerDebtMonitoringFixture(array $features = ['Customer debts', 'Reports']): array
{
    $user = User::factory()->create();
    $plan = Plan::query()->create([
        'name' => 'Debt Plan',
        'slug' => 'debt-plan-'.Str::random(6),
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

    $business = Business::query()->create(['name' => 'Debt Stores', 'status' => BusinessStatus::Active]);
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

function customerDebtSummaryFixture(Business $business, Branch $branch, array $overrides = []): ReplicatedCustomerDebtSummary
{
    $customerUuid = $overrides['customer_uuid'] ?? (string) Str::uuid();
    $summary = ReplicatedCustomerDebtSummary::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'uuid' => $overrides['uuid'] ?? (string) Str::uuid(),
        'customer_uuid' => $customerUuid,
        'customer_name' => $overrides['customer_name'] ?? 'Ada Customer',
        'phone' => $overrides['phone'] ?? '08030000000',
        'credit_limit_minor' => $overrides['credit_limit_minor'] ?? 500000,
        'current_balance_minor' => $overrides['current_balance_minor'] ?? 220000,
        'credit_sales_minor' => $overrides['credit_sales_minor'] ?? 300000,
        'overdue_amount_minor' => $overrides['overdue_amount_minor'] ?? 120000,
        'debt_status' => $overrides['debt_status'] ?? 'overdue',
        'last_payment_at' => $overrides['last_payment_at'] ?? now()->subDays(3),
        'last_payment_minor' => $overrides['last_payment_minor'] ?? 50000,
        'last_payment_method' => $overrides['last_payment_method'] ?? 'cash',
        'oldest_unpaid_sale_at' => $overrides['oldest_unpaid_sale_at'] ?? now()->subDays(45),
        'oldest_unpaid_sale_number' => $overrides['oldest_unpaid_sale_number'] ?? 'SALE-CREDIT-001',
        'age_current_minor' => $overrides['age_current_minor'] ?? 100000,
        'age_1_30_minor' => $overrides['age_1_30_minor'] ?? 50000,
        'age_31_60_minor' => $overrides['age_31_60_minor'] ?? 70000,
        'age_61_90_minor' => $overrides['age_61_90_minor'] ?? 0,
        'age_over_90_minor' => $overrides['age_over_90_minor'] ?? 0,
        'last_synchronized_at' => $overrides['last_synchronized_at'] ?? now(),
        'source_version' => 1,
        'source_idempotency_key' => $overrides['source_idempotency_key'] ?? 'debt-idem-'.$customerUuid,
        'source_occurred_at' => now(),
    ]);

    ReplicatedCustomerDebtPayment::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'customer_debt_summary_id' => $summary->id,
        'uuid' => (string) Str::uuid(),
        'customer_uuid' => $customerUuid,
        'customer_name' => $summary->customer_name,
        'amount_minor' => 50000,
        'payment_method' => 'cash',
        'reference' => 'DEBT-PAY-001',
        'paid_at' => now()->subDays(3),
    ]);

    return $summary;
}

function signedCustomerDebtSyncRequest(TestCase $test, string $credential, array $payload): TestResponse
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

it('stores replicated customer debt summaries and payment history from synchronization records', function (): void {
    Queue::fake();
    [, $business, $branch] = customerDebtMonitoringFixture();
    $deviceUser = User::factory()->create();
    $activation = app(ShopAppActivationService::class)->createActivation($business, $branch, $deviceUser, [
        'device_label' => 'Debt Shop App',
        'device_type' => 'shop_app',
        'expires_at' => now()->addMinutes(30),
        'application_version_id' => null,
    ]);
    $credential = app(ShopAppActivationService::class)->activate([
        'activation_code' => $activation['code'],
        'device_identity' => 'debt-machine',
        'app_version' => '1.0.0',
    ])['credential'];
    $device = Device::query()->where('business_id', $business->id)->firstOrFail();
    $recordUuid = (string) Str::uuid();
    $customerUuid = (string) Str::uuid();
    $paymentUuid = (string) Str::uuid();

    signedCustomerDebtSyncRequest($this, $credential, [
        'protocol_version' => '1.0',
        'batch_uuid' => (string) Str::uuid(),
        'business_uuid' => $business->uuid,
        'branch_uuid' => $branch->uuid,
        'device_uuid' => $device->uuid,
        'created_at' => now()->toISOString(),
        'records' => [[
            'entity' => 'customer_debt_summary',
            'record_uuid' => $recordUuid,
            'operation' => 'summary_update',
            'version' => 1,
            'idempotency_key' => 'debt-'.$recordUuid,
            'occurred_at' => now()->toISOString(),
            'payload' => [
                'customer_uuid' => $customerUuid,
                'customer_name' => 'Sync Debtor',
                'phone' => '08031111111',
                'credit_limit_minor' => 500000,
                'current_balance_minor' => 220000,
                'credit_sales_minor' => 300000,
                'overdue_amount_minor' => 120000,
                'debt_status' => 'overdue',
                'last_payment_at' => now()->subDays(3)->toISOString(),
                'last_payment_minor' => 50000,
                'last_payment_method' => 'cash',
                'oldest_unpaid_sale_at' => now()->subDays(45)->toISOString(),
                'oldest_unpaid_sale_number' => 'SALE-CREDIT-001',
                'age_current_minor' => 100000,
                'age_1_30_minor' => 50000,
                'age_31_60_minor' => 70000,
                'age_61_90_minor' => 0,
                'age_over_90_minor' => 0,
                'last_synchronized_at' => now()->toISOString(),
                'payment_history' => [[
                    'uuid' => $paymentUuid,
                    'amount_minor' => 50000,
                    'payment_method' => 'cash',
                    'reference' => 'SYNC-PAY-001',
                    'paid_at' => now()->subDays(3)->toISOString(),
                ]],
            ],
        ]],
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'accepted');

    expect(ReplicatedCustomerDebtSummary::query()->where('customer_uuid', $customerUuid)->where('current_balance_minor', 220000)->exists())->toBeTrue()
        ->and(ReplicatedCustomerDebtPayment::query()->where('uuid', $paymentUuid)->where('reference', 'SYNC-PAY-001')->exists())->toBeTrue()
        ->and(SyncRecord::query()->where('record_uuid', $recordUuid)->exists())->toBeTrue();
});

it('renders customer debt reports balances ageing payments and branch comparison', function (): void {
    [$user, $business, $branch] = customerDebtMonitoringFixture();
    customerDebtSummaryFixture($business, $branch);
    customerDebtSummaryFixture($business, $branch, [
        'customer_name' => 'Highest Debtor',
        'phone' => '08039999999',
        'current_balance_minor' => 900000,
        'overdue_amount_minor' => 800000,
        'age_over_90_minor' => 800000,
        'oldest_unpaid_sale_number' => 'SALE-OLD-001',
    ]);
    customerDebtSummaryFixture($business, $branch, [
        'customer_name' => 'Current Customer',
        'debt_status' => 'current',
        'overdue_amount_minor' => 0,
        'current_balance_minor' => 60000,
    ]);

    $this->actingAs($user)
        ->withSession(['platform.business_id' => $business->id, 'platform.branch_id' => $branch->id])
        ->get(route('platform.customer-debts'))
        ->assertOk()
        ->assertSee('Customer debt monitoring')
        ->assertSee('Ada Customer')
        ->assertSee('The Platform must not overwrite local customer balances')
        ->assertDontSee('Edit balance')
        ->assertDontSee('Overwrite balance');

    Livewire::actingAs($user)->withQueryParams(['view' => 'balances'])->test(CustomerDebtMonitoringPage::class)->assertSee('Customer balances')->assertSee('Ada Customer');
    Livewire::actingAs($user)->withQueryParams(['view' => 'overdue'])->test(CustomerDebtMonitoringPage::class)->assertSee('Overdue balances')->assertSee('Highest Debtor')->assertDontSee('Current Customer');
    Livewire::actingAs($user)->withQueryParams(['view' => 'credit_sales'])->test(CustomerDebtMonitoringPage::class)->assertSee('Credit-sales report')->assertSee('SALE-CREDIT-001');
    Livewire::actingAs($user)->withQueryParams(['view' => 'payments'])->test(CustomerDebtMonitoringPage::class)->assertSee('Customer-payment history')->assertSee('DEBT-PAY-001');
    Livewire::actingAs($user)->withQueryParams(['view' => 'ageing'])->test(CustomerDebtMonitoringPage::class)->assertSee('Debt ageing report')->assertSee('Current')->assertSee('More than 90 days overdue');
    Livewire::actingAs($user)->withQueryParams(['view' => 'highest'])->test(CustomerDebtMonitoringPage::class)->assertSee('Highest debtors')->assertSee('Highest Debtor');
    Livewire::actingAs($user)->withQueryParams(['view' => 'branch'])->test(CustomerDebtMonitoringPage::class)->assertSee('Branch debt comparison')->assertSee('Main Branch');
});

it('filters customer debts by customer and enforces permissions and tenant isolation', function (): void {
    [$deniedUser, $deniedBusiness, $deniedBranch] = customerDebtMonitoringFixture(['Sales']);
    customerDebtSummaryFixture($deniedBusiness, $deniedBranch, ['customer_name' => 'DENIED-DEBTOR']);

    $this->actingAs($deniedUser)
        ->withSession(['platform.business_id' => $deniedBusiness->id, 'platform.branch_id' => $deniedBranch->id])
        ->get(route('platform.customer-debts'))
        ->assertOk()
        ->assertSee('Customer debt monitoring is not available')
        ->assertDontSee('DENIED-DEBTOR');

    [$user, $business, $branch] = customerDebtMonitoringFixture();
    customerDebtSummaryFixture($business, $branch, ['customer_name' => 'VISIBLE-DEBTOR', 'phone' => '08032222222']);
    customerDebtSummaryFixture($business, $branch, ['customer_name' => 'FILTERED-DEBTOR', 'phone' => '08033333333']);
    $otherBusiness = Business::query()->create(['name' => 'Other Debt Business', 'status' => BusinessStatus::Active]);
    $otherBranch = Branch::query()->create(['business_id' => $otherBusiness->id, 'name' => 'Other Branch', 'status' => BranchStatus::Active]);
    customerDebtSummaryFixture($otherBusiness, $otherBranch, ['customer_name' => 'HIDDEN-DEBTOR']);

    $this->actingAs($user)
        ->withSession(['platform.business_id' => $business->id, 'platform.branch_id' => $branch->id])
        ->get(route('platform.customer-debts', ['customer' => '08032222222']))
        ->assertOk()
        ->assertSee('VISIBLE-DEBTOR')
        ->assertDontSee('FILTERED-DEBTOR');

    $this->actingAs($user)
        ->withSession(['platform.business_id' => $business->id, 'platform.branch_id' => $branch->id])
        ->get(route('platform.customer-debts'))
        ->assertOk()
        ->assertSee('VISIBLE-DEBTOR')
        ->assertDontSee('HIDDEN-DEBTOR');
});
