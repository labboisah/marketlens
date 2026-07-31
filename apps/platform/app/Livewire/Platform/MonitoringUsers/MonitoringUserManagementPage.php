<?php

namespace App\Livewire\Platform\MonitoringUsers;

use App\Domain\Access\Models\BusinessMonitoringUser;
use App\Domain\Access\Models\MonitoringUserInvitation;
use App\Domain\Access\Services\MonitoringUserManagementService;
use App\Domain\Tenancy\Models\Business;
use App\Domain\Tenancy\Services\BusinessContext;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class MonitoringUserManagementPage extends Component
{
    use AuthorizesRequests;

    public string $email = '';

    public ?int $roleId = null;

    /** @var array<int, int|string> */
    public array $branchIds = [];

    /** @var array<string, bool> */
    public array $modulePermissions = [];

    public int $expiresInDays = 7;

    public function mount(BusinessContext $businessContext, MonitoringUserManagementService $service): void
    {
        $business = $this->currentBusiness($businessContext);
        $this->authorize('manageMonitoringUsers', $business);
        $this->roleId = $service->roles()->firstWhere('slug', 'remote-manager')?->id;
        foreach (array_keys($service->permissions()) as $permission) {
            $this->modulePermissions[$permission] = false;
        }
    }

    public function render(BusinessContext $businessContext, MonitoringUserManagementService $service): View
    {
        $business = $this->currentBusiness($businessContext);
        $this->authorize('manageMonitoringUsers', $business);

        return view('livewire.platform.monitoring-users.monitoring-user-management-page', [
            'business' => $business,
            'roles' => $service->roles(),
            'permissions' => $service->permissions(),
            'branches' => $business->branches()->orderBy('name')->get(),
            'assignments' => BusinessMonitoringUser::query()
                ->with(['user', 'role', 'branches'])
                ->where('business_id', $business->id)
                ->latest()
                ->get(),
            'invitations' => MonitoringUserInvitation::query()
                ->with('role')
                ->where('business_id', $business->id)
                ->latest()
                ->get(),
        ])->title('Monitoring users');
    }

    public function invite(BusinessContext $businessContext, MonitoringUserManagementService $service): void
    {
        
        $business = $this->currentBusiness($businessContext);
        
        $this->authorize('manageMonitoringUsers', $business);

        $roleIds = $service->roles()->pluck('id')->all();
        $validated = $this->validate([
            'email' => ['required', 'email', 'max:160'],
            'roleId' => ['required', 'integer', Rule::in($roleIds)],
            'branchIds' => ['array'],
            'branchIds.*' => ['integer'],
            'modulePermissions' => ['array'],
            'modulePermissions.*' => ['boolean'],
            'expiresInDays' => ['required', 'integer', 'min:1', 'max:30'],
        ]);

        $service->invite(
            $business,
            auth()->user(),
            ['email' => $validated['email'], 'role_id' => $validated['roleId'], 'expires_in_days' => $validated['expiresInDays']],
            collect($validated['branchIds'] ?? [])->map(fn ($id): int => (int) $id)->values()->all(),
            collect($validated['modulePermissions'])->filter()->keys()->all(),
        );

        $this->email = '';
        $this->branchIds = [];
        foreach (array_keys($service->permissions()) as $permission) {
            $this->modulePermissions[$permission] = false;
        }

        $this->dispatch('notify', tone: 'success', title: 'Monitoring user invited', message: 'The invitation and access settings were recorded.');
    }

    public function resend(BusinessContext $businessContext, MonitoringUserManagementService $service, int $invitationId): void
    {
        $business = $this->currentBusiness($businessContext);
        $this->authorize('manageMonitoringUsers', $business);
        $service->resend($business, auth()->user(), $invitationId);
        $this->dispatch('notify', tone: 'success', title: 'Invitation resent', message: 'The invitation expiration was extended.');
    }

    public function cancel(BusinessContext $businessContext, MonitoringUserManagementService $service, int $invitationId): void
    {
        $business = $this->currentBusiness($businessContext);
        $this->authorize('manageMonitoringUsers', $business);
        $service->cancel($business, auth()->user(), $invitationId);
        $this->dispatch('notify', tone: 'warning', title: 'Invitation cancelled', message: 'The pending invitation can no longer be accepted.');
    }

    public function suspend(BusinessContext $businessContext, MonitoringUserManagementService $service, int $assignmentId): void
    {
        $business = $this->currentBusiness($businessContext);
        $this->authorize('manageMonitoringUsers', $business);
        $service->suspend($business, auth()->user(), $assignmentId);
        $this->dispatch('notify', tone: 'warning', title: 'Monitoring user suspended', message: 'The user cannot access this business while suspended.');
    }

    public function revoke(BusinessContext $businessContext, MonitoringUserManagementService $service, int $assignmentId): void
    {
        $business = $this->currentBusiness($businessContext);
        $this->authorize('manageMonitoringUsers', $business);
        $service->revoke($business, auth()->user(), $assignmentId);
        $this->dispatch('notify', tone: 'danger', title: 'Access revoked', message: 'The monitoring user access was revoked for this business.');
    }

    private function currentBusiness(BusinessContext $businessContext): Business
    {
        return auth()->user()->accessibleBusinesses()
            ->where('id', $businessContext->id() ?: auth()->user()->accessibleBusinesses()->first()?->id)
            ->firstOrFail();
    }
}
