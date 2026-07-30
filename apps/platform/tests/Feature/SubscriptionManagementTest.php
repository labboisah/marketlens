<?php

use App\Domain\Billing\Models\BillingProfile;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Models\SubscriptionChangeRequest;
use App\Domain\Billing\Services\SubscriptionManagementService;
use App\Domain\Devices\Models\Device;
use App\Domain\Devices\Services\ShopAppActivationService;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Enums\AccountStatus;
use App\Enums\BranchStatus;
use App\Enums\BusinessStatus;
use App\Enums\DeviceStatus;
use App\Enums\SubscriptionStatus;
use App\Livewire\Platform\Subscriptions\SubscriptionManagementPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function subscriptionOwner(): User
{
    return User::factory()->create([
        'account_status' => AccountStatus::Active,
        'email_verified_at' => now(),
    ]);
}

function subscriptionPlan(string $name, int $priceMinor, array $features): Plan
{
    $plan = Plan::query()->create([
        'name' => $name,
        'slug' => Str::slug($name).'-'.Str::random(4),
        'billing_interval' => 'monthly',
        'price_minor' => $priceMinor,
        'currency' => 'NGN',
        'trial_days' => 14,
        'sort_order' => $priceMinor,
        'is_active' => true,
    ]);

    foreach ($features as $index => $feature) {
        $plan->features()->create(['name' => $feature, 'sort_order' => $index + 1]);
    }

    return $plan;
}

function subscriptionBusiness(User $user, Plan $plan, SubscriptionStatus $status = SubscriptionStatus::Active, ?Carbon\Carbon $endsAt = null): array
{
    $business = Business::query()->create([
        'name' => 'Subscription Stores',
        'status' => BusinessStatus::Active,
        'onboarding_status' => 'completed',
        'email' => 'billing@example.test',
        'phone' => '08000000000',
    ]);
    $branch = Branch::query()->create([
        'business_id' => $business->id,
        'name' => 'Main Branch',
        'code' => 'MAIN',
        'status' => BranchStatus::Active,
        'is_head_office' => true,
    ]);
    $user->ownedBusinesses()->attach($business->id, ['role' => 'owner']);

    $subscription = Subscription::query()->create([
        'business_id' => $business->id,
        'plan_id' => $plan->id,
        'status' => $status,
        'starts_at' => now()->subMonth(),
        'ends_at' => $endsAt ?? now()->addMonth(),
    ]);

    return [$business, $branch, $subscription];
}

it('renders owner subscription management with plan usage history options and billing profile', function (): void {
    $owner = subscriptionOwner();
    $plan = subscriptionPlan('Growth', 25000, ['Sales', 'Reports', '3 Branches', '2 Shop App devices', '5 Monitoring users']);
    [$business, $branch, $subscription] = subscriptionBusiness($owner, $plan, SubscriptionStatus::Trialing, now()->addDays(10));
    $subscription->payments()->create([
        'amount_minor' => 25000,
        'currency' => 'NGN',
        'status' => \App\Enums\PaymentStatus::Paid,
        'paid_at' => now(),
    ]);

    $this->actingAs($owner)
        ->withSession(['platform.business_id' => $business->id, 'platform.branch_id' => $branch->id])
        ->get(route('platform.subscription'))
        ->assertOk()
        ->assertSee('Subscription management')
        ->assertSee('Current plan')
        ->assertSee('Growth')
        ->assertSee('Usage against limits')
        ->assertSee('Shop App devices')
        ->assertSee('Trial status')
        ->assertSee('Payment history')
        ->assertSee('Cancellation request')
        ->assertSee('Billing profile');

    $this->actingAs($owner)
        ->withSession(['platform.business_id' => $business->id, 'platform.branch_id' => $branch->id])
        ->get(route('platform.dashboard'))
        ->assertOk()
        ->assertSee(route('platform.subscription'));
});

it('saves billing profiles and records cancellation requests without deleting business data', function (): void {
    $owner = subscriptionOwner();
    $plan = subscriptionPlan('Starter', 10000, ['Sales']);
    [$business, $branch] = subscriptionBusiness($owner, $plan);

    Livewire::actingAs($owner)
        ->test(SubscriptionManagementPage::class)
        ->set('billingName', 'Ada Stores Ltd')
        ->set('billingEmail', 'accounts@example.test')
        ->set('billingPhone', '08111111111')
        ->set('billingAddress', '1 Market Road')
        ->set('taxNumber', 'TIN-123')
        ->set('currency', 'NGN')
        ->call('saveBillingProfile')
        ->assertDispatched('notify')
        ->set('cancellationReason', 'Seasonal closure')
        ->call('requestCancellation')
        ->assertDispatched('notify');

    expect(BillingProfile::query()->where('business_id', $business->id)->where('billing_email', 'accounts@example.test')->exists())->toBeTrue()
        ->and(SubscriptionChangeRequest::query()->where('business_id', $business->id)->where('type', 'cancellation')->exists())->toBeTrue()
        ->and(Business::query()->whereKey($business->id)->exists())->toBeTrue()
        ->and(Branch::query()->whereKey($branch->id)->exists())->toBeTrue();
});

it('warns before downgrade requests when usage exceeds target plan limits', function (): void {
    $owner = subscriptionOwner();
    $basic = subscriptionPlan('Basic', 10000, ['Sales']);
    $growth = subscriptionPlan('Growth', 50000, ['Sales', 'Reports', '5 Branches', '3 Shop App devices']);
    [$business, $branch] = subscriptionBusiness($owner, $growth);
    Branch::query()->create(['business_id' => $business->id, 'name' => 'Second Branch', 'code' => 'SECOND', 'status' => BranchStatus::Active]);
    Device::query()->create(['business_id' => $business->id, 'branch_id' => $branch->id, 'name' => 'Main Shop App', 'type' => 'shop_app', 'status' => DeviceStatus::Active]);
    Device::query()->create(['business_id' => $business->id, 'branch_id' => $branch->id, 'name' => 'Backup Shop App', 'type' => 'shop_app', 'status' => DeviceStatus::Active]);

    Livewire::actingAs($owner)
        ->test(SubscriptionManagementPage::class)
        ->call('requestPlanChange', $basic->id)
        ->assertDispatched('notify');

    $request = SubscriptionChangeRequest::query()->where('business_id', $business->id)->firstOrFail();

    expect($request->type)->toBe('downgrade')
        ->and($request->warnings)->not->toBeEmpty()
        ->and(collect($request->warnings)->implode(' '))->toContain('Branches usage is 2')
        ->and(collect($request->warnings)->implode(' '))->toContain('Shop App devices usage is 2');
});

it('restricts premium features after the configurable grace period while keeping billing access', function (): void {
    config(['marketlens.billing.grace_days' => 3]);
    $owner = subscriptionOwner();
    $plan = subscriptionPlan('Sales Plan', 10000, ['Sales', 'Reports']);
    [$business, $branch] = subscriptionBusiness($owner, $plan, SubscriptionStatus::PastDue, now()->subDays(5));

    expect(app(SubscriptionManagementService::class)->allowsOperationalAccess($business->currentSubscription))->toBeFalse();

    $this->actingAs($owner)
        ->withSession(['platform.business_id' => $business->id, 'platform.branch_id' => $branch->id])
        ->get(route('platform.dashboard'))
        ->assertOk()
        ->assertSee('Subscription')
        ->assertDontSee(route('platform.sales'));

    $this->actingAs($owner)
        ->withSession(['platform.business_id' => $business->id, 'platform.branch_id' => $branch->id])
        ->get(route('platform.subscription'))
        ->assertOk()
        ->assertSee('Premium access restricted');
});

it('uses plan limits for device activation', function (): void {
    $owner = subscriptionOwner();
    $plan = subscriptionPlan('Device Growth', 30000, ['2 Shop App devices']);
    [$business, $branch] = subscriptionBusiness($owner, $plan);
    Device::query()->create(['business_id' => $business->id, 'branch_id' => $branch->id, 'name' => 'Main Shop App', 'type' => 'shop_app', 'status' => DeviceStatus::Active]);

    app(ShopAppActivationService::class)->createActivation($business, $branch, $owner, [
        'device_label' => 'Second Shop App',
        'device_type' => 'shop_app',
        'expires_at' => now()->addMinutes(30),
        'application_version_id' => null,
    ]);

    app(ShopAppActivationService::class)->createActivation($business, $branch, $owner, [
        'device_label' => 'Third Shop App',
        'device_type' => 'shop_app',
        'expires_at' => now()->addMinutes(30),
        'application_version_id' => null,
    ]);
})->throws(ValidationException::class);


it('allows device activation during the configured past-due grace period', function (): void {
    config(['marketlens.billing.grace_days' => 3]);
    $owner = subscriptionOwner();
    $plan = subscriptionPlan('Grace Device Plan', 30000, ['2 Shop App devices']);
    [$business, $branch] = subscriptionBusiness($owner, $plan, SubscriptionStatus::PastDue, now()->subDay());

    $result = app(ShopAppActivationService::class)->createActivation($business, $branch, $owner, [
        'device_label' => 'Grace Shop App',
        'device_type' => 'shop_app',
        'expires_at' => now()->addMinutes(30),
        'application_version_id' => null,
    ]);

    expect($result['activation']->exists)->toBeTrue();
});
