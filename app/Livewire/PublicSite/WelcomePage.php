<?php

namespace App\Livewire\PublicSite;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.public')]
#[Title('MarketLens | Retail visibility and control')]
class WelcomePage extends Component
{
    public function render()
    {
        return view('livewire.public-site.welcome-page');
    }
}
