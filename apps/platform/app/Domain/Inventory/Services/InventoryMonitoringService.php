<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Inventory\Models\ReplicatedInventoryMovement;
use App\Domain\Inventory\Models\ReplicatedInventoryStock;
use App\Domain\Inventory\Models\ReplicatedStockAlert;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class InventoryMonitoringService
{
    public function stockQuery(int $businessId, ?int $branchId, array $filters = []): Builder
    {
        return ReplicatedInventoryStock::query()
            ->with('branch')
            ->where('replicated_inventory_stocks.business_id', $businessId)
            ->when($branchId, fn (Builder $query): Builder => $query->where('replicated_inventory_stocks.branch_id', $branchId))
            ->when($filters['branch_id'] ?? null, fn (Builder $query, int|string $branch): Builder => $query->where('replicated_inventory_stocks.branch_id', $branch))
            ->when($filters['warehouse'] ?? null, fn (Builder $query, string $warehouse): Builder => $query->where(function (Builder $query) use ($warehouse): void {
                $query->where('warehouse_uuid', $warehouse)->orWhere('warehouse_name', 'like', "%{$warehouse}%");
            }))
            ->when($filters['product'] ?? null, fn (Builder $query, string $product): Builder => $query->where(function (Builder $query) use ($product): void {
                $query->where('product_uuid', $product)->orWhere('product_name', 'like', "%{$product}%")->orWhere('sku', 'like', "%{$product}%")->orWhere('barcode', 'like', "%{$product}%");
            }))
            ->when($filters['category'] ?? null, fn (Builder $query, string $category): Builder => $query->where(function (Builder $query) use ($category): void {
                $query->where('category_uuid', $category)->orWhere('category_name', 'like', "%{$category}%");
            }))
            ->when($filters['brand'] ?? null, fn (Builder $query, string $brand): Builder => $query->where(function (Builder $query) use ($brand): void {
                $query->where('brand_uuid', $brand)->orWhere('brand_name', 'like', "%{$brand}%");
            }))
            ->when($filters['expiring'] ?? false, fn (Builder $query): Builder => $query->whereNotNull('expires_on')->where('expires_on', '<=', now()->addDays(30)->toDateString()))
            ->when($filters['stock_status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('stock_status', $status));
    }

    public function movementQuery(int $businessId, ?int $branchId, array $filters = []): Builder
    {
        return ReplicatedInventoryMovement::query()
            ->with('branch')
            ->where('replicated_inventory_movements.business_id', $businessId)
            ->when($branchId, fn (Builder $query): Builder => $query->where('replicated_inventory_movements.branch_id', $branchId))
            ->when($filters['branch_id'] ?? null, fn (Builder $query, int|string $branch): Builder => $query->where('replicated_inventory_movements.branch_id', $branch))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from): Builder => $query->where('occurred_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to): Builder => $query->where('occurred_at', '<=', Carbon::parse($to)->endOfDay()))
            ->when($filters['warehouse'] ?? null, fn (Builder $query, string $warehouse): Builder => $query->where(function (Builder $query) use ($warehouse): void {
                $query->where('warehouse_uuid', $warehouse)->orWhere('warehouse_name', 'like', "%{$warehouse}%");
            }))
            ->when($filters['product'] ?? null, fn (Builder $query, string $product): Builder => $query->where(function (Builder $query) use ($product): void {
                $query->where('product_uuid', $product)->orWhere('product_name', 'like', "%{$product}%")->orWhere('sku', 'like', "%{$product}%");
            }));
    }

    public function stocks(int $businessId, ?int $branchId, array $filters): LengthAwarePaginator
    {
        return $this->stockQuery($businessId, $branchId, $filters)->orderBy('product_name')->paginate(15);
    }

    public function movements(int $businessId, ?int $branchId, array $filters): LengthAwarePaginator
    {
        return $this->movementQuery($businessId, $branchId, $filters)->latest('occurred_at')->paginate(15);
    }

    public function overview(int $businessId, ?int $branchId, array $filters): array
    {
        $stocks = $this->stockQuery($businessId, $branchId, $filters);

        return [
            'products_count' => (clone $stocks)->count(),
            'stock_value_minor' => (int) (clone $stocks)->sum('stock_value_minor'),
            'low_stock_count' => (clone $stocks)->where('stock_status', 'low')->count(),
            'out_of_stock_count' => (clone $stocks)->where('stock_status', 'out_of_stock')->count(),
            'overstocked_count' => (clone $stocks)->where('stock_status', 'overstocked')->count(),
            'expiring_count' => (clone $stocks)->whereNotNull('expires_on')->where('expires_on', '<=', now()->addDays(30)->toDateString())->count(),
            'damaged_alert_count' => ReplicatedStockAlert::query()->where('business_id', $businessId)->when($branchId, fn ($query) => $query->where('branch_id', $branchId))->where('alert_type', 'damaged')->count(),
            'last_synchronized_at' => (clone $stocks)->max('last_synchronized_at'),
        ];
    }

    public function alerts(int $businessId, ?int $branchId, array $filters): Collection
    {
        return ReplicatedStockAlert::query()
            ->with('branch')
            ->where('business_id', $businessId)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->when($filters['branch_id'] ?? null, fn ($query, $branch) => $query->where('branch_id', $branch))
            ->latest('occurred_at')
            ->limit(50)
            ->get();
    }

    public function branchComparison(int $businessId, array $filters): Collection
    {
        return $this->stockQuery($businessId, null, $filters)
            ->join('branches', 'branches.id', '=', 'replicated_inventory_stocks.branch_id')
            ->selectRaw('replicated_inventory_stocks.branch_id, branches.name as branch_name, count(*) as products_count, sum(replicated_inventory_stocks.stock_value_minor) as stock_value_minor, sum(replicated_inventory_stocks.available_quantity) as available_quantity')
            ->groupBy('replicated_inventory_stocks.branch_id', 'branches.name')
            ->orderByDesc('stock_value_minor')
            ->get();
    }

    public function movementSpeed(int $businessId, ?int $branchId, array $filters, string $direction): Collection
    {
        $query = $this->movementQuery($businessId, $branchId, $filters)
            ->where('quantity_delta', '<', 0)
            ->selectRaw('product_uuid, product_name, sku, abs(sum(quantity_delta)) as moved_quantity, count(*) as movement_count')
            ->groupBy('product_uuid', 'product_name', 'sku');

        return ($direction === 'fast' ? $query->orderByDesc('moved_quantity') : $query->orderBy('moved_quantity'))->limit(50)->get();
    }
}
