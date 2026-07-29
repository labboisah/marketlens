<?php

namespace App\Livewire\Platform\Branches;

use App\Domain\Branches\Services\MultiBranchMonitoringService;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Services\BusinessContext;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class MultiBranchMonitoringPage extends Component
{
    #[Url]
    public string $period = 'today';

    #[Url]
    public ?string $from = null;

    #[Url]
    public ?string $to = null;

    #[Url]
    public string $rankingMetric = 'net_sales_minor';

    #[Url]
    public ?string $branchFilter = null;

    public function render(BusinessContext $businessContext, MultiBranchMonitoringService $branches): View
    {
        $businessId = $this->resolvedBusinessId($businessContext);
        $range = $branches->range($this->period, $this->from, $this->to);
        $metric = $this->allowedRankingMetric($this->rankingMetric);

        if ($businessId && $this->canViewBranchMonitoring($businessId) === false) {
            return view('livewire.platform.branches.multi-branch-monitoring-page', [
                'businessId' => $businessId,
                'accessDenied' => true,
                'range' => $range,
                'data' => null,
                'branches' => collect(),
                'rankingOptions' => $this->rankingOptions(),
            ])->title('Multi-branch monitoring');
        }

        return view('livewire.platform.branches.multi-branch-monitoring-page', [
            'businessId' => $businessId,
            'accessDenied' => false,
            'range' => $range,
            'data' => $businessId ? $branches->data($businessId, $range, $metric, $this->branchFilter ? (int) $this->branchFilter : null) : null,
            'branches' => $businessId ? Branch::query()->where('business_id', $businessId)->orderBy('name')->get() : collect(),
            'rankingOptions' => $this->rankingOptions(),
        ])->title('Multi-branch monitoring');
    }

    public function money(int $minor): string
    {
        $sign = $minor < 0 ? '-' : '';

        return $sign.'NGN '.number_format(abs($minor) / 100, 2);
    }

    /**
     * @return array<string, string>
     */
    public function rankingOptions(): array
    {
        return [
            'gross_sales_minor' => 'Gross sales',
            'net_sales_minor' => 'Net sales',
            'transaction_count' => 'Transaction count',
            'gross_profit_minor' => 'Gross profit',
            'expenses_minor' => 'Expenses',
            'estimated_net_profit_minor' => 'Estimated net profit',
            'stock_value_minor' => 'Stock value',
            'customer_debt_minor' => 'Customer debt',
            'supplier_balance_minor' => 'Supplier balance',
            'cashier_variance_minor' => 'Cash shortage',
            'low_stock_count' => 'Low-stock count',
            'active_client_apps_count' => 'Active Client Apps',
        ];
    }

    private function allowedRankingMetric(string $metric): string
    {
        return array_key_exists($metric, $this->rankingOptions()) ? $metric : 'net_sales_minor';
    }

    private function resolvedBusinessId(BusinessContext $businessContext): ?int
    {
        return $businessContext->id() ?: auth()->user()?->ownedBusinesses()->value('businesses.id');
    }

    private function canViewBranchMonitoring(int $businessId): bool
    {
        $business = auth()->user()?->ownedBusinesses()
            ->with('currentSubscription.plan.features')
            ->whereKey($businessId)
            ->first();

        $features = $business?->currentSubscription?->plan?->features
            ->pluck('name')
            ->map(fn (string $name): string => strtolower($name))
            ->all() ?? [];

        return collect($features)->contains(fn (string $feature): bool => str_contains($feature, 'multiple branches') || str_contains($feature, 'remote dashboard') || str_contains($feature, 'reports'));
    }
}
