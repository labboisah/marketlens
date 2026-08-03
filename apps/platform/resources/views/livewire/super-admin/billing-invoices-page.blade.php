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
    <table class="ml-table w-full">
      <thead>
        <tr>
          <th>Invoice</th>
          <th>Business</th>
          <th>Subscription</th>
          <th>Status</th>
          <th>Amount</th>
          <th>Issued</th>
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
            <td class="font-medium">{{ money($invoice->total_minor, $invoice->currency) }}</td>
            <td class="text-xs text-muted">{{ $invoice->issued_at?->format('M j, Y') ?? 'N/A' }}</td>
          </tr>
        @empty
          <tr>
            <td colspan="6" class="text-sm text-muted">No invoices available.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </section>
</div>
