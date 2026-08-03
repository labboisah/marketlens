<?php

namespace App\Livewire\SuperAdmin;

use App\Domain\Billing\Models\Invoice;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.super-admin')]
class BillingInvoicesPage extends Component
{
    use AuthorizesRequests;

    public function render(): View
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        return view('livewire.super-admin.billing-invoices-page', [
            'invoices' => Invoice::query()
                ->with(['business', 'subscription'])
                ->latest('issued_at')
                ->limit(50)
                ->get(),
        ])->title('Billing invoices');
    }
}
