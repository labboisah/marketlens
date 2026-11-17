<?php

namespace App\Livewire\Platform\Inventory;

use App\Domain\Inventory\Models\ReplicatedInventoryStock;
use App\Domain\Inventory\Services\InventoryMonitoringService;
use App\Domain\Licensing\Services\BusinessLicenseEntitlementService;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Services\BranchContext;
use App\Domain\Tenancy\Services\BusinessContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class InventoryMonitoringPage extends Component
{
    use WithPagination;

    #[Url]
    public string $view = 'overview';

    #[Url]
    public ?string $from = null;

    #[Url]
    public ?string $to = null;

    #[Url]
    public ?string $branchFilter = null;

    #[Url]
    public ?string $warehouse = null;

    #[Url]
    public ?string $product = null;

    #[Url]
    public ?string $category = null;

    #[Url]
    public ?string $brand = null;

    #[Url]
    public ?string $stockStatus = null;

    public function mount(): void
    {
        $this->from ??= now()->startOfMonth()->toDateString();
        $this->to ??= now()->toDateString();
    }

    public function render(BusinessContext $businessContext, BranchContext $branchContext, InventoryMonitoringService $inventory): View
    {
        $businessId = $this->resolvedBusinessId($businessContext);
        $branchId = $this->resolvedBranchId($branchContext);
        $filters = $this->filters();

        if ($businessId && $this->canViewInventory($businessId) === false) {
            return view('livewire.platform.inventory.inventory-monitoring-page', [
                'businessId' => $businessId, 'accessDenied' => true, 'overview' => null, 'stocks' => null, 'movements' => null,
                'alerts' => collect(), 'branches' => collect(), 'branchComparison' => collect(), 'fastMoving' => collect(), 'slowMoving' => collect(), 'selectedStock' => null,
            ])->title('Inventory monitoring');
        }

        return view('livewire.platform.inventory.inventory-monitoring-page', [
            'businessId' => $businessId,
            'accessDenied' => false,
            'overview' => $businessId ? $inventory->overview($businessId, $branchId, $filters) : null,
            'stocks' => $businessId ? $inventory->stocks($businessId, $branchId, $filters) : null,
            'movements' => $businessId ? $inventory->movements($businessId, $branchId, $filters) : null,
            'alerts' => $businessId ? $inventory->alerts($businessId, $branchId, $filters) : collect(),
            'branches' => $businessId ? Branch::query()->where('business_id', $businessId)->orderBy('name')->get() : collect(),
            'branchComparison' => $businessId ? $inventory->branchComparison($businessId, $filters) : collect(),
            'fastMoving' => $businessId ? $inventory->movementSpeed($businessId, $branchId, $filters, 'fast') : collect(),
            'slowMoving' => $businessId ? $inventory->movementSpeed($businessId, $branchId, $filters, 'slow') : collect(),
            'selectedStock' => $this->selectedStock($businessId, $branchId),
        ])->title('Inventory monitoring');
    }

    public function showDetails(string $productUuid): void
    {
        $this->view = 'details';
        $this->product = $productUuid;
    }

    public function money(int $minor): string
    {
        return 'NGN '.number_format($minor / 100, 2);
    }

    public function quantity(int|float|string $value): string
    {
        return number_format((float) $value, 4);
    }

    private function selectedStock(?int $businessId, ?int $branchId): ?ReplicatedInventoryStock
    {
        if (! $businessId || $this->view !== 'details' || ! $this->product) {
            return null;
        }

        return ReplicatedInventoryStock::query()
            ->with(['branch', 'movements' => fn ($query) => $query->latest('occurred_at')->limit(10)])
            ->where('business_id', $businessId)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->where(function ($query): void {
                $query->where('product_uuid', $this->product)->orWhere('sku', $this->product)->orWhere('barcode', $this->product);
            })
            ->first();
    }

    private function filters(): array
    {
        $status = $this->stockStatus;
        $status = match ($this->view) {
            'low' => 'low',
            'out' => 'out_of_stock',
            'overstocked' => 'overstocked',
            default => $status,
        };

        return [
            'from' => $this->from ? Carbon::parse($this->from)->toDateString() : null,
            'to' => $this->to ? Carbon::parse($this->to)->toDateString() : null,
            'branch_id' => $this->branchFilter,
            'warehouse' => $this->warehouse,
            'product' => $this->view === 'details' ? null : $this->product,
            'category' => $this->category,
            'brand' => $this->brand,
            'stock_status' => $status,
            'expiring' => $this->view === 'expiring',
        ];
    }

    private function resolvedBusinessId(BusinessContext $businessContext): ?int
    {
        return $businessContext->id() ?: auth()->user()?->ownedBusinesses()->value('businesses.id');
    }

    private function resolvedBranchId(BranchContext $branchContext): ?int
    {
        return $this->branchFilter ? (int) $this->branchFilter : $branchContext->id();
    }

    private function canViewInventory(int $businessId): bool
    {
        $business = auth()->user()?->ownedBusinesses()->whereKey($businessId)->first();

        return $business !== null && app(BusinessLicenseEntitlementService::class)->allowsAny($business, ['inventory', 'stock']);
    }
}
