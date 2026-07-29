<?php

use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Devices\Models\Device;
use App\Domain\Devices\Services\ShopAppActivationService;
use App\Domain\Expenses\Models\ReplicatedExpense;
use App\Domain\Sales\Models\ReplicatedSale;
use App\Domain\Synchronization\Models\SyncRecord;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Enums\BranchStatus;
use App\Enums\BusinessStatus;
use App\Enums\SubscriptionStatus;
use App\Livewire\Platform\Expenses\ExpenseMonitoringPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

uses(RefreshDatabase::class);

function expenseMonitoringFixture(array $features = ['Expenses', 'Reports']): array
{
    $user = User::factory()->create();
    $plan = Plan::query()->create([
        'name' => 'Expense Plan',
        'slug' => 'expense-plan-'.Str::random(6),
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

    $business = Business::query()->create(['name' => 'Expense Stores', 'status' => BusinessStatus::Active]);
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

function replicatedExpenseFixture(Business $business, Branch $branch, array $overrides = []): ReplicatedExpense
{
    $uuid = $overrides['uuid'] ?? (string) Str::uuid();

    return ReplicatedExpense::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'uuid' => $uuid,
        'expense_number' => $overrides['expense_number'] ?? 'EXP-001',
        'category_uuid' => $overrides['category_uuid'] ?? (string) Str::uuid(),
        'category_name' => $overrides['category_name'] ?? 'Fuel',
        'description' => $overrides['description'] ?? 'Generator fuel',
        'amount_minor' => $overrides['amount_minor'] ?? 45000,
        'payment_method' => $overrides['payment_method'] ?? 'cash',
        'creator_uuid' => $overrides['creator_uuid'] ?? (string) Str::uuid(),
        'creator_name' => $overrides['creator_name'] ?? 'Ada Manager',
        'approver_uuid' => $overrides['approver_uuid'] ?? (string) Str::uuid(),
        'approver_name' => $overrides['approver_name'] ?? 'Owner User',
        'status' => $overrides['status'] ?? 'approved',
        'expense_date' => $overrides['expense_date'] ?? now(),
        'receipt_attachment_path' => $overrides['receipt_attachment_path'] ?? 'receipts/exp-001.jpg',
        'is_recurring' => $overrides['is_recurring'] ?? false,
        'recurring_period' => $overrides['recurring_period'] ?? null,
        'source_version' => 1,
        'source_idempotency_key' => $overrides['source_idempotency_key'] ?? 'expense-idem-'.$uuid,
        'source_occurred_at' => now(),
    ]);
}

function replicatedExpenseSaleFixture(Business $business, Branch $branch, int $totalMinor = 300000): void
{
    $saleUuid = (string) Str::uuid();

    ReplicatedSale::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'uuid' => $saleUuid,
        'sale_number' => 'EXP-SALE-001',
        'transaction_number' => 'EXP-TXN-001',
        'cashier_name' => 'Ada Cashier',
        'customer_name' => 'Walk In',
        'sale_type' => 'retail',
        'status' => 'completed',
        'payment_status' => 'paid',
        'sold_at' => now(),
        'total_minor' => $totalMinor,
        'cost_minor' => 200000,
        'gross_profit_minor' => $totalMinor - 200000,
        'source_version' => 1,
        'source_idempotency_key' => 'expense-sale-'.$saleUuid,
        'source_occurred_at' => now(),
    ]);
}

function signedExpenseSyncRequest(TestCase $test, string $credential, array $payload): TestResponse
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

it('stores replicated expenses from synchronized expense records', function (): void {
    Queue::fake();
    [, $business, $branch] = expenseMonitoringFixture();
    $deviceUser = User::factory()->create();
    $activation = app(ShopAppActivationService::class)->createActivation($business, $branch, $deviceUser, [
        'device_label' => 'Expense Shop App',
        'device_type' => 'shop_app',
        'expires_at' => now()->addMinutes(30),
        'application_version_id' => null,
    ]);
    $credential = app(ShopAppActivationService::class)->activate([
        'activation_code' => $activation['code'],
        'device_identity' => 'expense-machine',
        'app_version' => '1.0.0',
    ])['credential'];
    $device = Device::query()->where('business_id', $business->id)->firstOrFail();
    $expenseUuid = (string) Str::uuid();

    signedExpenseSyncRequest($this, $credential, [
        'protocol_version' => '1.0',
        'batch_uuid' => (string) Str::uuid(),
        'business_uuid' => $business->uuid,
        'branch_uuid' => $branch->uuid,
        'device_uuid' => $device->uuid,
        'created_at' => now()->toISOString(),
        'records' => [[
            'entity' => 'expense',
            'record_uuid' => $expenseUuid,
            'operation' => 'create',
            'version' => 1,
            'idempotency_key' => 'expense-'.$expenseUuid,
            'occurred_at' => now()->toISOString(),
            'payload' => [
                'expense_number' => 'EXP-SYNC-001',
                'category_name' => 'Fuel',
                'description' => 'Generator fuel',
                'amount_minor' => 45000,
                'payment_method' => 'cash',
                'creator_name' => 'Sync Manager',
                'approver_name' => 'Owner User',
                'status' => 'approved',
                'expense_date' => now()->toISOString(),
                'receipt_attachment_path' => 'receipts/exp-sync-001.jpg',
                'is_recurring' => true,
                'recurring_period' => 'monthly',
            ],
        ]],
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'accepted');

    expect(ReplicatedExpense::query()->where('uuid', $expenseUuid)->where('expense_number', 'EXP-SYNC-001')->exists())->toBeTrue()
        ->and(SyncRecord::query()->where('record_uuid', $expenseUuid)->exists())->toBeTrue();
});

it('renders expense monitoring reports details branch comparison and read-only records', function (): void {
    [$user, $business, $branch] = expenseMonitoringFixture();
    $expense = replicatedExpenseFixture($business, $branch);
    replicatedExpenseFixture($business, $branch, [
        'expense_number' => 'EXP-PENDING',
        'status' => 'pending',
        'category_name' => 'Repairs',
        'creator_name' => 'Musa Storekeeper',
        'amount_minor' => 25000,
    ]);
    replicatedExpenseFixture($business, $branch, [
        'expense_number' => 'EXP-REJECTED',
        'status' => 'rejected',
        'category_name' => 'Transport',
        'amount_minor' => 15000,
    ]);
    replicatedExpenseFixture($business, $branch, [
        'expense_number' => 'EXP-RECUR',
        'category_name' => 'Rent',
        'is_recurring' => true,
        'recurring_period' => 'monthly',
        'amount_minor' => 100000,
    ]);
    replicatedExpenseSaleFixture($business, $branch);

    $this->actingAs($user)
        ->withSession(['platform.business_id' => $business->id, 'platform.branch_id' => $branch->id])
        ->get(route('platform.expenses'))
        ->assertOk()
        ->assertSee('Expense monitoring')
        ->assertSee('EXP-001')
        ->assertSee('Expenses by category')
        ->assertSee('Expense-to-sales comparison')
        ->assertDontSee('Edit expense')
        ->assertDontSee('Approve expense');

    Livewire::actingAs($user)->withQueryParams(['view' => 'category'])->test(ExpenseMonitoringPage::class)->assertSee('Fuel');
    Livewire::actingAs($user)->withQueryParams(['view' => 'branch'])->test(ExpenseMonitoringPage::class)->assertSee('Main Branch');
    Livewire::actingAs($user)->withQueryParams(['view' => 'creator'])->test(ExpenseMonitoringPage::class)->assertSee('Ada Manager');
    Livewire::actingAs($user)->withQueryParams(['view' => 'approved'])->test(ExpenseMonitoringPage::class)->assertSee('EXP-001')->assertDontSee('EXP-PENDING');
    Livewire::actingAs($user)->withQueryParams(['view' => 'pending_rejected'])->test(ExpenseMonitoringPage::class)->assertSee('EXP-PENDING')->assertSee('EXP-REJECTED');
    Livewire::actingAs($user)->withQueryParams(['view' => 'recurring'])->test(ExpenseMonitoringPage::class)->assertSee('Monthly');
    Livewire::actingAs($user)->withQueryParams(['view' => 'trend'])->test(ExpenseMonitoringPage::class)->assertSee('Expense trend');
    Livewire::actingAs($user)->withQueryParams(['view' => 'comparison'])->test(ExpenseMonitoringPage::class)->assertSee('Expense-to-sales comparison')->assertSee('Sales');

    Livewire::actingAs($user)
        ->withQueryParams(['view' => 'details', 'expenseNumber' => $expense->uuid])
        ->test(ExpenseMonitoringPage::class)
        ->assertSee('Expense details')
        ->assertSee('EXP-001')
        ->assertSee('Generator fuel')
        ->assertSee('Receipt attachment')
        ->assertSee('Source device')
        ->assertSee('Synchronization timestamp');
});

it('filters expenses by date category and branch and provides exports', function (): void {
    [$user, $business, $branch] = expenseMonitoringFixture();
    replicatedExpenseFixture($business, $branch, ['expense_number' => 'VISIBLE-FUEL', 'category_name' => 'Fuel', 'expense_date' => now()]);
    replicatedExpenseFixture($business, $branch, ['expense_number' => 'HIDDEN-OLD', 'category_name' => 'Fuel', 'expense_date' => now()->subMonths(2)]);
    replicatedExpenseFixture($business, $branch, ['expense_number' => 'HIDDEN-RENT', 'category_name' => 'Rent', 'expense_date' => now()]);

    Livewire::actingAs($user)
        ->withQueryParams(['category' => 'Fuel', 'from' => now()->startOfMonth()->toDateString(), 'to' => now()->toDateString()])
        ->test(ExpenseMonitoringPage::class)
        ->assertSee('VISIBLE-FUEL')
        ->assertDontSee('HIDDEN-OLD')
        ->assertDontSee('HIDDEN-RENT');

    Livewire::actingAs($user)
        ->test(ExpenseMonitoringPage::class)
        ->call('exportCsv')
        ->assertFileDownloaded();

    Livewire::actingAs($user)
        ->test(ExpenseMonitoringPage::class)
        ->call('exportPdfFoundation')
        ->assertFileDownloaded();
});

it('enforces expense permissions and tenant isolation', function (): void {
    [$deniedUser, $deniedBusiness, $deniedBranch] = expenseMonitoringFixture(['Sales']);
    replicatedExpenseFixture($deniedBusiness, $deniedBranch, ['expense_number' => 'DENIED-EXP']);

    $this->actingAs($deniedUser)
        ->withSession(['platform.business_id' => $deniedBusiness->id, 'platform.branch_id' => $deniedBranch->id])
        ->get(route('platform.expenses'))
        ->assertOk()
        ->assertSee('Expense monitoring is not available')
        ->assertDontSee('DENIED-EXP');

    [$user, $business, $branch] = expenseMonitoringFixture();
    replicatedExpenseFixture($business, $branch, ['expense_number' => 'VISIBLE-EXP']);
    $otherBusiness = Business::query()->create(['name' => 'Other Expense Business', 'status' => BusinessStatus::Active]);
    $otherBranch = Branch::query()->create(['business_id' => $otherBusiness->id, 'name' => 'Other Branch', 'status' => BranchStatus::Active]);
    replicatedExpenseFixture($otherBusiness, $otherBranch, ['expense_number' => 'HIDDEN-EXP']);

    $this->actingAs($user)
        ->withSession(['platform.business_id' => $business->id, 'platform.branch_id' => $branch->id])
        ->get(route('platform.expenses'))
        ->assertOk()
        ->assertSee('VISIBLE-EXP')
        ->assertDontSee('HIDDEN-EXP');
});
