<?php

namespace App\Livewire\PublicSite;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.public')]
#[Title('MarketLens Business Types | Retail profiles')]
class BusinessTypesPage extends Component
{
    public function render()
    {
        return view('livewire.public-site.business-types-page');
    }
}
