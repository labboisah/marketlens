<?php

namespace App\Livewire\Platform\Reports;

use App\Domain\Reporting\Models\ReportExport;
use App\Domain\Reporting\Models\ScheduledReport;
use App\Domain\Reporting\Services\ReportCentreService;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Domain\Tenancy\Services\BusinessContext;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class ReportCentrePage extends Component
{
    use AuthorizesRequests;

    #[Url]
    public string $report = 'daily_sales';

    public string $format = 'csv';

    public ?string $from = null;

    public ?string $to = null;

    public string $branchId = '';

    public string $status = '';

    public string $product = '';

    public string $cashier = '';

    public string $customer = '';

    public string $supplier = '';

    public string $scheduleFrequency = 'weekly';

    public function mount(): void
    {
        $this->from ??= now()->startOfMonth()->toDateString();
        $this->to ??= now()->toDateString();
    }

    public function render(BusinessContext $businessContext, ReportCentreService $reports): View
    {
        $business = $this->currentBusiness($businessContext);
        $this->authorize('view', $business);

        $filters = $this->filters();
        $previewRows = $reports->previewRows($business->id, $this->report, $filters);
        $history = ReportExport::query()
            ->where('business_id', $business->id)
            ->latest()
            ->limit(15)
            ->get()
            ->map(function (ReportExport $export) use ($reports): ReportExport {
                $export->setAttribute('download_url', $reports->signedDownloadUrl($export));

                return $export;
            });

        return view('livewire.platform.reports.report-centre-page', [
            'business' => $business,
            'branches' => Branch::query()->where('business_id', $business->id)->orderBy('name')->get(),
            'groups' => $reports->groups(),
            'reportLabel' => $reports->reportLabel($this->report),
            'previewRows' => $previewRows,
            'history' => $history,
            'scheduledReports' => ScheduledReport::query()->where('business_id', $business->id)->latest()->limit(10)->get(),
        ])->title('Reports and exports');
    }

    public function queueExport(BusinessContext $businessContext, ReportCentreService $reports): void
    {
        $business = $this->currentBusiness($businessContext);
        $this->authorize('view', $business);

        $this->validate([
            'report' => ['required', 'string'],
            'format' => ['required', 'in:csv,pdf'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'branchId' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'max:80'],
            'product' => ['nullable', 'string', 'max:120'],
            'cashier' => ['nullable', 'string', 'max:120'],
            'customer' => ['nullable', 'string', 'max:120'],
            'supplier' => ['nullable', 'string', 'max:120'],
        ]);

        $reports->queueExport($business, auth()->user(), $this->report, $this->format, $this->filters());
        $this->dispatch('notify', tone: 'success', title: 'Report queued', message: 'The report export was queued and will appear in report history.');
    }

    public function createSchedule(BusinessContext $businessContext, ReportCentreService $reports): void
    {
        $business = $this->currentBusiness($businessContext);
        $this->authorize('manage', $business);

        $this->validate([
            'report' => ['required', 'string'],
            'format' => ['required', 'in:csv,pdf'],
            'scheduleFrequency' => ['required', 'in:daily,weekly,monthly'],
        ]);

        $reports->createSchedule($business, auth()->user(), $this->report, $this->format, $this->scheduleFrequency, $this->filters());
        $this->dispatch('notify', tone: 'success', title: 'Schedule created', message: 'The scheduled-report foundation was saved.');
    }

    private function currentBusiness(BusinessContext $businessContext): Business
    {
        return auth()->user()->ownedBusinesses()
            ->with('branches')
            ->whereKey($businessContext->id() ?: auth()->user()->ownedBusinesses()->value('businesses.id'))
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(): array
    {
        return collect([
            'from' => $this->from,
            'to' => $this->to,
            'branch_id' => $this->branchId ?: null,
            'status' => $this->status ?: null,
            'product' => $this->product ?: null,
            'cashier' => $this->cashier ?: null,
            'customer' => $this->customer ?: null,
            'supplier' => $this->supplier ?: null,
        ])->filter(fn ($value): bool => filled($value))->all();
    }
}
