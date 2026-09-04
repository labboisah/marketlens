<?php

use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Reporting\Jobs\GenerateReportExport;
use App\Domain\Reporting\Models\ReportExport;
use App\Domain\Reporting\Models\ScheduledReport;
use App\Domain\Reporting\Services\ReportCentreService;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Enums\AccountStatus;
use App\Enums\BranchStatus;
use App\Enums\BusinessStatus;
use App\Enums\SubscriptionStatus;
use App\Livewire\Platform\Reports\ReportCentrePage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function reportCentreFixture(array $features = ['Reports', 'Remote dashboard']): array
{
    $user = User::factory()->create([
        'account_status' => AccountStatus::Active,
        'email_verified_at' => now(),
    ]);
    $plan = Plan::query()->create([
        'name' => 'Reports Plan',
        'slug' => 'reports-plan-'.Str::random(6),
        'billing_interval' => 'monthly',
        'price_minor' => 35000,
        'currency' => 'NGN',
        'trial_days' => 14,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    foreach ($features as $index => $feature) {
        $plan->features()->create(['name' => $feature, 'sort_order' => $index + 1]);
    }

    $business = Business::query()->create([
        'name' => 'Report Stores',
        'status' => BusinessStatus::Active,
        'onboarding_status' => 'completed',
    ]);
    $branch = Branch::query()->create([
        'business_id' => $business->id,
        'name' => 'Main Branch',
        'code' => 'MAIN',
        'status' => BranchStatus::Active,
        'is_head_office' => true,
    ]);
    $user->ownedBusinesses()->attach($business->id, ['role' => 'owner']);

    Subscription::query()->create([
        'business_id' => $business->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active,
        'starts_at' => now(),
        'ends_at' => now()->addMonth(),
    ]);

    DB::table('business_dashboard_daily_summaries')->insert([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'summary_date' => now()->toDateString(),
        'gross_sales_minor' => 500000,
        'net_sales_minor' => 470000,
        'completed_sales_count' => 18,
        'cash_payments_minor' => 200000,
        'bank_transfer_payments_minor' => 170000,
        'pos_terminal_payments_minor' => 100000,
        'credit_sales_minor' => 0,
        'expenses_minor' => 20000,
        'gross_profit_minor' => 150000,
        'estimated_net_profit_minor' => 130000,
        'customer_debt_minor' => 50000,
        'supplier_balance_minor' => 80000,
        'current_stock_value_minor' => 1200000,
        'low_stock_products_count' => 3,
        'cashier_variance_minor' => -2000,
        'last_successful_sync_at' => now(),
        'summarized_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('replicated_inventory_stocks')->insert([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'uuid' => (string) Str::uuid(),
        'product_uuid' => (string) Str::uuid(),
        'product_name' => 'Rice 50kg',
        'sku' => 'RICE-50',
        'warehouse_uuid' => (string) Str::uuid(),
        'warehouse_name' => 'Main Warehouse',
        'available_quantity' => 2,
        'reserved_quantity' => 0,
        'stock_value_minor' => 90000,
        'reorder_level' => 5,
        'stock_status' => 'low',
        'last_synchronized_at' => now(),
        'source_version' => 1,
        'source_idempotency_key' => 'stock-report-rice',
        'source_occurred_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return [$user, $business, $branch];
}

it('renders consolidated report groups filters preview and sidebar link', function (): void {
    [$user, $business, $branch] = reportCentreFixture();

    $this->actingAs($user)
        ->withSession(['platform.business_id' => $business->id, 'platform.branch_id' => $branch->id])
        ->get(route('platform.reports'))
        ->assertOk()
        ->assertSee('Reports and exports')
        ->assertSee('Daily sales')
        ->assertSee('Sales by branch')
        ->assertSee('Current stock')
        ->assertSee('Synchronization health')
        ->assertSee('Report history')
        ->assertSee('500000');

    $this->actingAs($user)
        ->withSession(['platform.business_id' => $business->id, 'platform.branch_id' => $branch->id])
        ->get(route('platform.dashboard'))
        ->assertOk()
        ->assertSee(route('platform.reports'));
});

it('queues csv and pdf report exports and records history', function (): void {
    Queue::fake();
    [$user, $business] = reportCentreFixture();

    Livewire::actingAs($user)
        ->test(ReportCentrePage::class)
        ->set('report', 'daily_sales')
        ->set('format', 'csv')
        ->call('queueExport')
        ->assertDispatched('notify')
        ->set('format', 'pdf')
        ->call('queueExport')
        ->assertDispatched('notify');

    Queue::assertPushed(GenerateReportExport::class, 2);

    expect(ReportExport::query()->where('business_id', $business->id)->where('format', 'csv')->exists())->toBeTrue()
        ->and(ReportExport::query()->where('business_id', $business->id)->where('format', 'pdf')->exists())->toBeTrue();
});

it('generates completed exports with secure expiring download links', function (): void {
    [$user, $business] = reportCentreFixture();
    $export = app(ReportCentreService::class)->queueExport($business, $user, 'daily_sales', 'csv', [
        'from' => now()->startOfMonth()->toDateString(),
        'to' => now()->toDateString(),
    ]);

    app(GenerateReportExport::class, ['reportExportId' => $export->id])->handle(app(ReportCentreService::class));
    $export->refresh();

    expect($export->status)->toBe('completed')
        ->and($export->content)->toContain('gross_sales_minor')
        ->and($export->rows_count)->toBe(1)
        ->and(app(ReportCentreService::class)->signedDownloadUrl($export))->not->toBeNull();

    $this->actingAs($user)
        ->get(app(ReportCentreService::class)->signedDownloadUrl($export))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
});

it('rejects unsigned expired or cross-tenant report downloads', function (): void {
    [$user, $business] = reportCentreFixture();
    [$otherUser, $otherBusiness, $otherBranch] = reportCentreFixture();
    $export = ReportExport::query()->create([
        'business_id' => $business->id,
        'requested_by' => $user->id,
        'report_key' => 'daily_sales',
        'report_group' => 'sales',
        'format' => 'csv',
        'status' => 'completed',
        'content' => "a,b\n1,2\n",
        'rows_count' => 1,
        'queued_at' => now(),
        'completed_at' => now(),
        'expires_at' => now()->addHour(),
    ]);

    $this->actingAs($user)
        ->get(route('platform.reports.download', ['export' => $export->uuid]))
        ->assertForbidden();

    $this->actingAs($otherUser)
        ->withSession(['platform.business_id' => $otherBusiness->id, 'platform.branch_id' => $otherBranch->id])
        ->get(URL::temporarySignedRoute('platform.reports.download', now()->addHour(), ['export' => $export->uuid]))
        ->assertForbidden();

    $export->forceFill(['expires_at' => now()->subMinute()])->save();

    $this->actingAs($user)
        ->get(URL::temporarySignedRoute('platform.reports.download', now()->addHour(), ['export' => $export->uuid]))
        ->assertStatus(410);
});

it('creates scheduled-report foundation records', function (): void {
    [$user, $business] = reportCentreFixture();

    Livewire::actingAs($user)
        ->test(ReportCentrePage::class)
        ->set('report', 'low_stock')
        ->set('format', 'csv')
        ->set('scheduleFrequency', 'weekly')
        ->call('createSchedule')
        ->assertDispatched('notify');

    expect(ScheduledReport::query()->where('business_id', $business->id)->where('report_key', 'low_stock')->where('frequency', 'weekly')->exists())->toBeTrue();
});

it('keeps report previews and history tenant isolated', function (): void {
    [$user, $business] = reportCentreFixture();
    $hiddenBusiness = Business::query()->create(['name' => 'Hidden Report Business', 'status' => BusinessStatus::Active]);

    DB::table('business_dashboard_daily_summaries')->insert([
        'business_id' => $hiddenBusiness->id,
        'branch_id' => null,
        'summary_date' => now()->toDateString(),
        'gross_sales_minor' => 999999,
        'net_sales_minor' => 999999,
        'completed_sales_count' => 99,
        'cash_payments_minor' => 0,
        'bank_transfer_payments_minor' => 0,
        'pos_terminal_payments_minor' => 0,
        'credit_sales_minor' => 0,
        'expenses_minor' => 0,
        'gross_profit_minor' => 0,
        'estimated_net_profit_minor' => 0,
        'customer_debt_minor' => 0,
        'supplier_balance_minor' => 0,
        'current_stock_value_minor' => 0,
        'low_stock_products_count' => 0,
        'cashier_variance_minor' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Livewire::actingAs($user)
        ->test(ReportCentrePage::class)
        ->assertSee('500000')
        ->assertDontSee('999999');

    expect(app(ReportCentreService::class)->previewRows($business->id, 'daily_sales', [])->pluck('gross_sales_minor')->all())->not->toContain(999999);
});

it('can preview every registered report definition with shared filters', function (): void {
    [$user, $business, $branch] = reportCentreFixture();
    $reports = app(ReportCentreService::class);

    foreach ($reports->groups() as $groupReports) {
        foreach (array_keys($groupReports) as $reportKey) {
            $rows = $reports->previewRows($business->id, $reportKey, [
                'from' => now()->startOfMonth()->toDateString(),
                'to' => now()->toDateString(),
                'branch_id' => $branch->id,
            ]);

            expect($rows)->toBeInstanceOf(\Illuminate\Support\Collection::class);
        }
    }
});

it('generates pdf exports as downloadable pdf files', function (): void {
    [$user, $business] = reportCentreFixture();
    $export = app(ReportCentreService::class)->queueExport($business, $user, 'daily_sales', 'pdf', [
        'from' => now()->startOfMonth()->toDateString(),
        'to' => now()->toDateString(),
    ]);

    app(GenerateReportExport::class, ['reportExportId' => $export->id])->handle(app(ReportCentreService::class));
    $export->refresh();

    expect($export->status)->toBe('completed')
        ->and($export->content)->toStartWith('%PDF-1.4')
        ->and($export->rows_count)->toBe(1);

    $this->actingAs($user)
        ->get(app(ReportCentreService::class)->signedDownloadUrl($export))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});
