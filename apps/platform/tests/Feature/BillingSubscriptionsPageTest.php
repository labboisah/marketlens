<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Tenancy\Models\Business;
use App\Enums\AccountStatus;
use App\Enums\BusinessStatus;
use App\Enums\SubscriptionStatus;
use App\Livewire\SuperAdmin\BillingSubscriptionsPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function billingSubscriptionPlan(string $name, int $priceMinor): Plan
{
    return Plan::query()->create([
        'name' => $name,
        'slug' => str($name)->slug().'-'.Str::random(6),
        'billing_interval' => 'monthly',
        'price_minor' => $priceMinor,
        'currency' => 'NGN',
        'trial_days' => 14,
        'sort_order' => 1,
        'is_active' => true,
    ]);
}

it('lets super admins manage all subscriptions', function (): void {
    $superAdmin = User::factory()->create([
        'account_status' => AccountStatus::Active,
        'email_verified_at' => now(),
        'is_super_admin' => true,
    ]);

    $business = Business::query()->create([
        'name' => 'Ada Stores',
        'status' => BusinessStatus::Active,
    ]);

    $starter = billingSubscriptionPlan('Starter Plan', 25000);
    $growth = billingSubscriptionPlan('Growth Plan', 75000);

    $subscription = Subscription::query()->create([
        'business_id' => $business->id,
        'plan_id' => $starter->id,
        'status' => SubscriptionStatus::Trialing,
        'starts_at' => now()->subDays(3),
        'ends_at' => now()->addDays(11),
    ]);

    Livewire::actingAs($superAdmin)
        ->test(BillingSubscriptionsPage::class)
        ->assertSee('All subscriptions')
        ->assertSee('Ada Stores')
        ->assertSee('Starter Plan')
        ->assertSee('Edit')
        ->call('editSubscription', $subscription->id)
        ->assertSet('editingSubscriptionId', $subscription->id)
        ->assertSet('planId', $starter->id)
        ->set('planId', $growth->id)
        ->set('status', SubscriptionStatus::Active->value)
        ->set('startsAt', '2026-08-01')
        ->set('endsAt', '2026-09-01')
        ->call('saveSubscription')
        ->assertHasNoErrors()
        ->assertDispatched('notify')
        ->call('setSubscriptionStatus', $subscription->id, SubscriptionStatus::PastDue->value)
        ->assertHasNoErrors()
        ->assertDispatched('notify')
        ->call('setSubscriptionStatus', $subscription->id, SubscriptionStatus::Cancelled->value)
        ->assertHasNoErrors()
        ->assertDispatched('notify');

    $subscription->refresh();

    expect($subscription->plan_id)->toBe($growth->id)
        ->and($subscription->status)->toBe(SubscriptionStatus::Cancelled)
        ->and(AuditLog::query()->where('action', 'subscription.admin_updated')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'subscription.admin_status_updated')->count())->toBe(2);
});
