<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Modules\Services\ModuleAccessService;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Domain\Tenancy\Services\BranchManagement;
use App\Enums\AccountStatus;
use App\Enums\BranchStatus;
use App\Enums\BusinessModule;
use App\Enums\SubscriptionStatus;
use App\Livewire\Platform\Branches\BranchManagementPage;
use App\Livewire\Platform\Settings\BusinessSettingsPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function moduleAccessFixture(bool $planAllows = true, bool $businessEnabled = true, string $branchStatus = 'inherit'): array
{
    $user = User::factory()->create([
        'account_status' => AccountStatus::Active,
        'email_verified_at' => now(),
    ]);

    $plan = Plan::query()->create([
        'name' => 'Module Plan',
        'slug' => 'module-plan-'.str()->random(6),
        'billing_interval' => 'monthly',
        'price_minor' => 10000,
        'currency' => 'NGN',
        'trial_days' => 14,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    if ($planAllows) {
        $plan->features()->create(['key' => 'module:services', 'name' => 'Services and Revenue Sharing']);
    }

    $business = Business::query()->create([
        'name' => 'Module Stores',
        'onboarding_status' => 'completed',
        'module_settings' => [
            'services' => ['enabled' => $businessEnabled],
            'shareholders' => ['enabled' => false],
        ],
    ]);

    $branch = Branch::query()->create([
        'business_id' => $business->id,
        'name' => 'Main',
        'status' => BranchStatus::Active,
        'is_head_office' => true,
        'module_settings' => [
            'services' => ['status' => $branchStatus],
        ],
    ]);

    $user->ownedBusinesses()->attach($business->id, ['role' => 'owner']);

    Subscription::query()->create([
        'business_id' => $business->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active,
        'starts_at' => now(),
        'ends_at' => now()->addMonth(),
    ]);

    return [$user, $business->refresh(), $branch->refresh(), $plan];
}

it('resolves module access from plan business and branch gates', function (bool $planAllows, bool $businessEnabled, string $branchStatus, bool $expected): void {
    [, $business, $branch] = moduleAccessFixture($planAllows, $businessEnabled, $branchStatus);

    expect(app(ModuleAccessService::class)->available($business, BusinessModule::Services, $branch))->toBe($expected);
})->with([
    'all gates pass through inherit' => [true, true, 'inherit', true],
    'plan blocks' => [false, true, 'inherit', false],
    'business blocks' => [true, false, 'inherit', false],
    'branch disables' => [true, true, 'disabled', false],
    'branch enables business-enabled module' => [true, true, 'enabled', true],
]);

it('rejects branch enabled overrides when business or plan blocks the module', function (): void {
    [$user, $business, $branch] = moduleAccessFixture(planAllows: true, businessEnabled: false);

    app(BranchManagement::class)->updateModuleSettings($branch, $user, [
        'services' => ['status' => 'enabled'],
    ]);
})->throws(ValidationException::class);

it('saves business module settings with audit logging and percentage validation', function (): void {
    [$user, $business, $branch] = moduleAccessFixture();

    $this->withSession(['platform.business_id' => $business->id, 'platform.branch_id' => $branch->id]);

    Livewire::actingAs($user)
        ->test(BusinessSettingsPage::class)
        ->set('moduleSettings.services.enabled', true)
        ->set('moduleSettings.services.default_calculation_basis', 'gross_amount')
        ->set('moduleSettings.services.staff_percentage', 40)
        ->set('moduleSettings.services.business_percentage', 50)
        ->set('moduleSettings.services.expense_reserve_percentage', 10)
        ->set('moduleSettings.shareholders.enabled', false)
        ->call('saveModules')
        ->assertHasNoErrors()
        ->assertDispatched('notify');

    expect($business->refresh()->module_settings['services']['staff_percentage'])->toBe(40)
        ->and(AuditLog::query()->where('business_id', $business->id)->where('action', 'business_settings.modules_updated')->exists())->toBeTrue();
});

it('blocks direct web routes for unavailable modules', function (): void {
    [$user, $business, $branch] = moduleAccessFixture(planAllows: false);

    $this->actingAs($user)
        ->withSession(['platform.business_id' => $business->id, 'platform.branch_id' => $branch->id])
        ->get(route('platform.services'))
        ->assertRedirect(route('platform.permission-denied'));
});

it('returns standard module unavailable responses for monitoring api access', function (): void {
    [$user, $business, $branch] = moduleAccessFixture(planAllows: false);
    Sanctum::actingAs($user);

    $this->getJson(route('api.v1.monitoring.services', [
        'business_uuid' => $business->uuid,
        'branch_uuid' => $branch->uuid,
    ]))
        ->assertForbidden()
        ->assertJsonPath('code', 'MODULE_UNAVAILABLE')
        ->assertJsonPath('meta.module_access.module', 'services');
});

it('saves branch module inheritance settings when gates allow it', function (): void {
    [$user, $business, $branch] = moduleAccessFixture();

    $this->withSession(['platform.business_id' => $business->id, 'platform.branch_id' => $branch->id]);

    Livewire::actingAs($user)
        ->test(BranchManagementPage::class)
        ->set('selectedBranchId', $branch->id)
        ->set('moduleSettings.services.status', 'disabled')
        ->set('moduleSettings.services.settlement_frequency', 'weekly')
        ->set('moduleSettings.shareholders.status', 'inherit')
        ->set('moduleSettings.shareholders.settlement_frequency', 'monthly')
        ->call('saveModuleSettings')
        ->assertHasNoErrors()
        ->assertDispatched('notify');

    expect($branch->refresh()->module_settings['services']['status'])->toBe('disabled')
        ->and(AuditLog::query()->where('branch_id', $branch->id)->where('action', 'branch.modules_updated')->exists())->toBeTrue();
});
