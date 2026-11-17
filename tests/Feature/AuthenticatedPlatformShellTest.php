<?php

use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Licensing\Models\BusinessLicenseAssociation;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Enums\AccountStatus;
use App\Enums\SubscriptionStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function platformShellUser(): User
{
    return User::factory()->create([
        'account_status' => AccountStatus::Active,
        'email_verified_at' => now(),
    ]);
}

function platformShellPlan(string $name, array $features): Plan
{
    $plan = Plan::query()->create([
        'name' => $name,
        'slug' => strtolower($name),
        'description' => $name.' plan',
        'billing_interval' => 'monthly',
        'price_minor' => 10000,
        'currency' => 'NGN',
        'trial_days' => 14,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    foreach ($features as $index => $feature) {
        $plan->features()->create([
            'name' => $feature,
            'tooltip' => $feature,
            'sort_order' => $index + 1,
        ]);
    }

    return $plan;
}

function platformShellBusiness(User $user, string $name, Plan $plan): array
{
    $business = Business::query()->create(['name' => $name]);
    $branch = Branch::query()->create([
        'business_id' => $business->id,
        'name' => $name.' Main',
        'code' => strtoupper(substr($name, 0, 3)),
    ]);

    $user->ownedBusinesses()->attach($business->id, ['role' => 'owner']);

    Subscription::query()->create([
        'business_id' => $business->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Trialing,
        'starts_at' => now(),
        'ends_at' => now()->addDays(14),
    ]);

    $featureNames = collect($plan->features()->pluck('name'))->map(fn (string $name): string => strtolower($name));
    BusinessLicenseAssociation::query()->create([
        'business_id' => $business->id,
        'provider' => 'kernelbridge',
        'product_code' => 'MARKETLENS',
        'license_uuid' => 'lic_shell_'.$business->uuid,
        'subscription_uuid' => 'sub_shell_'.$business->uuid,
        'status' => 'active',
        'entitlements' => [
            'mode_sales' => $featureNames->contains(fn (string $name): bool => str_contains($name, 'sales')),
            'mode_production' => $featureNames->contains(fn (string $name): bool => str_contains($name, 'production')),
            'mode_services' => $featureNames->contains(fn (string $name): bool => str_contains($name, 'services')),
            'module_shareholders' => $featureNames->contains(fn (string $name): bool => str_contains($name, 'shareholder')),
        ],
    ]);

    return [$business, $branch];
}

it('resolves selected business and branch server side for the authenticated owner only', function (): void {
    $owner = platformShellUser();
    $otherOwner = platformShellUser();
    $plan = platformShellPlan('Basic', ['Sales', 'Inventory', 'Remote dashboard']);
    [$ownedBusiness, $ownedBranch] = platformShellBusiness($owner, 'Owned Stores', $plan);
    [$foreignBusiness, $foreignBranch] = platformShellBusiness($otherOwner, 'Foreign Stores', $plan);

    $response = $this
        ->actingAs($owner)
        ->withSession([
            'platform.business_id' => $foreignBusiness->id,
            'platform.branch_id' => $foreignBranch->id,
        ])
        ->get(route('platform.dashboard'));

    $response
        ->assertOk()
        ->assertSee('Owned Stores')
        ->assertSee('Owned Stores Main')
        ->assertDontSee('Foreign Stores')
        ->assertDontSee('Foreign Stores Main')
        ->assertSessionHas('platform.business_id', $ownedBusiness->id)
        ->assertSessionHas('platform.branch_id', $ownedBranch->id);
});

it('rejects branch selector updates outside the selected business', function (): void {
    $owner = platformShellUser();
    $otherOwner = platformShellUser();
    $plan = platformShellPlan('Basic', ['Sales', 'Inventory', 'Remote dashboard']);
    [$ownedBusiness] = platformShellBusiness($owner, 'Owned Stores', $plan);
    [, $foreignBranch] = platformShellBusiness($otherOwner, 'Foreign Stores', $plan);

    $this
        ->actingAs($owner)
        ->post(route('platform.context.update'), [
            'business_id' => $ownedBusiness->id,
            'branch_id' => $foreignBranch->id,
            'date_range' => 'today',
        ])
        ->assertForbidden();
});

it('shows only subscription-allowed sidebar modules', function (): void {
    $owner = platformShellUser();
    $plan = platformShellPlan('Basic', [
        'Sales',
        'Products',
        'Inventory',
        'Expenses',
        'Customer debts',
        'Remote dashboard',
    ]);

    platformShellBusiness($owner, 'Ada Stores', $plan);

    $this
        ->actingAs($owner)
        ->get(route('platform.dashboard'))
        ->assertOk()
        ->assertSee('Sales')
        ->assertSee('Inventory')
        ->assertSee('Expenses')
        ->assertSee('Customers')
        ->assertSee('Purchase &amp; Supplier', false)
        ->assertSee('Cashier &amp; Staff', false)
        ->assertDontSee('Alerts');
});

it('shows the KernelBridge plan and modules instead of the legacy local plan', function (): void {
    $owner = platformShellUser();
    $legacyPlan = platformShellPlan('Professional', ['Production', 'Services', 'Shareholders']);
    [$business] = platformShellBusiness($owner, 'KernelBridge Stores', $legacyPlan);
    $business->kernelbridgeLicense()->firstOrFail()->forceFill([
        'license_uuid' => 'lic_shell_sales',
        'subscription_uuid' => 'sub_shell_sales',
        'status' => 'active',
        'entitlements' => [
            'mode_sales' => true,
            'mode_production' => false,
            'mode_services' => false,
            'module_shareholders' => false,
        ],
        'provider_metadata' => ['plan_name' => 'MarketLens Sales'],
    ])->save();

    $this->actingAs($owner)
        ->get(route('platform.dashboard'))
        ->assertOk()
        ->assertSee('MarketLens Sales')
        ->assertDontSee('Professional')
        ->assertSee('href="'.route('platform.sales').'"', false)
        ->assertDontSee('href="'.route('platform.production').'"', false)
        ->assertDontSee('href="'.route('platform.services').'#catalogue', false)
        ->assertDontSee('href="'.route('platform.shareholders').'"', false);
});

it('blocks direct navigation to a feature excluded by the linked KernelBridge plan', function (): void {
    $owner = platformShellUser();
    $plan = platformShellPlan('Sales only', ['Sales', 'Remote dashboard']);
    [$business, $branch] = platformShellBusiness($owner, 'Sales Stores', $plan);

    $this->actingAs($owner)
        ->withSession(['platform.business_id' => $business->id, 'platform.branch_id' => $branch->id])
        ->get(route('platform.services'))
        ->assertRedirect(route('platform.permission-denied'));
});

it('renders the permission denied state inside the platform shell', function (): void {
    $owner = platformShellUser();
    $plan = platformShellPlan('Basic', ['Sales', 'Remote dashboard']);

    platformShellBusiness($owner, 'Ada Stores', $plan);

    $this
        ->actingAs($owner)
        ->get(route('platform.permission-denied'))
        ->assertOk()
        ->assertSee('Permission denied')
        ->assertSee('Your account cannot access this business, branch, or Platform module.');
});

it('renders shared notifications and loading feedback in the authenticated shell', function (): void {
    $owner = platformShellUser();
    $plan = platformShellPlan('Basic', ['Sales', 'Remote dashboard']);

    platformShellBusiness($owner, 'Ada Stores', $plan);

    $this
        ->actingAs($owner)
        ->withSession(['success' => 'Saved successfully'])
        ->get(route('platform.dashboard'))
        ->assertOk()
        ->assertSee('aria-live="polite"', false)
        ->assertSee('aria-label="Loading page"', false)
        ->assertSee('ml-linear-progress')
        ->assertSee('Loading MarketLens')
        ->assertSee('Working')
        ->assertSee('Saved successfully');
});

it('links remote monitoring to a real screen and shows the selected branch sync time', function (): void {
    $owner = platformShellUser();
    $plan = platformShellPlan('Sidebar', ['Sales', 'Remote dashboard']);
    [, $branch] = platformShellBusiness($owner, 'Sidebar Stores', $plan);
    $branch->update(['last_synchronized_at' => now()->subMinute()]);

    $this->actingAs($owner)->get(route('platform.dashboard'))
        ->assertOk()
        ->assertSee('uppercase tracking-wide', false)
        ->assertSee('href="'.route('platform.branch-monitoring').'"', false)
        ->assertDontSee('href="#monitoring"', false)
        ->assertSee('Last sync: 1 minute ago');

    $branch->update(['last_synchronized_at' => null]);
    $this->get(route('platform.dashboard'))->assertOk()->assertSee('Awaiting first sync');
});
