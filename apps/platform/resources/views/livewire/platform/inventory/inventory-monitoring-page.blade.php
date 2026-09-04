@php
  $views = [
    'overview' => 'Inventory overview',
    'current' => 'Current stock by branch',
    'details' => 'Product stock details',
    'low' => 'Low-stock products',
    'out' => 'Out-of-stock products',
    'overstocked' => 'Overstocked products',
    'expiring' => 'Expiring products',
    'damaged' => 'Damaged-stock summaries',
    'adjustments' => 'Stock adjustments',
    'transfers' => 'Stock transfers',
    'value' => 'Stock-value summary',
    'fast' => 'Fast-moving products',
    'slow' => 'Slow-moving products',
  ];
  $lastSync = isset($overview['last_synchronized_at']) && $overview['last_synchronized_at'] ? \Illuminate\Support\Carbon::parse($overview['last_synchronized_at']) : null;
  $isStale = ! $lastSync || $lastSync->lt(now()->subHours(2));
@endphp

<div class="flex flex-col gap-6">
  <x-app.page-header title="Inventory monitoring" description="Cloud inventory is for monitoring only. The Business Server remains authoritative for local stock operations." />

  <x-app.card title="Filters" description="Filter synchronized stock by branch, warehouse, product, category, brand, stock status, and date range.">
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
      <label class="text-sm font-medium"><span class="text-muted">Warehouse</span><input type="search" wire:model.live.debounce.300ms="warehouse" class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"></label>
      <label class="text-sm font-medium"><span class="text-muted">Product</span><input type="search" wire:model.live.debounce.300ms="product" placeholder="Name, SKU or barcode" class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"></label>
      <label class="text-sm font-medium"><span class="text-muted">Category</span><input type="search" wire:model.live.debounce.300ms="category" class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"></label>
      <label class="text-sm font-medium"><span class="text-muted">Brand</span><input type="search" wire:model.live.debounce.300ms="brand" class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"></label>
      <label class="text-sm font-medium">
        <span class="text-muted">Stock status</span>
        <select wire:model.live="stockStatus" class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
          <option value="">Any</option>
          @foreach (['normal', 'low', 'out_of_stock', 'overstocked', 'expiring', 'damaged'] as $status)
            <option value="{{ $status }}">{{ str($status)->replace('_', ' ')->title() }}</option>
          @endforeach
        </select>
      </label>
    </div>
  </x-app.card>

  <nav class="flex flex-wrap gap-2" aria-label="Inventory monitoring views">
    @foreach ($views as $key => $label)
      <button type="button" wire:click="$set('view', '{{ $key }}')" class="rounded-md border px-3 py-2 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-primary {{ $view === $key ? 'border-primary bg-primary text-white' : 'border-border bg-surface hover:bg-surface-muted' }}">{{ $label }}</button>
    @endforeach
  </nav>

  @if ($accessDenied ?? false)
    <x-app.permission-denied required="Inventory or stock reports" title="Inventory monitoring is not available" message="The current subscription or authorization does not allow access to synchronized inventory monitoring." />
  @elseif (! $businessId)
    <x-app.empty-state title="No business selected" message="Select a business before viewing synchronized inventory." />
  @else
    <x-app.stale-data-warning :last-synced-at="$lastSync ? $lastSync->diffForHumans() : 'Awaiting first inventory sync'" />
    @if ($isStale)
      <x-app.status-badge tone="warning">Delayed inventory data</x-app.status-badge>
    @endif

    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
      @foreach ([
        'Products monitored' => number_format($overview['products_count'] ?? 0),
        'Stock value' => $this->money($overview['stock_value_minor'] ?? 0),
        'Low stock' => number_format($overview['low_stock_count'] ?? 0),
        'Out of stock' => number_format($overview['out_of_stock_count'] ?? 0),
        'Overstocked' => number_format($overview['overstocked_count'] ?? 0),
        'Expiring products' => number_format($overview['expiring_count'] ?? 0),
        'Damaged stock alerts' => number_format($overview['damaged_alert_count'] ?? 0),
        'Last synchronization' => $lastSync ? $lastSync->format('M j, Y H:i') : 'Not synchronized',
      ] as $label => $value)
        <x-app.card><p class="text-sm font-medium text-muted">{{ $label }}</p><p class="mt-2 font-mono text-2xl font-bold">{{ $value }}</p></x-app.card>
      @endforeach
    </section>

    @if ($view === 'details')
      <x-app.card title="Product stock details" description="Read-only synchronized product stock details with branch comparison and recent movement summary.">
        @if ($selectedStock)
          <div class="grid gap-4 lg:grid-cols-3">
            <div><p class="text-sm text-muted">Product</p><p class="font-semibold">{{ $selectedStock->product_name }}</p><p class="text-sm text-muted">SKU</p><p>{{ $selectedStock->sku }}</p><p class="text-sm text-muted">Barcode</p><p>{{ $selectedStock->barcode }}</p><p class="text-sm text-muted">Base unit</p><p>{{ $selectedStock->base_unit }}</p></div>
            <div><p class="text-sm text-muted">Available quantity</p><p class="font-mono">{{ $this->quantity($selectedStock->available_quantity) }}</p><p class="text-sm text-muted">Reserved quantity</p><p class="font-mono">{{ $this->quantity($selectedStock->reserved_quantity) }}</p><p class="text-sm text-muted">Stock value</p><p class="font-mono">{{ $this->money($selectedStock->stock_value_minor) }}</p><p class="text-sm text-muted">Reorder level</p><p class="font-mono">{{ $this->quantity($selectedStock->reorder_level) }}</p></div>
            <div><p class="text-sm text-muted">Last movement</p><p>{{ $selectedStock->last_movement_at?->format('M j, Y H:i') ?? 'None' }}</p><p class="text-sm text-muted">Last synchronization</p><p>{{ $selectedStock->last_synchronized_at?->format('M j, Y H:i') ?? 'Not synchronized' }}</p><p class="text-sm text-muted">Branch</p><p>{{ $selectedStock->branch?->name }}</p><p class="text-sm text-muted">Warehouse</p><p>{{ $selectedStock->warehouse_name }}</p></div>
          </div>
          <h3 class="mt-4 text-sm font-semibold">Recent movement summary</h3>
          <x-app.table density="compact"><thead><tr><th>Type</th><th>Quantity</th><th>Value</th><th>Reference</th><th>Occurred</th></tr></thead><tbody>@foreach ($selectedStock->movements as $movement)<tr><td>{{ str($movement->movement_type)->replace('_', ' ')->title() }}</td><td class="font-mono">{{ $this->quantity($movement->quantity_delta) }}</td><td class="font-mono">{{ $this->money($movement->value_minor) }}</td><td>{{ $movement->reference }}</td><td>{{ $movement->occurred_at?->format('M j, Y H:i') }}</td></tr>@endforeach</tbody></x-app.table>
        @else
          <x-app.empty-state title="Product not found" message="Search by product UUID, SKU, or barcode, then open product stock details." />
        @endif
      </x-app.card>
    @elseif (in_array($view, ['adjustments', 'transfers'], true))
      <x-app.card title="{{ $views[$view] }}" description="Recent synchronized movement records.">
        <x-app.table density="compact"><thead><tr><th>Product</th><th>Type</th><th>Warehouse</th><th>Quantity</th><th>Reference</th><th>Reason</th><th>Occurred</th></tr></thead><tbody>
          @foreach ($movements as $movement)
            @continue($view === 'adjustments' && $movement->movement_type !== 'adjustment')
            @continue($view === 'transfers' && ! in_array($movement->movement_type, ['transfer_in', 'transfer_out'], true))
            <tr><td>{{ $movement->product_name }}</td><td>{{ str($movement->movement_type)->replace('_', ' ')->title() }}</td><td>{{ $movement->warehouse_name }}</td><td class="font-mono">{{ $this->quantity($movement->quantity_delta) }}</td><td>{{ $movement->reference }}</td><td>{{ $movement->reason }}</td><td>{{ $movement->occurred_at?->format('M j, Y H:i') }}</td></tr>
          @endforeach
        </tbody></x-app.table><div class="mt-4">{{ $movements?->links() }}</div>
      </x-app.card>
    @elseif ($view === 'damaged')
      <x-app.card title="Damaged-stock summaries" description="Synchronized damaged stock alerts and movement summaries.">
        @foreach ($alerts->where('alert_type', 'damaged') as $alert)
          <div class="flex justify-between gap-4 border-b border-border py-2 text-sm last:border-0"><span>{{ $alert->product_name }} - {{ $alert->message }}</span><x-app.status-badge tone="danger">{{ str($alert->severity)->title() }}</x-app.status-badge></div>
        @endforeach
      </x-app.card>
    @elseif ($view === 'fast' || $view === 'slow')
      <x-app.card title="{{ $views[$view] }}" description="Movement speed from synchronized stock movement records.">
        <x-app.table density="compact"><thead><tr><th>Product</th><th>SKU</th><th>Moved quantity</th><th>Movements</th></tr></thead><tbody>@foreach (($view === 'fast' ? $fastMoving : $slowMoving) as $row)<tr><td>{{ $row->product_name }}</td><td>{{ $row->sku }}</td><td class="font-mono">{{ $this->quantity($row->moved_quantity) }}</td><td class="font-mono">{{ number_format($row->movement_count) }}</td></tr>@endforeach</tbody></x-app.table>
      </x-app.card>
    @elseif ($view === 'value')
      <x-app.card title="Stock-value summary" description="Branch comparison of synchronized stock value.">
        <x-app.table density="compact"><thead><tr><th>Branch</th><th>Products</th><th>Quantity</th><th>Stock value</th></tr></thead><tbody>@foreach ($branchComparison as $row)<tr><td>{{ $row->branch_name }}</td><td class="font-mono">{{ number_format($row->products_count) }}</td><td class="font-mono">{{ $this->quantity($row->available_quantity) }}</td><td class="font-mono">{{ $this->money($row->stock_value_minor) }}</td></tr>@endforeach</tbody></x-app.table>
      </x-app.card>
    @else
      <x-app.card title="{{ $views[$view] }}" description="Current synchronized stock by branch.">
        <x-app.table density="compact"><thead><tr><th>Product</th><th>SKU</th><th>Barcode</th><th>Branch</th><th>Warehouse</th><th>Available</th><th>Reserved</th><th>Status</th><th>Value</th><th>Last sync</th></tr></thead><tbody>
          @forelse ($stocks as $stock)
            <tr><td><button type="button" wire:click="showDetails('{{ $stock->product_uuid }}')" class="font-semibold text-primary hover:underline">{{ $stock->product_name }}</button></td><td>{{ $stock->sku }}</td><td>{{ $stock->barcode }}</td><td>{{ $stock->branch?->name }}</td><td>{{ $stock->warehouse_name }}</td><td class="font-mono">{{ $this->quantity($stock->available_quantity) }}</td><td class="font-mono">{{ $this->quantity($stock->reserved_quantity) }}</td><td>{{ str($stock->stock_status)->replace('_', ' ')->title() }}</td><td class="font-mono">{{ $this->money($stock->stock_value_minor) }}</td><td>{{ $stock->last_synchronized_at?->format('M j, Y H:i') }}</td></tr>
          @empty
            <tr><td colspan="10"><x-app.empty-state title="No synchronized inventory" message="Inventory appears after Business Servers synchronize stock summaries." /></td></tr>
          @endforelse
        </tbody></x-app.table><div class="mt-4">{{ $stocks?->links() }}</div>
      </x-app.card>
    @endif
  @endif
</div>
