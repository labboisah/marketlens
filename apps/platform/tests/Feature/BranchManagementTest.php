<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Devices\Models\Device;
use App\Domain\Devices\Services\ShopAppActivationService;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Domain\Tenancy\Services\BranchManagement;
use App\Enums\AccountStatus;
use App\Enums\BranchStatus;
use App\Enums\SubscriptionStatus;
use App\Livewire\Platform\Branches\BranchManagementPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function branchManagementUser(): User
{
    return User::factory()->create([
        'account_status' => AccountStatus::Active,
        'email_verified_at' => now(),
    ]);
}

function branchManagementPlan(array $features = ['One branch']): Plan
{
    $plan = Plan::query()->create([
        'name' => 'Branch Plan',
        'slug' => 'branch-plan',
        'billing_interval' => 'monthly',
        'price_minor' => 10000,
        'currency' => 'NGN',
        'trial_days' => 14,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    foreach ($features as $index => $feature) {
        $plan->features()->create(['name' => $feature, 'sort_order' => $index + 1]);
    }

    return $plan;
}

function branchManagementBusiness(User $user, ?Plan $plan = null): Business
{
    $business = Business::query()->create(['name' => 'Ada Stores']);
    $user->ownedBusinesses()->attach($business->id, ['role' => 'owner']);

    Subscription::query()->create([
        'business_id' => $business->id,
        'plan_id' => ($plan ?? branchManagementPlan())->id,
        'status' => SubscriptionStatus::Trialing,
        'starts_at' => now(),
        'ends_at' => now()->addDays(14),
    ]);

    return $business;
}

it('renders branch listing creation form and details for business owners', function (): void {
    $user = branchManagementUser();
    $business = branchManagementBusiness($user);
    Branch::query()->create([
        'business_id' => $business->id,
        'name' => 'Main Branch',
        'code' => 'MAIN',
        'is_head_office' => true,
        'status' => BranchStatus::Active,
        'last_synchronized_at' => now(),
    ]);

    $this
        ->actingAs($user)
        ->get(route('platform.branches'))
        ->assertOk()
        ->assertSee('Branches')
        ->assertSee('Main Branch')
        ->assertSee('Head office')
        ->assertSee('Device activation');
});

it('creates branches with unique codes and records audit logs', function (): void {
    $user = branchManagementUser();
    $business = branchManagementBusiness($user, branchManagementPlan(['Multiple branches']));

    $this->withSession(['platform.business_id' => $business->id]);

    Livewire::actingAs($user)
        ->test(BranchManagementPage::class)
        ->set('name', 'Lekki Branch')
        ->set('code', 'lekki')
        ->set('phone', '+2348000000000')
        ->call('createBranch')
        ->assertHasNoErrors();

    $branch = Branch::query()->where('code', 'LEKKI')->firstOrFail();

    expect($branch->business_id)->toBe($business->id)
        ->and($branch->status)->toBe(BranchStatus::Active)
        ->and(AuditLog::query()->where('action', 'branch.created')->where('branch_id', $branch->id)->exists())->toBeTrue();
});

it('enforces branch subscription limits', function (): void {
    $user = branchManagementUser();
    $business = branchManagementBusiness($user, branchManagementPlan(['One branch']));
    Branch::query()->create([
        'business_id' => $business->id,
        'name' => 'Main Branch',
        'code' => 'MAIN',
        'is_head_office' => true,
        'status' => BranchStatus::Active,
    ]);

    app(BranchManagement::class)->create($business, $user, [
        'name' => 'Second Branch',
        'code' => 'SECOND',
        'is_head_office' => false,
    ]);
})->throws(ValidationException::class);

it('enforces branch code uniqueness within a business', function (): void {
    $user = branchManagementUser();
    $business = branchManagementBusiness($user, branchManagementPlan(['Multiple branches']));
    Branch::query()->create([
        'business_id' => $business->id,
        'name' => 'Main Branch',
        'code' => 'MAIN',
        'is_head_office' => true,
        'status' => BranchStatus::Active,
    ]);

    app(BranchManagement::class)->create($business, $user, [
        'name' => 'Duplicate Main',
        'code' => 'MAIN',
        'is_head_office' => false,
    ]);
})->throws(ValidationException::class);

it('keeps exactly one head office when another branch is designated', function (): void {
    $user = branchManagementUser();
    $business = branchManagementBusiness($user, branchManagementPlan(['Multiple branches']));
    $main = Branch::query()->create([
        'business_id' => $business->id,
        'name' => 'Main Branch',
        'code' => 'MAIN',
        'is_head_office' => true,
        'status' => BranchStatus::Active,
    ]);
    $second = Branch::query()->create([
        'business_id' => $business->id,
        'name' => 'Second Branch',
        'code' => 'SECOND',
        'is_head_office' => false,
        'status' => BranchStatus::Active,
    ]);

    app(BranchManagement::class)->designateHeadOffice($second, $user);

    expect($main->refresh()->is_head_office)->toBeFalse()
        ->and($second->refresh()->is_head_office)->toBeTrue()
        ->and($business->branches()->where('is_head_office', true)->count())->toBe(1);
});

it('suspends branches without deleting synchronized history', function (): void {
    $user = branchManagementUser();
    $business = branchManagementBusiness($user, branchManagementPlan(['Multiple branches']));
    $main = Branch::query()->create([
        'business_id' => $business->id,
        'name' => 'Main Branch',
        'code' => 'MAIN',
        'is_head_office' => true,
        'status' => BranchStatus::Active,
    ]);
    $branch = Branch::query()->create([
        'business_id' => $business->id,
        'name' => 'Second Branch',
        'code' => 'SECOND',
        'is_head_office' => false,
        'status' => BranchStatus::Active,
        'last_synchronized_at' => now(),
    ]);
    Device::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'name' => 'Shop App',
        'type' => 'shop_app',
    ]);

    app(BranchManagement::class)->suspend($branch, $user);

    expect($branch->refresh()->status)->toBe(BranchStatus::Suspended)
        ->and($branch->exists)->toBeTrue()
        ->and($main->refresh()->is_head_office)->toBeTrue()
        ->and(AuditLog::query()->where('action', 'branch.suspended')->where('branch_id', $branch->id)->exists())->toBeTrue();
});

it('blocks device activation for suspended branches', function (): void {
    $user = branchManagementUser();
    $business = branchManagementBusiness($user, branchManagementPlan(['More Client App devices']));
    $branch = Branch::query()->create([
        'business_id' => $business->id,
        'name' => 'Suspended Branch',
        'code' => 'SUSP',
        'is_head_office' => true,
        'status' => BranchStatus::Active,
    ]);
    $result = app(ShopAppActivationService::class)->createActivation($business, $branch, $user, [
        'device_label' => 'Shop App',
        'device_type' => 'shop_app',
        'expires_at' => now()->addMinutes(30),
        'application_version_id' => null,
    ]);
    $branch->forceFill(['status' => BranchStatus::Suspended])->save();

    $this
        ->withHeader('Accept', 'application/json')
        ->postJson(route('api.v1.device.activate'), [
            'activation_code' => $result['code'],
            'device_identity' => 'machine-guid-123',
            'app_version' => '1.0.0',
        ])
        ->assertUnprocessable();
});
