<?php

namespace App\Livewire\Platform\Services;

use App\Domain\Modules\Services\ModuleAccessService;
use App\Domain\Synchronization\Services\SynchronizedOperationalData;
use App\Domain\Tenancy\Models\Business;
use App\Domain\Tenancy\Services\BranchContext;
use App\Domain\Tenancy\Services\BusinessContext;
use App\Enums\BusinessModule;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ServicesModulePage extends Component
{
    public function render(
        BusinessContext $businessContext,
        BranchContext $branchContext,
        ModuleAccessService $moduleAccess,
        SynchronizedOperationalData $synchronizedData,
    ): View {
        $business = Business::query()->with('branches')->findOrFail($businessContext->id());
        $branch = $business->branches->firstWhere('id', $branchContext->id());

        return view('livewire.platform.services.services-module-page', [
            'access' => $moduleAccess->resolve($business, BusinessModule::Services, $branch),
            'data' => $synchronizedData->services($business->id, $branch?->id),
            'currency' => $business->default_currency,
        ])->title(BusinessModule::Services->displayName());
    }
}
