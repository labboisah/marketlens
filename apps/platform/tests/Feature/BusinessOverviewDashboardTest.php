<?php

use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Reporting\Models\BusinessDashboardDailySummary;
use App\Domain\Reporting\Models\DashboardBranchSummary;
use App\Domain\Reporting\Models\DashboardBusinessActivity;
use App\Domain\Reporting\Models\DashboardCashierException;
use App\Domain\Reporting\Models\DashboardProductSummary;
use App\Domain\Reporting\Services\BusinessOverviewDashboard;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Enums\AccountStatus;
use App\Enums\SubscriptionStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function overviewDashboardUser(): User
{
    return User::factory()->create([
        'account_status' => AccountStatus::Active,
        'email_verified_at' => now(),
    ]);
}

function overviewDashboardBusiness(User $user, string $name = 'Ada Stores'): array
{
    $plan = Plan::query()->create([
        'name' => 'Professional',
        'slug' => 'professional',
        'billing_interval' => 'monthly',
        'price_minor' => 10000,
        'currency' => 'NGN',
        'trial_days' => 14,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    foreach (['Sales', 'Inventory', 'Expenses', 'Customer debts', 'Purchases and suppliers', 'Cashier shifts', 'Multiple branches', 'Security alerts', 'Remote dashboard'] as $index => $feature) {
        $plan->features()->create(['name' => $feature, 'sort_order' => $index + 1]);
    }

    $business = Business::query()->create(['name' => $name]);
    $branch = Branch::query()->create(['business_id' => $business->id, 'name' => 'Main Branch', 'code' => 'MAIN']);
    $user->ownedBusinesses()->attach($business->id, ['role' => 'owner']);

    Subscription::query()->create([
        'business_id' => $business->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Trialing,
        'starts_at' => now(),
        'ends_at' => now()->addDays(14),
    ]);

    return [$business, $branch];
}

it('renders empty states when no synchronized dashboard summaries exist', function (): void {
    $user = overviewDashboardUser();
    overviewDashboardBusiness($user);

    $this
        ->actingAs($user)
        ->get(route('platform.dashboard'))
        ->assertOk()
        ->assertSee('No synchronized dashboard summaries yet')
        ->assertSee('Today gross sales')
        ->assertSee('NGN 0.00')
        ->assertSee('No sales trend yet');
});

it('renders synchronized dashboard summary metrics and sections without fabricated values', function (): void {
    Cache::flush();

    $user = overviewDashboardUser();
    [$business, $branch] = overviewDashboardBusiness($user);
    $foreignUser = overviewDashboardUser();
    [$foreignBusiness, $foreignBranch] = overviewDashboardBusiness($foreignUser, 'Foreign Stores');

    BusinessDashboardDailySummary::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'summary_date' => today(),
        'gross_sales_minor' => 1500000,
        'net_sales_minor' => 1200000,
        'completed_sales_count' => 12,
        'cash_payments_minor' => 500000,
        'bank_transfer_payments_minor' => 300000,
        'pos_terminal_payments_minor' => 250000,
        'credit_sales_minor' => 150000,
        'expenses_minor' => 100000,
        'gross_profit_minor' => 450000,
        'estimated_net_profit_minor' => 350000,
        'customer_debt_minor' => 700000,
        'supplier_balance_minor' => 400000,
        'current_stock_value_minor' => 2500000,
        'low_stock_products_count' => 3,
        'cashier_variance_minor' => -5000,
        'last_successful_sync_at' => now(),
        'summarized_at' => now(),
    ]);

    BusinessDashboardDailySummary::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'summary_date' => today()->subDay(),
        'net_sales_minor' => 900000,
        'last_successful_sync_at' => now()->subDay(),
        'summarized_at' => now()->subDay(),
    ]);

    BusinessDashboardDailySummary::query()->create([
        'business_id' => $foreignBusiness->id,
        'branch_id' => $foreignBranch->id,
        'summary_date' => today(),
        'net_sales_minor' => 99999999,
        'last_successful_sync_at' => now(),
    ]);

    DashboardProductSummary::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'summary_date' => today(),
        'product_uuid' => (string) Str::uuid(),
        'product_name' => 'Golden Rice',
        'sku' => 'RICE-01',
        'quantity_sold' => 8,
        'sales_minor' => 800000,
        'days_without_sale' => 0,
        'is_low_stock' => true,
        'stock_quantity' => 2,
    ]);

    DashboardProductSummary::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'summary_date' => today(),
        'product_uuid' => (string) Str::uuid(),
        'product_name' => 'Slow Beans',
        'quantity_sold' => 0,
        'sales_minor' => 0,
        'days_without_sale' => 30,
        'stock_quantity' => 20,
    ]);

    DashboardBranchSummary::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'summary_date' => today(),
        'gross_sales_minor' => 1500000,
        'net_sales_minor' => 1200000,
        'completed_sales_count' => 12,
        'last_successful_sync_at' => now(),
    ]);

    DashboardCashierException::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'summary_date' => today(),
        'cashier_uuid' => (string) Str::uuid(),
        'cashier_name' => 'Ada Cashier',
        'variance_minor' => -5000,
        'status' => 'open',
    ]);

    DashboardBusinessActivity::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'type' => 'security_alert',
        'title' => 'Manager approval required',
        'occurred_at' => now(),
    ]);

    $this
        ->actingAs($user)
        ->get(route('platform.dashboard'))
        ->assertOk()
        ->assertSee('NGN 15,000.00')
        ->assertSee('NGN 12,000.00')
        ->assertSee('Completed sales')
        ->assertSee('12')
        ->assertSee('Golden Rice')
        ->assertSee('Slow Beans')
        ->assertSee('Main Branch')
        ->assertSee('Ada Cashier')
        ->assertSee('Manager approval required')
        ->assertDontSee('999,999.99');
});

it('caches dashboard summary reads by business branch and date range', function (): void {
    Cache::flush();

    $user = overviewDashboardUser();
    [$business, $branch] = overviewDashboardBusiness($user);

    BusinessDashboardDailySummary::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'summary_date' => today(),
        'net_sales_minor' => 100000,
    ]);

    $service = app(BusinessOverviewDashboard::class);
    $first = $service->data($business->id, $branch->id, 'today');

    BusinessDashboardDailySummary::query()->where('business_id', $business->id)->update(['net_sales_minor' => 999000]);

    $second = $service->data($business->id, $branch->id, 'today');

    expect($first['metrics']['net_sales_minor'])->toBe(100000)
        ->and($second['metrics']['net_sales_minor'])->toBe(100000);
});
