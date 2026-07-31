<?php

use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Devices\Models\Device;
use App\Domain\Notifications\Models\PlatformNotification;
use App\Domain\SuperAdmin\Models\SuperAdminActionConfirmation;
use App\Domain\Support\Models\SupportTicket;
use App\Domain\Synchronization\Models\SyncBatch;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Enums\AccountStatus;
use App\Enums\BranchStatus;
use App\Enums\BusinessStatus;
use App\Enums\DeviceStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\SupportTicketStatus;
use App\Enums\SyncBatchStatus;
use App\Livewire\SuperAdmin\SuperAdminDashboardPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function superAdminFixture(): array
{
    $owner = User::factory()->create(['account_status' => AccountStatus::Active, 'email_verified_at' => now()]);
    $superAdmin = User::factory()->create(['account_status' => AccountStatus::Active, 'email_verified_at' => now(), 'is_super_admin' => true]);

    $plan = Plan::query()->create([
        'name' => 'Operator Plan',
        'slug' => 'operator-plan-'.Str::random(6),
        'billing_interval' => 'monthly',
        'price_minor' => 30000,
        'currency' => 'NGN',
        'trial_days' => 14,
        'sort_order' => 1,
        'is_active' => true,
    ]);
    $plan->features()->create(['name' => 'Reports', 'sort_order' => 1]);

    $business = Business::query()->create(['name' => 'Operator Stores', 'status' => BusinessStatus::Active, 'onboarding_status' => 'completed']);
    $suspendedBusiness = Business::query()->create(['name' => 'Suspended Stores', 'status' => BusinessStatus::Suspended, 'onboarding_status' => 'completed']);
    $branch = Branch::query()->create(['business_id' => $business->id, 'name' => 'Main Branch', 'status' => BranchStatus::Active]);
    $owner->ownedBusinesses()->attach($business->id, ['role' => 'owner']);

    Subscription::query()->create(['business_id' => $business->id, 'plan_id' => $plan->id, 'status' => SubscriptionStatus::Active, 'starts_at' => now(), 'ends_at' => now()->addMonth()]);
    Subscription::query()->create(['business_id' => $suspendedBusiness->id, 'plan_id' => $plan->id, 'status' => SubscriptionStatus::Trialing, 'starts_at' => now(), 'ends_at' => now()->addDays(7)]);
    Subscription::query()->create(['business_id' => $suspendedBusiness->id, 'plan_id' => $plan->id, 'status' => SubscriptionStatus::Cancelled, 'starts_at' => now()->subMonths(2), 'ends_at' => now()->subMonth()]);

    $device = Device::query()->create(['business_id' => $business->id, 'branch_id' => $branch->id, 'name' => 'Main Shop App', 'type' => 'shop_app', 'status' => DeviceStatus::Active, 'app_version' => '1.2.3', 'last_synchronized_at' => now()->subDays(2)]);
    SyncBatch::query()->create(['business_id' => $business->id, 'branch_id' => $branch->id, 'device_id' => $device->id, 'status' => SyncBatchStatus::Failed, 'records_count' => 2, 'received_at' => now()]);
    SupportTicket::query()->create(['business_id' => $business->id, 'subject' => 'Need help', 'status' => SupportTicketStatus::Open, 'priority' => 'normal']);
    PlatformNotification::query()->create(['business_id' => $business->id, 'type' => 'security', 'severity' => 'high', 'category' => 'security', 'status' => 'open', 'title' => 'Security alert']);

    return [$owner, $superAdmin, $business, $branch];
}

it('keeps super-admin area separate from business-owner navigation and denies non admins', function (): void {
    [$owner, $superAdmin, $business, $branch] = superAdminFixture();

    $this->actingAs($owner)
        ->withSession(['platform.business_id' => $business->id, 'platform.branch_id' => $branch->id])
        ->get(route('super-admin.dashboard'))
        ->assertForbidden();

    $this->actingAs($superAdmin)
        ->get(route('super-admin.dashboard'))
        ->assertOk()
        ->assertSee('Super administration')
        ->assertSee('Platform Super-Administrator Dashboard')
        ->assertSee('Administration modules')
        ->assertSee('Businesses')
        ->assertSee('Legal documents')
        ->assertDontSee('Remote Monitoring')
        ->assertDontSee('Monitoring Users');
});

it('renders platform-wide metrics and administration modules for super admins', function (): void {
    [$owner, $superAdmin] = superAdminFixture();

    Livewire::actingAs($superAdmin)
        ->test(SuperAdminDashboardPage::class)
        ->assertSee('Total businesses')
        ->assertSee('Active businesses')
        ->assertSee('Trial businesses')
        ->assertSee('Suspended businesses')
        ->assertSee('Active subscriptions')
        ->assertSee('Expired subscriptions')
        ->assertSee('MRR foundation')
        ->assertSee('Active Shop App devices')
        ->assertSee('Delayed devices')
        ->assertSee('Failed sync batches')
        ->assertSee('Application versions in use')
        ->assertSee('Open support tickets')
        ->assertSee('Security alerts')
        ->call('setModule', 'support-tickets')
        ->assertSet('module', 'support-tickets')
        ->assertSee('Need help');
});

it('records sensitive action confirmation and rejects unrestricted impersonation assumptions', function (): void {
    [$owner, $superAdmin] = superAdminFixture();

    Livewire::actingAs($superAdmin)
        ->test(SuperAdminDashboardPage::class)
        ->set('confirmationPhrase', 'wrong phrase')
        ->call('confirmSensitiveAction')
        ->assertHasErrors(['confirmationPhrase'])
        ->set('confirmationPhrase', 'CONFIRM SUPER ADMIN ACTION')
        ->call('confirmSensitiveAction')
        ->assertHasNoErrors()
        ->assertDispatched('notify');

    expect(SuperAdminActionConfirmation::query()->where('user_id', $superAdmin->id)->where('action', 'super_admin.sensitive_action_confirmation')->exists())->toBeTrue()
        ->and($superAdmin->refresh()->super_admin_confirmed_at)->not->toBeNull();

    $this->actingAs($superAdmin)
        ->get('/admin/platform/impersonate/'.$owner->id)
        ->assertNotFound();
});
