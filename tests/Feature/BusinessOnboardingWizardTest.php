<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Licensing\Models\BusinessLicenseAssociation;
use App\Domain\Onboarding\Models\OwnerOnboardingDraft;
use App\Domain\Onboarding\Services\CompleteOwnerOnboarding;
use App\Domain\Owners\Models\BusinessOwner;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Enums\AccountStatus;
use App\Enums\BusinessStatus;
use App\Enums\SubscriptionStatus;
use App\Livewire\Onboarding\BusinessOnboardingWizard;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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

function onboardingPayload(): array
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
        'branch.operating_modes' => ['sales', 'services'],
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
    $user = onboardingUser();

    $component = Livewire::actingAs($user)->test(BusinessOnboardingWizard::class);

    foreach (onboardingPayload() as $field => $value) {
        $component->set($field, $value);
    }

    $component->call('nextStep')->assertSet('step', 2);

    $draft = OwnerOnboardingDraft::query()->where('user_id', $user->id)->firstOrFail();

    expect($draft->current_step)->toBe(2)
        ->and(data_get($draft->data, 'business.name'))->toBe('Ada Stores');

    $component->call('previousStep')->assertSet('step', 1);
});

it('moves saved onboarding drafts past the retired plan step', function (): void {
    $user = onboardingUser();
    OwnerOnboardingDraft::query()->create([
        'user_id' => $user->id,
        'current_step' => 4,
        'data' => [
            'plan' => ['plan_id' => 123],
            'monitoring' => ['daily_report_preference' => 'weekly'],
        ],
    ]);

    Livewire::actingAs($user)
        ->test(BusinessOnboardingWizard::class)
        ->assertSet('step', 3)
        ->assertSee('Step 3 of 4')
        ->assertSee('Monitoring')
        ->assertDontSee('Plan')
        ->assertDontSee('plan.plan_id');
});

it('creates business branch owner and audit log without requiring a local plan', function (): void {
    $user = onboardingUser();

    $component = Livewire::actingAs($user)->test(BusinessOnboardingWizard::class);

    foreach (onboardingPayload() as $field => $value) {
        $component->set($field, $value);
    }

    $component->set('branch.is_head_office', false);

    $component
        ->call('nextStep')
        ->call('nextStep')
        ->call('nextStep')
        ->assertSet('step', 4)
        ->call('complete')
        ->assertRedirect(route('onboarding.completed'));

    $business = Business::query()->where('name', 'Ada Stores')->firstOrFail();
    $branch = Branch::query()->where('name', 'Main Branch')->firstOrFail();

    expect($business->default_branch_id)->toBe($branch->id)
        ->and($branch->is_head_office)->toBeTrue()
        ->and($branch->business_id)->toBe($business->id)
        ->and($branch->operating_modes)->toBe(['sales', 'services'])
        ->and(BusinessOwner::query()->where('user_id', $user->id)->exists())->toBeTrue()
        ->and(Subscription::query()->where('business_id', $business->id)->exists())->toBeFalse()
        ->and(Invoice::query()->where('business_id', $business->id)->exists())->toBeFalse()
        ->and(AuditLog::query()->where('action', 'business_owner.onboarding_completed')->exists())->toBeTrue();
});

it('redirects owners without an active business license to the account license activation page', function (): void {
    $user = onboardingUser();
    $business = Business::query()->create([
        'name' => 'Needs Activation',
        'status' => BusinessStatus::Active,
        'onboarding_status' => 'completed',
        'email' => 'needs-activation@example.test',
        'phone' => '08000000009',
    ]);
    BusinessOwner::query()->create(['business_id' => $business->id, 'user_id' => $user->id, 'role' => 'owner']);

    $this->actingAs($user)
        ->get(route('platform.dashboard'))
        ->assertRedirect(route('platform.account-license-activation'));
});

it('requires an active license for the selected business even when another business is licensed', function (): void {
    $user = onboardingUser();
    $licensedBusiness = Business::query()->create([
        'name' => 'Licensed Business',
        'status' => BusinessStatus::Active,
        'onboarding_status' => 'completed',
    ]);
    $unlicensedBusiness = Business::query()->create([
        'name' => 'Unlicensed Business',
        'status' => BusinessStatus::Active,
        'onboarding_status' => 'completed',
    ]);
    BusinessOwner::query()->create(['business_id' => $licensedBusiness->id, 'user_id' => $user->id, 'role' => 'owner']);
    BusinessOwner::query()->create(['business_id' => $unlicensedBusiness->id, 'user_id' => $user->id, 'role' => 'owner']);
    BusinessLicenseAssociation::query()->create([
        'business_id' => $licensedBusiness->id,
        'product_code' => 'MARKETLENS',
        'license_uuid' => 'license-for-other-business',
        'status' => 'active',
    ]);

    $plan = Plan::query()->create([
        'name' => 'Legacy Services',
        'slug' => 'legacy-services',
        'billing_interval' => 'monthly',
        'price_minor' => 10000,
        'currency' => 'NGN',
        'trial_days' => 14,
        'sort_order' => 1,
        'is_active' => true,
    ]);
    $plan->features()->create(['key' => 'module:services', 'name' => 'Services and Revenue Sharing']);
    Subscription::query()->create([
        'business_id' => $unlicensedBusiness->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active,
        'starts_at' => now(),
        'ends_at' => now()->addMonth(),
    ]);
    Branch::query()->create([
        'business_id' => $unlicensedBusiness->id,
        'name' => 'Unlicensed Main',
        'code' => 'UNLICENSED',
        'status' => 'active',
        'is_head_office' => true,
        'operating_modes' => ['services'],
    ]);

    $this->actingAs($user)
        ->withSession(['platform.business_id' => $unlicensedBusiness->id])
        ->get(route('platform.services'))
        ->assertRedirect(route('platform.account-license-activation'));
});

it('redirects the onboarding completion page when the owner has no active license', function (): void {
    $user = onboardingUser();
    $business = Business::query()->create([
        'name' => 'Needs Activation',
        'status' => BusinessStatus::Active,
        'onboarding_status' => 'completed',
        'email' => 'needs-activation@example.test',
        'phone' => '08000000009',
    ]);
    BusinessOwner::query()->create(['business_id' => $business->id, 'user_id' => $user->id, 'role' => 'owner']);

    $this->actingAs($user)
        ->get(route('onboarding.completed'))
        ->assertRedirect(route('platform.account-license-activation'));
});

it('renders the onboarding completion page when the owner has an active license', function (): void {
    $user = onboardingUser();
    $business = Business::query()->create([
        'name' => 'Licensed Business',
        'status' => BusinessStatus::Active,
        'onboarding_status' => 'completed',
        'email' => 'licensed-business@example.test',
        'phone' => '08000000011',
    ]);
    BusinessOwner::query()->create(['business_id' => $business->id, 'user_id' => $user->id, 'role' => 'owner']);
    BusinessLicenseAssociation::query()->create([
        'business_id' => $business->id,
        'product_code' => 'MARKETLENS',
        'license_uuid' => 'license-active',
        'status' => 'active',
    ]);

    $this->actingAs($user)
        ->get(route('onboarding.completed'))
        ->assertOk()
        ->assertViewIs('onboarding.completed');
});

it('lets owners activate a business license from the account activation page', function (): void {
    $user = onboardingUser();
    $business = Business::query()->create([
        'name' => 'Activate Me',
        'status' => BusinessStatus::Active,
        'onboarding_status' => 'completed',
        'email' => 'activate-me@example.test',
        'phone' => '08000000010',
    ]);
    BusinessOwner::query()->create(['business_id' => $business->id, 'user_id' => $user->id, 'role' => 'owner']);

    $this->actingAs($user)
        ->get(route('platform.account-license-activation'))
        ->assertOk()
        ->assertSee('kernelbridge-account-activation')
        ->assertDontSee('vendor/kernelbridge-licensing/activation.css');

    expect(file_exists(public_path('vendor/kernelbridge-licensing/activation.css')))->toBeTrue()
        ->and(file_exists(public_path('vendor/kernelbridge-licensing/kernelbridge-logo.png')))->toBeTrue();

    config(['kernelbridge.api_token' => 'test-api-credential']);
    config(['kernelbridge.platform_link_redirect_route' => 'platform.dashboard']);

    Http::fake([
        '*/platform/account-links/link_activated' => Http::sequence()
            ->push([
                'success' => true,
                'data' => [
                    'uuid' => 'link_activated',
                    'license_uuid' => 'lic_activated',
                    'business_uuid' => $business->uuid,
                    'status' => 'approved',
                    'license_status' => 'active',
                    'entitlements' => [],
                ],
            ])
            ->push([
                'success' => true,
                'data' => [
                    'uuid' => 'link_activated',
                    'license_uuid' => 'lic_activated',
                    'business_uuid' => $business->uuid,
                    'status' => 'approved',
                    'license_status' => 'active',
                    'entitlements' => [],
                ],
            ])
            ->push([
                'success' => true,
                'data' => [
                    'uuid' => 'link_activated',
                    'license_uuid' => 'lic_activated',
                    'business_uuid' => $business->uuid,
                    'status' => 'revoked',
                    'installations_revoked' => 0,
                    'activation_codes_revoked' => 0,
                ],
            ]),
        '*/platform/account-links/requests' => Http::sequence()
            ->push([
                'success' => true,
                'data' => [
                    'uuid' => 'link_activated',
                    'product_code' => 'MARKETLENS',
                    'license_uuid' => 'lic_activated',
                    'subscription_uuid' => 'sub_activated',
                    'business_uuid' => $business->uuid,
                    'status' => 'pending',
                    'license_status' => 'active',
                    'entitlements' => [],
                ],
                'meta' => ['request_id' => 'request-pending'],
            ], 202)
    ]);

    $this->actingAs($user)
        ->postJson(route('platform.account-license-activation.link'), ['activation_code' => 'KB-ACT-123'])
        ->assertOk()
        ->assertJsonPath('linkId', 'link_activated')
        ->assertJsonPath('status', 'pending');

    $linkedLicense = $business->fresh()->kernelbridgeLicense()->firstOrFail();
    expect(json_encode($linkedLicense->toArray()))->not->toContain('KB-ACT-123');

    $this->actingAs($user)
        ->getJson(route('platform.account-license-activation.link.status', 'link_activated'))
        ->assertOk()
        ->assertJsonPath('status', 'approved');

    $otherUser = onboardingUser();
    $otherBusiness = Business::query()->create([
        'name' => 'Another Business',
        'status' => BusinessStatus::Active,
        'onboarding_status' => 'completed',
        'email' => 'another-business@example.test',
        'phone' => '08000000011',
    ]);
    BusinessOwner::query()->create(['business_id' => $otherBusiness->id, 'user_id' => $otherUser->id, 'role' => 'owner']);
    $this->actingAs($otherUser)
        ->getJson(route('platform.account-license-activation.link.status', 'link_activated'))
        ->assertNotFound();

    $this->actingAs($user)
        ->postJson(route('platform.account-license-activation.link.complete', 'link_activated'))
        ->assertOk()
        ->assertJsonPath('redirectTo', route('platform.dashboard'));

    $this->actingAs($user)
        ->deleteJson(route('platform.account-license-activation.link.unlink', 'link_activated'))
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($business->fresh()->kernelbridgeLicense()->first())->toBeNull();
    Http::assertSent(fn ($request) => str_contains((string) $request->url(), config('kernelbridge.api_url').'/platform/account-links/requests')
        && $request['activation_code'] === 'KB-ACT-123');
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
