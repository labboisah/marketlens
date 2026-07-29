<?php

namespace App\Livewire\Platform\Devices;

use App\Domain\Devices\Models\Device;
use App\Domain\Devices\Models\DeviceActivation;
use App\Domain\Devices\Services\ShopAppActivationService;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Domain\Tenancy\Services\BusinessContext;
use App\Domain\Versions\Models\ApplicationVersion;
use App\Enums\ApplicationVersionStatus;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class DeviceActivationPage extends Component
{
    use AuthorizesRequests;

    public ?int $branchId = null;

    public string $deviceLabel = '';

    public string $deviceType = 'shop_app';

    public int $expiresInMinutes = 30;

    public ?int $applicationVersionId = null;

    public ?string $generatedCode = null;

    public function render(BusinessContext $businessContext)
    {
        $business = $this->currentBusiness($businessContext);
        $this->authorize('manage', $business);

        $branches = $business->branches()->orderBy('name')->get();
        $devices = Device::query()
            ->with(['activations' => fn ($query) => $query->latest()])
            ->where('business_id', $business->id)
            ->latest()
            ->get();
        $versions = ApplicationVersion::query()
            ->where('application', 'shop-app')
            ->where('status', ApplicationVersionStatus::Published)
            ->orderByDesc('released_at')
            ->orderByDesc('id')
            ->get();

        $this->branchId ??= $branches->first()?->id;

        return view('livewire.platform.devices.device-activation-page', [
            'business' => $business,
            'branches' => $branches,
            'devices' => $devices,
            'versions' => $versions,
        ]);
    }

    public function generate(ShopAppActivationService $activationService, BusinessContext $businessContext): void
    {
        $business = $this->currentBusiness($businessContext);
        $this->authorize('manage', $business);

        $validated = $this->validate([
            'branchId' => ['required', 'integer', 'exists:branches,id'],
            'deviceLabel' => ['required', 'string', 'max:120'],
            'deviceType' => ['required', 'string', 'max:40'],
            'expiresInMinutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'applicationVersionId' => ['nullable', 'integer', 'exists:application_versions,id'],
        ]);

        $branch = Branch::query()
            ->where('business_id', $business->id)
            ->whereKey($validated['branchId'])
            ->firstOrFail();

        $result = $activationService->createActivation($business, $branch, auth()->user(), [
            'device_label' => $validated['deviceLabel'],
            'device_type' => $validated['deviceType'],
            'expires_at' => now()->addMinutes((int) $validated['expiresInMinutes']),
            'application_version_id' => $validated['applicationVersionId'],
        ]);

        $this->generatedCode = $result['code'];
        $this->deviceLabel = '';
        session()->flash('device_status', 'Activation code generated. It is shown once.');
        $this->dispatch('notify', tone: 'success', title: 'Activation code generated', message: 'Share the short-lived code with the Shop App. It is shown only once.');
    }

    public function revokeActivation(int $activationId, ShopAppActivationService $activationService, BusinessContext $businessContext): void
    {
        $business = $this->currentBusiness($businessContext);
        $this->authorize('manage', $business);

        $activation = DeviceActivation::query()
            ->whereHas('device', fn ($query) => $query->where('business_id', $business->id))
            ->findOrFail($activationId);

        $activationService->revokeActivation($activation, auth()->user());
        $this->dispatch('notify', tone: 'warning', title: 'Activation revoked', message: 'The unused activation code can no longer be used.');
    }

    public function revokeDevice(int $deviceId, ShopAppActivationService $activationService, BusinessContext $businessContext): void
    {
        $business = $this->currentBusiness($businessContext);
        $this->authorize('manage', $business);

        $device = Device::query()
            ->where('business_id', $business->id)
            ->findOrFail($deviceId);

        $activationService->revokeDevice($device, auth()->user());
        $this->dispatch('notify', tone: 'danger', title: 'Device revoked', message: 'The Shop App credential is no longer authorized for synchronization.');
    }

    private function currentBusiness(BusinessContext $businessContext): Business
    {
        return Business::query()
            ->with(['branches', 'currentSubscription.plan.features'])
            ->whereKey($businessContext->id() ?: auth()->user()->ownedBusinesses()->value('businesses.id'))
            ->firstOrFail();
    }
}
