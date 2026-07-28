<?php

namespace App\Livewire\Platform\Administration;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Dashboard extends Component
{
    public array $summary = [
        'businesses' => 0,
        'branches' => 0,
        'active_devices' => 0,
        'sync_batches_today' => 0,
    ];

    public function render()
    {
        return view('livewire.platform.administration.dashboard');
    }
}
