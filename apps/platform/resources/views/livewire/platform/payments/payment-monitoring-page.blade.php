@php
  $views = [
    'overview' => 'Payment overview',
    'transactions' => 'Payment transactions',
    'methods' => 'Payment-method breakdown',
    'unverified_transfers' => 'Unverified transfers',
    'cash' => 'Cash collections',
    'pos' => 'POS-terminal collections',
    'credit' => 'Credit sales',
    'branch' => 'Branch payment comparison',
    'cashier' => 'Cashier payment comparison',
    'exceptions' => 'Exception views',
  ];
@endphp

<div class="flex flex-col gap-6">
  <x-app.page-header
    title="Payment monitoring"
    description="Read-only reconciliation from synchronized Business Server payments and cashier settlements. Corrections must happen in the Business Server."
  >
    <x-slot:actions>
      <button type="button" wire:click="exportCsv" class="rounded-md border border-border px-3 py-2 text-sm font-semibold hover:bg-surface-muted focus:outline-none focus:ring-2 focus:ring-primary">CSV export</button>
      <button type="button" wire:click="exportPdfFoundation" class="rounded-md border border-border px-3 py-2 text-sm font-semibold hover:bg-surface-muted focus:outline-none focus:ring-2 focus:ring-primary">PDF foundation</button>
    </x-slot:actions>
  </x-app.page-header>

  <x-app.card title="Filters" description="Filter synchronized payments by branch, date range, method, status, cashier, or transaction number.">
    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
      <label class="text-sm font-medium"><span class="text-muted">From</span><input type="date" wire:model.live="from" class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"></label>
      <label class="text-sm font-medium"><span class="text-muted">To</span><input type="date" wire:model.live="to" class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"></label>
      <label class="text-sm font-medium">
        <span class="text-muted">Branch</span>
        <select wire:model.live="branchFilter" class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
          <option value="">Selected branch</option>
          @foreach ($branches as $branch)
            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
          @endforeach
        </select>
      </label>
      <label class="text-sm font-medium">
        <span class="text-muted">Payment method</span>
        <select wire:model.live="method" class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
          <option value="">Any</option>
          @foreach (['cash', 'bank_transfer', 'pos_terminal', 'mobile_money', 'credit', 'mixed'] as $methodOption)
            <option value="{{ $methodOption }}">{{ str($methodOption)->replace('_', ' ')->title() }}</option>
          @endforeach
        </select>
      </label>
      <label class="text-sm font-medium">
        <span class="text-muted">Payment status</span>
        <select wire:model.live="status" class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
          <option value="">Any</option>
          @foreach (['pending', 'completed', 'unverified', 'refunded', 'reversed', 'mismatched', 'voided'] as $statusOption)
            <option value="{{ $statusOption }}">{{ str($statusOption)->replace('_', ' ')->title() }}</option>
          @endforeach
        </select>
      </label>
      <label class="text-sm font-medium"><span class="text-muted">Cashier</span><input type="search" wire:model.live.debounce.300ms="cashier" placeholder="Name or UUID" class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"></label>
      <label class="text-sm font-medium"><span class="text-muted">Transaction number</span><input type="search" wire:model.live.debounce.300ms="transactionNumber" placeholder="Sale or transaction no." class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"></label>
    </div>
  </x-app.card>

  <nav class="flex flex-wrap gap-2" aria-label="Payment monitoring views">
    @foreach ($views as $key => $label)
      <button type="button" wire:click="$set('view', '{{ $key }}')" class="rounded-md border px-3 py-2 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-primary {{ $view === $key ? 'border-primary bg-primary text-white' : 'border-border bg-surface hover:bg-surface-muted' }}">{{ $label }}</button>
    @endforeach
  </nav>

  @if ($accessDenied ?? false)
    <x-app.permission-denied required="Sales or Cashier settlement" title="Payment monitoring is not available" message="The current subscription or authorization does not allow access to synchronized payment monitoring." />
  @elseif (! $businessId)
    <x-app.empty-state title="No business selected" message="Select a business before viewing synchronized payment records." />
  @else
    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
      @foreach ([
        'Expected amounts' => $this->money($overview['expected_cash_minor'] ?? 0),
        'Actual submitted cash' => $this->money($overview['actual_cash_minor'] ?? 0),
        'Shortage' => $this->money($overview['shortage_minor'] ?? 0),
        'Surplus' => $this->money($overview['surplus_minor'] ?? 0),
        'Unverified transfer value' => $this->money($overview['unverified_transfer_minor'] ?? 0),
        'Refunds' => $this->money($overview['refunds_minor'] ?? 0),
        'Cash expenses' => $this->money($overview['cash_expenses_minor'] ?? 0),
        'Payment mismatches' => number_format($overview['payment_mismatch_count'] ?? 0),
        'Repeated reversals' => number_format($overview['reversal_count'] ?? 0),
        'Payment total' => $this->money($overview['payment_total_minor'] ?? 0),
      ] as $label => $value)
        <x-app.card>
          <p class="text-sm font-medium text-muted">{{ $label }}</p>
          <p class="mt-2 font-mono text-2xl font-bold">{{ $value }}</p>
        </x-app.card>
      @endforeach
    </section>

    @if (in_array($view, ['overview', 'transactions', 'unverified_transfers', 'cash', 'pos', 'credit'], true))
      <x-app.card title="{{ $views[$view] }}" description="Synchronized payment transactions">
        <x-app.table density="compact">
          <thead><tr><th>Sale</th><th>Branch</th><th>Cashier</th><th>Method</th><th>Status</th><th>Amount</th><th>Reference</th><th>Paid at</th></tr></thead>
          <tbody>
            @forelse ($transactions as $payment)
              <tr>
                <td>{{ $payment->sale?->sale_number ?? $payment->sale_uuid }}</td>
                <td>{{ $payment->branch?->name }}</td>
                <td>{{ $payment->sale?->cashier_name }}</td>
                <td>{{ str($payment->method)->replace('_', ' ')->title() }}</td>
                <td><x-app.status-badge tone="{{ in_array($payment->status, ['completed'], true) ? 'success' : (in_array($payment->status, ['refunded', 'reversed', 'mismatched'], true) ? 'danger' : 'warning') }}">{{ str($payment->status)->replace('_', ' ')->title() }}</x-app.status-badge></td>
                <td class="font-mono">{{ $this->money($payment->amount_minor) }}</td>
                <td>{{ $payment->reference }}</td>
                <td>{{ $payment->paid_at?->format('M j, Y H:i') }}</td>
              </tr>
            @empty
              <tr><td colspan="8"><x-app.empty-state title="No synchronized payments" message="Payments appear here after Business Servers synchronize payment records." /></td></tr>
            @endforelse
          </tbody>
        </x-app.table>
        <div class="mt-4">{{ $transactions?->links() }}</div>
      </x-app.card>
    @elseif ($view === 'methods')
      <x-app.card title="Payment-method breakdown" description="Cash, bank transfer, POS terminal, mobile money, credit, and mixed payments.">
        <x-app.table density="compact">
          <thead><tr><th>Method</th><th>Status</th><th>Count</th><th>Amount</th></tr></thead>
          <tbody>@foreach ($methodBreakdown as $row)<tr><td>{{ str($row->method)->replace('_', ' ')->title() }}</td><td>{{ str($row->status)->replace('_', ' ')->title() }}</td><td class="font-mono">{{ number_format($row->payment_count) }}</td><td class="font-mono">{{ $this->money($row->amount_minor) }}</td></tr>@endforeach</tbody>
        </x-app.table>
      </x-app.card>
    @elseif ($view === 'branch')
      <x-app.card title="Branch payment comparison" description="Payment totals grouped by synchronized branch.">
        <x-app.table density="compact">
          <thead><tr><th>Branch</th><th>Count</th><th>Amount</th></tr></thead>
          <tbody>@foreach ($branchComparison as $row)<tr><td>{{ $row->branch_name }}</td><td class="font-mono">{{ number_format($row->payment_count) }}</td><td class="font-mono">{{ $this->money($row->amount_minor) }}</td></tr>@endforeach</tbody>
        </x-app.table>
      </x-app.card>
    @elseif ($view === 'cashier')
      <x-app.card title="Cashier payment comparison" description="Payment totals grouped by synchronized cashier profile on the sale.">
        <x-app.table density="compact">
          <thead><tr><th>Cashier</th><th>Count</th><th>Amount</th></tr></thead>
          <tbody>@foreach ($cashierComparison as $row)<tr><td>{{ $row->cashier_name }}</td><td class="font-mono">{{ number_format($row->payment_count) }}</td><td class="font-mono">{{ $this->money($row->amount_minor) }}</td></tr>@endforeach</tbody>
        </x-app.table>
      </x-app.card>
    @else
      <section class="grid gap-4 lg:grid-cols-2">
        @foreach ([
          'Cash shortages' => $exceptions['cashShortages'] ?? collect(),
          'Unusually high refunds' => $exceptions['highRefunds'] ?? collect(),
          'Unverified transfers' => $exceptions['unverifiedTransfers'] ?? collect(),
          'Payment mismatches' => $exceptions['paymentMismatches'] ?? collect(),
          'Repeated payment reversals' => $exceptions['repeatedReversals'] ?? collect(),
        ] as $title => $rows)
          <x-app.card :title="$title" description="Read-only exception view from synchronized records.">
            @forelse ($rows as $row)
              <div class="flex justify-between gap-4 border-b border-border py-2 text-sm last:border-0">
                <span>{{ $row->cashier_name ?? $row->sale?->cashier_name ?? $row->reference ?? $row->uuid }}</span>
                <span class="font-mono">{{ $this->money($row->shortage_minor ?? $row->amount_minor ?? $row->unverified_transfer_minor ?? 0) }}</span>
              </div>
            @empty
              <x-app.empty-state title="No exceptions" message="No synchronized records currently match this exception type." />
            @endforelse
          </x-app.card>
        @endforeach
      </section>
    @endif
  @endif
</div>
