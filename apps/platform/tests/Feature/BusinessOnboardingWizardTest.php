<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Onboarding\Models\OwnerOnboardingDraft;
use App\Domain\Onboarding\Services\CompleteOwnerOnboarding;
use App\Domain\Owners\Models\BusinessOwner;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Enums\AccountStatus;
use App\Enums\SubscriptionStatus;
use App\Livewire\Onboarding\BusinessOnboardingWizard;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function onboardingUser(): User
{
    return User::factory()->create([
        'account_status' => AccountStatus::Active,
        'email_verified_at' => now(),
    ]);
}

function onboardingPayload(int $planId): array
{
    return [
        'business.name' => 'Ada Stores',
        'business.legal_name' => 'Ada Stores Limited',
        'business.business_type' => 'supermarket',
        'business.registration_number' => 'RC-123',
        'business.tax_number' => 'TIN-456',
        'business.phone' => '+2348000000000',
        'business.alternative_phone' => '+2348111111111',
        'business.email' => 'business@example.test',
        'business.address' => '12 Market Road',
        'business.state' => 'Lagos',
        'business.local_government_area' => 'Ikeja',
        'business.default_currency' => 'NGN',
        'business.timezone' => 'Africa/Lagos',
        'business.preferred_language' => 'en',
        'branch.name' => 'Main Branch',
        'branch.code' => 'MAIN',
        'branch.phone' => '+2348000000000',
        'branch.email' => 'branch@example.test',
        'branch.address' => '12 Market Road',
        'branch.state' => 'Lagos',
        'branch.local_government_area' => 'Ikeja',
        'branch.is_head_office' => true,
        'plan.plan_id' => $planId,
        'monitoring.daily_report_preference' => 'daily',
        'monitoring.low_stock_alerts' => true,
        'monitoring.cashier_shortage_alerts' => true,
        'monitoring.synchronization_delay_alerts' => true,
        'monitoring.security_alerts' => true,
        'monitoring.email_notifications' => true,
        'monitoring.push_notification_readiness' => true,
        'monitoring.sms_placeholder' => false,
        'monitoring.whatsapp_placeholder' => false,
    ];
}

it('requires authentication for onboarding', function (): void {
    $this->get('/onboarding')->assertRedirect('/login');
});

it('redirects verified owners without a business from dashboard to onboarding', function (): void {
    $user = onboardingUser();

    $this->actingAs($user)
        ->get(route('platform.dashboard'))
        ->assertRedirect(route('onboarding.wizard'));
});

it('validates step one before navigation', function (): void {
    $user = onboardingUser();

    Livewire::actingAs($user)
        ->test(BusinessOnboardingWizard::class)
        ->call('nextStep')
        ->assertHasErrors(['business.name', 'business.business_type', 'business.phone']);
});

it('navigates steps and persists draft data', function (): void {
    $this->seed(PlanSeeder::class);
    $user = onboardingUser();
    $plan = Plan::query()->where('slug', 'basic')->where('billing_interval', 'monthly')->firstOrFail();

    $component = Livewire::actingAs($user)->test(BusinessOnboardingWizard::class);

    foreach (onboardingPayload($plan->id) as $field => $value) {
        $component->set($field, $value);
    }

    $component->call('nextStep')->assertSet('step', 2);

    $draft = OwnerOnboardingDraft::query()->where('user_id', $user->id)->firstOrFail();

    expect($draft->current_step)->toBe(2)
        ->and(data_get($draft->data, 'business.name'))->toBe('Ada Stores');

    $component->call('previousStep')->assertSet('step', 1);
});

it('creates business branch owner subscription and audit log transactionally', function (): void {
    $this->seed(PlanSeeder::class);
    $user = onboardingUser();
    $plan = Plan::query()->where('slug', 'standard')->where('billing_interval', 'monthly')->firstOrFail();

    $component = Livewire::actingAs($user)->test(BusinessOnboardingWizard::class);

    foreach (onboardingPayload($plan->id) as $field => $value) {
        $component->set($field, $value);
    }

    $component
        ->call('nextStep')
        ->call('nextStep')
        ->call('nextStep')
        ->call('nextStep')
        ->call('complete')
        ->assertRedirect(route('onboarding.completed'));

    expect(Business::query()->where('name', 'Ada Stores')->exists())->toBeTrue()
        ->and(Branch::query()->where('name', 'Main Branch')->exists())->toBeTrue()
        ->and(BusinessOwner::query()->where('user_id', $user->id)->exists())->toBeTrue()
        ->and(Subscription::query()->where('plan_id', $plan->id)->where('status', SubscriptionStatus::Trialing)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'business_owner.onboarding_completed')->exists())->toBeTrue();
});

it('prevents duplicate business creation for the same owner', function (): void {
    $this->seed(PlanSeeder::class);
    $user = onboardingUser();
    $business = Business::query()->create(['name' => 'Existing Business']);
    BusinessOwner::query()->create(['business_id' => $business->id, 'user_id' => $user->id, 'role' => 'owner']);
    $draft = OwnerOnboardingDraft::query()->create([
        'user_id' => $user->id,
        'current_step' => 5,
        'data' => ['plan' => ['plan_id' => Plan::query()->firstOrFail()->id]],
    ]);

    app(CompleteOwnerOnboarding::class)->handle($user, $draft);
})->throws(ValidationException::class);
