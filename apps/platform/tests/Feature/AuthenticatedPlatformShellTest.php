<?php

use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
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
        ->assertDontSee('Suppliers')
        ->assertDontSee('Cashiers')
        ->assertDontSee('Alerts');
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
