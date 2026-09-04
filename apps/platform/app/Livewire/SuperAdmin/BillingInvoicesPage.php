<?php

namespace App\Livewire\SuperAdmin;

use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Services\BillingInvoiceService;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.super-admin')]
class BillingInvoicesPage extends Component
{
    use AuthorizesRequests;

    public ?int $selectedInvoiceId = null;

    public function render(): View
    {
        $this->assertSuperAdmin();

        return view('livewire.super-admin.billing-invoices-page', [
            'invoices' => Invoice::query()
                ->with(['business', 'subscription.plan', 'payments'])
                ->latest('issued_at')
                ->limit(50)
                ->get(),
            'selectedInvoice' => $this->selectedInvoice(),
        ])->title('Billing invoices');
    }

    public function money(int|float|string|null $minor, ?string $currency = null): string
    {
        return ($currency ?: config('marketlens.currency.default', 'NGN')).' '.number_format(((int) $minor) / 100, 2);
    }

    public function selectInvoice(int $invoiceId): void
    {
        $this->assertSuperAdmin();
        $this->resetErrorBag();

        $this->selectedInvoiceId = Invoice::query()->whereKey($invoiceId)->value('id');
    }

    public function markPaid(int $invoiceId, BillingInvoiceService $invoiceService): void
    {
        $this->assertSuperAdmin();
        $this->resetErrorBag();

        $invoice = Invoice::query()->with(['payments', 'business', 'subscription.plan'])->findOrFail($invoiceId);

        if ($invoice->status !== InvoiceStatus::Issued) {
            $this->addError('invoiceAction', 'Only issued invoices can be marked paid.');

            return;
        }

        $outstandingMinor = $this->outstandingMinor($invoice);

        if ($outstandingMinor <= 0) {
            $this->addError('invoiceAction', 'This invoice has no outstanding balance.');

            return;
        }

        $invoiceService->recordInvoicePayment(
            $invoice,
            $outstandingMinor,
            'manual',
            'SUPERADMIN-'.$invoice->invoice_number,
        );

        $this->selectedInvoiceId = $invoice->id;
        $this->dispatch('notify', tone: 'success', title: 'Invoice marked paid', message: $invoice->invoice_number.' has been marked as paid.');
    }

    public function cancelInvoice(int $invoiceId): void
    {
        $this->assertSuperAdmin();
        $this->resetErrorBag();

        $invoice = Invoice::query()->findOrFail($invoiceId);

        if ($invoice->status !== InvoiceStatus::Issued) {
            $this->addError('invoiceAction', 'Only issued invoices can be cancelled.');

            return;
        }

        $invoice->forceFill(['status' => InvoiceStatus::Cancelled])->save();
        $this->selectedInvoiceId = $invoice->id;
        $this->dispatch('notify', tone: 'warning', title: 'Invoice cancelled', message: $invoice->invoice_number.' has been cancelled.');
    }

    public function outstandingMinor(Invoice $invoice): int
    {
        $totalMinor = (int) $invoice->getRawOriginal('total_minor');
        $paidMinor = (int) $invoice->payments()
            ->where('status', PaymentStatus::Paid->value)
            ->sum('amount_minor');

        return max(0, $totalMinor - $paidMinor);
    }

    private function selectedInvoice(): ?Invoice
    {
        if (! $this->selectedInvoiceId) {
            return null;
        }

        return Invoice::query()
            ->with(['business', 'subscription.plan', 'items', 'payments'])
            ->find($this->selectedInvoiceId);
    }

    private function assertSuperAdmin(): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);
    }
}
