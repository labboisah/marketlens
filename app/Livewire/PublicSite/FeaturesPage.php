<?php

namespace App\Livewire\PublicSite;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.public')]
#[Title('MarketLens Features | Sales, inventory, sync and monitoring')]
class FeaturesPage extends Component
{
    public function render()
    {
        return view('livewire.public-site.features-page');
    }
}
