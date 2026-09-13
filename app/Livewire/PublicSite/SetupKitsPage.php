<?php

declare(strict_types=1);

namespace App\Livewire\PublicSite;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.public')]
#[Title('Business setup kits and CSV templates | MarketLens')]
class SetupKitsPage extends Component
{
    public function render(): View
    {
        $path = resource_path('setup-kits/manifest.json');
        $pack = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        $zip = resource_path('setup-kits/marketlens-client-setup-v1.zip');
        $available = is_array($pack) && is_string($pack['sha256'] ?? null) && is_file($zip)
            && hash_equals($pack['sha256'], (string) hash_file('sha256', $zip));

        return view('livewire.public-site.setup-kits-page', ['pack' => $available ? $pack : null]);
    }
}
