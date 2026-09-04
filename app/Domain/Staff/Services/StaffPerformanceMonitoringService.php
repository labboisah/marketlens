<?php

namespace App\Domain\Staff\Services;

use App\Domain\Payments\Models\ReplicatedCashierSettlement;
use App\Domain\Payments\Models\ReplicatedCashierShift;
use App\Domain\Reporting\Models\StaffActivitySummary;
use App\Domain\Sales\Models\ReplicatedSale;
use App\Domain\Synchronization\Models\OperationalUserProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class StaffPerformanceMonitoringService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function profilesQuery(int $businessId, ?int $branchId, array $filters = []): Builder
    {
        return OperationalUserProfile::query()
            ->with('branch')
            ->where('business_id', $businessId)
            ->when($branchId, fn (Builder $query): Builder => $query->where('branch_id', $branchId))
            ->when($filters['branch_id'] ?? null, fn (Builder $query, int|string $branch): Builder => $query->where('branch_id', $branch))
            ->when($filters['role'] ?? null, fn (Builder $query, string $role): Builder => $query->where(function (Builder $query) use ($role): void {
                $query->where('role_code', 'like', "%{$role}%")->orWhere('role_name', 'like', "%{$role}%");
            }))
            ->when($filters['staff'] ?? null, fn (Builder $query, string $staff): Builder => $query->where(function (Builder $query) use ($staff): void {
                $query->where('uuid', $staff)
                    ->orWhere('display_name', 'like', "%{$staff}%")
                    ->orWhere('staff_code', 'like', "%{$staff}%")
                    ->orWhere('username', 'like', "%{$staff}%");
            }));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function cashierPerformance(int $businessId, ?int $branchId, array $filters): Collection
    {
        $sales = $this->salesQuery($businessId, $branchId, $filters)
            ->selectRaw('cashier_uuid, coalesce(cashier_name, \'Unknown cashier\') as cashier_name')
            ->selectRaw('count(*) as transaction_count')
            ->selectRaw('sum(total_minor) as total_sales_minor')
            ->selectRaw('sum(discount_minor) as discounts_minor')
            ->selectRaw('sum(case when status = \'returned\' then 1 else 0 end) as returned_count')
            ->selectRaw('sum(case when status = \'cancelled\' then 1 else 0 end) as cancelled_count')
            ->selectRaw('max(source_occurred_at) as last_synchronized_at')
            ->groupBy('cashier_uuid', 'cashier_name')
            ->get()
            ->keyBy(fn ($row): string => $row->cashier_uuid ?: 'name:'.$row->cashier_name);

        $settlements = $this->settlementQuery($businessId, $branchId, $filters)
            ->selectRaw('cashier_uuid, coalesce(cashier_name, \'Unknown cashier\') as cashier_name')
            ->selectRaw('sum(expected_cash_minor) as expected_cash_minor')
            ->selectRaw('sum(actual_cash_minor) as actual_cash_minor')
            ->selectRaw('sum(shortage_minor) as shortage_minor')
            ->selectRaw('sum(surplus_minor) as surplus_minor')
            ->selectRaw('max(settled_at) as last_settled_at')
            ->groupBy('cashier_uuid', 'cashier_name')
            ->get()
            ->keyBy(fn ($row): string => $row->cashier_uuid ?: 'name:'.$row->cashier_name);

        $shifts = $this->shiftQuery($businessId, $branchId, $filters)
            ->selectRaw('cashier_uuid, coalesce(cashier_name, \'Unknown cashier\') as cashier_name')
            ->selectRaw('sum(duration_minutes) as shift_duration_minutes')
            ->selectRaw('max(last_activity_at) as last_active_shift_at')
            ->groupBy('cashier_uuid', 'cashier_name')
            ->get()
            ->keyBy(fn ($row): string => $row->cashier_uuid ?: 'name:'.$row->cashier_name);

        return $sales->keys()
            ->merge($settlements->keys())
            ->merge($shifts->keys())
            ->unique()
            ->map(function (string $key) use ($sales, $settlements, $shifts): array {
                $sale = $sales->get($key);
                $settlement = $settlements->get($key);
                $shift = $shifts->get($key);
                $transactionCount = (int) ($sale->transaction_count ?? 0);
                $totalSales = (int) ($sale->total_sales_minor ?? 0);

                return [
                    'cashier_uuid' => $sale->cashier_uuid ?? $settlement->cashier_uuid ?? $shift->cashier_uuid ?? null,
                    'cashier_name' => $sale->cashier_name ?? $settlement->cashier_name ?? $shift->cashier_name ?? 'Unknown cashier',
                    'total_sales_minor' => $totalSales,
                    'transaction_count' => $transactionCount,
                    'average_sale_minor' => $transactionCount > 0 ? intdiv($totalSales, $transactionCount) : 0,
                    'discounts_minor' => (int) ($sale->discounts_minor ?? 0),
                    'returned_count' => (int) ($sale->returned_count ?? 0),
                    'cancelled_count' => (int) ($sale->cancelled_count ?? 0),
                    'expected_cash_minor' => (int) ($settlement->expected_cash_minor ?? 0),
                    'actual_cash_minor' => (int) ($settlement->actual_cash_minor ?? 0),
                    'shortage_minor' => (int) ($settlement->shortage_minor ?? 0),
                    'surplus_minor' => (int) ($settlement->surplus_minor ?? 0),
                    'shift_duration_minutes' => (int) ($shift->shift_duration_minutes ?? 0),
                    'last_active_shift_at' => $shift->last_active_shift_at ?? $settlement->last_settled_at ?? null,
                    'last_synchronized_at' => $sale->last_synchronized_at ?? null,
                ];
            })
            ->sortByDesc('total_sales_minor')
            ->values();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function storekeeperActivity(int $businessId, ?int $branchId, array $filters): Collection
    {
        return $this->summaryQuery($businessId, $branchId, $filters)
            ->where(function (Builder $query): void {
                $query->whereRaw('lower(role_name) like ?', ['%store%'])
                    ->orWhereRaw('lower(role_name) like ?', ['%inventory%'])
                    ->orWhere('purchases_recorded_count', '>', 0)
                    ->orWhere('stock_counts_count', '>', 0)
                    ->orWhere('stock_adjustments_count', '>', 0)
                    ->orWhere('damaged_stock_records_count', '>', 0)
                    ->orWhere('transfers_processed_count', '>', 0);
            })
            ->selectRaw('operational_user_uuid, display_name, role_name')
            ->selectRaw('sum(purchases_recorded_count) as purchases_recorded_count')
            ->selectRaw('sum(stock_counts_count) as stock_counts_count')
            ->selectRaw('sum(stock_adjustments_count) as stock_adjustments_count')
            ->selectRaw('sum(damaged_stock_records_count) as damaged_stock_records_count')
            ->selectRaw('sum(transfers_processed_count) as transfers_processed_count')
            ->selectRaw('sum(repeated_stock_adjustment_count) as repeated_stock_adjustment_count')
            ->selectRaw('max(last_activity_at) as last_activity_at')
            ->groupBy('operational_user_uuid', 'display_name', 'role_name')
            ->orderByDesc('last_activity_at')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function managerActivity(int $businessId, ?int $branchId, array $filters): Collection
    {
        return $this->summaryQuery($businessId, $branchId, $filters)
            ->where(function (Builder $query): void {
                $query->whereRaw('lower(role_name) like ?', ['%manager%'])
                    ->orWhereRaw('lower(role_name) like ?', ['%supervisor%'])
                    ->orWhere('discounts_approved_count', '>', 0)
                    ->orWhere('expenses_approved_count', '>', 0)
                    ->orWhere('returns_approved_count', '>', 0)
                    ->orWhere('stock_adjustments_approved_count', '>', 0)
                    ->orWhere('cashier_settlements_reviewed_count', '>', 0);
            })
            ->selectRaw('operational_user_uuid, display_name, role_name')
            ->selectRaw('sum(discounts_approved_count) as discounts_approved_count')
            ->selectRaw('sum(expenses_approved_count) as expenses_approved_count')
            ->selectRaw('sum(returns_approved_count) as returns_approved_count')
            ->selectRaw('sum(stock_adjustments_approved_count) as stock_adjustments_approved_count')
            ->selectRaw('sum(cashier_settlements_reviewed_count) as cashier_settlements_reviewed_count')
            ->selectRaw('max(last_activity_at) as last_activity_at')
            ->groupBy('operational_user_uuid', 'display_name', 'role_name')
            ->orderByDesc('last_activity_at')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, Collection>
     */
    public function exceptions(int $businessId, ?int $branchId, array $filters): array
    {
        $cashiers = $this->cashierPerformance($businessId, $branchId, $filters);

        return [
            'frequentShortages' => $cashiers->where('shortage_minor', '>', 0)->sortByDesc('shortage_minor')->values(),
            'excessiveDiscounts' => $cashiers->filter(fn (array $row): bool => $row['discounts_minor'] >= 50000 || ($row['transaction_count'] > 0 && $row['discounts_minor'] / max($row['total_sales_minor'], 1) >= 0.15))->values(),
            'highCancellations' => $cashiers->filter(fn (array $row): bool => $row['transaction_count'] >= 3 && $row['cancelled_count'] / max($row['transaction_count'], 1) >= 0.2)->values(),
            'highReturns' => $cashiers->filter(fn (array $row): bool => $row['transaction_count'] >= 3 && $row['returned_count'] / max($row['transaction_count'], 1) >= 0.2)->values(),
            'repeatedStockAdjustments' => $this->summaryQuery($businessId, $branchId, $filters)->where('repeated_stock_adjustment_count', '>', 0)->latest('last_activity_at')->limit(25)->get(),
            'outsideNormalHours' => $this->summaryQuery($businessId, $branchId, $filters)->where('outside_normal_hours_count', '>', 0)->latest('last_activity_at')->limit(25)->get(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    public function overview(int $businessId, ?int $branchId, array $filters): array
    {
        return [
            'staff_count' => (int) $this->profilesQuery($businessId, $branchId, $filters)->count(),
            'cashier_count' => (int) $this->profilesQuery($businessId, $branchId, ['role' => 'cashier'])->count(),
            'storekeeper_count' => (int) $this->profilesQuery($businessId, $branchId, ['role' => 'store'])->count(),
            'manager_count' => (int) $this->profilesQuery($businessId, $branchId, ['role' => 'manager'])->count(),
            'total_sales_minor' => (int) $this->salesQuery($businessId, $branchId, $filters)->sum('total_minor'),
            'shortage_minor' => (int) $this->settlementQuery($businessId, $branchId, $filters)->sum('shortage_minor'),
            'surplus_minor' => (int) $this->settlementQuery($businessId, $branchId, $filters)->sum('surplus_minor'),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function activitySummaries(int $businessId, ?int $branchId, array $filters): Collection
    {
        return $this->summaryQuery($businessId, $branchId, $filters)
            ->latest('last_activity_at')
            ->limit(50)
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function latestSynchronization(int $businessId, ?int $branchId, array $filters): ?Carbon
    {
        $latest = $this->salesQuery($businessId, $branchId, $filters)->max('source_occurred_at')
            ?? $this->settlementQuery($businessId, $branchId, $filters)->max('source_occurred_at')
            ?? $this->summaryQuery($businessId, $branchId, $filters)->max('last_activity_at');

        return $latest ? Carbon::parse($latest) : null;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function salesQuery(int $businessId, ?int $branchId, array $filters = []): Builder
    {
        return ReplicatedSale::query()
            ->where('business_id', $businessId)
            ->when($branchId, fn (Builder $query): Builder => $query->where('branch_id', $branchId))
            ->when($filters['branch_id'] ?? null, fn (Builder $query, int|string $branch): Builder => $query->where('branch_id', $branch))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from): Builder => $query->where('sold_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to): Builder => $query->where('sold_at', '<=', Carbon::parse($to)->endOfDay()));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function settlementQuery(int $businessId, ?int $branchId, array $filters = []): Builder
    {
        return ReplicatedCashierSettlement::query()
            ->where('business_id', $businessId)
            ->when($branchId, fn (Builder $query): Builder => $query->where('branch_id', $branchId))
            ->when($filters['branch_id'] ?? null, fn (Builder $query, int|string $branch): Builder => $query->where('branch_id', $branch))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from): Builder => $query->where('settled_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to): Builder => $query->where('settled_at', '<=', Carbon::parse($to)->endOfDay()));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function shiftQuery(int $businessId, ?int $branchId, array $filters = []): Builder
    {
        return ReplicatedCashierShift::query()
            ->where('business_id', $businessId)
            ->when($branchId, fn (Builder $query): Builder => $query->where('branch_id', $branchId))
            ->when($filters['branch_id'] ?? null, fn (Builder $query, int|string $branch): Builder => $query->where('branch_id', $branch))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from): Builder => $query->where('opened_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to): Builder => $query->where('opened_at', '<=', Carbon::parse($to)->endOfDay()));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function summaryQuery(int $businessId, ?int $branchId, array $filters = []): Builder
    {
        return StaffActivitySummary::query()
            ->where('business_id', $businessId)
            ->when($branchId, fn (Builder $query): Builder => $query->where('branch_id', $branchId))
            ->when($filters['branch_id'] ?? null, fn (Builder $query, int|string $branch): Builder => $query->where('branch_id', $branch))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from): Builder => $query->whereDate('summary_date', '>=', Carbon::parse($from)->toDateString()))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to): Builder => $query->whereDate('summary_date', '<=', Carbon::parse($to)->toDateString()));
    }
}
