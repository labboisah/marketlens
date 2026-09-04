<?php

namespace App\Domain\Expenses\Services;

use App\Domain\Expenses\Models\ReplicatedExpense;
use App\Domain\Sales\Models\ReplicatedSale;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ExpenseMonitoringService
{
    public function expenseQuery(int $businessId, ?int $branchId, array $filters = []): Builder
    {
        return ReplicatedExpense::query()
            ->with(['branch', 'device'])
            ->where('replicated_expenses.business_id', $businessId)
            ->when($branchId, fn (Builder $query): Builder => $query->where('replicated_expenses.branch_id', $branchId))
            ->when($filters['branch_id'] ?? null, fn (Builder $query, int|string $branch): Builder => $query->where('replicated_expenses.branch_id', $branch))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from): Builder => $query->where('replicated_expenses.expense_date', '>=', Carbon::parse($from)->startOfDay()))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to): Builder => $query->where('replicated_expenses.expense_date', '<=', Carbon::parse($to)->endOfDay()))
            ->when($filters['category'] ?? null, fn (Builder $query, string $category): Builder => $query->where(function (Builder $query) use ($category): void {
                $query->where('replicated_expenses.category_uuid', $category)->orWhere('replicated_expenses.category_name', 'like', "%{$category}%");
            }))
            ->when($filters['creator'] ?? null, fn (Builder $query, string $creator): Builder => $query->where(function (Builder $query) use ($creator): void {
                $query->where('replicated_expenses.creator_uuid', $creator)->orWhere('replicated_expenses.creator_name', 'like', "%{$creator}%");
            }))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('replicated_expenses.status', $status))
            ->when($filters['statuses'] ?? null, fn (Builder $query, array $statuses): Builder => $query->whereIn('replicated_expenses.status', $statuses))
            ->when($filters['expense_number'] ?? null, fn (Builder $query, string $number): Builder => $query->where('replicated_expenses.expense_number', 'like', "%{$number}%"));
    }

    public function expenses(int $businessId, ?int $branchId, array $filters): LengthAwarePaginator
    {
        return $this->expenseQuery($businessId, $branchId, $filters)
            ->latest('expense_date')
            ->paginate(15);
    }

    public function overview(int $businessId, ?int $branchId, array $filters): array
    {
        $expenses = $this->expenseQuery($businessId, $branchId, $filters);

        return [
            'expense_count' => (clone $expenses)->count(),
            'total_minor' => (int) (clone $expenses)->sum('amount_minor'),
            'approved_minor' => (int) (clone $expenses)->where('status', 'approved')->sum('amount_minor'),
            'pending_minor' => (int) (clone $expenses)->where('status', 'pending')->sum('amount_minor'),
            'rejected_minor' => (int) (clone $expenses)->where('status', 'rejected')->sum('amount_minor'),
            'recurring_minor' => (int) (clone $expenses)->where('is_recurring', true)->sum('amount_minor'),
            'last_synchronized_at' => (clone $expenses)->max('updated_at'),
        ];
    }

    public function categoryBreakdown(int $businessId, ?int $branchId, array $filters): Collection
    {
        return $this->expenseQuery($businessId, $branchId, $filters)
            ->selectRaw('category_uuid, category_name, count(*) as expense_count, sum(amount_minor) as amount_minor')
            ->groupBy('category_uuid', 'category_name')
            ->orderByDesc('amount_minor')
            ->get();
    }

    public function branchComparison(int $businessId, array $filters): Collection
    {
        return $this->expenseQuery($businessId, null, $filters)
            ->join('branches', 'branches.id', '=', 'replicated_expenses.branch_id')
            ->selectRaw('replicated_expenses.branch_id, branches.name as branch_name, count(*) as expense_count, sum(replicated_expenses.amount_minor) as amount_minor')
            ->groupBy('replicated_expenses.branch_id', 'branches.name')
            ->orderByDesc('amount_minor')
            ->get();
    }

    public function creatorBreakdown(int $businessId, ?int $branchId, array $filters): Collection
    {
        return $this->expenseQuery($businessId, $branchId, $filters)
            ->selectRaw("coalesce(creator_name, 'Unknown') as creator_name, creator_uuid, count(*) as expense_count, sum(amount_minor) as amount_minor")
            ->groupBy('creator_uuid', 'creator_name')
            ->orderByDesc('amount_minor')
            ->get();
    }

    public function recurringSummary(int $businessId, ?int $branchId, array $filters): Collection
    {
        return $this->expenseQuery($businessId, $branchId, $filters)
            ->where('is_recurring', true)
            ->selectRaw("coalesce(recurring_period, 'unspecified') as recurring_period, count(*) as expense_count, sum(amount_minor) as amount_minor")
            ->groupBy('recurring_period')
            ->orderByDesc('amount_minor')
            ->get();
    }

    public function trend(int $businessId, ?int $branchId, array $filters): Collection
    {
        return $this->expenseQuery($businessId, $branchId, $filters)
            ->selectRaw('date(expense_date) as expense_day, count(*) as expense_count, sum(amount_minor) as amount_minor')
            ->groupBy('expense_day')
            ->orderBy('expense_day')
            ->get();
    }

    public function expenseToSalesComparison(int $businessId, ?int $branchId, array $filters): array
    {
        $expenses = $this->expenseQuery($businessId, $branchId, $filters);
        $sales = ReplicatedSale::query()
            ->where('business_id', $businessId)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->when($filters['branch_id'] ?? null, fn ($query, $branch) => $query->where('branch_id', $branch))
            ->when($filters['from'] ?? null, fn ($query, string $from) => $query->where('sold_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($filters['to'] ?? null, fn ($query, string $to) => $query->where('sold_at', '<=', Carbon::parse($to)->endOfDay()))
            ->where('status', 'completed');

        $expenseTotal = (int) (clone $expenses)->sum('amount_minor');
        $salesTotal = (int) (clone $sales)->sum('total_minor');

        return [
            'expenses_minor' => $expenseTotal,
            'sales_minor' => $salesTotal,
            'ratio' => $salesTotal > 0 ? round(($expenseTotal / $salesTotal) * 100, 2) : null,
        ];
    }
}
