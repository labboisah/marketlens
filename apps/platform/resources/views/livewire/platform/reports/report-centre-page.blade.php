<div class="flex flex-col gap-6">
  <x-app.page-header
    title="Reports and exports"
    description="Generate consolidated read-only reports from synchronized Platform summaries, monitor export history, and prepare scheduled reports."
  />

  <section class="grid gap-4 xl:grid-cols-[0.8fr_1.2fr]">
    <x-app.card title="Report catalogue" description="Choose a report group and configure filters. Large exports are handled by the reports queue.">
      <div class="space-y-4">
        @foreach ($groups as $group => $reports)
          <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-muted">{{ ucfirst($group) }}</p>
            <div class="mt-2 grid gap-2">
              @foreach ($reports as $key => $label)
                <label class="flex items-center gap-2 rounded-md border border-border p-2 text-sm">
                  <input type="radio" wire:model.live="report" value="{{ $key }}" class="text-primary focus:ring-primary">
                  <span>{{ $label }}</span>
                </label>
              @endforeach
            </div>
          </div>
        @endforeach
      </div>
    </x-app.card>

    <div class="flex flex-col gap-4">
      <x-app.card title="Filters" description="Filters are applied server-side and scoped to the selected business.">
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
          <label class="text-sm font-medium"><span class="text-muted">From</span><input type="date" wire:model.live="from" class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"></label>
          <label class="text-sm font-medium"><span class="text-muted">To</span><input type="date" wire:model.live="to" class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"></label>
          <label class="text-sm font-medium">
            <span class="text-muted">Branch</span>
            <select wire:model.live="branchId" class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
              <option value="">All branches</option>
              @foreach ($branches as $branch)
                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
              @endforeach
            </select>
          </label>
          <label class="text-sm font-medium"><span class="text-muted">Status</span><input wire:model.live.debounce.400ms="status" class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary" placeholder="completed, active, low"></label>
          <label class="text-sm font-medium"><span class="text-muted">Product</span><input wire:model.live.debounce.400ms="product" class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"></label>
          <label class="text-sm font-medium"><span class="text-muted">Cashier</span><input wire:model.live.debounce.400ms="cashier" class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"></label>
          <label class="text-sm font-medium"><span class="text-muted">Customer</span><input wire:model.live.debounce.400ms="customer" class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"></label>
          <label class="text-sm font-medium"><span class="text-muted">Supplier</span><input wire:model.live.debounce.400ms="supplier" class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"></label>
        </div>
      </x-app.card>

      <x-app.card title="Export actions" description="Exports are queued, recorded in history, and downloaded through expiring signed links.">
        <div class="flex flex-wrap items-end gap-3">
          <label class="text-sm font-medium">
            <span class="text-muted">Format</span>
            <select wire:model="format" class="mt-1 rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
              <option value="csv">CSV</option>
              <option value="pdf">PDF foundation</option>
            </select>
          </label>
          <button type="button" wire:click="queueExport" class="rounded-md bg-primary px-3 py-2 text-sm font-semibold text-white">Queue export</button>
          <label class="text-sm font-medium">
            <span class="text-muted">Schedule</span>
            <select wire:model="scheduleFrequency" class="mt-1 rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
              <option value="daily">Daily</option>
              <option value="weekly">Weekly</option>
              <option value="monthly">Monthly</option>
            </select>
          </label>
          <button type="button" wire:click="createSchedule" class="rounded-md border border-primary/40 px-3 py-2 text-sm font-semibold text-primary">Create schedule</button>
        </div>
      </x-app.card>
    </div>
  </section>

  <x-app.card title="{{ $reportLabel }} preview" description="The preview shows a limited sample. The full export is generated by the queue.">
    <x-app.table density="compact">
      <tbody>
        @forelse ($previewRows as $row)
          <tr>
            @foreach ((array) $row as $key => $value)
              <td><span class="text-xs text-muted">{{ str($key)->replace('_', ' ')->title() }}</span><br><span class="font-mono text-sm">{{ is_scalar($value) || is_null($value) ? ($value ?? 'None') : json_encode($value) }}</span></td>
            @endforeach
          </tr>
        @empty
          <tr><td><x-app.empty-state title="No report rows" message="No synchronized records matched the selected report filters." /></td></tr>
        @endforelse
      </tbody>
    </x-app.table>
  </x-app.card>

  <section class="grid gap-4 xl:grid-cols-[1.3fr_0.7fr]">
    <x-app.card title="Report history" description="Completed exports expose temporary signed download links until they expire.">
      <x-app.table density="compact">
        <thead><tr><th>Report</th><th>Format</th><th>Status</th><th>Rows</th><th>Queued</th><th>Expires</th><th>Download</th></tr></thead>
        <tbody>
          @forelse ($history as $export)
            <tr>
              <td>{{ str($export->report_key)->replace('_', ' ')->title() }}</td>
              <td>{{ strtoupper($export->format) }}</td>
              <td><x-app.status-badge tone="{{ $export->status === 'completed' ? 'success' : ($export->status === 'failed' ? 'danger' : 'warning') }}">{{ ucfirst($export->status) }}</x-app.status-badge></td>
              <td class="font-mono">{{ number_format($export->rows_count) }}</td>
              <td>{{ $export->queued_at?->format('M j, Y H:i') }}</td>
              <td>{{ $export->expires_at?->format('M j, Y H:i') ?? 'Not ready' }}</td>
              <td>
                @if ($export->download_url)
                  <a href="{{ $export->download_url }}" class="text-primary underline">Download</a>
                @else
                  <span class="text-muted">Pending</span>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="7"><x-app.empty-state title="No report history" message="Queued report exports appear here." /></td></tr>
          @endforelse
        </tbody>
      </x-app.table>
    </x-app.card>

    <x-app.card title="Scheduled-report foundation" description="Schedules are stored for future queue workers and delivery channels.">
      <div class="space-y-3">
        @forelse ($scheduledReports as $schedule)
          <div class="rounded-md border border-border p-3">
            <p class="font-semibold">{{ $schedule->name }}</p>
            <p class="text-xs text-muted">{{ strtoupper($schedule->format) }} - {{ ucfirst($schedule->frequency) }} - next {{ $schedule->next_run_at?->format('M j, Y H:i') }}</p>
          </div>
        @empty
          <x-app.empty-state title="No schedules" message="Create a scheduled report foundation from the export actions." />
        @endforelse
      </div>
    </x-app.card>
  </section>
</div>
