@php
  $views = [
    'overview' => 'Sales overview',
    'transactions' => 'Sales transactions',
    'product' => 'Product-sales report',
    'category' => 'Category-sales report',
    'cashier' => 'Cashier-sales report',
    'branch' => 'Branch-sales report',
    'cancelled' => 'Cancelled-sales report',
    'returned' => 'Returned-sales report',
  ];
@endphp

<div class="flex flex-col gap-6">
  <x-app.page-header
    title="Sales monitoring"
    description="Read-only monitoring from synchronized Shop App sales records. Completed sales cannot be edited on the Platform."
  >
    <x-slot:actions>
      <button type="button" wire:click="exportCsv" class="rounded-md border border-border px-3 py-2 text-sm font-semibold hover:bg-surface-muted focus:outline-none focus:ring-2 focus:ring-primary">
        CSV export
      </button>
      <button type="button" wire:click="exportPdfFoundation" class="rounded-md border border-border px-3 py-2 text-sm font-semibold hover:bg-surface-muted focus:outline-none focus:ring-2 focus:ring-primary">
        PDF foundation
      </button>
    </x-slot:actions>
  </x-app.page-header>

  <x-app.card title="Filters" description="Filter synchronized sales by branch, date, cashier, customer, product, category, payment, status, type, or transaction number.">
    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
      <label class="text-sm font-medium">
        <span class="text-muted">From</span>
        <input type="date" wire:model.live="from" class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
      </label>
      <label class="text-sm font-medium">
        <span class="text-muted">To</span>
        <input type="date" wire:model.live="to" class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
      </label>
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
        <span class="text-muted">Transaction number</span>
        <input type="search" wire:model.live.debounce.300ms="transactionNumber" placeholder="Sale or transaction no." class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
      </label>
      <label class="text-sm font-medium">
        <span class="text-muted">Cashier</span>
        <input type="search" wire:model.live.debounce.300ms="cashier" placeholder="Name or UUID" class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
      </label>
      <label class="text-sm font-medium">
        <span class="text-muted">Customer</span>
        <input type="search" wire:model.live.debounce.300ms="customer" placeholder="Name or UUID" class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
      </label>
      <label class="text-sm font-medium">
        <span class="text-muted">Product</span>
        <input type="search" wire:model.live.debounce.300ms="product" placeholder="Product name or UUID" class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
      </label>
      <label class="text-sm font-medium">
        <span class="text-muted">Category</span>
        <input type="search" wire:model.live.debounce.300ms="category" placeholder="Category name or UUID" class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
      </label>
      <label class="text-sm font-medium">
        <span class="text-muted">Payment status</span>
        <select wire:model.live="paymentStatus" class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
          <option value="">Any</option>
          @foreach (['paid', 'partial', 'unpaid', 'refunded', 'voided'] as $status)
            <option value="{{ $status }}">{{ str($status)->replace('_', ' ')->title() }}</option>
          @endforeach
        </select>
      </label>
      <label class="text-sm font-medium">
        <span class="text-muted">Sale status</span>
        <select wire:model.live="saleStatus" class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
          <option value="">Any</option>
          @foreach (['completed', 'cancelled', 'returned', 'partially_returned', 'voided'] as $status)
            <option value="{{ $status }}">{{ str($status)->replace('_', ' ')->title() }}</option>
          @endforeach
        </select>
      </label>
      <label class="text-sm font-medium">
        <span class="text-muted">Sale type</span>
        <input type="search" wire:model.live.debounce.300ms="saleType" placeholder="retail, wholesale, credit" class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
      </label>
    </div>
  </x-app.card>

  <nav class="flex flex-wrap gap-2" aria-label="Sales monitoring views">
    @foreach ($views as $key => $label)
      <button type="button" wire:click="$set('view', '{{ $key }}')" class="rounded-md border px-3 py-2 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-primary {{ $view === $key ? 'border-primary bg-primary text-white' : 'border-border bg-surface hover:bg-surface-muted' }}">
        {{ $label }}
      </button>
    @endforeach
  </nav>

  @if ($accessDenied ?? false)
    <x-app.permission-denied
      required="Sales"
      title="Sales monitoring is not available"
      message="The current subscription or authorization does not allow access to synchronized sales monitoring."
    />
  @elseif (! $businessId)
    <x-app.empty-state title="No business selected" message="Select a business before viewing synchronized sales." />
  @else
    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
      @foreach ([
        'Sales count' => number_format($overview['sales_count'] ?? 0),
        'Total sales' => $this->money($overview['total_minor'] ?? 0),
        'Gross profit' => $this->money($overview['gross_profit_minor'] ?? 0),
        'Cancelled sales' => number_format($overview['cancelled_count'] ?? 0),
        'Returned sales' => number_format($overview['returned_count'] ?? 0),
      ] as $label => $value)
        <x-app.card>
          <p class="text-sm font-medium text-muted">{{ $label }}</p>
          <p class="mt-2 font-mono text-2xl font-bold">{{ $value }}</p>
        </x-app.card>
      @endforeach
    </section>

    @if ($view === 'details')
      <x-app.card title="Sale details" description="Synchronized sale metadata, items and payments. This page is read-only.">
        @if ($selectedSale)
          <div class="grid gap-4 lg:grid-cols-3">
            <div class="space-y-2">
              <p class="text-sm text-muted">Sale number</p>
              <p class="font-semibold">{{ $selectedSale->sale_number }}</p>
              <p class="text-sm text-muted">Branch</p>
              <p>{{ $selectedSale->branch?->name ?? 'Unknown branch' }}</p>
              <p class="text-sm text-muted">Cashier</p>
              <p>{{ $selectedSale->cashier_name ?? 'Unknown cashier' }}</p>
              <p class="text-sm text-muted">Customer</p>
              <p>{{ $selectedSale->customer_name ?? 'Walk-in customer' }}</p>
            </div>
            <div class="space-y-2">
              <p class="text-sm text-muted">Date and time</p>
              <p>{{ $selectedSale->sold_at?->format('M j, Y H:i') }}</p>
              <p class="text-sm text-muted">Status</p>
              <x-app.status-badge tone="{{ in_array($selectedSale->status, ['cancelled', 'voided'], true) ? 'danger' : ($selectedSale->status === 'completed' ? 'success' : 'warning') }}">{{ str($selectedSale->status)->replace('_', ' ')->title() }}</x-app.status-badge>
              <p class="text-sm text-muted">Payment status</p>
              <p>{{ str($selectedSale->payment_status)->replace('_', ' ')->title() }}</p>
              <p class="text-sm text-muted">Sale type</p>
              <p>{{ str($selectedSale->sale_type)->replace('_', ' ')->title() }}</p>
            </div>
            <div class="space-y-2">
              <p class="text-sm text-muted">Total</p>
              <p class="font-mono font-semibold">{{ $this->money($selectedSale->total_minor) }}</p>
              <p class="text-sm text-muted">Cost</p>
              <p class="font-mono">{{ $this->money($selectedSale->cost_minor) }}</p>
              <p class="text-sm text-muted">Gross profit</p>
              <p class="font-mono">{{ $this->money($selectedSale->gross_profit_minor) }}</p>
              <p class="text-sm text-muted">Source device</p>
              <p>{{ $selectedSale->device?->name ?? 'Unknown device' }}</p>
            </div>
          </div>

          <div class="mt-4 grid gap-4 lg:grid-cols-2">
            <div>
              <h3 class="mb-2 text-sm font-semibold">Items</h3>
              <x-app.table density="compact">
                <thead><tr><th>Product</th><th>Qty</th><th>Unit</th><th>Price</th><th>Total</th><th>Profit</th></tr></thead>
                <tbody>
                  @foreach ($selectedSale->items as $item)
                    <tr>
                      <td>{{ $item->product_name }}</td>
                      <td class="font-mono">{{ $this->quantity($item->quantity) }}</td>
                      <td>{{ $item->unit }}</td>
                      <td class="font-mono">{{ $this->money($item->unit_price_minor) }}</td>
                      <td class="font-mono">{{ $this->money($item->line_total_minor) }}</td>
                      <td class="font-mono">{{ $this->money($item->gross_profit_minor) }}</td>
                    </tr>
                  @endforeach
                </tbody>
              </x-app.table>
            </div>
            <div>
              <h3 class="mb-2 text-sm font-semibold">Payments</h3>
              <x-app.table density="compact">
                <thead><tr><th>Method</th><th>Status</th><th>Amount</th><th>Reference</th></tr></thead>
                <tbody>
                  @foreach ($selectedSale->payments as $payment)
                    <tr>
                      <td>{{ str($payment->method)->replace('_', ' ')->title() }}</td>
                      <td>{{ str($payment->status)->replace('_', ' ')->title() }}</td>
                      <td class="font-mono">{{ $this->money($payment->amount_minor) }}</td>
                      <td>{{ $payment->reference }}</td>
                    </tr>
                  @endforeach
                </tbody>
              </x-app.table>
            </div>
          </div>

          <div class="mt-4 grid gap-4 lg:grid-cols-3">
            <div class="rounded-md border border-border p-3">
              <p class="text-sm font-semibold">Cancellation information</p>
              <pre class="mt-2 whitespace-pre-wrap text-xs text-muted">{{ json_encode($selectedSale->cancellation, JSON_PRETTY_PRINT) ?: 'None' }}</pre>
            </div>
            <div class="rounded-md border border-border p-3">
              <p class="text-sm font-semibold">Return information</p>
              <pre class="mt-2 whitespace-pre-wrap text-xs text-muted">{{ json_encode($selectedSale->return_summary, JSON_PRETTY_PRINT) ?: 'None' }}</pre>
            </div>
            <div class="rounded-md border border-border p-3">
              <p class="text-sm font-semibold">Synchronization metadata</p>
              <p class="mt-2 text-xs text-muted">Batch: {{ $selectedSale->syncBatch?->uuid ?? 'Unknown' }}</p>
              <p class="text-xs text-muted">Version: {{ $selectedSale->source_version }}</p>
              <p class="text-xs text-muted">Occurred: {{ $selectedSale->source_occurred_at?->format('M j, Y H:i') }}</p>
            </div>
          </div>
        @else
          <x-app.empty-state title="Sale not found" message="Search by synchronized sale UUID, sale number, or transaction number to view details." />
        @endif
      </x-app.card>
    @elseif (in_array($view, ['overview', 'transactions', 'cancelled', 'returned'], true))
      <x-app.card title="{{ $views[$view] }}" description="Read-only synchronized sale transactions">
        <x-app.table density="compact">
          <thead>
            <tr>
              <th>Sale number</th>
              <th>Branch</th>
              <th>Cashier</th>
              <th>Customer</th>
              <th>Date</th>
              <th>Status</th>
              <th>Payment</th>
              <th>Total</th>
              <th>Gross profit</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($sales as $sale)
              <tr>
                <td><button type="button" wire:click="showDetails('{{ $sale->uuid }}')" class="font-semibold text-primary hover:underline">{{ $sale->sale_number }}</button></td>
                <td>{{ $sale->branch?->name }}</td>
                <td>{{ $sale->cashier_name }}</td>
                <td>{{ $sale->customer_name ?? 'Walk-in' }}</td>
                <td>{{ $sale->sold_at?->format('M j, Y H:i') }}</td>
                <td>{{ str($sale->status)->replace('_', ' ')->title() }}</td>
                <td>{{ str($sale->payment_status)->replace('_', ' ')->title() }}</td>
                <td class="font-mono">{{ $this->money($sale->total_minor) }}</td>
                <td class="font-mono">{{ $this->money($sale->gross_profit_minor) }}</td>
              </tr>
            @empty
              <tr><td colspan="9"><x-app.empty-state title="No synchronized sales" message="Sales appear here after Shop Apps synchronize sale records." /></td></tr>
            @endforelse
          </tbody>
        </x-app.table>
        <div class="mt-4">{{ $sales?->links() }}</div>
      </x-app.card>
    @else
      <x-app.card title="{{ $views[$view] }}" description="Aggregated from synchronized replicated sales records">
        <x-app.table density="compact">
          @if ($view === 'product')
            <thead><tr><th>Product</th><th>SKU</th><th>Quantity</th><th>Sales</th><th>Gross profit</th></tr></thead>
            <tbody>
              @foreach ($productReport as $row)
                <tr><td>{{ $row->product_name }}</td><td>{{ $row->sku }}</td><td class="font-mono">{{ $this->quantity($row->quantity_sold) }}</td><td class="font-mono">{{ $this->money($row->sales_minor) }}</td><td class="font-mono">{{ $this->money($row->gross_profit_minor) }}</td></tr>
              @endforeach
            </tbody>
          @elseif ($view === 'category')
            <thead><tr><th>Category</th><th>Quantity</th><th>Sales</th><th>Gross profit</th></tr></thead>
            <tbody>
              @foreach ($categoryReport as $row)
                <tr><td>{{ $row->category_name }}</td><td class="font-mono">{{ $this->quantity($row->quantity_sold) }}</td><td class="font-mono">{{ $this->money($row->sales_minor) }}</td><td class="font-mono">{{ $this->money($row->gross_profit_minor) }}</td></tr>
              @endforeach
            </tbody>
          @elseif ($view === 'cashier')
            <thead><tr><th>Cashier</th><th>Sales count</th><th>Sales</th><th>Gross profit</th></tr></thead>
            <tbody>
              @foreach ($cashierReport as $row)
                <tr><td>{{ $row->cashier_name }}</td><td class="font-mono">{{ number_format($row->sales_count) }}</td><td class="font-mono">{{ $this->money($row->sales_minor) }}</td><td class="font-mono">{{ $this->money($row->gross_profit_minor) }}</td></tr>
              @endforeach
            </tbody>
          @elseif ($view === 'branch')
            <thead><tr><th>Branch</th><th>Sales count</th><th>Sales</th><th>Gross profit</th></tr></thead>
            <tbody>
              @foreach ($branchReport as $row)
                <tr><td>{{ $row->branch_name }}</td><td class="font-mono">{{ number_format($row->sales_count) }}</td><td class="font-mono">{{ $this->money($row->sales_minor) }}</td><td class="font-mono">{{ $this->money($row->gross_profit_minor) }}</td></tr>
              @endforeach
            </tbody>
          @endif
        </x-app.table>
      </x-app.card>
    @endif
  @endif
</div>
