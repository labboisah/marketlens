@php
  $views = [
    'overview' => 'Debt overview',
    'balances' => 'Customer balances',
    'overdue' => 'Overdue balances',
    'credit_sales' => 'Credit-sales report',
    'payments' => 'Customer-payment history',
    'ageing' => 'Debt ageing report',
    'highest' => 'Highest debtors',
    'branch' => 'Branch debt comparison',
  ];
  $lastSync = isset($overview['last_synchronized_at']) && $overview['last_synchronized_at'] ? \Illuminate\Support\Carbon::parse($overview['last_synchronized_at']) : null;
@endphp

<div class="flex flex-col gap-6">
  <x-app.page-header
    title="Customer debt monitoring"
    description="Read-only customer debt monitoring from Shop App-generated summaries. The Platform must not overwrite local customer balances."
  />

  <x-app.card title="Filters" description="Filter synchronized debt summaries by branch, customer, phone, or debt status.">
    <div class="grid gap-3 md:grid-cols-3">
      <label class="text-sm font-medium">
        <span class="text-muted">Branch</span>
        <select wire:model.live="branchFilter" class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
          <option value="">Selected branch</option>
          @foreach ($branches as $branch)
            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
          @endforeach
        </select>
      </label>
      <label class="text-sm font-medium"><span class="text-muted">Customer</span><input type="search" wire:model.live.debounce.300ms="customer" placeholder="Name, phone or UUID" class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"></label>
      <label class="text-sm font-medium">
        <span class="text-muted">Debt status</span>
        <select wire:model.live="debtStatus" class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
          <option value="">Any</option>
          @foreach (['current', 'overdue', 'blocked', 'settled'] as $status)
            <option value="{{ $status }}">{{ str($status)->title() }}</option>
          @endforeach
        </select>
      </label>
    </div>
  </x-app.card>

  <nav class="flex flex-wrap gap-2" aria-label="Customer debt monitoring views">
    @foreach ($views as $key => $label)
      <button type="button" wire:click="$set('view', '{{ $key }}')" class="rounded-md border px-3 py-2 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-primary {{ $view === $key ? 'border-primary bg-primary text-white' : 'border-border bg-surface hover:bg-surface-muted' }}">{{ $label }}</button>
    @endforeach
  </nav>

  @if ($accessDenied ?? false)
    <x-app.permission-denied required="Customer debts or reports" title="Customer debt monitoring is not available" message="The current subscription or authorization does not allow access to synchronized customer debt monitoring." />
  @elseif (! $businessId)
    <x-app.empty-state title="No business selected" message="Select a business before viewing synchronized customer debt records." />
  @else
    <x-app.stale-data-warning :last-synced-at="$lastSync ? $lastSync->diffForHumans() : 'Awaiting first debt sync'" />

    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
      @foreach ([
        'Customers with debt' => number_format($overview['customer_count'] ?? 0),
        'Current balance' => $this->money($overview['total_balance_minor'] ?? 0),
        'Overdue amount' => $this->money($overview['overdue_balance_minor'] ?? 0),
        'Credit sales' => $this->money($overview['credit_sales_minor'] ?? 0),
        'Highest balance' => $this->money($overview['highest_balance_minor'] ?? 0),
        'Last synchronized time' => $lastSync ? $lastSync->format('M j, Y H:i') : 'Not synchronized',
      ] as $label => $value)
        <x-app.card><p class="text-sm font-medium text-muted">{{ $label }}</p><p class="mt-2 font-mono text-2xl font-bold">{{ $value }}</p></x-app.card>
      @endforeach
    </section>

    @if ($view === 'payments')
      <x-app.card title="Customer-payment history" description="Replicated debt payments included in Shop App-generated summaries.">
        <x-app.table density="compact">
          <thead><tr><th>Customer</th><th>Phone</th><th>Branch</th><th>Amount</th><th>Method</th><th>Reference</th><th>Paid at</th></tr></thead>
          <tbody>
            @forelse ($payments as $payment)
              <tr><td>{{ $payment->customer_name }}</td><td>{{ $payment->summary?->phone }}</td><td>{{ $payment->branch?->name }}</td><td class="font-mono">{{ $this->money($payment->amount_minor) }}</td><td>{{ str($payment->payment_method ?? 'not specified')->replace('_', ' ')->title() }}</td><td>{{ $payment->reference }}</td><td>{{ $payment->paid_at?->format('M j, Y H:i') }}</td></tr>
            @empty
              <tr><td colspan="7"><x-app.empty-state title="No replicated payments" message="Payment history appears when the Shop App includes debt payment entries in synchronized summaries." /></td></tr>
            @endforelse
          </tbody>
        </x-app.table>
        <div class="mt-4">{{ $payments?->links() }}</div>
      </x-app.card>
    @elseif ($view === 'ageing')
      <x-app.card title="Debt ageing report" description="Shop App-generated ageing categories for unpaid balances.">
        <x-app.table density="compact"><thead><tr><th>Ageing category</th><th>Amount</th></tr></thead><tbody>@foreach ($ageing as $label => $amount)<tr><td>{{ $label }}</td><td class="font-mono">{{ $this->money($amount) }}</td></tr>@endforeach</tbody></x-app.table>
      </x-app.card>
    @elseif ($view === 'branch')
      <x-app.card title="Branch debt comparison" description="Customer debt totals grouped by synchronized branch.">
        <x-app.table density="compact"><thead><tr><th>Branch</th><th>Customers</th><th>Balance</th><th>Overdue</th></tr></thead><tbody>@foreach ($branchComparison as $row)<tr><td>{{ $row->branch_name }}</td><td class="font-mono">{{ number_format($row->customer_count) }}</td><td class="font-mono">{{ $this->money($row->balance_minor) }}</td><td class="font-mono">{{ $this->money($row->overdue_minor) }}</td></tr>@endforeach</tbody></x-app.table>
      </x-app.card>
    @else
      <x-app.card title="{{ $views[$view] }}" description="Synchronized customer balances. Balance corrections must happen in the Shop App ledger.">
        <x-app.table density="compact">
          <thead><tr><th>Customer</th><th>Phone</th><th>Branch</th><th>Credit limit</th><th>Current balance</th><th>Last payment</th><th>Oldest unpaid sale</th><th>Overdue amount</th><th>Debt status</th><th>Last synchronized time</th></tr></thead>
          <tbody>
            @forelse ($balances as $balance)
              @continue($view === 'credit_sales' && $balance->credit_sales_minor <= 0)
              <tr>
                <td>{{ $balance->customer_name }}</td>
                <td>{{ $balance->phone }}</td>
                <td>{{ $balance->branch?->name }}</td>
                <td class="font-mono">{{ $this->money($balance->credit_limit_minor) }}</td>
                <td class="font-mono">{{ $this->money($balance->current_balance_minor) }}</td>
                <td>{{ $balance->last_payment_at?->format('M j, Y H:i') ?? 'No payment' }}</td>
                <td>{{ $balance->oldest_unpaid_sale_number ?? 'None' }}</td>
                <td class="font-mono">{{ $this->money($balance->overdue_amount_minor) }}</td>
                <td><x-app.status-badge tone="{{ $balance->debt_status === 'current' || $balance->debt_status === 'settled' ? 'success' : ($balance->debt_status === 'blocked' ? 'danger' : 'warning') }}">{{ str($balance->debt_status)->title() }}</x-app.status-badge></td>
                <td>{{ $balance->last_synchronized_at?->format('M j, Y H:i') ?? 'Not synchronized' }}</td>
              </tr>
            @empty
              <tr><td colspan="10"><x-app.empty-state title="No synchronized customer debt" message="Customer balances appear after Shop Apps synchronize customer debt summaries." /></td></tr>
            @endforelse
          </tbody>
        </x-app.table>
        <div class="mt-4">{{ $balances?->links() }}</div>
      </x-app.card>
    @endif
  @endif
</div>
