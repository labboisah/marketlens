<?php

namespace App\Livewire\PublicSite;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.public')]
#[Title('How MarketLens Works | Simple retail architecture')]
class HowItWorksPage extends Component
{
    public function render()
    {
        return view('livewire.public-site.how-it-works-page');
    }
}
