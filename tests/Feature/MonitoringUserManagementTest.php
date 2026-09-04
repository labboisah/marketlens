<?php

use App\Domain\Access\Models\BusinessMonitoringUser;
use App\Domain\Access\Models\MonitoringUserInvitation;
use App\Domain\Access\Models\MonitoringRole;
use App\Domain\Access\Services\MonitoringUserManagementService;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Enums\AccountStatus;
use App\Enums\BranchStatus;
use App\Enums\BusinessStatus;
use App\Enums\SubscriptionStatus;
use App\Livewire\Platform\MonitoringUsers\MonitoringUserManagementPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function monitoringUserFixture(): array
{
    app(MonitoringUserManagementService::class)->ensureDefaults();

    $owner = User::factory()->create([
        'account_status' => AccountStatus::Active,
        'email_verified_at' => now(),
    ]);
    $monitor = User::factory()->create([
        'name' => 'Remote Manager',
        'email' => 'remote.manager@example.test',
        'account_status' => AccountStatus::Active,
        'email_verified_at' => now(),
    ]);

    $plan = Plan::query()->create([
        'name' => 'Monitoring Plan',
        'slug' => 'monitoring-plan-'.Str::random(6),
        'billing_interval' => 'monthly',
        'price_minor' => 25000,
        'currency' => 'NGN',
        'trial_days' => 14,
        'sort_order' => 1,
        'is_active' => true,
    ]);
    foreach (['Sales', 'Reports', 'Inventory', 'Expenses', 'Security alerts', 'Remote dashboard'] as $index => $feature) {
        $plan->features()->create(['name' => $feature, 'sort_order' => $index + 1]);
    }

    $business = Business::query()->create(['name' => 'Monitoring Stores', 'status' => BusinessStatus::Active, 'onboarding_status' => 'completed']);
    $branch = Branch::query()->create(['business_id' => $business->id, 'name' => 'Main Branch', 'code' => 'MAIN', 'status' => BranchStatus::Active]);
    $otherBranch = Branch::query()->create(['business_id' => $business->id, 'name' => 'Second Branch', 'code' => 'SECOND', 'status' => BranchStatus::Active]);
    $owner->ownedBusinesses()->attach($business->id, ['role' => 'owner']);

    Subscription::query()->create([
        'business_id' => $business->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active,
        'starts_at' => now(),
        'ends_at' => now()->addMonth(),
    ]);

    return [$owner, $monitor, $business, $branch, $otherBranch];
}

it('renders monitoring user management with roles permissions and sidebar link for owners', function (): void {
    [$owner, $monitor, $business, $branch] = monitoringUserFixture();

    $this->actingAs($owner)
        ->withSession(['platform.business_id' => $business->id, 'platform.branch_id' => $branch->id])
        ->get(route('platform.monitoring-users'))
        ->assertOk()
        ->assertSee('Monitoring users')
        ->assertSee('Business Owner')
        ->assertSee('Remote Manager')
        ->assertSee('View dashboard')
        ->assertSee('Manage monitoring users');

    $this->actingAs($owner)
        ->withSession(['platform.business_id' => $business->id, 'platform.branch_id' => $branch->id])
        ->get(route('platform.dashboard'))
        ->assertOk()
        ->assertSee(route('platform.monitoring-users'));
});

it('invites existing platform users assigns role branch and module access and audits the action', function (): void {
    [$owner, $monitor, $business, $branch] = monitoringUserFixture();
    $role = MonitoringRole::query()->where('slug', 'accountant')->firstOrFail();

    Livewire::actingAs($owner)
        ->test(MonitoringUserManagementPage::class)
        ->set('email', $monitor->email)
        ->set('roleId', $role->id)
        ->set('branchIds', [$branch->id])
        ->set('modulePermissions.view-dashboard', true)
        ->set('modulePermissions.view-sales', true)
        ->set('expiresInDays', 7)
        ->call('invite')
        ->assertDispatched('notify');

    $assignment = BusinessMonitoringUser::query()->where('business_id', $business->id)->where('user_id', $monitor->id)->firstOrFail();

    expect(MonitoringUserInvitation::query()->where('business_id', $business->id)->where('email', $monitor->email)->where('status', 'pending')->exists())->toBeTrue()
        ->and($assignment->role->slug)->toBe('accountant')
        ->and($assignment->branches()->pluck('branches.id')->all())->toBe([$branch->id])
        ->and($monitor->refresh()->hasBusinessPermission($business, 'view-sales'))->toBeTrue()
        ->and($monitor->canAccessBranch($business->id, $branch->id))->toBeTrue()
        ->and(AuditLog::query()->where('business_id', $business->id)->where('action', 'monitoring_user.invited')->exists())->toBeTrue();
});

it('supports invitation resend cancellation suspension and access revocation', function (): void {
    [$owner, $monitor, $business, $branch] = monitoringUserFixture();
    $role = MonitoringRole::query()->where('slug', 'remote-manager')->firstOrFail();
    $invitation = app(MonitoringUserManagementService::class)->invite($business, $owner, ['email' => $monitor->email, 'role_id' => $role->id, 'expires_in_days' => 7], [], []);
    $assignment = BusinessMonitoringUser::query()->where('business_id', $business->id)->where('user_id', $monitor->id)->firstOrFail();

    Livewire::actingAs($owner)
        ->test(MonitoringUserManagementPage::class)
        ->call('resend', $invitation->id)
        ->assertDispatched('notify')
        ->call('cancel', $invitation->id)
        ->assertDispatched('notify')
        ->call('suspend', $assignment->id)
        ->assertDispatched('notify')
        ->call('revoke', $assignment->id)
        ->assertDispatched('notify');

    expect($invitation->refresh()->status)->toBe('cancelled')
        ->and($invitation->send_count)->toBe(2)
        ->and($assignment->refresh()->status)->toBe('revoked')
        ->and($monitor->refresh()->canAccessBusiness($business))->toBeFalse();
});

it('lets active monitoring users access assigned business context but hides unauthorized modules', function (): void {
    [$owner, $monitor, $business, $branch, $otherBranch] = monitoringUserFixture();
    $role = MonitoringRole::query()->where('slug', 'read-only-viewer')->firstOrFail();
    app(MonitoringUserManagementService::class)->grantAccess($business, $monitor, $role, $owner, [$branch->id], ['view-dashboard', 'view-sales']);

    $this->actingAs($monitor)
        ->withSession(['platform.business_id' => $business->id, 'platform.branch_id' => $branch->id])
        ->get(route('platform.dashboard'))
        ->assertOk()
        ->assertSee('Overview')
        ->assertSee(route('platform.sales'))
        ->assertDontSee(route('platform.settings'))
        ->assertDontSee(route('platform.monitoring-users'));

    $this->actingAs($monitor)
        ->withSession(['platform.business_id' => $business->id, 'platform.branch_id' => $branch->id])
        ->post(route('platform.context.update'), ['business_id' => $business->id, 'branch_id' => $otherBranch->id, 'date_range' => 'today'])
        ->assertForbidden();
});

it('keeps monitoring invitations and assignments tenant isolated', function (): void {
    [$owner, $monitor, $business, $branch] = monitoringUserFixture();
    $otherOwner = User::factory()->create(['account_status' => AccountStatus::Active, 'email_verified_at' => now()]);
    $otherBusiness = Business::query()->create(['name' => 'Hidden Business', 'status' => BusinessStatus::Active, 'onboarding_status' => 'completed']);
    $otherOwner->ownedBusinesses()->attach($otherBusiness->id, ['role' => 'owner']);
    $role = MonitoringRole::query()->where('slug', 'auditor')->firstOrFail();
    app(MonitoringUserManagementService::class)->invite($business, $owner, ['email' => $monitor->email, 'role_id' => $role->id, 'expires_in_days' => 7], [$branch->id], ['view-dashboard']);

    Livewire::actingAs($otherOwner)
        ->test(MonitoringUserManagementPage::class)
        ->assertDontSee($monitor->email);
});
