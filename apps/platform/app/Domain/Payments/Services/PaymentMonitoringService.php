<?php

namespace App\Domain\Payments\Services;

use App\Domain\Payments\Models\ReplicatedCashierSettlement;
use App\Domain\Sales\Models\ReplicatedSalePayment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PaymentMonitoringService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paymentQuery(int $businessId, ?int $branchId, array $filters = []): Builder
    {
        return ReplicatedSalePayment::query()
            ->with(['branch', 'sale'])
            ->where('replicated_sale_payments.business_id', $businessId)
            ->when($branchId, fn (Builder $query): Builder => $query->where('replicated_sale_payments.branch_id', $branchId))
            ->when($filters['branch_id'] ?? null, fn (Builder $query, int|string $branch): Builder => $query->where('replicated_sale_payments.branch_id', $branch))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from): Builder => $query->where('replicated_sale_payments.paid_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to): Builder => $query->where('replicated_sale_payments.paid_at', '<=', Carbon::parse($to)->endOfDay()))
            ->when($filters['method'] ?? null, fn (Builder $query, string $method): Builder => $query->where('replicated_sale_payments.method', $method))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('replicated_sale_payments.status', $status))
            ->when($filters['cashier'] ?? null, fn (Builder $query, string $cashier): Builder => $query->whereHas('sale', function (Builder $query) use ($cashier): void {
                $query->where('cashier_uuid', $cashier)->orWhere('cashier_name', 'like', "%{$cashier}%");
            }))
            ->when($filters['transaction_number'] ?? null, fn (Builder $query, string $number): Builder => $query->whereHas('sale', function (Builder $query) use ($number): void {
                $query->where('transaction_number', 'like', "%{$number}%")->orWhere('sale_number', 'like', "%{$number}%");
            }));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function settlementQuery(int $businessId, ?int $branchId, array $filters = []): Builder
    {
        return ReplicatedCashierSettlement::query()
            ->with(['branch'])
            ->where('replicated_cashier_settlements.business_id', $businessId)
            ->when($branchId, fn (Builder $query): Builder => $query->where('replicated_cashier_settlements.branch_id', $branchId))
            ->when($filters['branch_id'] ?? null, fn (Builder $query, int|string $branch): Builder => $query->where('replicated_cashier_settlements.branch_id', $branch))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from): Builder => $query->where('replicated_cashier_settlements.settled_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to): Builder => $query->where('replicated_cashier_settlements.settled_at', '<=', Carbon::parse($to)->endOfDay()))
            ->when($filters['cashier'] ?? null, fn (Builder $query, string $cashier): Builder => $query->where(function (Builder $query) use ($cashier): void {
                $query->where('cashier_uuid', $cashier)->orWhere('cashier_name', 'like', "%{$cashier}%");
            }));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function payments(int $businessId, ?int $branchId, array $filters): LengthAwarePaginator
    {
        return $this->paymentQuery($businessId, $branchId, $filters)
            ->latest('replicated_sale_payments.paid_at')
            ->paginate(15);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    public function overview(int $businessId, ?int $branchId, array $filters): array
    {
        $payments = $this->paymentQuery($businessId, $branchId, $filters);
        $settlements = $this->settlementQuery($businessId, $branchId, $filters);

        return [
            'expected_cash_minor' => (int) (clone $settlements)->sum('expected_cash_minor'),
            'actual_cash_minor' => (int) (clone $settlements)->sum('actual_cash_minor'),
            'shortage_minor' => (int) (clone $settlements)->sum('shortage_minor'),
            'surplus_minor' => (int) (clone $settlements)->sum('surplus_minor'),
            'unverified_transfer_minor' => (int) (clone $settlements)->sum('unverified_transfer_minor') + (int) (clone $payments)->whereIn('method', ['bank_transfer', 'transfer'])->where('status', 'unverified')->sum('amount_minor'),
            'refunds_minor' => (int) (clone $payments)->where('status', 'refunded')->sum('amount_minor') + (int) (clone $settlements)->sum('refunds_minor'),
            'cash_expenses_minor' => (int) (clone $settlements)->sum('cash_expenses_minor'),
            'payment_mismatch_count' => (int) (clone $settlements)->sum('payment_mismatch_count') + (int) (clone $payments)->where('status', 'mismatched')->count(),
            'reversal_count' => (int) (clone $settlements)->sum('reversal_count') + (int) (clone $payments)->where('status', 'reversed')->count(),
            'payment_total_minor' => (int) (clone $payments)->sum('amount_minor'),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function methodBreakdown(int $businessId, ?int $branchId, array $filters): Collection
    {
        return $this->paymentQuery($businessId, $branchId, $filters)
            ->selectRaw('method, status, count(*) as payment_count, sum(amount_minor) as amount_minor')
            ->groupBy('method', 'status')
            ->orderBy('method')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function branchComparison(int $businessId, ?int $branchId, array $filters): Collection
    {
        return $this->paymentQuery($businessId, $branchId, $filters)
            ->join('branches', 'branches.id', '=', 'replicated_sale_payments.branch_id')
            ->selectRaw('replicated_sale_payments.branch_id, branches.name as branch_name, count(*) as payment_count, sum(replicated_sale_payments.amount_minor) as amount_minor')
            ->groupBy('replicated_sale_payments.branch_id', 'branches.name')
            ->orderByDesc('amount_minor')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function cashierComparison(int $businessId, ?int $branchId, array $filters): Collection
    {
        return $this->paymentQuery($businessId, $branchId, $filters)
            ->join('replicated_sales', 'replicated_sales.id', '=', 'replicated_sale_payments.replicated_sale_id')
            ->selectRaw('replicated_sales.cashier_uuid, coalesce(replicated_sales.cashier_name, \'Unknown cashier\') as cashier_name, count(*) as payment_count, sum(replicated_sale_payments.amount_minor) as amount_minor')
            ->groupBy('replicated_sales.cashier_uuid', 'replicated_sales.cashier_name')
            ->orderByDesc('amount_minor')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, Collection>
     */
    public function exceptions(int $businessId, ?int $branchId, array $filters): array
    {
        return [
            'cashShortages' => $this->settlementQuery($businessId, $branchId, $filters)->where('shortage_minor', '>', 0)->orderByDesc('shortage_minor')->limit(25)->get(),
            'highRefunds' => $this->paymentQuery($businessId, $branchId, $filters)->where('status', 'refunded')->where('amount_minor', '>=', 50000)->latest('paid_at')->limit(25)->get(),
            'unverifiedTransfers' => $this->paymentQuery($businessId, $branchId, $filters)->whereIn('method', ['bank_transfer', 'transfer'])->where('status', 'unverified')->latest('paid_at')->limit(25)->get(),
            'paymentMismatches' => $this->settlementQuery($businessId, $branchId, $filters)->where('payment_mismatch_count', '>', 0)->latest('settled_at')->limit(25)->get(),
            'repeatedReversals' => $this->settlementQuery($businessId, $branchId, $filters)->where('reversal_count', '>', 1)->latest('settled_at')->limit(25)->get(),
        ];
    }
}
