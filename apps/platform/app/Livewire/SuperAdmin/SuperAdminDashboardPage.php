<?php

namespace App\Livewire\SuperAdmin;

use App\Domain\SuperAdmin\Services\SuperAdminDashboardService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.super-admin')]
class SuperAdminDashboardPage extends Component
{
    use AuthorizesRequests;

    #[Url]
    public string $module = 'businesses';

    public string $confirmationPhrase = '';

    public function render(SuperAdminDashboardService $dashboard): View
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        return view('livewire.super-admin.dashboard-page', [
            'metrics' => $dashboard->metrics(),
            'modules' => $dashboard->modules(),
            'rows' => $dashboard->tableRows($this->module),
            'activeModule' => collect($dashboard->modules())->firstWhere('route', $this->module),
            'twoFactorReady' => (bool) auth()->user()?->super_admin_confirmed_at,
        ])->title('Platform Super Admin');
    }

    public function setModule(string $module, SuperAdminDashboardService $dashboard): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        if (! collect($dashboard->modules())->pluck('route')->contains($module)) {
            $this->addError('module', 'The selected administration module is not available.');

            return;
        }

        $this->module = $module;
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
