<?php

namespace App\Livewire\Platform\Payments;

use App\Domain\Licensing\Services\BusinessLicenseEntitlementService;
use App\Domain\Payments\Services\PaymentMonitoringService;
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
class PaymentMonitoringPage extends Component
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
    public ?string $method = null;

    #[Url]
    public ?string $status = null;

    #[Url]
    public ?string $cashier = null;

    #[Url]
    public ?string $transactionNumber = null;

    public function mount(): void
    {
        $this->from ??= now()->startOfMonth()->toDateString();
        $this->to ??= now()->toDateString();
    }

    public function render(BusinessContext $businessContext, BranchContext $branchContext, PaymentMonitoringService $payments): View
    {
        $businessId = $this->resolvedBusinessId($businessContext);
        $branchId = $this->resolvedBranchId($branchContext);
        $filters = $this->filters();

        if ($businessId && $this->canViewPayments($businessId) === false) {
            return view('livewire.platform.payments.payment-monitoring-page', [
                'businessId' => $businessId,
                'accessDenied' => true,
                'overview' => null,
                'transactions' => null,
                'methodBreakdown' => collect(),
                'branchComparison' => collect(),
                'cashierComparison' => collect(),
                'exceptions' => [],
                'branches' => collect(),
            ])->title('Payment monitoring');
        }

        return view('livewire.platform.payments.payment-monitoring-page', [
            'businessId' => $businessId,
            'accessDenied' => false,
            'overview' => $businessId ? $payments->overview($businessId, $branchId, $filters) : null,
            'transactions' => $businessId ? $payments->payments($businessId, $branchId, $filters) : null,
            'methodBreakdown' => $businessId ? $payments->methodBreakdown($businessId, $branchId, $filters) : collect(),
            'branchComparison' => $businessId ? $payments->branchComparison($businessId, null, $filters) : collect(),
            'cashierComparison' => $businessId ? $payments->cashierComparison($businessId, $branchId, $filters) : collect(),
            'exceptions' => $businessId ? $payments->exceptions($businessId, $branchId, $filters) : [],
            'branches' => $businessId ? Branch::query()->where('business_id', $businessId)->orderBy('name')->get() : collect(),
        ])->title('Payment monitoring');
    }

    public function exportCsv(BusinessContext $businessContext, BranchContext $branchContext, PaymentMonitoringService $payments): StreamedResponse
    {
        $businessId = $this->resolvedBusinessId($businessContext);
        abort_unless($businessId, 403);
        $rows = $payments->paymentQuery($businessId, $this->resolvedBranchId($branchContext), $this->filters())->latest('replicated_sale_payments.paid_at')->limit(1000)->get();

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Sale number', 'Transaction number', 'Branch', 'Cashier', 'Method', 'Status', 'Amount', 'Reference', 'Paid at']);

            foreach ($rows as $payment) {
                fputcsv($handle, [
                    $payment->sale?->sale_number,
                    $payment->sale?->transaction_number,
                    $payment->branch?->name,
                    $payment->sale?->cashier_name,
                    $payment->method,
                    $payment->status,
                    $payment->amount_minor,
                    $payment->reference,
                    $payment->paid_at?->toISOString(),
                ]);
            }

            fclose($handle);
        }, 'marketlens-payments-'.now()->format('YmdHis').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function exportPdfFoundation(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            echo "MarketLens Payment Monitoring PDF Export\n";
            echo "PDF rendering engine will attach to this read-only reconciliation report export foundation.\n";
        }, 'marketlens-payments-export-foundation.txt', ['Content-Type' => 'text/plain']);
    }

    public function money(int $minor): string
    {
        $sign = $minor < 0 ? '-' : '';

        return $sign.'NGN '.number_format(abs($minor) / 100, 2);
    }

    private function filters(): array
    {
        $method = $this->method;
        $status = $this->status;

        if ($this->view === 'cash') {
            $method = 'cash';
        } elseif ($this->view === 'pos') {
            $method = 'pos_terminal';
        } elseif ($this->view === 'credit') {
            $method = 'credit';
        } elseif ($this->view === 'unverified_transfers') {
            $method = 'bank_transfer';
            $status = 'unverified';
        }

        return [
            'from' => $this->from ? Carbon::parse($this->from)->toDateString() : null,
            'to' => $this->to ? Carbon::parse($this->to)->toDateString() : null,
            'branch_id' => $this->branchFilter,
            'method' => $method,
            'status' => $status,
            'cashier' => $this->cashier,
            'transaction_number' => $this->transactionNumber,
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

    private function canViewPayments(int $businessId): bool
    {
        $business = auth()->user()?->ownedBusinesses()->whereKey($businessId)->first();

        return $business !== null && app(BusinessLicenseEntitlementService::class)->allowsAny($business, ['sales', 'cashier settlement']);
    }
}
