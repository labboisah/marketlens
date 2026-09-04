<div class="flex flex-col gap-6">
  <x-app.page-header
    title="Notifications and alerts"
    description="Review synchronized alerts, device warnings, security events, subscription notices, and notification delivery preferences."
  />

  <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
    @foreach ([
      'Unread' => $counts['unread'] ?? 0,
      'Open alerts' => $counts['open'] ?? 0,
      'Resolved' => $counts['resolved'] ?? 0,
      'Danger severity' => $counts['danger'] ?? 0,
      'Warning severity' => $counts['warning'] ?? 0,
    ] as $label => $value)
      <x-app.card>
        <p class="text-sm font-medium text-muted">{{ $label }}</p>
        <p class="mt-2 font-mono text-2xl font-bold">{{ number_format($value) }}</p>
      </x-app.card>
    @endforeach
  </section>

  <x-app.card title="Notification filters" description="Filter by read state, severity, category, and branch.">
    <div class="grid gap-3 md:grid-cols-4">
      <label class="text-sm font-medium">
        <span class="text-muted">State</span>
        <select wire:model.live="state" class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
          <option value="open">Open</option>
          <option value="unread">Unread</option>
          <option value="read">Read</option>
          <option value="resolved">Resolved</option>
          <option value="all">All</option>
        </select>
      </label>
      <label class="text-sm font-medium">
        <span class="text-muted">Severity</span>
        <select wire:model.live="severity" class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
          <option value="all">All severities</option>
          <option value="information">Information</option>
          <option value="warning">Warning</option>
          <option value="danger">Danger</option>
          <option value="success">Success</option>
        </select>
      </label>
      <label class="text-sm font-medium">
        <span class="text-muted">Category</span>
        <select wire:model.live="category" class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
          <option value="all">All categories</option>
          @foreach ($categories as $option)
            <option value="{{ $option }}">{{ ucfirst(str_replace('_', ' ', $option)) }}</option>
          @endforeach
        </select>
      </label>
      <label class="text-sm font-medium">
        <span class="text-muted">Branch</span>
        <select wire:model.live="branch" class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
          <option value="all">All branches</option>
          @foreach ($branches as $option)
            <option value="{{ $option->id }}">{{ $option->name }}</option>
          @endforeach
        </select>
      </label>
    </div>
  </x-app.card>

  <x-app.card title="Notification centre" description="Alerts are read-only business signals. Resolving an alert records a note but does not alter immutable business transactions.">
    <x-app.table density="compact">
      <thead>
        <tr>
          <th>Alert</th>
          <th>Severity</th>
          <th>Category</th>
          <th>Business / Branch</th>
          <th>Related record</th>
          <th>Created</th>
          <th>Read</th>
          <th>Resolved</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($notifications as $notification)
          <tr>
            <td>
              <p class="font-semibold">{{ $notification->title }}</p>
              <p class="text-xs text-muted">{{ $notification->body }}</p>
              <p class="font-mono text-[11px] text-muted">{{ $notification->uuid }}</p>
            </td>
            <td><x-app.status-badge tone="{{ $notification->severity }}">{{ ucfirst($notification->severity) }}</x-app.status-badge></td>
            <td>{{ ucfirst(str_replace('_', ' ', $notification->category)) }}</td>
            <td>
              <p>{{ $business->name }}</p>
              <p class="text-xs text-muted">{{ $notification->branch?->name ?? 'All branches' }}</p>
            </td>
            <td>
              <p>{{ $notification->related_record_type ?? 'None' }}</p>
              <p class="font-mono text-xs text-muted">{{ $notification->related_record_uuid ?? '' }}</p>
            </td>
            <td>{{ $notification->created_at?->format('M j, Y H:i') }}</td>
            <td>{{ $notification->read_at?->format('M j, Y H:i') ?? 'Unread' }}</td>
            <td>
              <p>{{ $notification->resolved_at?->format('M j, Y H:i') ?? 'Open' }}</p>
              @if ($notification->resolution_note)
                <p class="text-xs text-muted">{{ $notification->resolution_note }}</p>
              @endif
            </td>
            <td>
              <div class="flex flex-wrap gap-2">
                @if ($notification->read_at)
                  <button type="button" wire:click="markUnread({{ $notification->id }})" class="rounded-md border border-border px-2 py-1 text-xs font-medium">Mark unread</button>
                @else
                  <button type="button" wire:click="markRead({{ $notification->id }})" class="rounded-md border border-primary/40 px-2 py-1 text-xs font-medium text-primary">Mark read</button>
                @endif
                @if (! $notification->resolved_at)
                  <button type="button" wire:click="startResolve({{ $notification->id }})" class="rounded-md border border-success/40 px-2 py-1 text-xs font-medium text-success">Resolve</button>
                @endif
              </div>
            </td>
          </tr>
        @empty
          <tr><td colspan="9"><x-app.empty-state title="No alerts" message="Alerts appear here when synchronization, stock, staff, security, device, subscription, or application events need attention." /></td></tr>
        @endforelse
      </tbody>
    </x-app.table>
    <div class="mt-4">{{ $notifications->links() }}</div>
  </x-app.card>

  @if ($resolvingNotificationId)
    <x-app.card title="Resolve alert" description="Add a short note explaining how this alert was reviewed. This does not change the source transaction.">
      <form wire:submit="resolveNotification" class="space-y-3">
        <textarea wire:model="resolutionNote" rows="3" class="w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary" placeholder="Resolution note"></textarea>
        @error('resolutionNote') <p class="text-xs text-danger">{{ $message }}</p> @enderror
        <div class="flex gap-2">
          <button type="submit" class="rounded-md bg-primary px-3 py-2 text-sm font-semibold text-white">Save resolution</button>
          <button type="button" wire:click="$set('resolvingNotificationId', null)" class="rounded-md border border-border px-3 py-2 text-sm font-semibold">Cancel</button>
        </div>
      </form>
    </x-app.card>
  @endif

  <x-app.card title="Notification preferences" description="External channels are queued when enabled. SMS and WhatsApp are placeholders until provider integration is approved.">
    <form wire:submit="savePreferences" class="space-y-4">
      <div class="overflow-x-auto">
        <table class="ml-table">
          <thead>
            <tr>
              <th>Category</th>
              @foreach ($channels as $channel)
                <th>{{ strtoupper(str_replace('_', ' ', $channel)) }}</th>
              @endforeach
            </tr>
          </thead>
          <tbody>
            @foreach ($categories as $categoryOption)
              <tr>
                <td>{{ ucfirst(str_replace('_', ' ', $categoryOption)) }}</td>
                @foreach ($channels as $channel)
                  @php($key = $categoryOption.'|'.$channel)
                  <td>
                    <label class="inline-flex items-center gap-2 text-sm">
                      <input type="checkbox" wire:model="preferences.{{ $key }}" class="rounded border-border text-primary focus:ring-primary">
                      <span class="sr-only">{{ $categoryOption }} {{ $channel }}</span>
                    </label>
                  </td>
                @endforeach
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <button type="submit" class="rounded-md bg-primary px-3 py-2 text-sm font-semibold text-white">Save preferences</button>
    </form>
  </x-app.card>
</div>
