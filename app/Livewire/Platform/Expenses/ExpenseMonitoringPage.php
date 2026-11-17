<?php

namespace App\Livewire\Platform\Expenses;

use App\Domain\Expenses\Models\ReplicatedExpense;
use App\Domain\Expenses\Services\ExpenseMonitoringService;
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
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app')]
class ExpenseMonitoringPage extends Component
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
    public ?string $category = null;

    #[Url]
    public ?string $creator = null;

    #[Url]
    public ?string $status = null;

    #[Url]
    public ?string $expenseNumber = null;

    public function mount(): void
    {
        $this->from ??= now()->startOfMonth()->toDateString();
        $this->to ??= now()->toDateString();
    }

    public function render(BusinessContext $businessContext, BranchContext $branchContext, ExpenseMonitoringService $expenses): View
    {
        $businessId = $this->resolvedBusinessId($businessContext);
        $branchId = $this->resolvedBranchId($branchContext);
        $filters = $this->filters();

        if ($businessId && $this->canViewExpenses($businessId) === false) {
            return view('livewire.platform.expenses.expense-monitoring-page', [
                'businessId' => $businessId,
                'accessDenied' => true,
                'overview' => null,
                'transactions' => null,
                'categoryBreakdown' => collect(),
                'branchComparison' => collect(),
                'creatorBreakdown' => collect(),
                'recurringSummary' => collect(),
                'trend' => collect(),
                'expenseToSales' => [],
                'branches' => collect(),
                'selectedExpense' => null,
            ])->title('Expense monitoring');
        }

        return view('livewire.platform.expenses.expense-monitoring-page', [
            'businessId' => $businessId,
            'accessDenied' => false,
            'overview' => $businessId ? $expenses->overview($businessId, $branchId, $filters) : null,
            'transactions' => $businessId ? $expenses->expenses($businessId, $branchId, $filters) : null,
            'categoryBreakdown' => $businessId ? $expenses->categoryBreakdown($businessId, $branchId, $filters) : collect(),
            'branchComparison' => $businessId ? $expenses->branchComparison($businessId, $filters) : collect(),
            'creatorBreakdown' => $businessId ? $expenses->creatorBreakdown($businessId, $branchId, $filters) : collect(),
            'recurringSummary' => $businessId ? $expenses->recurringSummary($businessId, $branchId, $filters) : collect(),
            'trend' => $businessId ? $expenses->trend($businessId, $branchId, $filters) : collect(),
            'expenseToSales' => $businessId ? $expenses->expenseToSalesComparison($businessId, $branchId, $filters) : [],
            'branches' => $businessId ? Branch::query()->where('business_id', $businessId)->orderBy('name')->get() : collect(),
            'selectedExpense' => $this->selectedExpense($businessId, $branchId),
        ])->title('Expense monitoring');
    }

    public function showDetails(string $expenseUuid): void
    {
        $this->view = 'details';
        $this->expenseNumber = $expenseUuid;
    }

    public function exportCsv(BusinessContext $businessContext, BranchContext $branchContext, ExpenseMonitoringService $expenses): StreamedResponse
    {
        $businessId = $this->resolvedBusinessId($businessContext);
        abort_unless($businessId, 403);

        $rows = $expenses->expenseQuery($businessId, $this->resolvedBranchId($branchContext), $this->filters())
            ->latest('expense_date')
            ->limit(1000)
            ->get();

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Expense number', 'Branch', 'Category', 'Description', 'Amount', 'Payment method', 'Creator', 'Approver', 'Status', 'Date', 'Receipt attachment', 'Source device', 'Synchronized at']);

            foreach ($rows as $expense) {
                fputcsv($handle, [
                    $expense->expense_number,
                    $expense->branch?->name,
                    $expense->category_name,
                    $expense->description,
                    $expense->amount_minor,
                    $expense->payment_method,
                    $expense->creator_name,
                    $expense->approver_name,
                    $expense->status,
                    $expense->expense_date?->toISOString(),
                    $expense->receipt_attachment_path,
                    $expense->device?->name ?? $expense->device?->uuid,
                    $expense->updated_at?->toISOString(),
                ]);
            }

            fclose($handle);
        }, 'marketlens-expenses-'.now()->format('YmdHis').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function exportPdfFoundation(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            echo "MarketLens Expense Monitoring PDF Export\n";
            echo "PDF rendering engine will attach to this read-only expense report export foundation.\n";
        }, 'marketlens-expenses-export-foundation.txt', ['Content-Type' => 'text/plain']);
    }

    public function money(int $minor): string
    {
        return 'NGN '.number_format($minor / 100, 2);
    }

    private function filters(): array
    {
        $status = match ($this->view) {
            'approved' => 'approved',
            'pending_rejected' => null,
            default => $this->status,
        };

        return [
            'from' => $this->from ? Carbon::parse($this->from)->toDateString() : null,
            'to' => $this->to ? Carbon::parse($this->to)->toDateString() : null,
            'branch_id' => $this->branchFilter,
            'category' => $this->category,
            'creator' => $this->creator,
            'status' => $status,
            'statuses' => $this->view === 'pending_rejected' ? ['pending', 'rejected'] : null,
            'expense_number' => $this->view === 'details' ? null : $this->expenseNumber,
        ];
    }

    private function selectedExpense(?int $businessId, ?int $branchId): ?ReplicatedExpense
    {
        if (! $businessId || $this->view !== 'details' || ! $this->expenseNumber) {
            return null;
        }

        return ReplicatedExpense::query()
            ->with(['branch', 'device', 'syncBatch'])
            ->where('business_id', $businessId)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->where(function ($query): void {
                $query->where('uuid', $this->expenseNumber)->orWhere('expense_number', $this->expenseNumber);
            })
            ->first();
    }

    private function resolvedBusinessId(BusinessContext $businessContext): ?int
    {
        return $businessContext->id() ?: auth()->user()?->ownedBusinesses()->value('businesses.id');
    }

    private function resolvedBranchId(BranchContext $branchContext): ?int
    {
        return $this->branchFilter ? (int) $this->branchFilter : $branchContext->id();
    }

    private function canViewExpenses(int $businessId): bool
    {
        $business = auth()->user()?->ownedBusinesses()->whereKey($businessId)->first();

        return $business !== null && app(BusinessLicenseEntitlementService::class)->allowsAny($business, ['expenses', 'reports']);
    }
}
