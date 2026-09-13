<?php

declare(strict_types=1);

namespace App\Livewire\Platform\Production;

use App\Domain\Production\ProductionMonitoring;
use App\Domain\Tenancy\Models\Business;
use App\Domain\Tenancy\Services\BranchContext;
use App\Domain\Tenancy\Services\BusinessContext;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ProductionPage extends Component
{
    public function render(BusinessContext $businessContext, BranchContext $branchContext, ProductionMonitoring $monitoring): View
    {
        $business = Business::query()->findOrFail($businessContext->id());

        return view('livewire.platform.production.production-page', [
            'data' => $monitoring->data(auth()->user(), $business, $branchContext->id()),
            'currency' => $business->default_currency,
        ])->title('Production');
    }
}
