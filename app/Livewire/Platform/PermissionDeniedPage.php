<?php

namespace App\Livewire\Platform;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class PermissionDeniedPage extends Component
{
    public function render()
    {
        return view('livewire.platform.permission-denied-page');
    }
}
