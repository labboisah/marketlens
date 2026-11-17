<?php

namespace App\Livewire\Platform\Purchases;

use App\Domain\Licensing\Services\BusinessLicenseEntitlementService;
use App\Domain\Purchases\Models\ReplicatedPurchase;
use App\Domain\Purchases\Services\PurchaseMonitoringService;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Services\BranchContext;
use App\Domain\Tenancy\Services\BusinessContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app')]
class PurchaseMonitoringPage extends Component
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
    public ?string $supplier = null;

    #[Url]
    public ?string $paymentStatus = null;

    #[Url]
    public ?string $purchaseNumber = null;

    public function mount(): void
    {
        $this->from ??= now()->startOfMonth()->toDateString();
        $this->to ??= now()->toDateString();
    }

    public function render(BusinessContext $businessContext, BranchContext $branchContext, PurchaseMonitoringService $purchases): View
    {
        $businessId = $this->resolvedBusinessId($businessContext);
        $branchId = $this->resolvedBranchId($branchContext);
        $filters = $this->filters();

        if ($businessId && $this->canViewPurchases($businessId) === false) {
            return view('livewire.platform.purchases.purchase-monitoring-page', [
                'businessId' => $businessId, 'accessDenied' => true, 'overview' => null, 'purchases' => null, 'selectedPurchase' => null,
                'supplierBalances' => null, 'payments' => null, 'trends' => collect(), 'costChanges' => collect(), 'branchComparison' => collect(), 'branches' => collect(),
            ])->title('Supplier and purchase monitoring');
        }

        return view('livewire.platform.purchases.purchase-monitoring-page', [
            'businessId' => $businessId,
            'accessDenied' => false,
            'overview' => $businessId ? $purchases->overview($businessId, $branchId, $filters) : null,
            'purchases' => $businessId ? $purchases->purchases($businessId, $branchId, $filters) : null,
            'selectedPurchase' => $this->selectedPurchase($businessId, $branchId),
            'supplierBalances' => $businessId ? $purchases->supplierBalances($businessId, $branchId, $filters) : null,
            'payments' => $businessId ? $purchases->payments($businessId, $branchId, $filters) : null,
            'trends' => $businessId ? $purchases->trends($businessId, $branchId, $filters) : collect(),
            'costChanges' => $businessId ? $purchases->costChanges($businessId, $branchId, $filters) : collect(),
            'branchComparison' => $businessId ? $purchases->branchComparison($businessId, $filters) : collect(),
            'branches' => $businessId ? Branch::query()->where('business_id', $businessId)->orderBy('name')->get() : collect(),
        ])->title('Supplier and purchase monitoring');
    }

    public function showDetails(string $purchaseUuid): void
    {
        $this->view = 'details';
        $this->purchaseNumber = $purchaseUuid;
    }

    public function exportCsv(BusinessContext $businessContext, BranchContext $branchContext, PurchaseMonitoringService $purchases): StreamedResponse
    {
        $businessId = $this->resolvedBusinessId($businessContext);
        abort_unless($businessId, 403);
        $rows = $purchases->purchaseQuery($businessId, $this->resolvedBranchId($branchContext), $this->filters())->latest('purchased_at')->limit(1000)->get();

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Purchase number', 'Supplier', 'Branch', 'Date', 'Status', 'Payment status', 'Subtotal', 'Transport', 'Additional costs', 'Total', 'Amount paid', 'Balance']);

            foreach ($rows as $purchase) {
                fputcsv($handle, [$purchase->purchase_number, $purchase->supplier_name, $purchase->branch?->name, $purchase->purchased_at?->toISOString(), $purchase->status, $purchase->payment_status, $purchase->subtotal_minor, $purchase->transport_cost_minor, $purchase->additional_cost_minor, $purchase->total_minor, $purchase->amount_paid_minor, $purchase->balance_minor]);
            }

            fclose($handle);
        }, 'marketlens-purchases-'.now()->format('YmdHis').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function exportPdfFoundation(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            echo "MarketLens Supplier and Purchase Monitoring PDF Export\n";
            echo "PDF rendering engine will attach to this read-only purchase and supplier balance report foundation.\n";
        }, 'marketlens-purchases-export-foundation.txt', ['Content-Type' => 'text/plain']);
    }

    public function money(int|float|string|null $minor): string
    {
        return 'NGN '.number_format(((int) $minor) / 100, 2);
    }

    public function quantity(int|float|string $value): string
    {
        return number_format((float) $value, 4);
    }

    private function selectedPurchase(?int $businessId, ?int $branchId): ?ReplicatedPurchase
    {
        if (! $businessId || $this->view !== 'details' || ! $this->purchaseNumber) {
            return null;
        }

        return ReplicatedPurchase::query()
            ->with(['items', 'payments', 'branch', 'device', 'syncBatch'])
            ->where('business_id', $businessId)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->where(function ($query): void {
                $query->where('uuid', $this->purchaseNumber)->orWhere('purchase_number', $this->purchaseNumber);
            })
            ->first();
    }

    private function filters(): array
    {
        return [
            'from' => $this->from ? Carbon::parse($this->from)->toDateString() : null,
            'to' => $this->to ? Carbon::parse($this->to)->toDateString() : null,
            'branch_id' => $this->branchFilter,
            'supplier' => $this->supplier,
            'payment_status' => $this->view === 'outstanding' ? 'partial' : $this->paymentStatus,
            'purchase_number' => $this->view === 'details' ? null : $this->purchaseNumber,
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

    private function canViewPurchases(int $businessId): bool
    {
        $business = auth()->user()?->ownedBusinesses()->whereKey($businessId)->first();

        return $business !== null && app(BusinessLicenseEntitlementService::class)->allowsAny($business, ['purchases', 'suppliers', 'reports']);
    }
}
