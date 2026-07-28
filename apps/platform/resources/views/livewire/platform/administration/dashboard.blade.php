<div class="flex flex-col gap-6">
  <x-app.page-header
    title="Platform administration"
    description="Cloud operations shell for tenants, subscriptions, devices, synchronization, and owner visibility."
  >
    <x-slot:actions>
      <flux:button variant="primary">Review sync health</flux:button>
    </x-slot:actions>
  </x-app.page-header>

  <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
    <x-app.card title="Businesses" description="Tenant accounts">
      <p class="font-mono text-3xl font-bold tabular-nums">{{ $summary['businesses'] }}</p>
    </x-app.card>

    <x-app.card title="Branches" description="Scoped operating locations">
      <p class="font-mono text-3xl font-bold tabular-nums">{{ $summary['branches'] }}</p>
    </x-app.card>

    <x-app.card title="Devices" description="Active Shop App devices">
      <p class="font-mono text-3xl font-bold tabular-nums">{{ $summary['active_devices'] }}</p>
    </x-app.card>

    <x-app.card title="Sync batches" description="Received today">
      <p class="font-mono text-3xl font-bold tabular-nums">{{ $summary['sync_batches_today'] }}</p>
    </x-app.card>
  </section>

  <section class="grid gap-4 xl:grid-cols-[2fr_1fr]">
    <x-app.card title="Platform modules" description="Scaffolded boundaries ready for implementation">
      <div class="grid gap-2 sm:grid-cols-2">
        @foreach (['Administration', 'Authentication', 'Business Tenancy', 'Business Owners', 'Businesses', 'Branches', 'Plans', 'Subscriptions', 'Subscription Payments', 'Devices', 'Device Activations', 'Synchronization', 'Monitoring API', 'Notifications', 'Audit Logs', 'Support Tickets', 'Application Versions'] as $module)
          <x-app.status-badge tone="information">{{ $module }}</x-app.status-badge>
        @endforeach
      </div>
    </x-app.card>

    <x-app.card title="Synchronization posture" description="Queued processing only">
      <div class="flex flex-col gap-3 text-sm text-muted">
        <x-app.sync-indicator state="synced" last-synced-at="Not yet connected" />
        <p>Full sales, stock, purchase, and ledger synchronization logic has not been implemented.</p>
      </div>
    </x-app.card>
  </section>
</div>
