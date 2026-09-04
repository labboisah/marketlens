<?php

namespace App\Livewire\PublicSite;

use App\Domain\Billing\Models\Plan;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.public')]
#[Title('MarketLens Pricing | Plans for retail businesses')]
class PricingPage extends Component
{
    public function render(): View
    {
        $plans = Plan::query()
            ->with('features')
            ->active()
            ->orderBy('sort_order')
            ->orderByRaw("case billing_interval when 'monthly' then 1 when 'annual' then 2 else 3 end")
            ->get()
            ->groupBy('name');

        return view('livewire.public-site.pricing-page', [
            'plans' => $plans,
        ]);
    }
}
