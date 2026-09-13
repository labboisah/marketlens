<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Devices\Services\ShopAppActivationService;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Domain\Versions\Models\ApplicationVersion;
use App\Enums\AccountStatus;
use App\Enums\ApplicationVersionStatus;
use App\Enums\BranchStatus;
use App\Enums\BusinessStatus;
use App\Enums\SubscriptionStatus;
use App\Livewire\SuperAdmin\ApplicationVersionManagementPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

require_once __DIR__.'/../Support/ManagedPackageFixture.php';
uses(RefreshDatabase::class);
beforeEach(fn () => Storage::fake('downloads'));

function applicationVersionSuperAdmin(): User
{
    return User::factory()->create([
        'account_status' => AccountStatus::Active,
        'email_verified_at' => now(),
        'is_super_admin' => true,
    ]);
}

function applicationVersionOwnerDevice($testCase, string $appVersion = '1.0.0'): array
{
    $owner = User::factory()->create(['account_status' => AccountStatus::Active, 'email_verified_at' => now()]);
    $plan = Plan::query()->create([
        'name' => 'Version Device Plan',
        'slug' => 'version-device-plan-'.Str::random(6),
        'billing_interval' => 'monthly',
        'price_minor' => 10000,
        'currency' => 'NGN',
        'trial_days' => 14,
        'sort_order' => 1,
        'is_active' => true,
    ]);
    $business = Business::query()->create(['name' => 'Version Stores', 'status' => BusinessStatus::Active]);
    $branch = Branch::query()->create([
        'business_id' => $business->id,
        'name' => 'Main Branch',
        'status' => BranchStatus::Active,
        'is_head_office' => true,
    ]);
    $owner->ownedBusinesses()->attach($business->id, ['role' => 'owner']);
    Subscription::query()->create([
        'business_id' => $business->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active,
        'starts_at' => now(),
        'ends_at' => now()->addMonth(),
    ]);

    $activation = app(ShopAppActivationService::class)->createActivation($business, $branch, $owner, [
        'device_label' => 'Main Business Server',
        'device_type' => 'shop_app',
        'expires_at' => now()->addMinutes(30),
        'application_version_id' => null,
    ]);

    $credential = $testCase->withHeader('Accept', 'application/json')->postJson(route('api.v1.device.activate'), [
        'activation_code' => $activation['code'],
        'device_identity' => 'device-version-guid',
        'app_version' => $appVersion,
        'operating_system' => ['name' => 'windows', 'architecture' => 'x64'],
    ])->assertOk()->json('data.credential');

    return [$owner, $business, $branch, $credential];
}

it('lets super admins create application versions with secure release files', function (): void {
    Storage::fake('local');
    $superAdmin = applicationVersionSuperAdmin();
    $key = managedPackageFixture();

    Livewire::actingAs($superAdmin)
        ->test(ApplicationVersionManagementPage::class)
        ->set('application', 'shop_app')
        ->set('platform', 'windows')
        ->set('architecture', 'x64')
        ->set('version', '2.0.0')
        ->set('releaseChannel', 'stable')
        ->set('status', 'published')
        ->set('releaseNotes', 'Faster startup and safer synchronization.')
        ->set('minimumSupportedVersion', '1.5.0')
        ->set('recommendedVersion', '2.0.0')
        ->set('mandatoryUpdate', true)
        ->set('isActive', true)
        ->set('releasedAt', now()->format('Y-m-d\TH:i'))
        ->set('managedPackage', $key)
        ->call('createVersion')
        ->assertHasNoErrors()
        ->assertDispatched('notify');

    $version = ApplicationVersion::query()->firstOrFail();

    expect($version->application)->toBe('shop_app')
        ->and($version->platform)->toBe('windows')
        ->and($version->architecture)->toBe('x64')
        ->and($version->release_channel)->toBe('stable')
        ->and($version->status)->toBe(ApplicationVersionStatus::Published)
        ->and($version->minimum_supported_version)->toBe('1.5.0')
        ->and($version->mandatory_update)->toBeTrue()
        ->and($version->checksum)->not->toBeNull()
        ->and($version->download_path)->not->toBeNull();

    Storage::disk('downloads')->assertExists($version->download_path);
    expect(AuditLog::query()->where('action', 'application_version.created')->exists())->toBeTrue();

    $this->actingAs($superAdmin)
        ->get(route('super-admin.application-versions'))
        ->assertOk()
        ->assertSee('Application-Version Management')
        ->assertSee('Signed download');
});

it('blocks non-super-admin users from application-version management', function (): void {
    $owner = User::factory()->create(['account_status' => AccountStatus::Active, 'email_verified_at' => now()]);

    $this->actingAs($owner)
        ->get(route('super-admin.application-versions'))
        ->assertForbidden();
});

it('returns device application update metadata with signed download details', function (): void {
    Storage::fake('local');
    [$owner, $business, $branch, $credential] = applicationVersionOwnerDevice($this, '1.0.0');
    Storage::disk('local')->put('application-versions/shop_app/stable/2.0.0/release.zip', 'release-binary');
    $checksum = hash('sha256', 'release-binary');

    ApplicationVersion::query()->create([
        'application' => 'shop_app',
        'platform' => 'windows',
        'architecture' => 'x64',
        'version' => '2.0.0',
        'release_channel' => 'stable',
        'status' => ApplicationVersionStatus::Published,
        'release_notes' => 'Mandatory security update.',
        'minimum_supported_version' => '1.5.0',
        'recommended_version' => '2.0.0',
        'mandatory_update' => true,
        'is_active' => true,
        'download_disk' => 'local',
        'download_path' => 'application-versions/shop_app/stable/2.0.0/release.zip',
        'download_original_name' => 'shop-app-2.0.0.zip',
        'download_mime_type' => 'application/zip',
        'download_size_bytes' => strlen('release-binary'),
        'checksum' => $checksum,
        'released_at' => now()->subMinute(),
    ]);

    $response = $this
        ->withToken($credential)
        ->getJson(route('api.v1.device.application-update', [
            'current_version' => '1.0.0',
            'platform' => 'windows',
            'architecture' => 'x64',
        ]))
        ->assertOk()
        ->assertJsonPath('data.current_version', '1.0.0')
        ->assertJsonPath('data.latest_version', '2.0.0')
        ->assertJsonPath('data.minimum_supported_version', '1.5.0')
        ->assertJsonPath('data.recommended_version', '2.0.0')
        ->assertJsonPath('data.update_available', true)
        ->assertJsonPath('data.mandatory_update', true)
        ->assertJsonPath('data.download.file_name', 'shop-app-2.0.0.zip')
        ->assertJsonPath('data.download.checksum', $checksum)
        ->assertJsonPath('data.checksum', $checksum)
        ->assertJsonPath('data.release_notes', 'Mandatory security update.');

    expect($response->json('data.download.url'))->toContain('signature=');

    $this->get($response->json('data.download.url'))->assertOk();
});

it('uses release channels and active published versions when checking updates', function (): void {
    [$owner, $business, $branch, $credential] = applicationVersionOwnerDevice($this, '2.0.0');

    ApplicationVersion::query()->create([
        'application' => 'shop_app',
        'version' => '3.0.0',
        'release_channel' => 'stable',
        'status' => ApplicationVersionStatus::Draft,
        'is_active' => true,
        'released_at' => now()->subDay(),
    ]);
    ApplicationVersion::query()->create([
        'application' => 'shop_app',
        'version' => '2.1.0',
        'release_channel' => 'stable',
        'status' => ApplicationVersionStatus::Published,
        'is_active' => false,
        'released_at' => now()->subDay(),
    ]);
    ApplicationVersion::query()->create([
        'application' => 'shop_app',
        'version' => '2.2.0',
        'release_channel' => 'beta',
        'status' => ApplicationVersionStatus::Published,
        'is_active' => true,
        'released_at' => now()->subDay(),
    ]);

    $this->withToken($credential)
        ->getJson(route('api.v1.device.application-update', ['current_version' => '2.0.0']))
        ->assertOk()
        ->assertJsonPath('data.latest_version', null)
        ->assertJsonPath('data.update_available', false);

    $this->withToken($credential)
        ->getJson(route('api.v1.device.application-update', ['current_version' => '2.0.0', 'release_channel' => 'beta']))
        ->assertOk()
        ->assertJsonPath('data.latest_version', '2.2.0')
        ->assertJsonPath('data.update_available', true)
        ->assertJsonPath('data.mandatory_update', false);
});
