<?php

use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Notifications\Jobs\SendAlertNotification;
use App\Domain\Notifications\Models\AlertNotificationPreference;
use App\Domain\Notifications\Models\PlatformNotification;
use App\Domain\Notifications\Services\AlertNotificationService;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Enums\AccountStatus;
use App\Enums\BranchStatus;
use App\Enums\BusinessStatus;
use App\Enums\SubscriptionStatus;
use App\Livewire\Platform\Notifications\NotificationCentrePage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function alertCentreFixture(array $features = ['Security alerts', 'Remote dashboard']): array
{
    $user = User::factory()->create([
        'account_status' => AccountStatus::Active,
        'email_verified_at' => now(),
    ]);
    $plan = Plan::query()->create([
        'name' => 'Alerts Plan',
        'slug' => 'alerts-plan-'.Str::random(6),
        'billing_interval' => 'monthly',
        'price_minor' => 25000,
        'currency' => 'NGN',
        'trial_days' => 14,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    foreach ($features as $index => $feature) {
        $plan->features()->create(['name' => $feature, 'sort_order' => $index + 1]);
    }

    $business = Business::query()->create([
        'name' => 'Alert Stores',
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

    return [$user, $business, $branch];
}

it('creates alerts with severity category business branch related record and deduplication', function (): void {
    Queue::fake();
    [$user, $business, $branch] = alertCentreFixture();
    $service = app(AlertNotificationService::class);

    $alert = $service->create($business, [
        'branch_id' => $branch->id,
        'type' => 'low_stock',
        'title' => 'Low stock: Rice 50kg',
        'body' => 'Rice 50kg is below reorder level.',
        'related_record_type' => 'product',
        'related_record_uuid' => (string) Str::uuid(),
        'dedupe_key' => 'low-stock-rice-main',
    ]);
    $duplicate = $service->create($business, [
        'branch_id' => $branch->id,
        'type' => 'low_stock',
        'title' => 'Low stock: Rice 50kg',
        'dedupe_key' => 'low-stock-rice-main',
    ]);

    expect($duplicate->id)->toBe($alert->id)
        ->and(PlatformNotification::query()->count())->toBe(1)
        ->and($alert->severity)->toBe('warning')
        ->and($alert->category)->toBe('inventory')
        ->and($alert->business_id)->toBe($business->id)
        ->and($alert->branch_id)->toBe($branch->id)
        ->and($alert->channels)->toContain('in_app')
        ->and($alert->delivered_channels)->toContain('in_app');

    $this->actingAs($user)
        ->withSession(['platform.business_id' => $business->id, 'platform.branch_id' => $branch->id])
        ->get(route('platform.notifications'))
        ->assertOk()
        ->assertSee('Notifications and alerts')
        ->assertSee('Low stock: Rice 50kg')
        ->assertSee('Unread');
});

it('supports read unread and resolution state changes', function (): void {
    [$user, $business, $branch] = alertCentreFixture();
    $alert = app(AlertNotificationService::class)->create($business, [
        'branch_id' => $branch->id,
        'type' => 'device_offline',
        'title' => 'Business Server device offline',
        'dedupe_key' => 'device-offline-main',
    ]);

    Livewire::actingAs($user)
        ->test(NotificationCentrePage::class)
        ->call('markRead', $alert->id)
        ->assertDispatched('notify')
        ->call('markUnread', $alert->id)
        ->assertDispatched('notify')
        ->call('startResolve', $alert->id)
        ->set('resolutionNote', 'Device contacted and was restarted.')
        ->call('resolveNotification')
        ->assertDispatched('notify');

    expect($alert->refresh()->read_at)->toBeNull()
        ->and($alert->resolved_at)->not->toBeNull()
        ->and($alert->resolution_note)->toBe('Device contacted and was restarted.')
        ->and($alert->resolved_by)->toBe($user->id);
});

it('enforces notification centre authorization and tenant isolation', function (): void {
    [$user, $business, $branch] = alertCentreFixture();
    app(AlertNotificationService::class)->create($business, [
        'branch_id' => $branch->id,
        'type' => 'suspicious_login',
        'title' => 'Suspicious login',
        'dedupe_key' => 'visible-login',
    ]);

    $otherBusiness = Business::query()->create(['name' => 'Hidden Alerts Business', 'status' => BusinessStatus::Active]);
    $otherBranch = Branch::query()->create(['business_id' => $otherBusiness->id, 'name' => 'Hidden Branch', 'status' => BranchStatus::Active]);
    PlatformNotification::query()->create([
        'business_id' => $otherBusiness->id,
        'branch_id' => $otherBranch->id,
        'type' => 'large_expense',
        'severity' => 'warning',
        'category' => 'expenses',
        'status' => 'open',
        'title' => 'Hidden expense alert',
        'dedupe_key' => 'hidden-expense',
    ]);

    $this->get(route('platform.notifications'))->assertRedirect(route('login'));

    Livewire::actingAs($user)
        ->test(NotificationCentrePage::class)
        ->assertSee('Suspicious login')
        ->assertDontSee('Hidden expense alert');
});

it('enforces owner preferences and queues enabled external notification channels', function (): void {
    Queue::fake();
    config(['alerts.channels.email' => true]);
    [$user, $business, $branch] = alertCentreFixture();
    $service = app(AlertNotificationService::class);

    $service->create($business, [
        'branch_id' => $branch->id,
        'type' => 'subscription_expiring',
        'title' => 'Subscription expiring soon',
        'dedupe_key' => 'subscription-expiring',
    ]);

    Queue::assertPushed(SendAlertNotification::class);

    Queue::fake();
    $service->setPreference($business, $user, 'subscription', 'email', false);
    $muted = $service->create($business, [
        'branch_id' => $branch->id,
        'type' => 'subscription_expiring',
        'title' => 'Subscription expiring again',
        'dedupe_key' => 'subscription-expiring-muted',
    ]);

    Queue::assertNotPushed(SendAlertNotification::class);
    expect($muted->channels)->not->toContain('email')
        ->and(AlertNotificationPreference::query()->where('business_id', $business->id)->where('channel', 'email')->where('enabled', false)->exists())->toBeTrue();
});

it('saves notification preferences from the centre', function (): void {
    [$user, $business, $branch] = alertCentreFixture();
    app(AlertNotificationService::class)->create($business, [
        'branch_id' => $branch->id,
        'type' => 'cashier_shortage',
        'title' => 'Cashier shortage requires review',
        'dedupe_key' => 'cashier-shortage',
    ]);

    Livewire::actingAs($user)
        ->test(NotificationCentrePage::class)
        ->set('preferences.cashiers|email', true)
        ->set('preferences.cashiers|push', true)
        ->set('preferences.cashiers|sms', false)
        ->call('savePreferences')
        ->assertDispatched('notify');

    expect(AlertNotificationPreference::query()
        ->where('business_id', $business->id)
        ->where('user_id', $user->id)
        ->where('category', 'cashiers')
        ->where('channel', 'push')
        ->where('enabled', true)
        ->exists())->toBeTrue();
});
