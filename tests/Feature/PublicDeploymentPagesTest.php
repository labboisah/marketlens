<?php

use App\Domain\Versions\Models\ApplicationVersion;
use App\Enums\ApplicationVersionStatus;
use App\Livewire\PublicSite\DeploymentPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);
beforeEach(fn () => Storage::fake('downloads'));

it('offers public setup guidance and the complete versioned kit without login', function (): void {
    $pack = json_decode(file_get_contents(resource_path('setup-kits/manifest.json')), true, flags: JSON_THROW_ON_ERROR);
    $response = $this->get('/setup-kits')->assertOk()
        ->assertSee('25 template sets')
        ->assertSee('Download Business Server')
        ->assertSee('Download Business App')
        ->assertSee('Download setup templates (ZIP)')
        ->assertSee(route('public.desktop-download', ['application' => 'business-server']), false)
        ->assertSee(route('public.desktop-download', ['application' => 'business-app']), false)
        ->assertSee('Templates from Server 2.2.0')
        ->assertSee(route('public.client-setup-kit'), false)
        ->assertSee(route('public.deployment'), false)
        ->assertSee('10,000 rows');
    foreach ($pack['datasets'] as $dataset) {
        $response->assertSee($dataset['label']);
    }
    $this->get('/')->assertOk()
        ->assertDontSee('Download Business Server')
        ->assertDontSee('Download Business App')
        ->assertDontSee('Download setup templates (ZIP)')
        ->assertSee(route('public.deployment'), false);
    $this->get('/deployment')->assertOk()
        ->assertSee('Download setup templates (ZIP)')
        ->assertSee(route('public.client-setup-kit'), false)
        ->assertSee('No published installer for x64 yet')
        ->assertSee('Upgrade Server before Apps')
        ->assertSee(route('public.setup-kits'), false);
});

it('shows only published installers for the selected computer architecture', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('releases/server.exe', 'signed-fixture');
    $release = ApplicationVersion::query()->create([
        'application' => 'shop_app', 'platform' => 'windows', 'architecture' => 'x64',
        'version' => '2.1.0', 'release_channel' => 'stable',
        'status' => ApplicationVersionStatus::Published, 'is_active' => true,
        'download_disk' => 'local', 'download_path' => 'releases/server.exe',
        'download_original_name' => 'server.exe', 'checksum' => hash('sha256', 'signed-fixture'),
        'released_at' => now()->subMinute(),
    ]);
    $this->get('/deployment')->assertOk()->assertSee('Version 2.1.0')
        ->assertSee(route('public.desktop-download', ['application' => 'business-server', 'architecture' => 'x64']), false);
    Livewire::test(DeploymentPage::class)->set('architecture', 'arm64')
        ->assertSee('No published installer for arm64 yet')->assertDontSee('Version 2.1.0');
    $release->update(['is_active' => false]);
    $this->get('/deployment')->assertOk()->assertDontSee('Version 2.1.0');
    $this->get('/deployment?architecture=unsupported')->assertStatus(422);
});
