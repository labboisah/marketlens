<?php

namespace App\Domain\Reporting\Services;

use App\Domain\Reporting\Models\BusinessDashboardDailySummary;
use App\Domain\Reporting\Models\DashboardBranchSummary;
use App\Domain\Reporting\Models\DashboardBusinessActivity;
use App\Domain\Reporting\Models\DashboardCashierException;
use App\Domain\Reporting\Models\DashboardProductSummary;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class BusinessOverviewDashboard
{
    /**
     * @return array<string, mixed>
     */
    public function data(int $businessId, ?int $branchId, string $dateRange): array
    {
        $range = $this->range($dateRange);
        $cacheKey = implode(':', [
            'business-overview-dashboard',
            $businessId,
            $branchId ?: 'all',
            $dateRange,
            $range['start']->toDateString(),
            $range['end']->toDateString(),
        ]);

        return Cache::remember($cacheKey, now()->addMinutes(5), fn (): array => $this->freshData($businessId, $branchId, $range));
    }

    /**
     * @param  array{start: CarbonImmutable, end: CarbonImmutable}  $range
     * @return array<string, mixed>
     */
    private function freshData(int $businessId, ?int $branchId, array $range): array
    {
        $summaries = $this->forSummaryDateRange($this->summaryQuery($businessId, $branchId), $range['start'], $range['end'])->get();

        $todaySummary = $this->summaryQuery($businessId, $branchId)
            ->whereDate('summary_date', today())
            ->first();

        $latestSummary = $this->summaryQuery($businessId, $branchId)
            ->orderByDesc('summary_date')
            ->first();

        return [
            'hasData' => $summaries->isNotEmpty(),
            'metrics' => $this->metrics($summaries),
            'today' => $this->metrics(collect($todaySummary ? [$todaySummary] : [])),
            'comparisons' => $this->comparisons($businessId, $branchId),
            'freshness' => [
                'last_successful_sync_at' => $latestSummary?->last_successful_sync_at,
                'summarized_at' => $latestSummary?->summarized_at,
                'is_stale' => ! $latestSummary?->last_successful_sync_at || $latestSummary->last_successful_sync_at->lt(now()->subHours(2)),
            ],
            'salesTrend' => $this->salesTrend($summaries),
            'paymentBreakdown' => $this->paymentBreakdown($summaries),
            'stockAlerts' => $this->productQuery($businessId, $branchId)->where('is_low_stock', true)->orderBy('stock_quantity')->limit(6)->get(),
            'topProducts' => $this->productQuery($businessId, $branchId)->orderByDesc('quantity_sold')->limit(6)->get(),
            'slowProducts' => $this->productQuery($businessId, $branchId)->where('days_without_sale', '>', 0)->orderByDesc('days_without_sale')->limit(6)->get(),
            'branchPerformance' => $this->branchPerformance($businessId, $range),
            'cashierExceptions' => $this->cashierQuery($businessId, $branchId)->orderByDesc('summary_date')->limit(6)->get(),
            'securityAlerts' => $this->activityQuery($businessId, $branchId)->where('type', 'security_alert')->limit(6)->get(),
            'recentActivity' => $this->activityQuery($businessId, $branchId)->limit(8)->get(),
        ];
    }

    /**
     * @param  Collection<int, BusinessDashboardDailySummary>  $summaries
     * @return array<string, int>
     */
    private function metrics(Collection $summaries): array
    {
        return [
            'gross_sales_minor' => (int) $summaries->sum('gross_sales_minor'),
            'net_sales_minor' => (int) $summaries->sum('net_sales_minor'),
            'completed_sales_count' => (int) $summaries->sum('completed_sales_count'),
            'cash_payments_minor' => (int) $summaries->sum('cash_payments_minor'),
            'bank_transfer_payments_minor' => (int) $summaries->sum('bank_transfer_payments_minor'),
            'pos_terminal_payments_minor' => (int) $summaries->sum('pos_terminal_payments_minor'),
            'credit_sales_minor' => (int) $summaries->sum('credit_sales_minor'),
            'expenses_minor' => (int) $summaries->sum('expenses_minor'),
            'gross_profit_minor' => (int) $summaries->sum('gross_profit_minor'),
            'estimated_net_profit_minor' => (int) $summaries->sum('estimated_net_profit_minor'),
            'customer_debt_minor' => (int) $summaries->max('customer_debt_minor'),
            'supplier_balance_minor' => (int) $summaries->max('supplier_balance_minor'),
            'current_stock_value_minor' => (int) $summaries->max('current_stock_value_minor'),
            'low_stock_products_count' => (int) $summaries->max('low_stock_products_count'),
            'cashier_variance_minor' => (int) $summaries->sum('cashier_variance_minor'),
        ];
    }

    /**
     * @return array<string, array{current: int, previous: int, difference: int}>
     */
    private function comparisons(int $businessId, ?int $branchId): array
    {
        return [
            'today_vs_yesterday' => $this->compare($businessId, $branchId, today(), today(), today()->subDay(), today()->subDay()),
            'week_vs_previous_week' => $this->compare($businessId, $branchId, today()->startOfWeek(), today()->endOfWeek(), today()->subWeek()->startOfWeek(), today()->subWeek()->endOfWeek()),
            'month_vs_previous_month' => $this->compare($businessId, $branchId, today()->startOfMonth(), today()->endOfMonth(), today()->subMonthNoOverflow()->startOfMonth(), today()->subMonthNoOverflow()->endOfMonth()),
        ];
    }

    private function compare(int $businessId, ?int $branchId, mixed $currentStart, mixed $currentEnd, mixed $previousStart, mixed $previousEnd): array
    {
        $current = (int) $this->summaryQuery($businessId, $branchId)
            ->whereDate('summary_date', '>=', $currentStart->toDateString())
            ->whereDate('summary_date', '<=', $currentEnd->toDateString())
            ->sum('net_sales_minor');
        $previous = (int) $this->summaryQuery($businessId, $branchId)
            ->whereDate('summary_date', '>=', $previousStart->toDateString())
            ->whereDate('summary_date', '<=', $previousEnd->toDateString())
            ->sum('net_sales_minor');

        return [
            'current' => $current,
            'previous' => $previous,
            'difference' => $current - $previous,
        ];
    }

    /**
     * @param  Collection<int, BusinessDashboardDailySummary>  $summaries
     */
    private function salesTrend(Collection $summaries): array
    {
        return $summaries
            ->sortBy('summary_date')
            ->map(fn (BusinessDashboardDailySummary $summary): array => [
                'date' => $summary->summary_date->toDateString(),
                'gross_sales_minor' => (int) $summary->gross_sales_minor,
                'net_sales_minor' => (int) $summary->net_sales_minor,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, BusinessDashboardDailySummary>  $summaries
     */
    private function paymentBreakdown(Collection $summaries): array
    {
        return [
            'cash' => (int) $summaries->sum('cash_payments_minor'),
            'bank_transfer' => (int) $summaries->sum('bank_transfer_payments_minor'),
            'pos_terminal' => (int) $summaries->sum('pos_terminal_payments_minor'),
            'credit' => (int) $summaries->sum('credit_sales_minor'),
        ];
    }

    private function branchPerformance(int $businessId, array $range): Collection
    {
        return DashboardBranchSummary::query()
            ->with('branch')
            ->where('business_id', $businessId)
            ->whereDate('summary_date', '>=', $range['start']->toDateString())
            ->whereDate('summary_date', '<=', $range['end']->toDateString())
            ->orderByDesc('net_sales_minor')
            ->limit(8)
            ->get();
    }

    private function forSummaryDateRange(mixed $query, CarbonImmutable $start, CarbonImmutable $end): mixed
    {
        return $query
            ->whereDate('summary_date', '>=', $start->toDateString())
            ->whereDate('summary_date', '<=', $end->toDateString());
    }

    private function summaryQuery(int $businessId, ?int $branchId)
    {
        return BusinessDashboardDailySummary::query()
            ->where('business_id', $businessId)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId));
    }

    private function productQuery(int $businessId, ?int $branchId)
    {
        return DashboardProductSummary::query()
            ->where('business_id', $businessId)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->orderByDesc('summary_date');
    }

    private function cashierQuery(int $businessId, ?int $branchId)
    {
        return DashboardCashierException::query()
            ->where('business_id', $businessId)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId));
    }

    private function activityQuery(int $businessId, ?int $branchId)
    {
        return DashboardBusinessActivity::query()
            ->where('business_id', $businessId)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->orderByDesc('occurred_at');
    }

    /**
     * @return array{start: CarbonImmutable, end: CarbonImmutable}
     */
    private function range(string $dateRange): array
    {
        $today = CarbonImmutable::today();

        return match ($dateRange) {
            '7_days' => ['start' => $today->subDays(6), 'end' => $today],
            '30_days' => ['start' => $today->subDays(29), 'end' => $today],
            'month' => ['start' => $today->startOfMonth(), 'end' => $today->endOfMonth()],
            default => ['start' => $today, 'end' => $today],
        };
    }
}
