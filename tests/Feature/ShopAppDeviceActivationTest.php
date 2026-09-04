<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Devices\Models\Device;
use App\Domain\Devices\Models\DeviceActivation;
use App\Domain\Devices\Services\ShopAppActivationService;
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

it('activates a Business Server with a single-use code and returns credential once', function (): void {
    [$user, $business, $branch] = deviceActivationFixture(deviceActivationPlan(['More Business App devices']));
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
            'app_version' => '2.1.0',
            'operating_system' => ['name' => 'Windows', 'version' => '11'],
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Device activated.')
        ->assertJsonStructure(['data' => [
            'device_uuid',
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
        ->assertJsonPath('data.business_name', 'Ada Stores')
        ->assertJsonPath('data.branch_name', 'Main Branch')
        ->assertJsonPath('data.subscription_state', 'trialing')
        ->assertJsonPath('data.sync_protocol_version', '1.0');

    $credential = $response->json('data.credential');
    $device = Device::query()->where('business_id', $business->id)->firstOrFail();

    expect($device->status)->toBe(DeviceStatus::Active)
        ->and($device->credential_hash)->not->toBe($credential)
        ->and($device->operating_system['name'])->toBe('Windows')
        ->and($device->activations()->first()->used_at)->not->toBeNull()
        ->and(AuditLog::query()->where('action', 'device.activated')->exists())->toBeTrue();

    $this
        ->withHeader('Accept', 'application/json')
        ->postJson(route('api.v1.device.activate'), [
            'activation_code' => $result['code'],
            'device_identity' => 'machine-guid-123',
            'app_version' => '2.1.0',
        ])
        ->assertUnprocessable();
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

    $this
        ->withToken($credential)
        ->getJson(route('api.v1.device.status'))
        ->assertOk()
        ->assertJsonMissing(['credential' => $credential])
        ->assertJsonPath('data.status', 'active');

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
