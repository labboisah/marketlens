<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Devices\Models\Device;
use App\Domain\Devices\Models\DeviceActivation;
use App\Domain\Devices\Services\ShopAppActivationService;
use App\Domain\Licensing\Models\BusinessLicenseAssociation;
use App\Domain\Modules\Services\ModuleAccessService;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Domain\Versions\Models\ApplicationVersion;
use App\Enums\AccountStatus;
use App\Enums\ApplicationVersionStatus;
use App\Enums\BranchStatus;
use App\Enums\BusinessStatus;
use App\Enums\DeviceStatus;
use App\Enums\SubscriptionStatus;
use App\Livewire\Platform\Devices\DeviceActivationPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function deviceActivationUser(): User
{
    return User::factory()->create([
        'account_status' => AccountStatus::Active,
        'email_verified_at' => now(),
    ]);
}

function deviceActivationPlan(array $features = ['One branch']): Plan
{
    $plan = Plan::query()->create([
        'name' => 'Device Plan',
        'slug' => 'device-plan',
        'billing_interval' => 'monthly',
        'price_minor' => 10000,
        'currency' => 'NGN',
        'trial_days' => 14,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    foreach ($features as $index => $feature) {
        $plan->features()->create(['name' => $feature, 'sort_order' => $index + 1]);
    }

    return $plan;
}

function deviceActivationFixture(?Plan $plan = null, SubscriptionStatus $subscriptionStatus = SubscriptionStatus::Trialing): array
{
    $user = deviceActivationUser();
    $business = Business::query()->create(['name' => 'Ada Stores', 'status' => BusinessStatus::Active]);
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
        'plan_id' => ($plan ?? deviceActivationPlan())->id,
        'status' => $subscriptionStatus,
        'starts_at' => now(),
        'ends_at' => now()->addDays(14),
    ]);

    return [$user, $business, $branch];
}

function publishedShopVersion(string $version = '1.0.0'): ApplicationVersion
{
    return ApplicationVersion::query()->create([
        'application' => 'shop-app',
        'version' => $version,
        'status' => ApplicationVersionStatus::Published,
        'released_at' => now(),
    ]);
}

it('lets business owners generate hashed short-lived activation codes', function (): void {
    [$user, $business, $branch] = deviceActivationFixture(deviceActivationPlan(['More Business App devices']));
    $version = publishedShopVersion();

    $component = Livewire::actingAs($user)
        ->test(DeviceActivationPage::class)
        ->set('branchId', $branch->id)
        ->set('deviceLabel', 'Main Business Server')
        ->set('deviceType', 'shop_app')
        ->set('expiresInMinutes', 30)
        ->set('applicationVersionId', $version->id)
        ->call('generate')
        ->assertHasNoErrors()
        ->assertDispatched('notify')
        ->assertSet('deviceLabel', '')
        ->assertSet('generatedDeviceLabel', 'Main Business Server')
        ->assertSet('generatedBranchName', 'Main Branch');

    $generatedCode = $component->get('generatedCode');

    expect($generatedCode)->toBeString()->not->toBe('');

    $component->assertSee($generatedCode);

    $device = Device::query()->where('business_id', $business->id)->firstOrFail();
    $activation = DeviceActivation::query()->where('device_id', $device->id)->firstOrFail();

    expect($device->status)->toBe(DeviceStatus::Pending)
        ->and($device->application_version_id)->toBe($version->id)
        ->and($activation->activation_code_hash)->not->toBeNull()
        ->and($activation->expires_at->isFuture())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'device.activation_created')->exists())->toBeTrue();
});

it('allows a Business Server to safely retry activation with the same installation identity', function (): void {
    [$user, $business, $branch] = deviceActivationFixture(deviceActivationPlan(['More Business App devices']));
    $branch->forceFill(['operating_modes' => ['sales', 'production']])->save();
    $version = publishedShopVersion('2.1.0');
    $result = app(ShopAppActivationService::class)->createActivation($business, $branch, $user, [
        'device_label' => 'Main Business Server',
        'device_type' => 'shop_app',
        'expires_at' => now()->addMinutes(30),
        'application_version_id' => $version->id,
    ]);

    $response = $this
        ->withHeader('Accept', 'application/json')
        ->postJson(route('api.v1.device.activate'), [
            'activation_code' => $result['code'],
            'device_identity' => 'machine-guid-123',
            'installation_uuid' => '00000000-0000-4000-8000-000000000801',
            'app_version' => '2.1.0',
            'operating_system' => ['name' => 'Windows', 'version' => '11'],
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Device activated.')
        ->assertJsonStructure(['data' => [
            'device_uuid',
            'device_type',
            'credential',
            'credential_preview',
            'business_uuid',
            'branch_uuid',
            'business_name',
            'branch_name',
            'subscription_state',
            'plan_features',
            'enabled_modules',
            'branch_settings',
            'sync_protocol_version',
        ]])
        ->assertJsonPath('data.business_uuid', $business->uuid)
        ->assertJsonPath('data.branch_uuid', $branch->uuid)
        ->assertJsonPath('data.device_type', 'shop_app')
        ->assertJsonPath('data.business_name', 'Ada Stores')
        ->assertJsonPath('data.branch_name', 'Main Branch')
        ->assertJsonPath('data.subscription_state', 'trialing')
        ->assertJsonPath('data.branch_settings.operating_modes', ['sales', 'production'])
        ->assertJsonPath('data.sync_protocol_version', '1.0');

    $credential = $response->json('data.credential');
    $device = Device::query()->where('business_id', $business->id)->firstOrFail();

    expect($device->status)->toBe(DeviceStatus::Active)
        ->and($device->credential_hash)->not->toBe($credential)
        ->and($device->operating_system['name'])->toBe('Windows')
        ->and($device->activations()->first()->used_at)->not->toBeNull()
        ->and(AuditLog::query()->where('action', 'device.activated')->exists())->toBeTrue();

    $branch->forceFill(['operating_modes' => ['services']])->save();
    $this->withToken($credential)
        ->getJson(route('api.v1.device.status'))
        ->assertOk()
        ->assertJsonPath('data.branch_settings.operating_modes', ['services']);

    $retry = $this
        ->withHeader('Accept', 'application/json')
        ->postJson(route('api.v1.device.activate'), [
            'activation_code' => $result['code'],
            'device_identity' => 'machine-guid-123',
            'installation_uuid' => '00000000-0000-4000-8000-000000000801',
            'app_version' => '2.1.0',
        ]);
    $retry->assertOk()
        ->assertJsonPath('data.credential', $response->json('data.credential'))
        ->assertJsonPath('data.device_uuid', $response->json('data.device_uuid'));

    $this
        ->withHeader('Accept', 'application/json')
        ->postJson(route('api.v1.device.activate'), [
            'activation_code' => $result['code'],
            'device_identity' => 'different-machine',
            'installation_uuid' => '00000000-0000-4000-8000-000000000802',
            'app_version' => '2.1.0',
        ])
        ->assertUnprocessable();
});

it('does not consume a client-app code when a Business Server presents it', function (): void {
    [$user, $business, $branch] = deviceActivationFixture(deviceActivationPlan(['More Business App devices']));
    $activation = app(ShopAppActivationService::class)->createActivation($business, $branch, $user, [
        'device_label' => 'Business App',
        'device_type' => 'client_app',
        'expires_at' => now()->addMinutes(30),
        'application_version_id' => null,
    ]);

    $this->withHeader('Accept', 'application/json')
        ->postJson(route('api.v1.device.activate'), [
            'activation_code' => $activation['code'],
            'device_identity' => 'machine-guid-123',
            'expected_device_type' => 'shop_app',
            'installation_uuid' => '00000000-0000-4000-8000-000000000803',
            'app_version' => '1.0.0',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'VALIDATION_EXCEPTION')
        ->assertJsonPath('errors.activation_code.0', 'This activation code is for a client_app device, not a shop_app device. Generate a code for the Business Server.');

    expect($activation['activation']->fresh()->used_at)->toBeNull();
});

it('generates and redeems Business App codes using the linked KernelBridge device limit', function (): void {
    [$user, $business, $branch] = deviceActivationFixture();
    Subscription::query()->where('business_id', $business->id)->delete();
    BusinessLicenseAssociation::query()->create([
        'business_id' => $business->id,
        'provider' => 'kernelbridge',
        'product_code' => 'MARKETLENS',
        'license_uuid' => '00000000-0000-4000-8000-000000000901',
        'subscription_uuid' => '00000000-0000-4000-8000-000000000902',
        'status' => 'active',
        'expires_at' => now()->addYear(),
        'entitlements' => ['max_client_app_devices' => 1],
        'provider_metadata' => [
            'account_link_uuid' => '00000000-0000-4000-8000-000000000903',
            'account_link_status' => 'approved',
        ],
    ]);

    $activation = app(ShopAppActivationService::class)->createActivation($business, $branch, $user, [
        'device_label' => 'Front Counter Business App',
        'device_type' => 'client_app',
        'expires_at' => now()->addMinutes(30),
        'application_version_id' => null,
    ]);

    $response = $this->withHeader('Accept', 'application/json')->postJson(route('api.v1.device.activate'), [
        'activation_code' => $activation['code'],
        'device_identity' => 'business-app-device-1',
        'app_version' => '1.0.0',
    ]);
    $response->assertOk()->assertJsonPath('data.device_type', 'client_app');

    expect(fn () => app(ShopAppActivationService::class)->createActivation($business, $branch, $user, [
        'device_label' => 'Second Business App',
        'device_type' => 'client_app',
        'expires_at' => now()->addMinutes(30),
        'application_version_id' => null,
    ]))->toThrow(ValidationException::class, 'The Business App device limit has been reached for this license.');
});

it('fails closed when the linked KernelBridge license has no Business App device limit', function (): void {
    [$user, $business, $branch] = deviceActivationFixture();
    Subscription::query()->where('business_id', $business->id)->delete();
    BusinessLicenseAssociation::query()->create([
        'business_id' => $business->id,
        'provider' => 'kernelbridge',
        'product_code' => 'MARKETLENS',
        'license_uuid' => '00000000-0000-4000-8000-000000000904',
        'subscription_uuid' => '00000000-0000-4000-8000-000000000905',
        'status' => 'active',
        'expires_at' => now()->addYear(),
        'entitlements' => [],
        'provider_metadata' => [
            'account_link_uuid' => '00000000-0000-4000-8000-000000000906',
            'account_link_status' => 'approved',
        ],
    ]);

    expect(fn () => app(ShopAppActivationService::class)->createActivation($business, $branch, $user, [
        'device_label' => 'Unlicensed Business App',
        'device_type' => 'client_app',
        'expires_at' => now()->addMinutes(30),
        'application_version_id' => null,
    ]))->toThrow(ValidationException::class, 'The active KernelBridge license has no valid max_client_app_devices entitlement.');
});

it('preserves local-subscription Business App device limits when no KernelBridge license is linked', function (): void {
    [$user, $business, $branch] = deviceActivationFixture(deviceActivationPlan(['2 Business App devices']));

    foreach (['First', 'Second'] as $label) {
        app(ShopAppActivationService::class)->createActivation($business, $branch, $user, [
            'device_label' => $label.' Business App',
            'device_type' => 'client_app',
            'expires_at' => now()->addMinutes(30),
            'application_version_id' => null,
        ]);
    }

    expect(fn () => app(ShopAppActivationService::class)->createActivation($business, $branch, $user, [
        'device_label' => 'Third Business App',
        'device_type' => 'client_app',
        'expires_at' => now()->addMinutes(30),
        'application_version_id' => null,
    ]))->toThrow(ValidationException::class, 'The current subscription does not allow another Business App device.');
});

it('requires a KernelBridge license to generate a Business Server activation code', function (): void {
    [$user, $business, $branch] = deviceActivationFixture();
    config(['kernelbridge.enforce_server_grants' => true]);

    expect(fn () => app(ShopAppActivationService::class)->createActivation($business, $branch, $user, [
        'device_label' => 'Unlicensed Business Server',
        'device_type' => 'shop_app',
        'expires_at' => now()->addMinutes(30),
        'application_version_id' => null,
    ]))->toThrow(ValidationException::class);
});

it('issues a KernelBridge installation code only for an approved account link', function (): void {
    [$user, $business, $branch] = deviceActivationFixture(deviceActivationPlan(['More Business App devices']));
    config([
        'kernelbridge.enforce_server_grants' => true,
        'kernelbridge.api_token' => 'test-platform-credential',
    ]);
    $license = BusinessLicenseAssociation::query()->create([
        'business_id' => $business->id,
        'provider' => 'kernelbridge',
        'product_code' => 'MARKETLENS',
        'license_uuid' => '00000000-0000-4000-8000-000000000501',
        'subscription_uuid' => '00000000-0000-4000-8000-000000000502',
        'status' => 'active',
        'expires_at' => now()->addYear(),
        'entitlements' => ['max_server_installations' => 2],
        'provider_metadata' => [
            'plan_name' => 'MarketLens Sales',
            'plan_uuid' => 'kb-plan-sales',
            'account_link_uuid' => '00000000-0000-4000-8000-000000000505',
            'account_link_status' => 'approved',
            'api_base_url' => 'https://licensing.example.test/api/v1',
        ],
    ]);
    $installationUuid = '00000000-0000-4000-8000-000000000503';
    Http::fake([
        '*/platform/account-links/*/activation-codes' => Http::sequence()
            ->push(['success' => false, 'error' => ['message' => 'Temporary outage']], 503)
            ->push([
            'success' => true,
            'data' => [
                'link_uuid' => '00000000-0000-4000-8000-000000000505',
                'installation_uuid' => $installationUuid,
                'activation_code' => 'KB-SHORT-LIVED-CODE',
                'expires_at' => now()->addMinutes(10)->toISOString(),
            ],
            'meta' => ['request_id' => 'activation-code-request-1'],
        ], 201),
    ]);

    $activation = app(ShopAppActivationService::class)->createActivation($business, $branch, $user, [
        'device_label' => 'Licensed Business Server',
        'device_type' => 'shop_app',
        'expires_at' => now()->addMinutes(30),
        'application_version_id' => null,
    ]);

    $response = $this->withHeader('Accept', 'application/json')->postJson(route('api.v1.device.activate'), [
        'activation_code' => $activation['code'],
        'device_identity' => 'machine-guid-grant-test',
        'expected_device_type' => 'shop_app',
        'installation_uuid' => $installationUuid,
        'app_version' => '1.0.0',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.kernelbridge_installation_code.activation_code', 'KB-SHORT-LIVED-CODE')
        ->assertJsonPath('data.kernelbridge_installation_code.installation_uuid', $installationUuid)
        ->assertJsonPath('data.kernelbridge_api_url', 'https://licensing.example.test/api/v1')
        ->assertJsonMissingPath('data.kernelbridge_installation_grant');

    $retry = $this->withHeader('Accept', 'application/json')->postJson(route('api.v1.device.activate'), [
        'activation_code' => $activation['code'],
        'device_identity' => 'machine-guid-grant-test',
        'expected_device_type' => 'shop_app',
        'installation_uuid' => $installationUuid,
        'app_version' => '1.0.0',
    ]);
    $retry->assertOk()
        ->assertJsonPath('data.credential', $response->json('data.credential'))
        ->assertJsonPath('data.kernelbridge_installation_code.activation_code', 'KB-SHORT-LIVED-CODE');

    $device = Device::query()->where('business_id', $business->id)->firstOrFail();
    expect($device->kernelbridge_license_uuid)->toBe($license->license_uuid)
        ->and($device->kernelbridge_installation_uuid)->toBe($installationUuid)
        ->and($device->kernelbridge_installation_grant_uuid)->toBeNull()
        ->and($device->kernelbridge_grant_status)->toBe('activation_code_issued');

    $platformCredential = $response->json('data.credential');
    $this->withToken($platformCredential)
        ->getJson(route('api.v1.device.status'))
        ->assertOk()
        ->assertJsonPath('data.subscription_plan.name', 'MarketLens Sales')
        ->assertJsonPath('data.kernelbridge_installation_grant_uuid', null)
        ->assertJsonMissingPath('data.kernelbridge_installation_grant.credential');

    Http::assertSent(fn ($request) => str_contains((string) $request->url(), '/platform/account-links/00000000-0000-4000-8000-000000000505/activation-codes')
        && $request['installation_uuid'] === $installationUuid);
    Http::assertSentCount(2);
});

it('passes linked KernelBridge plan entitlements to Business Server without a local subscription', function (): void {
    [$user, $business, $branch] = deviceActivationFixture();
    Subscription::query()->where('business_id', $business->id)->delete();
    $branch->forceFill(['operating_modes' => ['sales', 'production']])->save();
    config([
        'kernelbridge.enforce_server_grants' => true,
        'kernelbridge.api_token' => 'test-platform-credential',
    ]);

    BusinessLicenseAssociation::query()->create([
        'business_id' => $business->id,
        'provider' => 'kernelbridge',
        'product_code' => 'MARKETLENS',
        'license_uuid' => '00000000-0000-4000-8000-000000000701',
        'subscription_uuid' => '00000000-0000-4000-8000-000000000702',
        'status' => 'active',
        'expires_at' => now()->addYear(),
        'entitlements' => [
            'mode_sales' => true,
            'mode_production' => true,
            'mode_services' => false,
            'module_shareholders' => true,
            'max_server_installations' => 2,
        ],
        'provider_metadata' => [
            'plan_name' => 'MarketLens Complete',
            'plan_uuid' => 'kb-plan-complete',
            'account_link_uuid' => '00000000-0000-4000-8000-000000000705',
            'account_link_status' => 'approved',
            'api_base_url' => 'https://licensing.example.test/api/v1',
        ],
    ]);
    app(ModuleAccessService::class)->forget($business);

    Http::fake([
        '*/platform/account-links/*/activation-codes' => Http::response([
            'success' => true,
            'data' => [
                'link_uuid' => '00000000-0000-4000-8000-000000000705',
                'installation_uuid' => '00000000-0000-4000-8000-000000000703',
                'activation_code' => 'KB-SHORT-LIVED-CODE',
                'expires_at' => now()->addMinutes(10)->toISOString(),
            ],
        ], 201),
    ]);

    $activation = app(ShopAppActivationService::class)->createActivation($business, $branch, $user, [
        'device_label' => 'Licensed Business Server',
        'device_type' => 'shop_app',
        'expires_at' => now()->addMinutes(30),
        'application_version_id' => null,
    ]);

    $response = $this->withHeader('Accept', 'application/json')->postJson(route('api.v1.device.activate'), [
        'activation_code' => $activation['code'],
        'device_identity' => 'machine-guid-entitlement-test',
        'installation_uuid' => '00000000-0000-4000-8000-000000000703',
        'app_version' => '1.0.0',
    ]);

    $response->assertOk()->assertJsonPath('data.subscription_state', 'active');
    expect($response->json('data.plan_features'))->toContain('sales', 'production', 'module:shareholders')
        ->and($response->json('data.enabled_modules'))->toContain('sales', 'production', 'shareholders');
});

it('returns active Platform-approved Business App devices for an activated Business Server', function (): void {
    [$user, $business, $branch] = deviceActivationFixture(deviceActivationPlan(['More Business App devices']));
    $result = app(ShopAppActivationService::class)->createActivation($business, $branch, $user, [
        'device_label' => 'Main Business Server',
        'device_type' => 'shop_app',
        'expires_at' => now()->addMinutes(30),
        'application_version_id' => null,
    ]);

    $credential = $this
        ->withHeader('Accept', 'application/json')
        ->postJson(route('api.v1.device.activate'), [
            'activation_code' => $result['code'],
            'device_identity' => 'machine-guid-123',
            'app_version' => '1.0.0',
        ])
        ->json('data.credential');

    $approved = Device::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'name' => 'Front Counter Business App',
        'type' => 'client_app',
        'status' => DeviceStatus::Active,
    ]);

    Device::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'name' => 'Pending Business App',
        'type' => 'client_app',
        'status' => DeviceStatus::Pending,
    ]);

    $this
        ->withToken($credential)
        ->getJson(route('api.v1.device.approved-client-apps'))
        ->assertOk()
        ->assertJsonPath('data.devices.0.uuid', $approved->uuid)
        ->assertJsonPath('data.devices.0.name', 'Front Counter Business App')
        ->assertJsonPath('data.devices.0.type', 'client_app')
        ->assertJsonPath('data.devices.0.status', 'active')
        ->assertJsonCount(1, 'data.devices');
});
it('exposes a public activation ping for Business Server connection tests', function (): void {
    $this
        ->getJson(route('api.v1.activation.ping'))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'ok');
});

it('rejects expired activation codes and audits failures', function (): void {
    [$user, $business, $branch] = deviceActivationFixture();
    $result = app(ShopAppActivationService::class)->createActivation($business, $branch, $user, [
        'device_label' => 'Expired Business Server',
        'device_type' => 'shop_app',
        'expires_at' => now()->subMinute(),
        'application_version_id' => null,
    ]);

    $this
        ->withHeader('Accept', 'application/json')
        ->postJson(route('api.v1.device.activate'), [
            'activation_code' => $result['code'],
            'device_identity' => 'machine-guid-123',
            'app_version' => '1.0.0',
        ])
        ->assertUnprocessable();

    expect(AuditLog::query()->where('action', 'device.activation_failed')->exists())->toBeTrue();
});

it('enforces business subscription branch device and application version policy', function (): void {
    [$user, $business, $branch] = deviceActivationFixture(subscriptionStatus: SubscriptionStatus::Cancelled);

    app(ShopAppActivationService::class)->createActivation($business, $branch, $user, [
        'device_label' => 'Blocked Business Server',
        'device_type' => 'shop_app',
        'expires_at' => now()->addMinutes(30),
        'application_version_id' => null,
    ]);
})->throws(ValidationException::class);

it('rejects activation for suspended branches', function (): void {
    [$user, $business, $branch] = deviceActivationFixture();
    $branch->forceFill(['status' => BranchStatus::Suspended])->save();

    app(ShopAppActivationService::class)->createActivation($business, $branch, $user, [
        'device_label' => 'Blocked Business Server',
        'device_type' => 'shop_app',
        'expires_at' => now()->addMinutes(30),
        'application_version_id' => null,
    ]);
})->throws(ValidationException::class);

it('enforces device limits for the current subscription', function (): void {
    [$user, $business, $branch] = deviceActivationFixture();
    Device::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'name' => 'Existing Business Server',
        'type' => 'shop_app',
        'status' => DeviceStatus::Active,
    ]);

    app(ShopAppActivationService::class)->createActivation($business, $branch, $user, [
        'device_label' => 'Second Business Server',
        'device_type' => 'shop_app',
        'expires_at' => now()->addMinutes(30),
        'application_version_id' => null,
    ]);
})->throws(ValidationException::class);

it('rejects assigned application version mismatches', function (): void {
    [$user, $business, $branch] = deviceActivationFixture(deviceActivationPlan(['More Business App devices']));
    $version = publishedShopVersion('3.0.0');
    $result = app(ShopAppActivationService::class)->createActivation($business, $branch, $user, [
        'device_label' => 'Versioned Business Server',
        'device_type' => 'shop_app',
        'expires_at' => now()->addMinutes(30),
        'application_version_id' => $version->id,
    ]);

    $this
        ->withHeader('Accept', 'application/json')
        ->postJson(route('api.v1.device.activate'), [
            'activation_code' => $result['code'],
            'device_identity' => 'machine-guid-123',
            'app_version' => '2.9.0',
        ])
        ->assertUnprocessable();
});

it('supports device status heartbeat and revocation without re-exposing credentials', function (): void {
    [$user, $business, $branch] = deviceActivationFixture(deviceActivationPlan(['More Business App devices']));
    $result = app(ShopAppActivationService::class)->createActivation($business, $branch, $user, [
        'device_label' => 'Main Business Server',
        'device_type' => 'shop_app',
        'expires_at' => now()->addMinutes(30),
        'application_version_id' => null,
    ]);

    $credential = $this
        ->withHeader('Accept', 'application/json')
        ->postJson(route('api.v1.device.activate'), [
            'activation_code' => $result['code'],
            'device_identity' => 'machine-guid-123',
            'app_version' => '1.0.0',
        ])
        ->json('data.credential');

    deviceActivationPlan(['Premium reports']);

    $this
        ->withToken($credential)
        ->getJson(route('api.v1.device.status'))
        ->assertOk()
        ->assertJsonMissing(['credential' => $credential])
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.subscription_state', 'trialing')
        ->assertJsonPath('data.subscription_plan.name', 'Device Plan')
        ->assertJsonFragment(['key' => 'More Business App devices', 'available' => true])
        ->assertJsonFragment(['key' => 'Premium reports', 'available' => false])
        ->assertJsonStructure(['data' => ['plan_features', 'enabled_modules', 'branch_settings', 'feature_catalog']]);

    $this
        ->withToken($credential)
        ->postJson(route('api.v1.device.heartbeat'), [
            'app_version' => '1.0.1',
            'last_synchronized_at' => now()->toISOString(),
            'operating_system' => ['name' => 'Windows', 'version' => '11'],
            'synchronization_errors' => [['code' => 'SYNC_TIMEOUT']],
        ])
        ->assertOk()
        ->assertJsonPath('data.shop_app_version', '1.0.1')
        ->assertJsonPath('data.operating_system.name', 'Windows');

    $device = Device::query()->where('business_id', $business->id)->firstOrFail();
    app(ShopAppActivationService::class)->revokeDevice($device, $user);

    $this
        ->withToken($credential)
        ->getJson(route('api.v1.device.status'))
        ->assertUnprocessable();
});
