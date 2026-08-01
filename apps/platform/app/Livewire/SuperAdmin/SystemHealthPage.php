<?php

namespace App\Livewire\SuperAdmin;

use App\Domain\Observability\Services\PlatformHealthService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.super-admin')]
class SystemHealthPage extends Component
{
    public function render(PlatformHealthService $health): View
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        return view('livewire.super-admin.system-health-page', [
            'snapshot' => $health->snapshot(),
        ])->title('Platform System Health');
    }
}
