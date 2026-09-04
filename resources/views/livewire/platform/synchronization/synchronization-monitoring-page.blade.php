<div class="flex flex-col gap-6">
  <x-app.page-header
    title="Synchronization monitoring"
    description="Monitor Business Server devices, batch processing, conflicts, heartbeat history, protocol versions, and application-version distribution."
  >
    <x-slot:actions>
      <a href="{{ route('platform.support', ['category' => 'synchronization']) }}" class="rounded-md border border-border px-3 py-2 text-sm font-semibold text-foreground hover:border-primary/50">Get sync support</a>
    </x-slot:actions>
  </x-app.page-header>

  <div class="flex flex-wrap gap-2">
    @foreach ($tabs as $key => $label)
      <button type="button" wire:click="setView('{{ $key }}')" class="rounded-md border px-3 py-2 text-sm font-semibold {{ $currentView === $key ? 'border-primary bg-primary text-white' : 'border-border bg-surface text-foreground hover:border-primary/60' }}">
        {{ $label }}
      </button>
    @endforeach
  </div>

  @if ($currentView === 'overview')
    @php($metrics = $overview['metrics'] ?? [])
    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
      @foreach ([
        'Active Business Server devices' => $metrics['active_devices'] ?? 0,
        'Online devices' => $metrics['online_devices'] ?? 0,
        'Recently synchronized' => $metrics['recently_synchronized_devices'] ?? 0,
        'Delayed devices' => $metrics['delayed_devices'] ?? 0,
        'Offline devices' => $metrics['offline_devices'] ?? 0,
        'Pending batches' => $metrics['pending_batches'] ?? 0,
        'Processing batches' => $metrics['processing_batches'] ?? 0,
        'Failed batches' => $metrics['failed_batches'] ?? 0,
        'Conflicting records' => $metrics['conflicting_records'] ?? 0,
        'Average sync delay' => is_null($metrics['average_sync_delay_minutes'] ?? null) ? 'No data' : (($metrics['average_sync_delay_minutes'] ?? 0).' min'),
      ] as $label => $value)
        <x-app.card>
          <p class="text-sm font-medium text-muted">{{ $label }}</p>
          <p class="mt-2 font-mono text-2xl font-bold">{{ is_numeric($value) ? number_format($value) : $value }}</p>
        </x-app.card>
      @endforeach
    </section>

    <section class="grid gap-4 xl:grid-cols-[1fr_0.7fr]">
      <x-app.card title="Last successful synchronization by branch" description="Freshness is based on configurable synchronization thresholds, not UI hard-coding.">
        <x-app.table density="compact">
          <thead><tr><th>Branch</th><th>Code</th><th>Last synchronization</th><th>Status</th></tr></thead>
          <tbody>
            @forelse ($overview['last_sync_by_branch'] ?? [] as $branch)
              <tr>
                <td>{{ $branch['name'] }}</td>
                <td class="font-mono">{{ $branch['code'] ?? 'None' }}</td>
                <td>{{ $branch['last_synchronized_at']?->format('M j, Y H:i') ?? 'Never synchronized' }}</td>
                <td><x-app.status-badge tone="{{ $branch['status'] === 'Fresh' ? 'success' : 'warning' }}">{{ $branch['status'] }}</x-app.status-badge></td>
              </tr>
            @empty
              <tr><td colspan="4"><x-app.empty-state title="No branch synchronization yet" message="Branch synchronization timestamps appear after Business Servers push accepted batches." /></td></tr>
            @endforelse
          </tbody>
        </x-app.table>
      </x-app.card>

      <x-app.card title="Configured status rules" description="Adjust these values in configuration or environment settings.">
        <dl class="space-y-3 text-sm">
          <div class="flex justify-between gap-4"><dt class="text-muted">Online</dt><dd class="font-mono">{{ config('synchronization.monitoring.online_minutes') }} min</dd></div>
          <div class="flex justify-between gap-4"><dt class="text-muted">Synced recently</dt><dd class="font-mono">{{ config('synchronization.monitoring.recent_sync_minutes') }} min</dd></div>
          <div class="flex justify-between gap-4"><dt class="text-muted">Delayed</dt><dd class="font-mono">{{ config('synchronization.monitoring.delayed_minutes') }} min</dd></div>
          <div class="flex justify-between gap-4"><dt class="text-muted">Offline after</dt><dd class="font-mono">{{ config('synchronization.monitoring.offline_minutes') }} min</dd></div>
        </dl>
      </x-app.card>
    </section>
  @endif

  @if ($currentView === 'devices')
    <x-app.card title="Device synchronization overview" description="Classifications are derived from configurable timing thresholds and device policy flags.">
      <x-app.table density="compact">
        <thead><tr><th>Device</th><th>Branch</th><th>Classification</th><th>Last contact</th><th>Last sync</th><th>Version</th><th>OS</th><th>Actions</th></tr></thead>
        <tbody>
          @forelse ($devices as $device)
            <tr>
              <td><p class="font-medium">{{ $device->name }}</p><p class="font-mono text-xs text-muted">{{ $device->uuid }}</p></td>
              <td>{{ $device->branch?->name ?? 'Unknown' }}</td>
              <td><x-app.status-badge tone="{{ in_array($device->sync_classification, ['Online', 'Synced recently'], true) ? 'success' : ($device->sync_classification === 'Revoked' ? 'danger' : 'warning') }}">{{ $device->sync_classification }}</x-app.status-badge></td>
              <td>{{ $device->last_seen_at?->format('M j, Y H:i') ?? 'No contact' }}</td>
              <td>{{ $device->last_synchronized_at?->format('M j, Y H:i') ?? 'No sync' }}</td>
              <td>{{ $device->app_version ?: 'Unknown' }}</td>
              <td>{{ data_get($device->operating_system, 'name', 'Unknown') }}</td>
              <td>
                <div class="flex flex-wrap gap-2">
                  @if ($device->status->value !== 'revoked')
                    <button type="button" wire:click="requireApplicationUpdate({{ $device->id }})" class="rounded-md border border-warning/40 px-2 py-1 text-xs font-medium text-warning">Require update</button>
                    <button type="button" wire:click="revokeDevice({{ $device->id }})" class="rounded-md border border-danger/40 px-2 py-1 text-xs font-medium text-danger">Revoke device</button>
                  @endif
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="8"><x-app.empty-state title="No Business Server devices" message="Activate a Business Server device before monitoring synchronization." /></td></tr>
          @endforelse
        </tbody>
      </x-app.table>
    </x-app.card>
  @endif

  @if ($currentView === 'batches')
    <x-app.card title="Sync batch history" description="Batch history is read-only except safe retry requests for eligible failed batches.">
      <x-app.table density="compact">
        <thead><tr><th>Batch</th><th>Status</th><th>Records</th><th>Device</th><th>Branch</th><th>Received</th><th>Processed</th><th>Actions</th></tr></thead>
        <tbody>
          @forelse ($batches as $batch)
            <tr>
              <td><button type="button" wire:click="showBatch('{{ $batch->uuid }}')" class="font-mono text-primary underline">{{ $batch->uuid }}</button><p class="text-xs text-muted">{{ $batch->trace_id }}</p></td>
              <td><x-app.status-badge tone="{{ in_array($batch->status->value, ['accepted', 'partial'], true) ? 'success' : (in_array($batch->status->value, ['failed', 'rejected'], true) ? 'danger' : 'warning') }}">{{ ucfirst($batch->status->value) }}</x-app.status-badge></td>
              <td class="font-mono">{{ number_format($batch->records_count) }}</td>
              <td>{{ $batch->device?->name ?? 'Unknown' }}</td>
              <td>{{ $batch->branch?->name ?? 'Unknown' }}</td>
              <td>{{ $batch->received_at?->format('M j, Y H:i') }}</td>
              <td>{{ $batch->processed_at?->format('M j, Y H:i') ?? 'Queued' }}</td>
              <td>
                @if (in_array($batch->status->value, ['failed', 'rejected', 'partial'], true))
                  <button type="button" wire:click="retryBatch('{{ $batch->uuid }}')" class="rounded-md border border-primary/40 px-2 py-1 text-xs font-medium text-primary">Retry eligible batch</button>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="8"><x-app.empty-state title="No synchronization batches" message="Batch records appear after Business Servers synchronize with the Platform." /></td></tr>
          @endforelse
        </tbody>
      </x-app.table>
      <div class="mt-4">{{ $batches?->links() }}</div>
    </x-app.card>
  @endif

  @if ($currentView === 'batch-detail')
    <x-app.card title="Sync batch details" description="Details are for monitoring and support. Immutable business transactions cannot be rewritten here.">
      @if ($batch)
        <dl class="grid gap-3 text-sm md:grid-cols-2 xl:grid-cols-4">
          <div><dt class="text-muted">Batch UUID</dt><dd class="font-mono">{{ $batch->uuid }}</dd></div>
          <div><dt class="text-muted">Status</dt><dd>{{ ucfirst($batch->status->value) }}</dd></div>
          <div><dt class="text-muted">Protocol</dt><dd>{{ $batch->protocol_version }}</dd></div>
          <div><dt class="text-muted">Records</dt><dd class="font-mono">{{ number_format($batch->records_count) }}</dd></div>
          <div><dt class="text-muted">Accepted</dt><dd class="font-mono">{{ count($batch->accepted_records ?? []) }}</dd></div>
          <div><dt class="text-muted">Rejected</dt><dd class="font-mono">{{ count($batch->rejected_records ?? []) }}</dd></div>
          <div><dt class="text-muted">Conflicts</dt><dd class="font-mono">{{ count($batch->conflicting_records ?? []) }}</dd></div>
          <div><dt class="text-muted">Trace ID</dt><dd class="font-mono">{{ $batch->trace_id ?? 'None' }}</dd></div>
        </dl>
        <p class="mt-4 text-sm text-muted">Administrators may retry eligible processing failures, revoke devices, or mark conflicts reviewed. They cannot edit completed Business Server sales, payments, debt ledgers, or stock movements from this page.</p>
      @else
        <x-app.empty-state title="Batch not found" message="The selected batch does not exist for this business." />
      @endif
    </x-app.card>
  @endif

  @if ($currentView === 'failed-records')
    <x-app.card title="Failed records" description="Download a redacted report for support without raw payload secrets or production tokens.">
      <div class="mb-3 flex flex-wrap gap-2"><button type="button" wire:click="downloadErrorReport" class="rounded-md bg-primary px-3 py-2 text-sm font-semibold text-white">Download redacted error report</button><a href="{{ route('platform.support', ['category' => 'synchronization']) }}" class="rounded-md border border-border px-3 py-2 text-sm font-semibold text-foreground hover:border-primary/50">Open support ticket</a></div>
      <x-app.table density="compact">
        <thead><tr><th>Record</th><th>Entity</th><th>Error</th><th>Retryable</th><th>Batch</th><th>Occurred</th></tr></thead>
        <tbody>
          @forelse ($failedRecords as $record)
            <tr><td class="font-mono">{{ $record->record_uuid }}</td><td>{{ $record->entity }}</td><td><p class="font-medium">{{ $record->error_code }}</p><p class="text-xs text-muted">{{ $record->error_message }}</p></td><td>{{ $record->retryable ? 'Yes' : 'No' }}</td><td class="font-mono">{{ $record->batch?->uuid }}</td><td>{{ $record->occurred_at?->format('M j, Y H:i') }}</td></tr>
          @empty
            <tr><td colspan="6"><x-app.empty-state title="No failed records" message="Rejected or failed synchronized records will appear here for support review." /></td></tr>
          @endforelse
        </tbody>
      </x-app.table>
      <div class="mt-4">{{ $failedRecords?->links() }}</div>
    </x-app.card>
  @endif

  @if ($currentView === 'conflicts')
    <x-app.card title="Conflict records" description="Conflicts can be marked reviewed, but immutable business transactions are not rewritten manually on the Platform.">
      <x-app.table density="compact">
        <thead><tr><th>Record</th><th>Entity</th><th>Issue</th><th>Device</th><th>Reviewed</th><th>Actions</th></tr></thead>
        <tbody>
          @forelse ($conflictRecords as $record)
            <tr>
              <td class="font-mono">{{ $record->record_uuid }}</td>
              <td>{{ $record->entity }}</td>
              <td><p class="font-medium">{{ $record->error_code }}</p><p class="text-xs text-muted">{{ $record->error_message }}</p></td>
              <td>{{ $record->device?->name ?? 'Unknown' }}</td>
              <td>{{ $record->reviewed_at?->format('M j, Y H:i') ?? 'Not reviewed' }}</td>
              <td>@if (! $record->reviewed_at)<button type="button" wire:click="markConflictReviewed({{ $record->id }})" class="rounded-md border border-primary/40 px-2 py-1 text-xs font-medium text-primary">Mark reviewed</button>@endif</td>
            </tr>
          @empty
            <tr><td colspan="6"><x-app.empty-state title="No conflict records" message="Conflict records appear when synchronized data needs manager or support review." /></td></tr>
          @endforelse
        </tbody>
      </x-app.table>
      <div class="mt-4">{{ $conflictRecords?->links() }}</div>
    </x-app.card>
  @endif

  @if ($currentView === 'heartbeats')
    <x-app.card title="Device heartbeat history" description="Recent contact events reported by authorized Business Server devices.">
      <x-app.table density="compact">
        <thead><tr><th>Device</th><th>Branch</th><th>Status</th><th>App version</th><th>Last sync</th><th>Reported</th><th>Errors</th></tr></thead>
        <tbody>
          @forelse ($heartbeatLogs as $log)
            <tr><td>{{ $log->device?->name ?? $log->device_uuid }}</td><td>{{ $log->branch?->name ?? 'Unknown' }}</td><td>{{ ucfirst($log->status) }}</td><td>{{ $log->app_version ?? 'Unknown' }}</td><td>{{ $log->last_synchronized_at?->format('M j, Y H:i') ?? 'No sync' }}</td><td>{{ $log->reported_at->format('M j, Y H:i') }}</td><td class="font-mono">{{ count($log->synchronization_errors ?? []) }}</td></tr>
          @empty
            <tr><td colspan="7"><x-app.empty-state title="No heartbeat history" message="Heartbeat logs appear when activated Business Servers contact the Platform." /></td></tr>
          @endforelse
        </tbody>
      </x-app.table>
      <div class="mt-4">{{ $heartbeatLogs?->links() }}</div>
    </x-app.card>
  @endif

  @if ($currentView === 'protocols')
    <x-app.card title="Protocol-version usage" description="Track synchronization protocol adoption across Business Server batches.">
      <x-app.table density="compact">
        <thead><tr><th>Protocol version</th><th>Batches</th></tr></thead>
        <tbody>
          @forelse ($protocolUsage as $row)
            <tr><td class="font-mono">{{ $row->protocol_version }}</td><td class="font-mono">{{ number_format($row->batches_count) }}</td></tr>
          @empty
            <tr><td colspan="2"><x-app.empty-state title="No protocol usage" message="Protocol usage appears after synchronization batches are received." /></td></tr>
          @endforelse
        </tbody>
      </x-app.table>
    </x-app.card>
  @endif

  @if ($currentView === 'versions')
    <x-app.card title="Application-version distribution" description="Understand which Business Server versions are still active across the business.">
      <x-app.table density="compact">
        <thead><tr><th>Business Server version</th><th>Devices</th></tr></thead>
        <tbody>
          @forelse ($versionDistribution as $row)
            <tr><td class="font-mono">{{ $row->app_version }}</td><td class="font-mono">{{ number_format($row->devices_count) }}</td></tr>
          @empty
            <tr><td colspan="2"><x-app.empty-state title="No version data" message="Version distribution appears after devices activate or send heartbeat events." /></td></tr>
          @endforelse
        </tbody>
      </x-app.table>
    </x-app.card>
  @endif
</div>

