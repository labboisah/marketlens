<?php

use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Devices\Models\Device;
use App\Domain\Devices\Services\ShopAppActivationService;
use App\Domain\Reporting\Models\DashboardBranchSummary;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Enums\BranchStatus;
use App\Enums\BusinessStatus;
use App\Enums\DeviceStatus;
use App\Enums\SubscriptionStatus;
use App\Livewire\Platform\Branches\MultiBranchMonitoringPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function multiBranchMonitoringFixture(array $features = ['Multiple branches', 'Remote dashboard', 'Reports']): array
{
    $user = User::factory()->create();
    $plan = Plan::query()->create([
        'name' => 'Branch Plan',
        'slug' => 'branch-plan-'.Str::random(6),
        'billing_interval' => 'monthly',
        'price_minor' => 30000,
        'currency' => 'NGN',
        'trial_days' => 14,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    foreach ($features as $index => $feature) {
        $plan->features()->create(['name' => $feature, 'sort_order' => $index + 1]);
    }

    $business = Business::query()->create(['name' => 'Branch Stores', 'status' => BusinessStatus::Active]);
    $mainBranch = Branch::query()->create([
        'business_id' => $business->id,
        'name' => 'Main Branch',
        'code' => 'MAIN',
        'status' => BranchStatus::Active,
        'last_synchronized_at' => now(),
        'operating_modes' => ['sales', 'production'],
    ]);
    $eastBranch = Branch::query()->create([
        'business_id' => $business->id,
        'name' => 'East Branch',
        'code' => 'EAST',
        'status' => BranchStatus::Active,
        'last_synchronized_at' => now()->subHours(4),
        'operating_modes' => ['services'],
    ]);
    $user->ownedBusinesses()->attach($business->id, ['role' => 'owner']);

    Subscription::query()->create([
        'business_id' => $business->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active,
        'starts_at' => now(),
        'ends_at' => now()->addMonth(),
    ]);

    return [$user, $business, $mainBranch, $eastBranch];
}

function branchSummaryFixture(Business $business, Branch $branch, array $overrides = []): DashboardBranchSummary
{
    return DashboardBranchSummary::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'summary_date' => $overrides['summary_date'] ?? now()->toDateString(),
        'gross_sales_minor' => $overrides['gross_sales_minor'] ?? 250000,
        'net_sales_minor' => $overrides['net_sales_minor'] ?? 220000,
        'completed_sales_count' => $overrides['completed_sales_count'] ?? 12,
        'gross_profit_minor' => $overrides['gross_profit_minor'] ?? 80000,
        'expenses_minor' => $overrides['expenses_minor'] ?? 15000,
        'estimated_net_profit_minor' => $overrides['estimated_net_profit_minor'] ?? 65000,
        'stock_value_minor' => $overrides['stock_value_minor'] ?? 900000,
        'customer_debt_minor' => $overrides['customer_debt_minor'] ?? 30000,
        'supplier_balance_minor' => $overrides['supplier_balance_minor'] ?? 40000,
        'cashier_variance_minor' => $overrides['cashier_variance_minor'] ?? 0,
        'low_stock_count' => $overrides['low_stock_count'] ?? 2,
        'active_client_apps_count' => $overrides['active_client_apps_count'] ?? 0,
        'last_successful_sync_at' => $overrides['last_successful_sync_at'] ?? now(),
        'summarized_at' => $overrides['summarized_at'] ?? now(),
    ]);
}

it('stores branch performance summaries from synchronization records', function (): void {
    Queue::fake();
    [, $business, $branch] = multiBranchMonitoringFixture();
    $deviceUser = User::factory()->create();
    $activation = app(ShopAppActivationService::class)->createActivation($business, $branch, $deviceUser, [
        'device_label' => 'Branch Business Server',
        'device_type' => 'shop_app',
        'expires_at' => now()->addMinutes(30),
        'application_version_id' => null,
    ]);
    $credential = app(ShopAppActivationService::class)->activate([
        'activation_code' => $activation['code'],
        'device_identity' => 'branch-machine',
        'app_version' => '1.0.0',
    ])['credential'];
    $device = Device::query()->where('business_id', $business->id)->firstOrFail();
    $recordUuid = (string) Str::uuid();
    $body = json_encode([
        'protocol_version' => '1.0',
        'batch_uuid' => (string) Str::uuid(),
        'business_uuid' => $business->uuid,
        'branch_uuid' => $branch->uuid,
        'device_uuid' => $device->uuid,
        'created_at' => now()->toISOString(),
        'records' => [[
            'entity' => 'branch_performance_summary',
            'record_uuid' => $recordUuid,
            'operation' => 'summary_update',
            'version' => 1,
            'idempotency_key' => 'branch-summary-'.$recordUuid,
            'occurred_at' => now()->toISOString(),
            'payload' => [
                'summary_date' => now()->toDateString(),
                'gross_sales_minor' => 500000,
                'net_sales_minor' => 450000,
                'transaction_count' => 20,
                'gross_profit_minor' => 160000,
                'expenses_minor' => 30000,
                'estimated_net_profit_minor' => 130000,
                'stock_value_minor' => 1200000,
                'customer_debt_minor' => 70000,
                'supplier_balance_minor' => 90000,
                'cashier_variance_minor' => 5000,
                'low_stock_count' => 4,
                'active_client_apps_count' => 3,
                'last_successful_sync_at' => now()->toISOString(),
                'summarized_at' => now()->toISOString(),
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

    expect(DashboardBranchSummary::query()->where('business_id', $business->id)->where('branch_id', $branch->id)->where('net_sales_minor', 450000)->exists())->toBeTrue();
});

it('renders branch comparison cards ranking trends health and detail dashboard', function (): void {
    [$user, $business, $mainBranch, $eastBranch] = multiBranchMonitoringFixture();
    branchSummaryFixture($business, $mainBranch, ['net_sales_minor' => 300000, 'gross_sales_minor' => 340000, 'gross_profit_minor' => 120000, 'last_successful_sync_at' => now(), 'active_client_apps_count' => 2]);
    branchSummaryFixture($business, $eastBranch, ['net_sales_minor' => 600000, 'gross_sales_minor' => 680000, 'gross_profit_minor' => 220000, 'cashier_variance_minor' => 10000, 'last_successful_sync_at' => now()->subHours(5)]);
    Device::query()->create([
        'business_id' => $business->id,
        'branch_id' => $mainBranch->id,
        'uuid' => (string) Str::uuid(),
        'name' => 'Business App 1',
        'type' => 'client_app',
        'status' => DeviceStatus::Active,
    ]);

    $this->actingAs($user)
        ->withSession(['platform.business_id' => $business->id, 'platform.branch_id' => $mainBranch->id])
        ->get(route('platform.branch-monitoring'))
        ->assertOk()
        ->assertSee('Multi-branch monitoring')
        ->assertSee('Branch comparison table')
        ->assertSee('Branch Performance')
        ->assertSee('Main Branch')
        ->assertSee('East Branch')
        ->assertSee('Stale data warning')
        ->assertSee('Active Business Apps')
        ->assertSee('Branch detail dashboard')
        ->assertDontSee('Edit branch sales');

    Livewire::actingAs($user)
        ->withQueryParams(['period' => 'month', 'rankingMetric' => 'gross_profit_minor', 'branchFilter' => (string) $eastBranch->id])
        ->test(MultiBranchMonitoringPage::class)
        ->assertSee('Branch ranking')
        ->assertSee('Branch trend comparison')
        ->assertSee('Cash shortage')
        ->assertSee('Synchronized data may be stale');
});

it('supports custom date range and does not expose other tenant branches', function (): void {
    [$user, $business, $mainBranch, $eastBranch] = multiBranchMonitoringFixture();
    branchSummaryFixture($business, $mainBranch, ['summary_date' => now()->subDays(3)->toDateString(), 'net_sales_minor' => 111000]);
    branchSummaryFixture($business, $eastBranch, ['summary_date' => now()->subDays(10)->toDateString(), 'net_sales_minor' => 999000]);
    $otherBusiness = Business::query()->create(['name' => 'Other Branch Business', 'status' => BusinessStatus::Active]);
    $otherBranch = Branch::query()->create(['business_id' => $otherBusiness->id, 'name' => 'Hidden Branch', 'status' => BranchStatus::Active]);
    branchSummaryFixture($otherBusiness, $otherBranch, ['net_sales_minor' => 777000]);

    Livewire::actingAs($user)
        ->withQueryParams([
            'period' => 'custom',
            'from' => now()->subDays(5)->toDateString(),
            'to' => now()->toDateString(),
        ])
        ->test(MultiBranchMonitoringPage::class)
        ->assertSee('Main Branch')
        ->assertDontSee('Hidden Branch');
});

it('enforces branch monitoring permissions', function (): void {
    [$user, $business, $branch] = multiBranchMonitoringFixture(['Sales']);
    branchSummaryFixture($business, $branch);

    $this->actingAs($user)
        ->withSession(['platform.business_id' => $business->id, 'platform.branch_id' => $branch->id])
        ->get(route('platform.branch-monitoring'))
        ->assertOk()
        ->assertSee('Multi-branch monitoring is not available')
        ->assertDontSee('Branch comparison table');
});

it('returns configured operating modes for authorized monitoring branches', function (): void {
    [$user, $business, $mainBranch, $eastBranch] = multiBranchMonitoringFixture();
    Sanctum::actingAs($user);

    $this->getJson(route('api.v1.monitoring.branches', ['business_uuid' => $business->uuid]))
        ->assertOk()
        ->assertJsonFragment([
            'business_uuid' => $business->uuid,
            'branch_uuid' => $mainBranch->uuid,
            'operating_modes' => ['sales', 'production'],
        ])
        ->assertJsonFragment([
            'business_uuid' => $business->uuid,
            'branch_uuid' => $eastBranch->uuid,
            'operating_modes' => ['services'],
        ]);
});
