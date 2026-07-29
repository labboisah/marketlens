<?php

namespace App\Livewire\Platform\Synchronization;

use App\Domain\Synchronization\Services\SynchronizationMonitoringService;
use App\Domain\Tenancy\Models\Business;
use App\Domain\Tenancy\Services\BusinessContext;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class SynchronizationMonitoringPage extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url]
    public string $view = 'overview';

    #[Url]
    public ?string $batchUuid = null;

    public function render(BusinessContext $businessContext, SynchronizationMonitoringService $monitoring): View
    {
        $business = $this->currentBusiness($businessContext);
        $this->authorize('manage', $business);

        $payload = [
            'business' => $business,
            'currentView' => $this->allowedView($this->view),
            'tabs' => $this->tabs(),
            'overview' => null,
            'devices' => collect(),
            'batches' => null,
            'batch' => null,
            'failedRecords' => null,
            'conflictRecords' => null,
            'heartbeatLogs' => null,
            'protocolUsage' => collect(),
            'versionDistribution' => collect(),
        ];

        match ($payload['currentView']) {
            'devices' => $payload['devices'] = $monitoring->devices($business->id),
            'batches' => $payload['batches'] = $monitoring->batches($business->id),
            'batch-detail' => $payload['batch'] = $this->batchUuid ? $monitoring->batch($business->id, $this->batchUuid) : null,
            'failed-records' => $payload['failedRecords'] = $monitoring->failedRecords($business->id),
            'conflicts' => $payload['conflictRecords'] = $monitoring->conflictRecords($business->id),
            'heartbeats' => $payload['heartbeatLogs'] = $monitoring->heartbeatHistory($business->id),
            'protocols' => $payload['protocolUsage'] = $monitoring->protocolUsage($business->id),
            'versions' => $payload['versionDistribution'] = $monitoring->applicationVersionDistribution($business->id),
            default => $payload['overview'] = $monitoring->overview($business->id),
        };

        return view('livewire.platform.synchronization.synchronization-monitoring-page', $payload)
            ->title('Synchronization monitoring');
    }

    public function setView(string $view): void
    {
        $this->view = $this->allowedView($view);
        $this->resetPage();
    }

    public function showBatch(string $batchUuid): void
    {
        $this->batchUuid = $batchUuid;
        $this->setView('batch-detail');
    }

    public function retryBatch(string $batchUuid, BusinessContext $businessContext, SynchronizationMonitoringService $monitoring): void
    {
        $business = $this->currentBusiness($businessContext);
        $this->authorize('manage', $business);

        $monitoring->retryBatch($business->id, $batchUuid, auth()->user());
        $this->dispatch('notify', tone: 'success', title: 'Retry queued', message: 'The eligible synchronization batch has been queued for processing.');
    }

    public function markConflictReviewed(int $recordId, BusinessContext $businessContext, SynchronizationMonitoringService $monitoring): void
    {
        $business = $this->currentBusiness($businessContext);
        $this->authorize('manage', $business);

        $monitoring->markConflictReviewed($business->id, $recordId, auth()->user());
        $this->dispatch('notify', tone: 'success', title: 'Conflict marked reviewed', message: 'The conflict remains unchanged and is recorded as reviewed.');
    }

    public function revokeDevice(int $deviceId, BusinessContext $businessContext, SynchronizationMonitoringService $monitoring): void
    {
        $business = $this->currentBusiness($businessContext);
        $this->authorize('manage', $business);

        $monitoring->revokeDevice($business->id, $deviceId, auth()->user());
        $this->dispatch('notify', tone: 'danger', title: 'Device revoked', message: 'The Shop App device can no longer synchronize.');
    }

    public function requireApplicationUpdate(int $deviceId, BusinessContext $businessContext, SynchronizationMonitoringService $monitoring): void
    {
        $business = $this->currentBusiness($businessContext);
        $this->authorize('manage', $business);

        $monitoring->requireApplicationUpdate($business->id, $deviceId, auth()->user());
        $this->dispatch('notify', tone: 'warning', title: 'Update required', message: 'The device has been flagged to update before continued operation.');
    }

    public function downloadErrorReport(BusinessContext $businessContext, SynchronizationMonitoringService $monitoring)
    {
        $business = $this->currentBusiness($businessContext);
        $this->authorize('manage', $business);

        return response()->streamDownload(
            fn () => print $monitoring->redactedErrorReport($business->id),
            'marketlens-sync-error-report.csv',
            ['Content-Type' => 'text/csv']
        );
    }

    /**
     * @return array<string, string>
     */
    public function tabs(): array
    {
        return [
            'overview' => 'Overview',
            'devices' => 'Devices',
            'batches' => 'Batches',
            'failed-records' => 'Failed records',
            'conflicts' => 'Conflicts',
            'heartbeats' => 'Heartbeats',
            'protocols' => 'Protocols',
            'versions' => 'Versions',
        ];
    }

    private function allowedView(string $view): string
    {
        return array_key_exists($view, $this->tabs()) || $view === 'batch-detail' ? $view : 'overview';
    }

    private function currentBusiness(BusinessContext $businessContext): Business
    {
        return auth()->user()->ownedBusinesses()
            ->with(['branches', 'currentSubscription.plan.features'])
            ->whereKey($businessContext->id() ?: auth()->user()->ownedBusinesses()->value('businesses.id'))
            ->firstOrFail();
    }
}
