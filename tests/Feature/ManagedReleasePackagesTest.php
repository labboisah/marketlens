<?php

use App\Domain\Versions\Models\ApplicationVersion;
use App\Domain\Versions\Services\ApplicationVersionService;
use App\Domain\Versions\Services\ManagedReleasePackages;
use App\Enums\AccountStatus;
use App\Livewire\SuperAdmin\ApplicationVersionManagementPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

require_once __DIR__.'/../Support/ManagedPackageFixture.php';
uses(RefreshDatabase::class);
beforeEach(function () {
    Storage::fake('downloads');
    Storage::fake('local');
});

it('downloads a complete ZIP with requirements independently of version uploads', function () {
    $key = managedPackageFixture();
    $package = app(ManagedReleasePackages::class)->find($key);
    $response = $this->get('/downloads/business-server')->assertOk()
        ->assertDownload('business-server-2.0.0-x64.zip')
        ->assertHeader('Content-Type', 'application/zip');
    $zip = new ZipArchive;
    expect($zip->open($response->baseResponse->getFile()->getPathname()))->toBeTrue();
    expect($zip->getFromName('REQUIREMENTS.md'))->toContain('Node.js');
    expect($zip->getFromName('START-HERE.md'))->toContain('Business Server');
    expect($zip->getFromName('setup-templates.zip'))->not->toBeFalse();
    expect($zip->getFromName($package['installer']['name']))->toBe('MZ-test-installer-2.0.0');
    $zip->close();
    $this->get('/deployment')->assertOk()->assertSee('Version 2.0.0')->assertSee('(ZIP)');
    expect(ApplicationVersion::count())->toBe(0);
});

it('keeps a permanent link while selecting newer complete releases and the requested architecture', function () {
    managedPackageFixture();
    managedPackageFixture(version: '2.1.0');
    managedPackageFixture(version: '9.0.0', channel: 'beta');
    $this->get('/downloads/business-server')->assertHeader('X-Release-Version', '2.1.0');
    $this->get('/downloads/business-server?architecture=arm64')->assertRedirect();
    $this->get('/downloads/business-server?architecture=../x64')->assertStatus(422);
    expect(app(ManagedReleasePackages::class)->find('../outside'))->toBeNull();
});

it('serves a clearly identified beta when no stable package is available and hides internal packages', function () {
    managedPackageFixture('business-app', '2.2.0', 'beta');
    managedPackageFixture('business-app', '3.0.0', 'internal');
    $this->get('/downloads/business-app')->assertOk()->assertHeader('X-Release-Channel', 'beta')->assertHeader('X-Release-Version', '2.2.0');
    $this->get('/deployment')->assertSee('Development-signed beta for testing');
});

it('rejects a tampered ZIP and ignores incomplete packages', function () {
    $key = managedPackageFixture();
    $package = app(ManagedReleasePackages::class)->find($key);
    $path = $key.'/'.$package['bundle']['name'];
    $original = Storage::disk('downloads')->get($path);
    Storage::disk('downloads')->put($path, str_repeat('x', strlen($original)));
    $this->get('/downloads/business-server')->assertStatus(503);
    Storage::disk('downloads')->delete($path);
    expect(app(ManagedReleasePackages::class)->find($key))->toBeNull();
});

it('creates release policy from a prepared package without an upload and keeps the updater executable', function () {
    $key = managedPackageFixture();
    $user = User::factory()->create(['is_super_admin' => true, 'account_status' => AccountStatus::Active, 'email_verified_at' => now()]);
    Livewire::actingAs($user)->test(ApplicationVersionManagementPage::class)
        ->assertDontSeeHtml('type="file"')->set('managedPackage', $key)
        ->assertSet('version', '2.0.0')->assertSet('platform', 'windows')
        ->set('status', 'published')->call('createVersion')->assertHasNoErrors();
    $release = ApplicationVersion::firstOrFail();
    expect($release->download_disk)->toBe('downloads');
    expect($release->download_original_name)->toEndWith('.exe');
    $manifest = app(ApplicationVersionService::class)->desktopManifest('shop_app', 'stable', 'x64');
    expect($manifest['latestVersion'])->toBe('2.0.0');
    $this->get($manifest['downloadUrl'])->assertOk()->assertDownload($release->download_original_name);
});

it('rejects mismatched or missing packages even if version fields are changed by the browser', function () {
    $key = managedPackageFixture();
    $user = User::factory()->create(['is_super_admin' => true, 'account_status' => AccountStatus::Active, 'email_verified_at' => now()]);
    Livewire::actingAs($user)->test(ApplicationVersionManagementPage::class)
        ->set('managedPackage', $key)->set('version', '8.0.0')->call('createVersion')->assertHasErrors('managedPackage');
    expect(ApplicationVersion::count())->toBe(0);
});

it('does not overwrite an existing release or accept an invalid executable', function () {
    managedPackageFixture();
    $this->artisan('releases:pack', ['application' => 'business-server', 'installer' => Storage::disk('downloads')->path('fixture.exe'), '--release-version' => '2.0.0', '--channel' => 'stable'])->assertExitCode(1);
    Storage::disk('downloads')->put('bad.exe', 'not-an-executable');
    $this->artisan('releases:pack', ['application' => 'business-app', 'installer' => Storage::disk('downloads')->path('bad.exe'), '--release-version' => '2.0.0'])->assertExitCode(1);
});
