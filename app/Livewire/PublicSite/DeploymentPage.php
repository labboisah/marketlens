<?php

declare(strict_types=1);

namespace App\Livewire\PublicSite;

use App\Domain\Versions\Services\ApplicationVersionService;
use App\Domain\Versions\Services\ManagedReleasePackages;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.public')]
#[Title('Deployment and upgrades | MarketLens')]
class DeploymentPage extends Component
{
    #[Url]
    public string $architecture = 'x64';

    public function render(ApplicationVersionService $versions): View
    {
        abort_unless(in_array($this->architecture, ['x64', 'arm64', 'ia32'], true), 422);
        $installers = [];
        foreach (['business-server' => ['shop_app', 'Business Server', 'Install on the main branch computer. Owns your local business records.'], 'business-app' => ['client_app', 'Business App', 'Install on staff workstations and pair with the branch Server.']] as $key => [$application, $label, $description]) {
            $package = app(ManagedReleasePackages::class)->latest($key, $this->architecture);
            $manifest = $package ? null : $versions->desktopManifest($application, 'stable', $this->architecture);
            $installers[] = ['key' => $key, 'label' => $label, 'description' => $description, 'version' => $package['version'] ?? $manifest['latestVersion'] ?? null, 'channel' => $package['channel'] ?? 'stable', 'bundle' => (bool) $package];
        }

        return view('livewire.public-site.deployment-page', ['installers' => $installers]);
    }
}
