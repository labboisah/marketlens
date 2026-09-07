<?php

namespace App\Livewire\Platform\Backups;

use App\Domain\Backups\Models\RestorePoint;
use App\Domain\Backups\Models\RestorePointRequest;
use App\Domain\Backups\Services\RestorePointService;
use App\Domain\Devices\Models\Device;
use App\Domain\Tenancy\Services\BusinessContext;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class RestorePointManagementPage extends Component
{
    public ?int $deviceId = null;

    public string $name = '';

    public string $confirmation = '';

    public ?string $selectedUuid = null;

    public string $action = '';

    public string $notice = '';

    private function devices()
    {
        $query = Device::query()->where('type', 'shop_app')->with(['business', 'branch']);
        if (! auth()->user()->isSuperAdmin()) {
            $user = auth()->user();
            $businessId = app(BusinessContext::class)->id() ?? 0;
            $query->where('business_id', $businessId);
            if (! $user->isBusinessOwner($businessId)) {
                $assignment = $user->monitoringAssignments()->where('business_id', $businessId)->where('status', 'active')->whereNull('suspended_at')->whereNull('revoked_at')->first();
                if ($assignment && $assignment->branches()->exists()) {
                    $query->whereIn('branch_id', $assignment->branches()->pluck('branches.id'));
                }
            }
        }

        return $query;
    }

    private function selected(string $permission = 'view'): Device
    {
        $device = $this->devices()->findOrFail($this->deviceId);
        app(RestorePointService::class)->authorize(auth()->user(), $device, $permission);

        return $device;
    }

    public function updatedDeviceId(): void
    {
        $this->selectedUuid = null;
        $this->confirmation = '';
        $this->notice = '';
    }

    public function createPoint(): void
    {
        $this->validate(['name' => 'required|string|max:120']);
        app(RestorePointService::class)->request(auth()->user(), $this->selected('create'), 'create', $this->name);
        $this->name = '';
        $this->notice = 'Backup requested. The server will create and upload it when online and configured.';
    }

    public function selectPoint(string $uuid, string $action): void
    {
        abort_unless(in_array($action, ['restore', 'delete']), 422);
        $device = $this->selected($action);
        RestorePoint::query()->where('device_id', $device->id)->where('business_id', $device->business_id)->where('branch_id', $device->branch_id)->where('uuid', $uuid)->firstOrFail();
        $this->selectedUuid = $uuid;
        $this->action = $action;
        $this->confirmation = '';
    }

    public function confirmPoint(): void
    {
        abort_unless(in_array($this->action, ['restore', 'delete']), 422);
        $device = $this->selected($this->action);
        $this->validate(['confirmation' => 'required|in:'.strtoupper($this->action)]);
        $point = RestorePoint::query()->where('device_id', $device->id)->where('business_id', $device->business_id)->where('branch_id', $device->branch_id)->where('uuid', $this->selectedUuid)->firstOrFail();
        $service = app(RestorePointService::class);
        if ($this->action === 'restore') {
            $service->request(auth()->user(), $device, 'restore', $point->name, $point);
            $this->notice = 'Restore requested. A local server administrator must review and confirm it.';
        } else {
            $service->delete(auth()->user(), $point);
            $this->notice = 'Restore point deleted from Platform storage.';
        }
        $this->selectedUuid = null;
        $this->confirmation = '';
    }

    public function cancelRequest(string $uuid): void
    {
        $device = $this->selected();
        DB::transaction(function () use ($device, $uuid): void {
            $job = RestorePointRequest::query()->where('business_id', $device->business_id)->where('branch_id', $device->branch_id)->where('device_id', $device->id)->where('uuid', $uuid)->lockForUpdate()->firstOrFail();
            app(RestorePointService::class)->authorize(auth()->user(), $device, $job->kind === 'create' ? 'create' : 'restore');
            abort_unless(($job->status === 'pending' || ($job->kind === 'create' && $job->status === 'processing')), 409, 'An executing restore cannot be cancelled.');
            $job->update(['status' => 'cancelled', 'completed_at' => now()]);
            app(RestorePointService::class)->audit('backup.request_cancelled', $device, $uuid, auth()->id());
        });
        $this->notice = 'Request cancelled.';
    }

    public function render()
    {
        $devices = $this->devices()->get()->filter(fn ($device) => auth()->user()->isSuperAdmin() || auth()->user()->hasBusinessPermission($device->business_id, 'backups.view'));
        $this->deviceId ??= $devices->first()?->id;
        $device = $this->deviceId ? $this->selected() : null;
        $points = $device ? RestorePoint::query()->where('business_id', $device->business_id)->where('branch_id', $device->branch_id)->where('device_id', $device->id)->latest()->limit(100)->get() : collect();
        $requests = $device ? RestorePointRequest::query()->where('business_id', $device->business_id)->where('branch_id', $device->branch_id)->where('device_id', $device->id)->latest()->limit(30)->get() : collect();
        $allowed = fn ($permission) => $device && (auth()->user()->isSuperAdmin() || auth()->user()->hasBusinessPermission($device->business_id, 'backups.'.$permission));

        return view('livewire.platform.backups.restore-point-management-page', compact('devices', 'device', 'points', 'requests', 'allowed'))->layout(auth()->user()->isSuperAdmin() ? 'layouts.super-admin' : 'layouts.app');
    }
}
