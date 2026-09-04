<?php

namespace App\Domain\Branches\Services;

use App\Domain\Devices\Models\Device;
use App\Domain\Reporting\Models\DashboardBranchSummary;
use App\Domain\Tenancy\Models\Branch;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class MultiBranchMonitoringService
{
    /**
     * @param  array{start: CarbonImmutable, end: CarbonImmutable}  $range
     * @return Collection<int, array<string, mixed>>
     */
    public function comparison(int $businessId, array $range, string $rankingMetric): Collection
    {
        $summaries = DashboardBranchSummary::query()
            ->with('branch')
            ->where('business_id', $businessId)
            ->whereDate('summary_date', '>=', $range['start']->toDateString())
            ->whereDate('summary_date', '<=', $range['end']->toDateString())
            ->get()
            ->groupBy('branch_id');

        $activeClientApps = Device::query()
            ->where('business_id', $businessId)
            ->where('type', 'client_app')
            ->where('status', 'active')
            ->selectRaw('branch_id, count(*) as active_client_apps_count')
            ->groupBy('branch_id')
            ->pluck('active_client_apps_count', 'branch_id');

        return Branch::query()
            ->where('business_id', $businessId)
            ->orderBy('name')
            ->get()
            ->map(function (Branch $branch) use ($summaries, $activeClientApps): array {
                $branchSummaries = $summaries->get($branch->id, collect());
                $latest = $branchSummaries->sortByDesc('summary_date')->first();
                $lastSync = $latest?->last_successful_sync_at ?? $branch->last_synchronized_at;
                $isStale = ! $lastSync || Carbon::parse($lastSync)->lt(now()->subHours(2));

                return [
                    'branch_id' => $branch->id,
                    'branch_name' => $branch->name,
                    'branch_code' => $branch->code,
                    'status' => $branch->status?->value ?? (string) $branch->status,
                    'gross_sales_minor' => (int) $branchSummaries->sum('gross_sales_minor'),
                    'net_sales_minor' => (int) $branchSummaries->sum('net_sales_minor'),
                    'transaction_count' => (int) $branchSummaries->sum('completed_sales_count'),
                    'gross_profit_minor' => (int) $branchSummaries->sum('gross_profit_minor'),
                    'expenses_minor' => (int) $branchSummaries->sum('expenses_minor'),
                    'estimated_net_profit_minor' => (int) $branchSummaries->sum('estimated_net_profit_minor'),
                    'stock_value_minor' => (int) $branchSummaries->max('stock_value_minor'),
                    'customer_debt_minor' => (int) $branchSummaries->max('customer_debt_minor'),
                    'supplier_balance_minor' => (int) $branchSummaries->max('supplier_balance_minor'),
                    'cashier_variance_minor' => (int) $branchSummaries->sum('cashier_variance_minor'),
                    'low_stock_count' => (int) $branchSummaries->max('low_stock_count'),
                    'last_successful_sync_at' => $lastSync ? Carbon::parse($lastSync) : null,
                    'active_client_apps_count' => (int) (($latest?->active_client_apps_count ?: null) ?? $activeClientApps->get($branch->id, 0)),
                    'has_data' => $branchSummaries->isNotEmpty(),
                    'is_stale' => $isStale,
                    'health' => $this->health($isStale, $branchSummaries),
                ];
            })
            ->sortByDesc(fn (array $row): int => $row[$rankingMetric] ?? 0)
            ->values();
    }

    /**
     * @param  array{start: CarbonImmutable, end: CarbonImmutable}  $range
     * @return array<string, mixed>
     */
    public function data(int $businessId, array $range, string $rankingMetric, ?int $selectedBranchId = null): array
    {
        $comparison = $this->comparison($businessId, $range, $rankingMetric);
        $selected = $comparison->firstWhere('branch_id', $selectedBranchId) ?? $comparison->first();

        return [
            'comparison' => $comparison,
            'cards' => $comparison->take(4),
            'ranking' => $comparison->sortBy([
                ['is_stale', 'asc'],
                [$rankingMetric, 'desc'],
            ])->values(),
            'trend' => $this->trend($businessId, $range),
            'selectedBranch' => $selected,
            'selectedTrend' => $selected ? $this->trend($businessId, $range, (int) $selected['branch_id']) : collect(),
            'health' => [
                'branch_count' => $comparison->count(),
                'fresh_count' => $comparison->where('is_stale', false)->count(),
                'stale_count' => $comparison->where('is_stale', true)->count(),
                'missing_data_count' => $comparison->where('has_data', false)->count(),
            ],
        ];
    }

    /**
     * @param  array{start: CarbonImmutable, end: CarbonImmutable}  $range
     */
    private function trend(int $businessId, array $range, ?int $branchId = null): Collection
    {
        return DashboardBranchSummary::query()
            ->with('branch')
            ->where('business_id', $businessId)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereDate('summary_date', '>=', $range['start']->toDateString())
            ->whereDate('summary_date', '<=', $range['end']->toDateString())
            ->orderBy('summary_date')
            ->get()
            ->map(fn (DashboardBranchSummary $summary): array => [
                'date' => $summary->summary_date->toDateString(),
                'branch_name' => $summary->branch?->name ?? 'Branch',
                'net_sales_minor' => (int) $summary->net_sales_minor,
                'gross_profit_minor' => (int) $summary->gross_profit_minor,
                'expenses_minor' => (int) $summary->expenses_minor,
                'low_stock_count' => (int) $summary->low_stock_count,
            ]);
    }

    /**
     * @param  Collection<int, DashboardBranchSummary>  $summaries
     */
    private function health(bool $isStale, Collection $summaries): string
    {
        if ($summaries->isEmpty()) {
            return 'No synchronized data';
        }

        if ($isStale) {
            return 'Stale data';
        }

        if ((int) $summaries->max('low_stock_count') > 0 || abs((int) $summaries->sum('cashier_variance_minor')) > 0) {
            return 'Requires review';
        }

        return 'Healthy';
    }

    /**
     * @return array{start: CarbonImmutable, end: CarbonImmutable}
     */
    public function range(string $period, ?string $customFrom = null, ?string $customTo = null): array
    {
        $today = CarbonImmutable::today();

        return match ($period) {
            'week' => ['start' => $today->startOfWeek(), 'end' => $today->endOfWeek()],
            'month' => ['start' => $today->startOfMonth(), 'end' => $today->endOfMonth()],
            'quarter' => ['start' => $today->startOfQuarter(), 'end' => $today->endOfQuarter()],
            'custom' => [
                'start' => $customFrom ? CarbonImmutable::parse($customFrom) : $today,
                'end' => $customTo ? CarbonImmutable::parse($customTo) : $today,
            ],
            default => ['start' => $today, 'end' => $today],
        };
    }
}
