<?php

namespace App\Livewire\PublicSite;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.public')]
#[Title('MarketLens Product | Offline-first retail platform')]
class ProductPage extends Component
{
    public function render()
    {
        return view('livewire.public-site.product-page');
    }
}
