<?php

namespace App\Domain\Purchases\Services;

use App\Domain\Purchases\Models\ReplicatedPurchase;
use App\Domain\Purchases\Models\ReplicatedPurchaseItem;
use App\Domain\Purchases\Models\ReplicatedPurchasePayment;
use App\Domain\Purchases\Models\ReplicatedSupplierBalance;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PurchaseMonitoringService
{
    public function purchaseQuery(int $businessId, ?int $branchId, array $filters = []): Builder
    {
        return ReplicatedPurchase::query()
            ->with(['branch', 'items', 'payments', 'device', 'syncBatch'])
            ->where('replicated_purchases.business_id', $businessId)
            ->when($branchId, fn (Builder $query): Builder => $query->where('replicated_purchases.branch_id', $branchId))
            ->when($filters['branch_id'] ?? null, fn (Builder $query, int|string $branch): Builder => $query->where('replicated_purchases.branch_id', $branch))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from): Builder => $query->where('replicated_purchases.purchased_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to): Builder => $query->where('replicated_purchases.purchased_at', '<=', Carbon::parse($to)->endOfDay()))
            ->when($filters['supplier'] ?? null, fn (Builder $query, string $supplier): Builder => $query->where(function (Builder $query) use ($supplier): void {
                $query->where('replicated_purchases.supplier_uuid', $supplier)->orWhere('replicated_purchases.supplier_name', 'like', "%{$supplier}%");
            }))
            ->when($filters['payment_status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('replicated_purchases.payment_status', $status))
            ->when($filters['purchase_number'] ?? null, fn (Builder $query, string $number): Builder => $query->where('replicated_purchases.purchase_number', 'like', "%{$number}%"));
    }

    public function purchases(int $businessId, ?int $branchId, array $filters): LengthAwarePaginator
    {
        return $this->purchaseQuery($businessId, $branchId, $filters)->latest('purchased_at')->paginate(15);
    }

    public function payments(int $businessId, ?int $branchId, array $filters): LengthAwarePaginator
    {
        return ReplicatedPurchasePayment::query()
            ->with(['branch', 'purchase'])
            ->where('replicated_purchase_payments.business_id', $businessId)
            ->when($branchId, fn (Builder $query): Builder => $query->where('replicated_purchase_payments.branch_id', $branchId))
            ->when($filters['branch_id'] ?? null, fn (Builder $query, int|string $branch): Builder => $query->where('replicated_purchase_payments.branch_id', $branch))
            ->when($filters['supplier'] ?? null, fn (Builder $query, string $supplier): Builder => $query->where(function (Builder $query) use ($supplier): void {
                $query->where('replicated_purchase_payments.supplier_uuid', $supplier)->orWhere('replicated_purchase_payments.supplier_name', 'like', "%{$supplier}%");
            }))
            ->latest('paid_at')
            ->paginate(15);
    }

    public function supplierBalances(int $businessId, ?int $branchId, array $filters): LengthAwarePaginator
    {
        return ReplicatedSupplierBalance::query()
            ->with('branch')
            ->where('business_id', $businessId)
            ->when($branchId, fn (Builder $query): Builder => $query->where('branch_id', $branchId))
            ->when($filters['branch_id'] ?? null, fn (Builder $query, int|string $branch): Builder => $query->where('branch_id', $branch))
            ->when($filters['supplier'] ?? null, fn (Builder $query, string $supplier): Builder => $query->where(function (Builder $query) use ($supplier): void {
                $query->where('supplier_uuid', $supplier)->orWhere('supplier_name', 'like', "%{$supplier}%")->orWhere('phone', 'like', "%{$supplier}%");
            }))
            ->orderByDesc('current_balance_minor')
            ->paginate(15);
    }

    public function overview(int $businessId, ?int $branchId, array $filters): array
    {
        $purchases = $this->purchaseQuery($businessId, $branchId, $filters);
        $balances = ReplicatedSupplierBalance::query()->where('business_id', $businessId)->when($branchId, fn ($query) => $query->where('branch_id', $branchId));

        return [
            'purchase_count' => (clone $purchases)->count(),
            'total_minor' => (int) (clone $purchases)->sum('total_minor'),
            'paid_minor' => (int) (clone $purchases)->sum('amount_paid_minor'),
            'balance_minor' => (int) (clone $purchases)->sum('balance_minor'),
            'transport_minor' => (int) (clone $purchases)->sum('transport_cost_minor'),
            'additional_minor' => (int) (clone $purchases)->sum('additional_cost_minor'),
            'supplier_balance_minor' => (int) (clone $balances)->sum('current_balance_minor'),
        ];
    }

    public function trends(int $businessId, ?int $branchId, array $filters): Collection
    {
        return $this->purchaseQuery($businessId, $branchId, $filters)
            ->selectRaw('date(purchased_at) as purchase_day, count(*) as purchase_count, sum(total_minor) as total_minor')
            ->groupBy('purchase_day')
            ->orderBy('purchase_day')
            ->get();
    }

    public function costChanges(int $businessId, ?int $branchId, array $filters): Collection
    {
        return ReplicatedPurchaseItem::query()
            ->where('business_id', $businessId)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->selectRaw('product_uuid, product_name, sku, min(unit_cost_minor) as lowest_cost_minor, max(unit_cost_minor) as highest_cost_minor, avg(unit_cost_minor) as average_cost_minor, count(*) as purchase_count')
            ->groupBy('product_uuid', 'product_name', 'sku')
            ->orderByDesc('highest_cost_minor')
            ->limit(50)
            ->get();
    }

    public function branchComparison(int $businessId, array $filters): Collection
    {
        return $this->purchaseQuery($businessId, null, $filters)
            ->join('branches', 'branches.id', '=', 'replicated_purchases.branch_id')
            ->selectRaw('replicated_purchases.branch_id, branches.name as branch_name, count(*) as purchase_count, sum(replicated_purchases.total_minor) as total_minor, sum(replicated_purchases.balance_minor) as balance_minor')
            ->groupBy('replicated_purchases.branch_id', 'branches.name')
            ->orderByDesc('total_minor')
            ->get();
    }
}
