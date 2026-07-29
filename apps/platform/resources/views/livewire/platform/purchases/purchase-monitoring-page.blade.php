@php
  $views = [
    'overview' => 'Purchase overview',
    'transactions' => 'Purchase transactions',
    'suppliers' => 'Supplier balances',
    'payments' => 'Purchase-payment report',
    'trends' => 'Purchase trends',
    'costs' => 'Product-cost changes',
    'outstanding' => 'Outstanding supplier balances',
    'branch' => 'Branch purchase comparison',
    'details' => 'Purchase details',
  ];
@endphp

<div class="flex flex-col gap-6">
  <x-app.page-header title="Supplier and purchase monitoring" description="Read-only monitoring from synchronized Shop App purchase and supplier-balance records.">
    <x-slot:actions>
      <button type="button" wire:click="exportCsv" class="rounded-md border border-border px-3 py-2 text-sm font-semibold hover:bg-surface-muted focus:outline-none focus:ring-2 focus:ring-primary">CSV export</button>
      <button type="button" wire:click="exportPdfFoundation" class="rounded-md border border-border px-3 py-2 text-sm font-semibold hover:bg-surface-muted focus:outline-none focus:ring-2 focus:ring-primary">PDF foundation</button>
    </x-slot:actions>
  </x-app.page-header>

  <x-app.card title="Filters" description="Filter synchronized purchases by branch, date range, supplier, payment status, or purchase number.">
    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
      <label class="text-sm font-medium"><span class="text-muted">From</span><input type="date" wire:model.live="from" class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"></label>
      <label class="text-sm font-medium"><span class="text-muted">To</span><input type="date" wire:model.live="to" class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"></label>
      <label class="text-sm font-medium"><span class="text-muted">Branch</span><select wire:model.live="branchFilter" class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"><option value="">Selected branch</option>@foreach ($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></label>
      <label class="text-sm font-medium"><span class="text-muted">Supplier</span><input type="search" wire:model.live.debounce.300ms="supplier" placeholder="Name or UUID" class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"></label>
      <label class="text-sm font-medium"><span class="text-muted">Payment status</span><select wire:model.live="paymentStatus" class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"><option value="">Any</option>@foreach (['paid', 'partial', 'unpaid', 'refunded', 'voided'] as $status)<option value="{{ $status }}">{{ str($status)->title() }}</option>@endforeach</select></label>
      <label class="text-sm font-medium"><span class="text-muted">Purchase number</span><input type="search" wire:model.live.debounce.300ms="purchaseNumber" class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"></label>
    </div>
  </x-app.card>

  <nav class="flex flex-wrap gap-2" aria-label="Supplier and purchase monitoring views">
    @foreach ($views as $key => $label)
      <button type="button" wire:click="$set('view', '{{ $key }}')" class="rounded-md border px-3 py-2 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-primary {{ $view === $key ? 'border-primary bg-primary text-white' : 'border-border bg-surface hover:bg-surface-muted' }}">{{ $label }}</button>
    @endforeach
  </nav>

  @if ($accessDenied ?? false)
    <x-app.permission-denied required="Purchases and suppliers" title="Supplier and purchase monitoring is not available" message="The current subscription or authorization does not allow access to synchronized purchase monitoring." />
  @elseif (! $businessId)
    <x-app.empty-state title="No business selected" message="Select a business before viewing synchronized purchases." />
  @else
    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
      @foreach ([
        'Purchase count' => number_format($overview['purchase_count'] ?? 0),
        'Purchase total' => $this->money($overview['total_minor'] ?? 0),
        'Amount paid' => $this->money($overview['paid_minor'] ?? 0),
        'Purchase balance' => $this->money($overview['balance_minor'] ?? 0),
        'Transport costs' => $this->money($overview['transport_minor'] ?? 0),
        'Additional costs' => $this->money($overview['additional_minor'] ?? 0),
        'Supplier balances' => $this->money($overview['supplier_balance_minor'] ?? 0),
      ] as $label => $value)
        <x-app.card><p class="text-sm font-medium text-muted">{{ $label }}</p><p class="mt-2 font-mono text-2xl font-bold">{{ $value }}</p></x-app.card>
      @endforeach
    </section>

    @if ($view === 'details')
      <x-app.card title="Purchase details" description="Purchase products, payments, costs, and synchronization metadata.">
        @if ($selectedPurchase)
          <div class="grid gap-4 lg:grid-cols-3">
            <div><p class="text-sm text-muted">Purchase number</p><p class="font-semibold">{{ $selectedPurchase->purchase_number }}</p><p class="text-sm text-muted">Supplier</p><p>{{ $selectedPurchase->supplier_name }}</p><p class="text-sm text-muted">Branch</p><p>{{ $selectedPurchase->branch?->name }}</p><p class="text-sm text-muted">Date</p><p>{{ $selectedPurchase->purchased_at?->format('M j, Y H:i') }}</p></div>
            <div><p class="text-sm text-muted">Transport and additional costs</p><p class="font-mono">{{ $this->money($selectedPurchase->transport_cost_minor + $selectedPurchase->additional_cost_minor) }}</p><p class="text-sm text-muted">Total</p><p class="font-mono">{{ $this->money($selectedPurchase->total_minor) }}</p><p class="text-sm text-muted">Amount paid</p><p class="font-mono">{{ $this->money($selectedPurchase->amount_paid_minor) }}</p><p class="text-sm text-muted">Balance</p><p class="font-mono">{{ $this->money($selectedPurchase->balance_minor) }}</p></div>
            <div><p class="text-sm text-muted">Payment status</p><p>{{ str($selectedPurchase->payment_status)->title() }}</p><p class="text-sm text-muted">Source device</p><p>{{ $selectedPurchase->device?->name ?? 'Unknown device' }}</p><p class="text-sm text-muted">Synchronization metadata</p><p class="text-xs text-muted">Batch: {{ $selectedPurchase->syncBatch?->uuid ?? 'Unknown' }}</p><p class="text-xs text-muted">Version: {{ $selectedPurchase->source_version }}</p></div>
          </div>
          <h3 class="mt-4 text-sm font-semibold">Products</h3>
          <x-app.table density="compact"><thead><tr><th>Product</th><th>Quantity</th><th>Unit</th><th>Cost</th><th>Total</th></tr></thead><tbody>@foreach ($selectedPurchase->items as $item)<tr><td>{{ $item->product_name }}</td><td class="font-mono">{{ $this->quantity($item->quantity) }}</td><td>{{ $item->unit }}</td><td class="font-mono">{{ $this->money($item->unit_cost_minor) }}</td><td class="font-mono">{{ $this->money($item->line_total_minor) }}</td></tr>@endforeach</tbody></x-app.table>
        @else
          <x-app.empty-state title="Purchase not found" message="Search by purchase number or UUID to view details." />
        @endif
      </x-app.card>
    @elseif ($view === 'suppliers' || $view === 'outstanding')
      <x-app.card title="{{ $views[$view] }}" description="Shop App-generated supplier balance summaries.">
        <x-app.table density="compact"><thead><tr><th>Supplier</th><th>Phone</th><th>Branch</th><th>Balance</th><th>Overdue</th><th>Last purchase</th><th>Last payment</th><th>Last sync</th></tr></thead><tbody>@forelse ($supplierBalances as $balance)@continue($view === 'outstanding' && $balance->current_balance_minor <= 0)<tr><td>{{ $balance->supplier_name }}</td><td>{{ $balance->phone }}</td><td>{{ $balance->branch?->name }}</td><td class="font-mono">{{ $this->money($balance->current_balance_minor) }}</td><td class="font-mono">{{ $this->money($balance->overdue_balance_minor) }}</td><td>{{ $balance->last_purchase_at?->format('M j, Y H:i') }}</td><td>{{ $balance->last_payment_at?->format('M j, Y H:i') }}</td><td>{{ $balance->last_synchronized_at?->format('M j, Y H:i') }}</td></tr>@empty<tr><td colspan="8"><x-app.empty-state title="No supplier balances" message="Supplier balances appear after synchronization." /></td></tr>@endforelse</tbody></x-app.table><div class="mt-4">{{ $supplierBalances?->links() }}</div>
      </x-app.card>
    @elseif ($view === 'payments')
      <x-app.card title="Purchase-payment report" description="Payments synchronized against purchases.">
        <x-app.table density="compact"><thead><tr><th>Purchase</th><th>Supplier</th><th>Branch</th><th>Method</th><th>Status</th><th>Amount</th><th>Reference</th><th>Paid at</th></tr></thead><tbody>@foreach ($payments as $payment)<tr><td>{{ $payment->purchase?->purchase_number ?? $payment->purchase_uuid }}</td><td>{{ $payment->supplier_name }}</td><td>{{ $payment->branch?->name }}</td><td>{{ str($payment->method ?? 'not specified')->replace('_', ' ')->title() }}</td><td>{{ str($payment->status)->title() }}</td><td class="font-mono">{{ $this->money($payment->amount_minor) }}</td><td>{{ $payment->reference }}</td><td>{{ $payment->paid_at?->format('M j, Y H:i') }}</td></tr>@endforeach</tbody></x-app.table><div class="mt-4">{{ $payments?->links() }}</div>
      </x-app.card>
    @elseif ($view === 'trends')
      <x-app.card title="Purchase trends" description="Daily purchase totals."><x-app.table density="compact"><thead><tr><th>Date</th><th>Count</th><th>Total</th></tr></thead><tbody>@foreach ($trends as $row)<tr><td>{{ $row->purchase_day }}</td><td class="font-mono">{{ number_format($row->purchase_count) }}</td><td class="font-mono">{{ $this->money($row->total_minor) }}</td></tr>@endforeach</tbody></x-app.table></x-app.card>
    @elseif ($view === 'costs')
      <x-app.card title="Product-cost changes" description="Lowest, highest, and average synchronized purchase costs by product."><x-app.table density="compact"><thead><tr><th>Product</th><th>SKU</th><th>Lowest</th><th>Highest</th><th>Average</th><th>Purchases</th></tr></thead><tbody>@foreach ($costChanges as $row)<tr><td>{{ $row->product_name }}</td><td>{{ $row->sku }}</td><td class="font-mono">{{ $this->money($row->lowest_cost_minor) }}</td><td class="font-mono">{{ $this->money($row->highest_cost_minor) }}</td><td class="font-mono">{{ $this->money($row->average_cost_minor) }}</td><td class="font-mono">{{ number_format($row->purchase_count) }}</td></tr>@endforeach</tbody></x-app.table></x-app.card>
    @elseif ($view === 'branch')
      <x-app.card title="Branch purchase comparison" description="Purchase totals grouped by branch."><x-app.table density="compact"><thead><tr><th>Branch</th><th>Purchases</th><th>Total</th><th>Balance</th></tr></thead><tbody>@foreach ($branchComparison as $row)<tr><td>{{ $row->branch_name }}</td><td class="font-mono">{{ number_format($row->purchase_count) }}</td><td class="font-mono">{{ $this->money($row->total_minor) }}</td><td class="font-mono">{{ $this->money($row->balance_minor) }}</td></tr>@endforeach</tbody></x-app.table></x-app.card>
    @else
      <x-app.card title="{{ $views[$view] }}" description="Synchronized purchase transactions.">
        <x-app.table density="compact"><thead><tr><th>Purchase number</th><th>Supplier</th><th>Branch</th><th>Date</th><th>Products</th><th>Total</th><th>Amount paid</th><th>Balance</th><th>Payment status</th><th>Synchronization metadata</th></tr></thead><tbody>@forelse ($purchases as $purchase)<tr><td><button type="button" wire:click="showDetails('{{ $purchase->uuid }}')" class="font-semibold text-primary hover:underline">{{ $purchase->purchase_number }}</button></td><td>{{ $purchase->supplier_name }}</td><td>{{ $purchase->branch?->name }}</td><td>{{ $purchase->purchased_at?->format('M j, Y H:i') }}</td><td>{{ $purchase->items->pluck('product_name')->join(', ') }}</td><td class="font-mono">{{ $this->money($purchase->total_minor) }}</td><td class="font-mono">{{ $this->money($purchase->amount_paid_minor) }}</td><td class="font-mono">{{ $this->money($purchase->balance_minor) }}</td><td>{{ str($purchase->payment_status)->title() }}</td><td class="text-xs text-muted">v{{ $purchase->source_version }} {{ $purchase->source_occurred_at?->format('M j, Y H:i') }}</td></tr>@empty<tr><td colspan="10"><x-app.empty-state title="No synchronized purchases" message="Purchases appear after Shop Apps synchronize purchase records." /></td></tr>@endforelse</tbody></x-app.table><div class="mt-4">{{ $purchases?->links() }}</div>
      </x-app.card>
    @endif
  @endif
</div>
