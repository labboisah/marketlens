<?php

namespace App\Domain\Customers\Services;

use App\Domain\Customers\Models\ReplicatedCustomerDebtPayment;
use App\Domain\Customers\Models\ReplicatedCustomerDebtSummary;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CustomerDebtMonitoringService
{
    public function summaryQuery(int $businessId, ?int $branchId, array $filters = []): Builder
    {
        return ReplicatedCustomerDebtSummary::query()
            ->with(['branch', 'device'])
            ->where('replicated_customer_debt_summaries.business_id', $businessId)
            ->when($branchId, fn (Builder $query): Builder => $query->where('replicated_customer_debt_summaries.branch_id', $branchId))
            ->when($filters['branch_id'] ?? null, fn (Builder $query, int|string $branch): Builder => $query->where('replicated_customer_debt_summaries.branch_id', $branch))
            ->when($filters['customer'] ?? null, fn (Builder $query, string $customer): Builder => $query->where(function (Builder $query) use ($customer): void {
                $query->where('replicated_customer_debt_summaries.customer_uuid', $customer)
                    ->orWhere('replicated_customer_debt_summaries.customer_name', 'like', "%{$customer}%")
                    ->orWhere('replicated_customer_debt_summaries.phone', 'like', "%{$customer}%");
            }))
            ->when($filters['debt_status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('replicated_customer_debt_summaries.debt_status', $status))
            ->when($filters['overdue'] ?? false, fn (Builder $query): Builder => $query->where('replicated_customer_debt_summaries.overdue_amount_minor', '>', 0));
    }

    public function paymentQuery(int $businessId, ?int $branchId, array $filters = []): Builder
    {
        return ReplicatedCustomerDebtPayment::query()
            ->with(['branch', 'summary'])
            ->where('replicated_customer_debt_payments.business_id', $businessId)
            ->when($branchId, fn (Builder $query): Builder => $query->where('replicated_customer_debt_payments.branch_id', $branchId))
            ->when($filters['branch_id'] ?? null, fn (Builder $query, int|string $branch): Builder => $query->where('replicated_customer_debt_payments.branch_id', $branch))
            ->when($filters['customer'] ?? null, fn (Builder $query, string $customer): Builder => $query->where(function (Builder $query) use ($customer): void {
                $query->where('replicated_customer_debt_payments.customer_uuid', $customer)
                    ->orWhere('replicated_customer_debt_payments.customer_name', 'like', "%{$customer}%");
            }));
    }

    public function balances(int $businessId, ?int $branchId, array $filters): LengthAwarePaginator
    {
        return $this->summaryQuery($businessId, $branchId, $filters)
            ->orderByDesc('current_balance_minor')
            ->paginate(15);
    }

    public function payments(int $businessId, ?int $branchId, array $filters): LengthAwarePaginator
    {
        return $this->paymentQuery($businessId, $branchId, $filters)
            ->latest('paid_at')
            ->paginate(15);
    }

    public function overview(int $businessId, ?int $branchId, array $filters): array
    {
        $summaries = $this->summaryQuery($businessId, $branchId, $filters);

        return [
            'customer_count' => (clone $summaries)->count(),
            'total_balance_minor' => (int) (clone $summaries)->sum('current_balance_minor'),
            'overdue_balance_minor' => (int) (clone $summaries)->sum('overdue_amount_minor'),
            'credit_sales_minor' => (int) (clone $summaries)->sum('credit_sales_minor'),
            'highest_balance_minor' => (int) (clone $summaries)->max('current_balance_minor'),
            'last_synchronized_at' => (clone $summaries)->max('last_synchronized_at'),
        ];
    }

    public function ageing(int $businessId, ?int $branchId, array $filters): array
    {
        $summaries = $this->summaryQuery($businessId, $branchId, $filters);

        return [
            'Current' => (int) (clone $summaries)->sum('age_current_minor'),
            '1-30 days overdue' => (int) (clone $summaries)->sum('age_1_30_minor'),
            '31-60 days overdue' => (int) (clone $summaries)->sum('age_31_60_minor'),
            '61-90 days overdue' => (int) (clone $summaries)->sum('age_61_90_minor'),
            'More than 90 days overdue' => (int) (clone $summaries)->sum('age_over_90_minor'),
        ];
    }

    public function branchComparison(int $businessId, array $filters): Collection
    {
        return $this->summaryQuery($businessId, null, $filters)
            ->join('branches', 'branches.id', '=', 'replicated_customer_debt_summaries.branch_id')
            ->selectRaw('replicated_customer_debt_summaries.branch_id, branches.name as branch_name, count(*) as customer_count, sum(replicated_customer_debt_summaries.current_balance_minor) as balance_minor, sum(replicated_customer_debt_summaries.overdue_amount_minor) as overdue_minor')
            ->groupBy('replicated_customer_debt_summaries.branch_id', 'branches.name')
            ->orderByDesc('balance_minor')
            ->get();
    }
}
