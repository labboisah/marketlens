<?php

namespace App\Livewire\Platform\Shareholders;

use App\Domain\Modules\Services\ModuleAccessService;
use App\Domain\Tenancy\Models\Business;
use App\Domain\Tenancy\Services\BranchContext;
use App\Domain\Tenancy\Services\BusinessContext;
use App\Enums\BusinessModule;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ShareholdersModulePage extends Component
{
    public function render(BusinessContext $businessContext, BranchContext $branchContext, ModuleAccessService $moduleAccess): View
    {
        $business = Business::query()->with('branches')->findOrFail($businessContext->id());
        $branch = $business->branches->firstWhere('id', $branchContext->id());

        return view('livewire.platform.shareholders.shareholders-module-page', [
            'access' => $moduleAccess->resolve($business, BusinessModule::Shareholders, $branch),
        ])->title(BusinessModule::Shareholders->displayName());
    }
}
