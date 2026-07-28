<?php

namespace App\Livewire\Platform\Administration;

use App\Domain\Reporting\Services\BusinessOverviewDashboard;
use App\Domain\Tenancy\Services\BranchContext;
use App\Domain\Tenancy\Services\BusinessContext;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Dashboard extends Component
{
    public function render(BusinessContext $businessContext, BranchContext $branchContext, BusinessOverviewDashboard $dashboard)
    {
        $businessId = $businessContext->id();
        $dateRange = session('platform.date_range', 'today');

        return view('livewire.platform.administration.dashboard', [
            'dashboard' => $businessId
                ? $dashboard->data($businessId, $branchContext->id(), $dateRange)
                : null,
            'dateRange' => $dateRange,
        ]);
    }

    public function money(int $minor): string
    {
        $sign = $minor < 0 ? '-' : '';

        return $sign.'NGN '.number_format(abs($minor) / 100, 2);
    }

    public function number(int|float|string $value): string
    {
        return number_format((float) $value, is_float($value) ? 2 : 0);
    }
}
