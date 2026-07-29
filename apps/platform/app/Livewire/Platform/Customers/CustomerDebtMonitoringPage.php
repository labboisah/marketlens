<?php

namespace App\Livewire\Platform\Customers;

use App\Domain\Customers\Services\CustomerDebtMonitoringService;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Services\BranchContext;
use App\Domain\Tenancy\Services\BusinessContext;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class CustomerDebtMonitoringPage extends Component
{
    use WithPagination;

    #[Url]
    public string $view = 'overview';

    #[Url]
    public ?string $branchFilter = null;

    #[Url]
    public ?string $customer = null;

    #[Url]
    public ?string $debtStatus = null;

    public function render(BusinessContext $businessContext, BranchContext $branchContext, CustomerDebtMonitoringService $debts): View
    {
        $businessId = $this->resolvedBusinessId($businessContext);
        $branchId = $this->resolvedBranchId($branchContext);
        $filters = $this->filters();

        if ($businessId && $this->canViewCustomerDebts($businessId) === false) {
            return view('livewire.platform.customers.customer-debt-monitoring-page', [
                'businessId' => $businessId,
                'accessDenied' => true,
                'overview' => null,
                'balances' => null,
                'payments' => null,
                'ageing' => [],
                'branchComparison' => collect(),
                'branches' => collect(),
            ])->title('Customer debt monitoring');
        }

        return view('livewire.platform.customers.customer-debt-monitoring-page', [
            'businessId' => $businessId,
            'accessDenied' => false,
            'overview' => $businessId ? $debts->overview($businessId, $branchId, $filters) : null,
            'balances' => $businessId ? $debts->balances($businessId, $branchId, $filters) : null,
            'payments' => $businessId ? $debts->payments($businessId, $branchId, $filters) : null,
            'ageing' => $businessId ? $debts->ageing($businessId, $branchId, $filters) : [],
            'branchComparison' => $businessId ? $debts->branchComparison($businessId, $filters) : collect(),
            'branches' => $businessId ? Branch::query()->where('business_id', $businessId)->orderBy('name')->get() : collect(),
        ])->title('Customer debt monitoring');
    }

    public function money(int $minor): string
    {
        return 'NGN '.number_format($minor / 100, 2);
    }

    private function filters(): array
    {
        return [
            'branch_id' => $this->branchFilter,
            'customer' => $this->customer,
            'debt_status' => $this->debtStatus,
            'overdue' => $this->view === 'overdue',
        ];
    }

    private function resolvedBusinessId(BusinessContext $businessContext): ?int
    {
        return $businessContext->id() ?: auth()->user()?->ownedBusinesses()->value('businesses.id');
    }

    private function resolvedBranchId(BranchContext $branchContext): ?int
    {
        return $this->branchFilter ? (int) $this->branchFilter : $branchContext->id();
    }

    private function canViewCustomerDebts(int $businessId): bool
    {
        $business = auth()->user()?->ownedBusinesses()
            ->with('currentSubscription.plan.features')
            ->whereKey($businessId)
            ->first();

        $features = $business?->currentSubscription?->plan?->features
            ->pluck('name')
            ->map(fn (string $name): string => strtolower($name))
            ->all() ?? [];

        return collect($features)->contains(fn (string $feature): bool => str_contains($feature, 'customer debts') || str_contains($feature, 'reports'));
    }
}
