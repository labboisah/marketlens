<?php

namespace App\Livewire\Platform\Staff;

use App\Domain\Staff\Services\StaffPerformanceMonitoringService;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Services\BranchContext;
use App\Domain\Tenancy\Services\BusinessContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app')]
class StaffPerformanceMonitoringPage extends Component
{
    #[Url]
    public string $view = 'cashiers';

    #[Url]
    public ?string $from = null;

    #[Url]
    public ?string $to = null;

    #[Url]
    public ?string $branchFilter = null;

    #[Url]
    public ?string $role = null;

    #[Url]
    public ?string $staff = null;

    public function mount(): void
    {
        $this->from ??= now()->startOfMonth()->toDateString();
        $this->to ??= now()->toDateString();
    }

    public function render(BusinessContext $businessContext, BranchContext $branchContext, StaffPerformanceMonitoringService $staffPerformance): View
    {
        $businessId = $this->resolvedBusinessId($businessContext);
        $branchId = $this->resolvedBranchId($branchContext);
        $filters = $this->filters();

        if ($businessId && $this->canViewStaffPerformance($businessId) === false) {
            return view('livewire.platform.staff.staff-performance-monitoring-page', [
                'businessId' => $businessId,
                'accessDenied' => true,
                'overview' => null,
                'cashiers' => collect(),
                'storekeepers' => collect(),
                'managers' => collect(),
                'activitySummaries' => collect(),
                'exceptions' => [],
                'latestSynchronization' => null,
                'branches' => collect(),
            ])->title('Staff performance monitoring');
        }

        return view('livewire.platform.staff.staff-performance-monitoring-page', [
            'businessId' => $businessId,
            'accessDenied' => false,
            'overview' => $businessId ? $staffPerformance->overview($businessId, $branchId, $filters) : null,
            'cashiers' => $businessId ? $staffPerformance->cashierPerformance($businessId, $branchId, $filters) : collect(),
            'storekeepers' => $businessId ? $staffPerformance->storekeeperActivity($businessId, $branchId, $filters) : collect(),
            'managers' => $businessId ? $staffPerformance->managerActivity($businessId, $branchId, $filters) : collect(),
            'activitySummaries' => $businessId ? $staffPerformance->activitySummaries($businessId, $branchId, $filters) : collect(),
            'exceptions' => $businessId ? $staffPerformance->exceptions($businessId, $branchId, $filters) : [],
            'latestSynchronization' => $businessId ? $staffPerformance->latestSynchronization($businessId, $branchId, $filters) : null,
            'branches' => $businessId ? Branch::query()->where('business_id', $businessId)->orderBy('name')->get() : collect(),
        ])->title('Staff performance monitoring');
    }

    public function exportCsv(BusinessContext $businessContext, BranchContext $branchContext, StaffPerformanceMonitoringService $staffPerformance): StreamedResponse
    {
        $businessId = $this->resolvedBusinessId($businessContext);
        abort_unless($businessId, 403);
        $rows = $staffPerformance->cashierPerformance($businessId, $this->resolvedBranchId($branchContext), $this->filters());

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Cashier', 'Total sales', 'Transactions', 'Average sale', 'Discounts', 'Returns', 'Cancelled', 'Expected cash', 'Actual cash', 'Shortage', 'Surplus', 'Shift minutes', 'Last active shift', 'Last synchronization']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['cashier_name'],
                    $row['total_sales_minor'],
                    $row['transaction_count'],
                    $row['average_sale_minor'],
                    $row['discounts_minor'],
                    $row['returned_count'],
                    $row['cancelled_count'],
                    $row['expected_cash_minor'],
                    $row['actual_cash_minor'],
                    $row['shortage_minor'],
                    $row['surplus_minor'],
                    $row['shift_duration_minutes'],
                    $row['last_active_shift_at'],
                    $row['last_synchronized_at'],
                ]);
            }

            fclose($handle);
        }, 'marketlens-staff-performance-'.now()->format('YmdHis').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function exportPdfFoundation(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            echo "MarketLens Staff Performance Monitoring PDF Export\n";
            echo "PDF rendering engine will attach to this neutral, read-only staff monitoring report foundation.\n";
        }, 'marketlens-staff-performance-export-foundation.txt', ['Content-Type' => 'text/plain']);
    }

    public function money(int $minor): string
    {
        $sign = $minor < 0 ? '-' : '';

        return $sign.'NGN '.number_format(abs($minor) / 100, 2);
    }

    public function minutes(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes.' min';
        }

        return intdiv($minutes, 60).'h '.($minutes % 60).'m';
    }

    private function filters(): array
    {
        return [
            'from' => $this->from ? Carbon::parse($this->from)->toDateString() : null,
            'to' => $this->to ? Carbon::parse($this->to)->toDateString() : null,
            'branch_id' => $this->branchFilter,
            'role' => $this->role,
            'staff' => $this->staff,
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

    private function canViewStaffPerformance(int $businessId): bool
    {
        $business = auth()->user()?->ownedBusinesses()
            ->with('currentSubscription.plan.features')
            ->whereKey($businessId)
            ->first();

        $features = $business?->currentSubscription?->plan?->features
            ->pluck('name')
            ->map(fn (string $name): string => strtolower($name))
            ->all() ?? [];

        return collect($features)->contains(fn (string $feature): bool => str_contains($feature, 'cashier shifts') || str_contains($feature, 'reports') || str_contains($feature, 'staff accountability'));
    }
}
