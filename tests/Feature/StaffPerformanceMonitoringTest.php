<?php

use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Devices\Models\Device;
use App\Domain\Devices\Services\ShopAppActivationService;
use App\Domain\Payments\Models\ReplicatedCashierSettlement;
use App\Domain\Payments\Models\ReplicatedCashierShift;
use App\Domain\Reporting\Models\StaffActivitySummary;
use App\Domain\Sales\Models\ReplicatedSale;
use App\Domain\Staff\Services\StaffPerformanceMonitoringService;
use App\Domain\Synchronization\Models\OperationalUserProfile;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Enums\BranchStatus;
use App\Enums\BusinessStatus;
use App\Enums\SubscriptionStatus;
use App\Livewire\Platform\Staff\StaffPerformanceMonitoringPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function staffPerformanceFixture(array $features = ['Cashier shifts', 'Reports', 'Staff accountability']): array
{
    $user = User::factory()->create();
    $plan = Plan::query()->create([
        'name' => 'Staff Plan',
        'slug' => 'staff-plan-'.Str::random(6),
        'billing_interval' => 'monthly',
        'price_minor' => 25000,
        'currency' => 'NGN',
        'trial_days' => 14,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    foreach ($features as $index => $feature) {
        $plan->features()->create(['name' => $feature, 'sort_order' => $index + 1]);
    }

    $business = Business::query()->create(['name' => 'Staff Stores', 'status' => BusinessStatus::Active]);
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

function staffProfileFixture(Business $business, Branch $branch, array $overrides = []): OperationalUserProfile
{
    $uuid = $overrides['uuid'] ?? (string) Str::uuid();

    return OperationalUserProfile::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'uuid' => $uuid,
        'employee_number' => $overrides['employee_number'] ?? 'EMP-001',
        'display_name' => $overrides['display_name'] ?? 'Ada Cashier',
        'username' => $overrides['username'] ?? 'ada',
        'staff_code' => $overrides['staff_code'] ?? 'CASH-01',
        'role_name' => $overrides['role_name'] ?? 'Cashier',
        'role_code' => $overrides['role_code'] ?? 'cashier',
        'status' => $overrides['status'] ?? 'active',
        'is_active' => true,
        'replicated_created_at' => now(),
        'replicated_updated_at' => now(),
    ]);
}

function staffSaleFixture(Business $business, Branch $branch, string $cashierUuid, array $overrides = []): ReplicatedSale
{
    $uuid = (string) Str::uuid();

    return ReplicatedSale::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'uuid' => $uuid,
        'sale_number' => $overrides['sale_number'] ?? 'STAFF-SALE-'.$uuid,
        'transaction_number' => $overrides['transaction_number'] ?? 'STAFF-TXN-'.$uuid,
        'cashier_uuid' => $cashierUuid,
        'cashier_name' => $overrides['cashier_name'] ?? 'Ada Cashier',
        'customer_name' => 'Walk In',
        'sale_type' => 'retail',
        'status' => $overrides['status'] ?? 'completed',
        'payment_status' => $overrides['payment_status'] ?? 'paid',
        'sold_at' => $overrides['sold_at'] ?? now(),
        'subtotal_minor' => $overrides['subtotal_minor'] ?? 100000,
        'discount_minor' => $overrides['discount_minor'] ?? 0,
        'tax_minor' => 0,
        'total_minor' => $overrides['total_minor'] ?? 100000,
        'cost_minor' => $overrides['cost_minor'] ?? 60000,
        'gross_profit_minor' => $overrides['gross_profit_minor'] ?? 40000,
        'source_version' => 1,
        'source_idempotency_key' => 'staff-sale-'.$uuid,
        'source_occurred_at' => now(),
    ]);
}

function staffSettlementFixture(Business $business, Branch $branch, string $cashierUuid): ReplicatedCashierSettlement
{
    $uuid = (string) Str::uuid();

    return ReplicatedCashierSettlement::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'uuid' => $uuid,
        'shift_uuid' => (string) Str::uuid(),
        'cashier_uuid' => $cashierUuid,
        'cashier_name' => 'Ada Cashier',
        'expected_cash_minor' => 150000,
        'actual_cash_minor' => 140000,
        'shortage_minor' => 10000,
        'surplus_minor' => 0,
        'status' => 'shortage',
        'settled_at' => now(),
        'source_version' => 1,
        'source_idempotency_key' => 'staff-settlement-'.$uuid,
        'source_occurred_at' => now(),
    ]);
}

function staffShiftFixture(Business $business, Branch $branch, string $cashierUuid): ReplicatedCashierShift
{
    $uuid = (string) Str::uuid();

    return ReplicatedCashierShift::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'uuid' => $uuid,
        'cashier_uuid' => $cashierUuid,
        'cashier_name' => 'Ada Cashier',
        'status' => 'closed',
        'opened_at' => now()->subHours(8),
        'closed_at' => now(),
        'duration_minutes' => 480,
        'last_activity_at' => now(),
        'source_version' => 1,
        'source_idempotency_key' => 'staff-shift-'.$uuid,
        'source_occurred_at' => now(),
    ]);
}

function staffActivityFixture(Business $business, Branch $branch, array $overrides = []): StaffActivitySummary
{
    return StaffActivitySummary::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'operational_user_uuid' => $overrides['operational_user_uuid'] ?? (string) Str::uuid(),
        'summary_date' => $overrides['summary_date'] ?? now()->toDateString(),
        'display_name' => $overrides['display_name'] ?? 'Bala Storekeeper',
        'role_name' => $overrides['role_name'] ?? 'Storekeeper',
        'sales_count' => $overrides['sales_count'] ?? 0,
        'gross_sales_minor' => $overrides['gross_sales_minor'] ?? 0,
        'voided_sales_count' => $overrides['voided_sales_count'] ?? 0,
        'returns_count' => $overrides['returns_count'] ?? 0,
        'cashier_variance_minor' => $overrides['cashier_variance_minor'] ?? 0,
        'purchases_recorded_count' => $overrides['purchases_recorded_count'] ?? 4,
        'stock_counts_count' => $overrides['stock_counts_count'] ?? 2,
        'stock_adjustments_count' => $overrides['stock_adjustments_count'] ?? 3,
        'damaged_stock_records_count' => $overrides['damaged_stock_records_count'] ?? 1,
        'transfers_processed_count' => $overrides['transfers_processed_count'] ?? 2,
        'discounts_approved_count' => $overrides['discounts_approved_count'] ?? 0,
        'expenses_approved_count' => $overrides['expenses_approved_count'] ?? 0,
        'returns_approved_count' => $overrides['returns_approved_count'] ?? 0,
        'stock_adjustments_approved_count' => $overrides['stock_adjustments_approved_count'] ?? 0,
        'cashier_settlements_reviewed_count' => $overrides['cashier_settlements_reviewed_count'] ?? 0,
        'outside_normal_hours_count' => $overrides['outside_normal_hours_count'] ?? 0,
        'excessive_discount_count' => $overrides['excessive_discount_count'] ?? 0,
        'high_cancellation_count' => $overrides['high_cancellation_count'] ?? 0,
        'high_return_count' => $overrides['high_return_count'] ?? 0,
        'repeated_stock_adjustment_count' => $overrides['repeated_stock_adjustment_count'] ?? 0,
        'last_activity_at' => $overrides['last_activity_at'] ?? now(),
    ]);
}

it('stores cashier shifts and staff activity summaries from synchronization records', function (): void {
    Queue::fake();
    [, $business, $branch] = staffPerformanceFixture();
    $deviceUser = User::factory()->create();
    $activation = app(ShopAppActivationService::class)->createActivation($business, $branch, $deviceUser, [
        'device_label' => 'Staff Business Server',
        'device_type' => 'shop_app',
        'expires_at' => now()->addMinutes(30),
        'application_version_id' => null,
    ]);
    $credential = app(ShopAppActivationService::class)->activate([
        'activation_code' => $activation['code'],
        'device_identity' => 'staff-machine',
        'app_version' => '1.0.0',
    ])['credential'];
    $device = Device::query()->where('business_id', $business->id)->firstOrFail();
    $cashierUuid = (string) Str::uuid();
    $shiftUuid = (string) Str::uuid();
    $summaryUuid = (string) Str::uuid();
    $body = json_encode([
        'protocol_version' => '1.0',
        'batch_uuid' => (string) Str::uuid(),
        'business_uuid' => $business->uuid,
        'branch_uuid' => $branch->uuid,
        'device_uuid' => $device->uuid,
        'created_at' => now()->toISOString(),
        'records' => [
            [
                'entity' => 'cashier_shift',
                'record_uuid' => $shiftUuid,
                'operation' => 'update',
                'version' => 1,
                'idempotency_key' => 'shift-'.$shiftUuid,
                'occurred_at' => now()->toISOString(),
                'payload' => [
                    'cashier_uuid' => $cashierUuid,
                    'cashier_name' => 'Sync Cashier',
                    'status' => 'closed',
                    'opened_at' => now()->subHours(6)->toISOString(),
                    'closed_at' => now()->toISOString(),
                    'duration_minutes' => 360,
                    'last_activity_at' => now()->toISOString(),
                ],
            ],
            [
                'entity' => 'staff_activity_summary',
                'record_uuid' => $summaryUuid,
                'operation' => 'summary_update',
                'version' => 1,
                'idempotency_key' => 'staff-summary-'.$summaryUuid,
                'occurred_at' => now()->toISOString(),
                'payload' => [
                    'operational_user_uuid' => $cashierUuid,
                    'summary_date' => now()->toDateString(),
                    'display_name' => 'Sync Cashier',
                    'role_name' => 'Cashier',
                    'sales_count' => 8,
                    'gross_sales_minor' => 200000,
                    'outside_normal_hours_count' => 1,
                    'last_activity_at' => now()->toISOString(),
                ],
            ],
        ],
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

    expect(ReplicatedCashierShift::query()->where('uuid', $shiftUuid)->where('duration_minutes', 360)->exists())->toBeTrue()
        ->and(StaffActivitySummary::query()->where('operational_user_uuid', $cashierUuid)->where('outside_normal_hours_count', 1)->exists())->toBeTrue();
});

it('renders cashier storekeeper manager and neutral review indicators', function (): void {
    [$user, $business, $branch] = staffPerformanceFixture();
    $cashier = staffProfileFixture($business, $branch);
    staffSaleFixture($business, $branch, $cashier->uuid, ['discount_minor' => 60000, 'total_minor' => 200000]);
    staffSaleFixture($business, $branch, $cashier->uuid, ['status' => 'cancelled', 'total_minor' => 50000]);
    staffSaleFixture($business, $branch, $cashier->uuid, ['status' => 'returned', 'total_minor' => 50000]);
    staffSettlementFixture($business, $branch, $cashier->uuid);
    staffShiftFixture($business, $branch, $cashier->uuid);
    staffActivityFixture($business, $branch, ['display_name' => 'Bala Storekeeper', 'role_name' => 'Storekeeper', 'repeated_stock_adjustment_count' => 2]);
    staffActivityFixture($business, $branch, [
        'display_name' => 'Musa Manager',
        'role_name' => 'Manager',
        'purchases_recorded_count' => 0,
        'stock_counts_count' => 0,
        'stock_adjustments_count' => 0,
        'damaged_stock_records_count' => 0,
        'transfers_processed_count' => 0,
        'discounts_approved_count' => 5,
        'expenses_approved_count' => 3,
        'returns_approved_count' => 2,
        'stock_adjustments_approved_count' => 4,
        'cashier_settlements_reviewed_count' => 1,
        'outside_normal_hours_count' => 1,
    ]);
    $serviceFilters = ['from' => now()->startOfMonth()->toDateString(), 'to' => now()->toDateString()];

    expect(StaffActivitySummary::query()->where('business_id', $business->id)->where('branch_id', $branch->id)->pluck('display_name')->all())->toContain('Bala Storekeeper')
        ->and(app(StaffPerformanceMonitoringService::class)->storekeeperActivity($business->id, $branch->id, $serviceFilters)->pluck('display_name')->all())->toContain('Bala Storekeeper')
        ->and(app(StaffPerformanceMonitoringService::class)->managerActivity($business->id, $branch->id, $serviceFilters)->pluck('display_name')->all())->toContain('Musa Manager');

    $this->actingAs($user)
        ->withSession(['platform.business_id' => $business->id, 'platform.branch_id' => $branch->id])
        ->get(route('platform.staff-performance'))
        ->assertOk()
        ->assertSee('Staff performance monitoring')
        ->assertSee('Cashier performance')
        ->assertSee('Ada Cashier')
        ->assertSee('Expected cash')
        ->assertSee('Shift duration')
        ->assertDontSee('Fraud')
        ->assertDontSee('Edit shift');

    Livewire::actingAs($user)->withQueryParams(['view' => 'storekeepers'])->test(StaffPerformanceMonitoringPage::class)->assertSee('Storekeeper activity')->assertSee('Bala Storekeeper')->assertSee('Damaged-stock records');
    Livewire::actingAs($user)->withQueryParams(['view' => 'managers'])->test(StaffPerformanceMonitoringPage::class)->assertSee('Manager activity')->assertSee('Musa Manager')->assertSee('Cashier settlements reviewed');
    Livewire::actingAs($user)->withQueryParams(['view' => 'exceptions'])->test(StaffPerformanceMonitoringPage::class)->assertSee('Frequent shortages')->assertSee('Requires review')->assertSee('Unusual activity')->assertSee('Outside expected range')->assertSee('Repeated stock adjustments');
    Livewire::actingAs($user)->withQueryParams(['view' => 'activity'])->test(StaffPerformanceMonitoringPage::class)->assertSee('Activity summaries')->assertSee('Review indicators');
});

it('enforces staff monitoring permissions and tenant isolation', function (): void {
    [$deniedUser, $deniedBusiness, $deniedBranch] = staffPerformanceFixture(['Inventory']);
    $hiddenCashier = staffProfileFixture($deniedBusiness, $deniedBranch, ['display_name' => 'Denied Cashier']);
    staffSaleFixture($deniedBusiness, $deniedBranch, $hiddenCashier->uuid, ['cashier_name' => 'Denied Cashier']);

    $this->actingAs($deniedUser)
        ->withSession(['platform.business_id' => $deniedBusiness->id, 'platform.branch_id' => $deniedBranch->id])
        ->get(route('platform.staff-performance'))
        ->assertOk()
        ->assertSee('Staff performance monitoring is not available')
        ->assertDontSee('Denied Cashier');

    [$user, $business, $branch] = staffPerformanceFixture();
    $visibleCashier = staffProfileFixture($business, $branch, ['display_name' => 'Visible Cashier']);
    staffSaleFixture($business, $branch, $visibleCashier->uuid, ['cashier_name' => 'Visible Cashier']);
    $otherBusiness = Business::query()->create(['name' => 'Other Staff Business', 'status' => BusinessStatus::Active]);
    $otherBranch = Branch::query()->create(['business_id' => $otherBusiness->id, 'name' => 'Other Branch', 'status' => BranchStatus::Active]);
    $otherCashier = staffProfileFixture($otherBusiness, $otherBranch, ['display_name' => 'Hidden Cashier']);
    staffSaleFixture($otherBusiness, $otherBranch, $otherCashier->uuid, ['cashier_name' => 'Hidden Cashier']);

    $this->actingAs($user)
        ->withSession(['platform.business_id' => $business->id, 'platform.branch_id' => $branch->id])
        ->get(route('platform.staff-performance'))
        ->assertOk()
        ->assertSee('Visible Cashier')
        ->assertDontSee('Hidden Cashier');
});

it('provides staff performance export foundations', function (): void {
    [$user, $business, $branch] = staffPerformanceFixture();
    $cashier = staffProfileFixture($business, $branch);
    staffSaleFixture($business, $branch, $cashier->uuid);

    Livewire::actingAs($user)
        ->test(StaffPerformanceMonitoringPage::class)
        ->call('exportCsv')
        ->assertFileDownloaded();

    Livewire::actingAs($user)
        ->test(StaffPerformanceMonitoringPage::class)
        ->call('exportPdfFoundation')
        ->assertFileDownloaded();
});
