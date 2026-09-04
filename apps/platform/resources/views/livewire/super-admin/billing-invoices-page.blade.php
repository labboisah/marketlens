<div class="flex flex-col gap-6">
  <section class="rounded-lg border border-border bg-surface p-5">
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <p class="text-sm font-semibold uppercase text-muted">Billing administration</p>
        <h1 class="mt-2 text-2xl font-bold">Invoices</h1>
        <p class="mt-2 max-w-3xl text-sm text-muted">View recent billing invoices and payment status across businesses.</p>
      </div>
      <span class="rounded-md border border-border px-3 py-2 text-sm font-semibold text-muted">Showing {{ $invoices->count() }} invoices</span>
    </div>
  </section>

  <section class="rounded-lg border border-border bg-surface p-4 overflow-x-auto">
    @error('invoiceAction')
      <div class="mb-4 rounded-lg border border-danger/30 bg-danger/10 p-3 text-sm font-semibold text-danger">{{ $message }}</div>
    @enderror

    <table class="ml-table w-full">
      <thead>
        <tr>
          <th>Invoice</th>
          <th>Business</th>
          <th>Subscription</th>
          <th>Status</th>
          <th>Amount</th>
          <th>Issued</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($invoices as $invoice)
          <tr>
            <td>
              <p class="font-medium">{{ $invoice->invoice_number }}</p>
              <p class="text-xs text-muted">{{ $invoice->uuid }}</p>
            </td>
            <td>
              <p class="font-medium">{{ $invoice->business->name }}</p>
              <p class="text-xs text-muted">{{ $invoice->business->uuid }}</p>
            </td>
            <td>
              <p class="font-medium">{{ $invoice->subscription?->plan?->name ?? 'N/A' }}</p>
              <p class="text-xs text-muted">{{ $invoice->subscription?->status?->value ?? 'No subscription' }}</p>
            </td>
            <td class="font-medium">{{ str($invoice->status->value)->replace('_', ' ')->title() }}</td>
            <td class="font-medium">{{ $this->money($invoice->getRawOriginal('total_minor'), $invoice->currency) }}</td>
            <td class="text-xs text-muted">{{ $invoice->issued_at?->format('M j, Y') ?? 'N/A' }}</td>
            <td>
              <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="selectInvoice({{ $invoice->id }})" class="rounded-md border border-border px-2 py-1 text-xs font-semibold text-foreground hover:border-primary/50">
                  View
                </button>
                @if ($invoice->status === \App\Enums\InvoiceStatus::Issued)
                  <button type="button" wire:click="markPaid({{ $invoice->id }})" wire:confirm="Mark this invoice as paid?" class="rounded-md border border-success/40 px-2 py-1 text-xs font-semibold text-success">
                    Mark paid
                  </button>
                  <button type="button" wire:click="cancelInvoice({{ $invoice->id }})" wire:confirm="Cancel this invoice?" class="rounded-md border border-danger/40 px-2 py-1 text-xs font-semibold text-danger">
                    Cancel
                  </button>
                @endif
              </div>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="7" class="text-sm text-muted">No invoices available.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </section>

  @if ($selectedInvoice)
    <section class="grid gap-4 lg:grid-cols-[0.95fr_1.05fr]">
      <section class="rounded-lg border border-border bg-surface p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <p class="text-sm font-semibold uppercase text-muted">Selected invoice</p>
            <h2 class="mt-1 text-xl font-bold">{{ $selectedInvoice->invoice_number }}</h2>
            <p class="mt-1 text-sm text-muted">{{ $selectedInvoice->business?->name }} · {{ $selectedInvoice->subscription?->plan?->name ?? 'No plan' }}</p>
          </div>
          <span class="rounded-md border border-border px-3 py-2 text-sm font-semibold">
            {{ str($selectedInvoice->status->value)->replace('_', ' ')->title() }}
          </span>
        </div>

        <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
          <div>
            <dt class="text-muted">Total</dt>
            <dd class="font-mono font-semibold">{{ $this->money($selectedInvoice->getRawOriginal('total_minor'), $selectedInvoice->currency) }}</dd>
          </div>
          <div>
            <dt class="text-muted">Outstanding</dt>
            <dd class="font-mono font-semibold">{{ $this->money($this->outstandingMinor($selectedInvoice), $selectedInvoice->currency) }}</dd>
          </div>
          <div>
            <dt class="text-muted">Issued</dt>
            <dd>{{ $selectedInvoice->issued_at?->format('M j, Y') ?? 'N/A' }}</dd>
          </div>
          <div>
            <dt class="text-muted">Due</dt>
            <dd>{{ $selectedInvoice->due_at?->format('M j, Y') ?? 'N/A' }}</dd>
          </div>
        </dl>

        @if ($selectedInvoice->description)
          <p class="mt-4 rounded-md bg-surface-muted p-3 text-sm text-muted">{{ $selectedInvoice->description }}</p>
        @endif
      </section>

      <section class="rounded-lg border border-border bg-surface p-4">
        <h3 class="font-semibold">Line items</h3>
        <div class="mt-3 overflow-x-auto">
          <table class="ml-table w-full">
            <thead><tr><th>Description</th><th>Qty</th><th>Unit</th><th>Total</th></tr></thead>
            <tbody>
              @forelse ($selectedInvoice->items as $item)
                <tr>
                  <td>{{ $item->description }}</td>
                  <td class="font-mono">{{ $item->quantity }}</td>
                  <td class="font-mono">{{ $this->money($item->getRawOriginal('unit_price_minor'), $selectedInvoice->currency) }}</td>
                  <td class="font-mono">{{ $this->money($item->getRawOriginal('total_minor'), $selectedInvoice->currency) }}</td>
                </tr>
              @empty
                <tr><td colspan="4" class="text-sm text-muted">No line items attached.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>

        <h3 class="mt-5 font-semibold">Payments</h3>
        <div class="mt-3 overflow-x-auto">
          <table class="ml-table w-full">
            <thead><tr><th>Method</th><th>Status</th><th>Amount</th><th>Paid</th></tr></thead>
            <tbody>
              @forelse ($selectedInvoice->payments as $payment)
                <tr>
                  <td>{{ str($payment->method)->replace('_', ' ')->title() }}</td>
                  <td>{{ str($payment->status->value)->replace('_', ' ')->title() }}</td>
                  <td class="font-mono">{{ $this->money($payment->getRawOriginal('amount_minor'), $payment->currency) }}</td>
                  <td class="text-xs text-muted">{{ $payment->paid_at?->format('M j, Y H:i') ?? 'N/A' }}</td>
                </tr>
              @empty
                <tr><td colspan="4" class="text-sm text-muted">No payments recorded.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </section>
    </section>
  @endif
</div>
