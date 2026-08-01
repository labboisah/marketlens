<?php

namespace App\Livewire\SuperAdmin;

use App\Domain\SuperAdmin\Services\SuperAdminDashboardService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.super-admin')]
class SuperAdminDashboardPage extends Component
{
    use AuthorizesRequests;

    public string $confirmationPhrase = '';

    public function render(SuperAdminDashboardService $dashboard): View
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        return view('livewire.super-admin.dashboard-page', [
            'ownerMetrics' => $dashboard->ownerAccountMetrics(),
            'ownerAccounts' => $dashboard->businessOwnerAccountSummaries(),
            'twoFactorReady' => (bool) auth()->user()?->super_admin_confirmed_at,
        ])->title('Business Owner Account Administration');
    }

    public function confirmSensitiveAction(SuperAdminDashboardService $dashboard): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $this->validate(['confirmationPhrase' => ['required', 'in:CONFIRM SUPER ADMIN ACTION']]);

        $dashboard->recordConfirmation(auth()->user(), 'super_admin.sensitive_action_confirmation', $this->confirmationPhrase, request()->ip(), request()->userAgent());
        auth()->user()->forceFill(['super_admin_confirmed_at' => now()])->save();
        $this->confirmationPhrase = '';

        $this->dispatch('notify', tone: 'success', title: 'Confirmation recorded', message: 'Sensitive action confirmation and audit foundation were recorded.');
    }
}
