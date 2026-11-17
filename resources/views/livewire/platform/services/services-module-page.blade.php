<div class="flex flex-col gap-6">
  <x-app.page-header
    title="Services and Revenue Sharing"
    description="Review service work, payments, and approved revenue shares synchronized from the Business Server."
  >
    <x-slot:actions>
      <x-app.status-badge tone="success">Synchronized data</x-app.status-badge>
    </x-slot:actions>
  </x-app.page-header>

  <x-app.stale-data-warning :last-synced-at="$data['summary']['last_sync']?->diffForHumans() ?? 'Awaiting first services sync'" />

  <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
    @foreach ([
      'Services' => number_format($data['summary']['services_count']),
      'Service orders' => number_format($data['summary']['orders_count']),
      'Completed' => number_format($data['summary']['completed_count']),
      'In progress' => number_format($data['summary']['in_progress_count']),
      'Payments' => $currency.' '.number_format($data['summary']['payments_total'], 2),
      'Business share' => $currency.' '.number_format($data['summary']['business_share'], 2),
      'Staff share' => $currency.' '.number_format($data['summary']['staff_share'], 2),
      'Shareholder share' => $currency.' '.number_format($data['summary']['shareholder_share'], 2),
    ] as $label => $value)
      <x-app.card><p class="text-sm font-medium text-muted">{{ $label }}</p><p class="mt-2 font-mono text-2xl font-bold">{{ $value }}</p></x-app.card>
    @endforeach
  </section>

  <span id="catalogue"></span>
  <x-app.card title="Service catalogue" description="Current service products synchronized from the selected branch.">
    <x-app.table density="compact">
      <thead><tr><th>Service</th><th>SKU</th><th>Retail price</th><th>Status</th><th>Last sync</th></tr></thead>
      <tbody>
        @forelse ($data['services'] as $service)
          <tr>
            <td class="font-semibold">{{ $service['name'] ?? 'Unnamed service' }}</td>
            <td>{{ $service['sku'] ?? '—' }}</td>
            <td class="font-mono">{{ $currency }} {{ number_format((float) ($service['current_price'] ?? 0), 2) }}</td>
            <td>{{ filter_var($service['active'] ?? false, FILTER_VALIDATE_BOOL) ? 'Active' : 'Inactive' }}</td>
            <td>{{ $service['_occurred_at']?->format('M j, Y H:i') }}</td>
          </tr>
        @empty
          <tr><td colspan="5"><x-app.empty-state title="No synchronized services" message="Service catalogue entries appear after the Business Server synchronizes service products." /></td></tr>
        @endforelse
      </tbody>
    </x-app.table>
  </x-app.card>

  <span id="orders"></span>
  <x-app.card title="Recent service orders" description="Read-only operational service activity from the selected branch.">
    <x-app.table density="compact">
      <thead><tr><th>Order</th><th>Work</th><th>Status</th><th>Total</th><th>Paid</th><th>Created</th></tr></thead>
      <tbody>
        @forelse ($data['orders'] as $order)
          <tr>
            <td class="font-semibold">{{ $order['order_number'] ?? $order['_record_uuid'] }}</td>
            <td>{{ str($order['work_description'] ?? '—')->limit(60) }}</td>
            <td>{{ str($order['status'] ?? 'unknown')->replace('_', ' ')->title() }}</td>
            <td class="font-mono">{{ $currency }} {{ number_format((float) $order['total_amount'], 2) }}</td>
            <td class="font-mono">{{ $currency }} {{ number_format((float) $order['paid_amount'], 2) }}</td>
            <td>{{ $order['_occurred_at']?->format('M j, Y H:i') }}</td>
          </tr>
        @empty
          <tr><td colspan="6"><x-app.empty-state title="No synchronized service orders" message="Service orders appear here after synchronization." /></td></tr>
        @endforelse
      </tbody>
    </x-app.table>
  </x-app.card>

  <span id="revenue"></span>
  <x-app.card title="Revenue allocations" description="Approved business, staff, and shareholder allocations from completed service work.">
    <x-app.table density="compact">
      <thead><tr><th>Order UUID</th><th>Recipient</th><th>Status</th><th>Amount</th><th>Created</th></tr></thead>
      <tbody>
        @forelse ($data['allocations'] as $allocation)
          <tr>
            <td class="font-mono text-xs">{{ $allocation['order_uuid'] ?? '—' }}</td>
            <td>{{ str($allocation['component_type'] ?? 'unknown')->replace('_', ' ')->title() }}</td>
            <td>{{ str($allocation['status'] ?? 'unknown')->title() }}</td>
            <td class="font-mono">{{ $currency }} {{ number_format((float) ($allocation['amount'] ?? 0), 2) }}</td>
            <td>{{ $allocation['_occurred_at']?->format('M j, Y H:i') }}</td>
          </tr>
        @empty
          <tr><td colspan="5"><x-app.empty-state title="No synchronized allocations" message="Revenue allocations appear after completed work is approved on the Business Server." /></td></tr>
        @endforelse
      </tbody>
    </x-app.table>
  </x-app.card>
</div>
