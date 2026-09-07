<?php

use App\Domain\Versions\Models\ApplicationVersion;
use App\Enums\ApplicationVersionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function desktopRelease(array $overrides = []): ApplicationVersion
{
    Storage::disk('local')->put('releases/installer.exe', 'installer-bytes');

    return ApplicationVersion::query()->create(array_merge([
        'application' => 'shop_app', 'platform' => 'windows', 'architecture' => 'x64',
        'version' => '2.0.0', 'release_channel' => 'stable',
        'status' => ApplicationVersionStatus::Published, 'is_active' => true,
        'download_disk' => 'local', 'download_path' => 'releases/installer.exe',
        'download_original_name' => 'installer.exe', 'checksum' => hash('sha256', 'installer-bytes'),
        'released_at' => now()->subMinute(),
    ], $overrides));
}

beforeEach(fn () => Storage::fake('local'));

it('serves separate public manifests with signed downloads and no business data', function (): void {
    desktopRelease();
    desktopRelease(['application' => 'client_app', 'version' => '3.0.0']);
    $server = $this->getJson('/api/v1/releases/business-server/manifest')->assertOk()
        ->assertJsonPath('application', 'business-server')->assertJsonPath('latestVersion', '2.0.0')
        ->assertJsonPath('minimumSupportedVersion', '0.0.0')->assertJsonPath('channel', 'stable');
    expect($server->json('downloadUrl'))->toContain('signature=');
    $this->get($server->json('downloadUrl'))->assertOk();
    $this->get('/application-versions/1/download')->assertForbidden();
    $this->getJson('/api/v1/releases/business-app/manifest')->assertOk()
        ->assertJsonPath('application', 'business-app')->assertJsonPath('latestVersion', '3.0.0');
});

it('selects the highest version rather than the most recently uploaded version', function (): void {
    desktopRelease(['version' => '2.10.0']);
    desktopRelease(['version' => '2.9.0', 'released_at' => now()]);
    $this->getJson('/api/v1/releases/business-server/manifest')->assertOk()->assertJsonPath('latestVersion', '2.10.0');
});

it('does not offer ineligible desktop packages', function (array $overrides): void {
    desktopRelease($overrides);
    $this->getJson('/api/v1/releases/business-server/manifest')->assertNoContent();
})->with([
    'draft' => [['status' => ApplicationVersionStatus::Draft]],
    'inactive' => [['is_active' => false]],
    'future' => [['released_at' => now()->addDay()]],
    'other channel' => [['release_channel' => 'beta']],
    'other platform' => [['platform' => 'linux']],
    'other architecture' => [['architecture' => 'arm64']],
    'zip archive' => [['download_original_name' => 'installer.zip']],
    'bad checksum' => [['checksum' => 'invalid']],
    'missing file' => [['download_path' => 'missing.exe']],
]);

it('validates selectors and returns no release for an empty catalog', function (): void {
    $this->getJson('/api/v1/releases/business-app/manifest')->assertNoContent();
    $this->getJson('/api/v1/releases/business-app/manifest?channel=internal')->assertUnprocessable();
    $this->getJson('/api/v1/releases/business-app/manifest?architecture=unknown')->assertUnprocessable();
    $this->getJson('/api/v1/releases/unknown/manifest')->assertNotFound();
});

it('selects beta and architecture explicitly', function (): void {
    desktopRelease(['release_channel' => 'beta', 'architecture' => 'arm64', 'version' => '3.0.0-beta.2']);
    $this->getJson('/api/v1/releases/business-server/manifest?channel=beta&architecture=arm64')
        ->assertOk()->assertJsonPath('latestVersion', '3.0.0-beta.2')->assertJsonPath('architecture', 'arm64');
});

it('routes owner downloads through the latest product release and rejects withdrawn links', function (): void {
    $server = desktopRelease();
    $client = desktopRelease(['application' => 'client_app', 'version' => '3.0.0']);
    foreach (['business-server' => $server, 'business-app' => $client] as $slug => $version) {
        $response = $this->get('/downloads/'.$slug)->assertRedirect();
        $url = $response->headers->get('Location');
        expect($url)->toContain('/application-versions/'.$version->id.'/download');
        $this->get($url)->assertOk();
        $version->update(['is_active' => false]);
        $this->get($url)->assertNotFound();
        $this->get('/downloads/'.$slug)->assertNotFound();
    }
    $this->get('/downloads/unknown')->assertNotFound();
    $this->getJson('/downloads/business-server?architecture=invalid')->assertUnprocessable();
});
