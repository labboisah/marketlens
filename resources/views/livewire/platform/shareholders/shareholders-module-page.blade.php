<div class="flex flex-col gap-6">
  <x-app.page-header
    title="Shareholders and Ownership"
    description="Review the current ownership register and immutable share transactions synchronized from the Business Server."
  >
    <x-slot:actions>
      <x-app.status-badge tone="success">Synchronized data</x-app.status-badge>
    </x-slot:actions>
  </x-app.page-header>

  <x-app.stale-data-warning :last-synced-at="$data['summary']['last_sync']?->diffForHumans() ?? 'Awaiting first shareholders sync'" />

  <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
    @foreach ([
      'Shareholders' => number_format($data['summary']['shareholders_count']),
      'Active shareholders' => number_format($data['summary']['active_count']),
      'Share classes' => number_format($data['summary']['share_classes_count']),
      'Units currently held' => number_format($data['summary']['units_held'], 2),
    ] as $label => $value)
      <x-app.card><p class="text-sm font-medium text-muted">{{ $label }}</p><p class="mt-2 font-mono text-2xl font-bold">{{ $value }}</p></x-app.card>
    @endforeach
  </section>

  @php
    $classNames = $data['share_classes']->mapWithKeys(fn ($class) => [($class['uuid'] ?? $class['_record_uuid']) => ($class['name'] ?? 'Unclassified')]);
    $shareholderNames = $data['shareholders']->mapWithKeys(fn ($shareholder) => [($shareholder['uuid'] ?? $shareholder['_record_uuid']) => ($shareholder['name'] ?? 'Unknown shareholder')]);
  @endphp

  <x-app.card title="Ownership register" description="Holdings are derived from synchronized issue and transfer transactions.">
    <x-app.table density="compact">
      <thead><tr><th>Shareholder</th><th>Status</th><th>Holdings by class</th><th>Total units</th><th>Last sync</th></tr></thead>
      <tbody>
        @forelse ($data['shareholders'] as $shareholder)
          <tr>
            <td class="font-semibold">{{ $shareholder['name'] ?? 'Unnamed shareholder' }}</td>
            <td>{{ str($shareholder['status'] ?? 'unknown')->title() }}</td>
            <td>
              @forelse ($shareholder['holdings'] as $classUuid => $units)
                <span class="mr-3 whitespace-nowrap">{{ $classNames[$classUuid] ?? 'Unclassified' }}: <span class="font-mono">{{ number_format($units, 2) }}</span></span>
              @empty
                —
              @endforelse
            </td>
            <td class="font-mono">{{ number_format($shareholder['total_units'], 2) }}</td>
            <td>{{ $shareholder['_occurred_at']?->format('M j, Y H:i') }}</td>
          </tr>
        @empty
          <tr><td colspan="5"><x-app.empty-state title="No synchronized shareholders" message="Shareholders appear after the Business Server synchronizes the ownership register." /></td></tr>
        @endforelse
      </tbody>
    </x-app.table>
  </x-app.card>

  <x-app.card title="Share transaction history" description="Immutable share issues and transfers synchronized from the Business Server.">
    <x-app.table density="compact">
      <thead><tr><th>Type</th><th>Share class</th><th>From</th><th>To</th><th>Units</th><th>Reference</th><th>Created</th></tr></thead>
      <tbody>
        @forelse ($data['transactions'] as $transaction)
          <tr>
            <td>{{ str($transaction['transaction_type'] ?? 'unknown')->replace('_', ' ')->title() }}</td>
            <td>{{ $classNames[$transaction['share_class_uuid'] ?? ''] ?? 'Unclassified' }}</td>
            <td>{{ $shareholderNames[$transaction['from_shareholder_uuid'] ?? ''] ?? '—' }}</td>
            <td>{{ $shareholderNames[$transaction['to_shareholder_uuid'] ?? ''] ?? '—' }}</td>
            <td class="font-mono">{{ number_format((float) ($transaction['units'] ?? 0), 2) }}</td>
            <td>{{ $transaction['reference'] ?? '—' }}</td>
            <td>{{ $transaction['_occurred_at']?->format('M j, Y H:i') }}</td>
          </tr>
        @empty
          <tr><td colspan="7"><x-app.empty-state title="No synchronized share transactions" message="Share issues and transfers appear here after synchronization." /></td></tr>
        @endforelse
      </tbody>
    </x-app.table>
  </x-app.card>
</div>
