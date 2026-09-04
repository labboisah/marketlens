<?php

namespace App\Domain\Sales\Services;

use App\Domain\Sales\Models\ReplicatedSale;
use App\Domain\Sales\Models\ReplicatedSaleItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SalesMonitoringService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function salesQuery(int $businessId, ?int $branchId, array $filters = []): Builder
    {
        return ReplicatedSale::query()
            ->with(['branch', 'device'])
            ->where('replicated_sales.business_id', $businessId)
            ->when($branchId, fn (Builder $query): Builder => $query->where('replicated_sales.branch_id', $branchId))
            ->when($filters['branch_id'] ?? null, fn (Builder $query, int|string $branch): Builder => $query->where('replicated_sales.branch_id', $branch))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from): Builder => $query->where('replicated_sales.sold_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to): Builder => $query->where('replicated_sales.sold_at', '<=', Carbon::parse($to)->endOfDay()))
            ->when($filters['cashier'] ?? null, fn (Builder $query, string $cashier): Builder => $query->where(function (Builder $query) use ($cashier): void {
                $query->where('replicated_sales.cashier_uuid', $cashier)->orWhere('replicated_sales.cashier_name', 'like', "%{$cashier}%");
            }))
            ->when($filters['customer'] ?? null, fn (Builder $query, string $customer): Builder => $query->where(function (Builder $query) use ($customer): void {
                $query->where('replicated_sales.customer_uuid', $customer)->orWhere('replicated_sales.customer_name', 'like', "%{$customer}%");
            }))
            ->when($filters['payment_status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('replicated_sales.payment_status', $status))
            ->when($filters['sale_status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('replicated_sales.status', $status))
            ->when($filters['returned_only'] ?? false, fn (Builder $query): Builder => $query->whereIn('replicated_sales.status', ['returned', 'partially_returned']))
            ->when($filters['sale_type'] ?? null, fn (Builder $query, string $type): Builder => $query->where('replicated_sales.sale_type', $type))
            ->when($filters['transaction_number'] ?? null, fn (Builder $query, string $number): Builder => $query->where(function (Builder $query) use ($number): void {
                $query->where('replicated_sales.transaction_number', 'like', "%{$number}%")->orWhere('replicated_sales.sale_number', 'like', "%{$number}%");
            }))
            ->when($filters['product'] ?? null, fn (Builder $query, string $product): Builder => $query->whereHas('items', function (Builder $query) use ($product): void {
                $query->where('product_uuid', $product)->orWhere('product_name', 'like', "%{$product}%");
            }))
            ->when($filters['category'] ?? null, fn (Builder $query, string $category): Builder => $query->whereHas('items', function (Builder $query) use ($category): void {
                $query->where('category_uuid', $category)->orWhere('category_name', 'like', "%{$category}%");
            }));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginatedSales(int $businessId, ?int $branchId, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->salesQuery($businessId, $branchId, $filters)
            ->latest('replicated_sales.sold_at')
            ->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    public function overview(int $businessId, ?int $branchId, array $filters): array
    {
        $query = $this->salesQuery($businessId, $branchId, $filters);

        return [
            'sales_count' => (clone $query)->count(),
            'total_minor' => (int) (clone $query)->sum('total_minor'),
            'gross_profit_minor' => (int) (clone $query)->sum('gross_profit_minor'),
            'cancelled_count' => (clone $query)->where('status', 'cancelled')->count(),
            'returned_count' => (clone $query)->whereIn('status', ['returned', 'partially_returned'])->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function productReport(int $businessId, ?int $branchId, array $filters): Collection
    {
        return $this->itemReportQuery($businessId, $branchId, $filters)
            ->selectRaw('product_uuid, product_name, sku, sum(quantity) as quantity_sold, sum(line_total_minor) as sales_minor, sum(gross_profit_minor) as gross_profit_minor')
            ->groupBy('product_uuid', 'product_name', 'sku')
            ->orderByDesc('sales_minor')
            ->limit(50)
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function categoryReport(int $businessId, ?int $branchId, array $filters): Collection
    {
        return $this->itemReportQuery($businessId, $branchId, $filters)
            ->selectRaw('category_uuid, coalesce(category_name, \'Uncategorized\') as category_name, sum(quantity) as quantity_sold, sum(line_total_minor) as sales_minor, sum(gross_profit_minor) as gross_profit_minor')
            ->groupBy('category_uuid', 'category_name')
            ->orderByDesc('sales_minor')
            ->limit(50)
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function cashierReport(int $businessId, ?int $branchId, array $filters): Collection
    {
        return $this->salesQuery($businessId, $branchId, $filters)
            ->selectRaw('replicated_sales.cashier_uuid, coalesce(replicated_sales.cashier_name, \'Unknown cashier\') as cashier_name, count(*) as sales_count, sum(replicated_sales.total_minor) as sales_minor, sum(replicated_sales.gross_profit_minor) as gross_profit_minor')
            ->groupBy('replicated_sales.cashier_uuid', 'replicated_sales.cashier_name')
            ->orderByDesc('sales_minor')
            ->limit(50)
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function branchReport(int $businessId, ?int $branchId, array $filters): Collection
    {
        return $this->salesQuery($businessId, $branchId, $filters)
            ->join('branches', 'branches.id', '=', 'replicated_sales.branch_id')
            ->selectRaw('replicated_sales.branch_id, branches.name as branch_name, count(*) as sales_count, sum(replicated_sales.total_minor) as sales_minor, sum(replicated_sales.gross_profit_minor) as gross_profit_minor')
            ->groupBy('replicated_sales.branch_id', 'branches.name')
            ->orderByDesc('sales_minor')
            ->limit(50)
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function exportedRows(int $businessId, ?int $branchId, array $filters): Collection
    {
        return $this->salesQuery($businessId, $branchId, $filters)
            ->latest('replicated_sales.sold_at')
            ->limit(1000)
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function itemReportQuery(int $businessId, ?int $branchId, array $filters): Builder
    {
        return ReplicatedSaleItem::query()
            ->where('replicated_sale_items.business_id', $businessId)
            ->when($branchId, fn (Builder $query): Builder => $query->where('replicated_sale_items.branch_id', $branchId))
            ->whereHas('sale', fn (Builder $query): Builder => $this->applySaleReportFilters($query, $filters));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applySaleReportFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['from'] ?? null, fn (Builder $query, string $from): Builder => $query->where('sold_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to): Builder => $query->where('sold_at', '<=', Carbon::parse($to)->endOfDay()))
            ->when($filters['payment_status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('payment_status', $status))
            ->when($filters['sale_status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('status', $status))
            ->when($filters['returned_only'] ?? false, fn (Builder $query): Builder => $query->whereIn('status', ['returned', 'partially_returned']))
            ->when($filters['sale_type'] ?? null, fn (Builder $query, string $type): Builder => $query->where('sale_type', $type));
    }
}
