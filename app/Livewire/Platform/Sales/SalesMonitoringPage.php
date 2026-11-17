<?php

namespace App\Livewire\Platform\Sales;

use App\Domain\Licensing\Services\BusinessLicenseEntitlementService;
use App\Domain\Sales\Models\ReplicatedSale;
use App\Domain\Sales\Services\SalesMonitoringService;
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
class SalesMonitoringPage extends Component
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
    public ?string $cashier = null;

    #[Url]
    public ?string $customer = null;

    #[Url]
    public ?string $product = null;

    #[Url]
    public ?string $category = null;

    #[Url]
    public ?string $paymentStatus = null;

    #[Url]
    public ?string $saleStatus = null;

    #[Url]
    public ?string $saleType = null;

    #[Url]
    public ?string $transactionNumber = null;

    public function mount(): void
    {
        $this->from ??= now()->startOfMonth()->toDateString();
        $this->to ??= now()->toDateString();
    }

    public function render(BusinessContext $businessContext, BranchContext $branchContext, SalesMonitoringService $sales): View
    {
        $businessId = $this->resolvedBusinessId($businessContext);
        $branchId = $this->resolvedBranchId($branchContext);
        $filters = $this->filters();

        if ($businessId && $this->canViewSales($businessId) === false) {
            return view('livewire.platform.sales.sales-monitoring-page', [
                'businessId' => $businessId,
                'accessDenied' => true,
                'overview' => null,
                'sales' => null,
                'productReport' => collect(),
                'categoryReport' => collect(),
                'cashierReport' => collect(),
                'branchReport' => collect(),
                'selectedSale' => null,
                'branches' => collect(),
            ])->title('Sales monitoring');
        }

        return view('livewire.platform.sales.sales-monitoring-page', [
            'businessId' => $businessId,
            'overview' => $businessId ? $sales->overview($businessId, $branchId, $filters) : null,
            'sales' => $businessId ? $sales->paginatedSales($businessId, $branchId, $filters) : null,
            'productReport' => $businessId ? $sales->productReport($businessId, $branchId, $filters) : collect(),
            'categoryReport' => $businessId ? $sales->categoryReport($businessId, $branchId, $filters) : collect(),
            'cashierReport' => $businessId ? $sales->cashierReport($businessId, $branchId, $filters) : collect(),
            'branchReport' => $businessId ? $sales->branchReport($businessId, null, $filters) : collect(),
            'selectedSale' => $this->selectedSale($businessId, $branchId),
            'branches' => $businessId ? Branch::query()->where('business_id', $businessId)->orderBy('name')->get() : collect(),
        ])->title('Sales monitoring');
    }

    public function updated($property): void
    {
        if ($property !== 'view') {
            $this->resetPage();
        }
    }

    public function showDetails(string $saleUuid): void
    {
        $this->view = 'details';
        $this->transactionNumber = $saleUuid;
    }

    public function exportCsv(BusinessContext $businessContext, BranchContext $branchContext, SalesMonitoringService $sales): StreamedResponse
    {
        $businessId = $this->resolvedBusinessId($businessContext);
        abort_unless($businessId, 403);

        $rows = $sales->exportedRows($businessId, $this->resolvedBranchId($branchContext), $this->filters());

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Sale number', 'Transaction number', 'Branch', 'Cashier', 'Customer', 'Sold at', 'Status', 'Payment status', 'Sale type', 'Total', 'Gross profit']);

            foreach ($rows as $sale) {
                fputcsv($handle, [
                    $sale->sale_number,
                    $sale->transaction_number,
                    $sale->branch?->name,
                    $sale->cashier_name,
                    $sale->customer_name,
                    $sale->sold_at?->toISOString(),
                    $sale->status,
                    $sale->payment_status,
                    $sale->sale_type,
                    $sale->total_minor,
                    $sale->gross_profit_minor,
                ]);
            }

            fclose($handle);
        }, 'marketlens-sales-'.now()->format('YmdHis').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function exportPdfFoundation(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            echo "MarketLens Sales Monitoring PDF Export\n";
            echo "PDF rendering engine will attach to this read-only report export foundation.\n";
        }, 'marketlens-sales-export-foundation.txt', ['Content-Type' => 'text/plain']);
    }

    public function money(int $minor): string
    {
        $sign = $minor < 0 ? '-' : '';

        return $sign.'NGN '.number_format(abs($minor) / 100, 2);
    }

    public function quantity(int|float|string $value): string
    {
        return number_format((float) $value, 4);
    }

    private function selectedSale(?int $businessId, ?int $branchId): ?ReplicatedSale
    {
        if (! $businessId || $this->view !== 'details' || ! $this->transactionNumber) {
            return null;
        }

        return ReplicatedSale::query()
            ->with(['items', 'payments', 'branch', 'device', 'syncBatch'])
            ->where('business_id', $businessId)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->where(function ($query): void {
                $query->where('uuid', $this->transactionNumber)
                    ->orWhere('sale_number', $this->transactionNumber)
                    ->orWhere('transaction_number', $this->transactionNumber);
            })
            ->first();
    }

    private function resolvedBranchId(BranchContext $branchContext): ?int
    {
        return $this->branchFilter ? (int) $this->branchFilter : $branchContext->id();
    }

    private function resolvedBusinessId(BusinessContext $businessContext): ?int
    {
        return $businessContext->id() ?: auth()->user()?->ownedBusinesses()->value('businesses.id');
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(): array
    {
        $saleStatus = $this->saleStatus;

        if ($this->view === 'cancelled') {
            $saleStatus = 'cancelled';
        }

        if ($this->view === 'returned') {
            $saleStatus = null;
        }

        return [
            'from' => $this->from ? Carbon::parse($this->from)->toDateString() : null,
            'to' => $this->to ? Carbon::parse($this->to)->toDateString() : null,
            'branch_id' => $this->branchFilter,
            'cashier' => $this->cashier,
            'customer' => $this->customer,
            'product' => $this->product,
            'category' => $this->category,
            'payment_status' => $this->paymentStatus,
            'sale_status' => $saleStatus,
            'returned_only' => $this->view === 'returned',
            'sale_type' => $this->saleType,
            'transaction_number' => $this->transactionNumber && $this->view !== 'details' ? $this->transactionNumber : null,
        ];
    }

    private function canViewSales(int $businessId): bool
    {
        $business = auth()->user()?->ownedBusinesses()->whereKey($businessId)->first();

        return $business !== null && app(BusinessLicenseEntitlementService::class)->allowsAny($business, ['sales']);
    }
}
