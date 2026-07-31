<div class="flex flex-col gap-6">
  <section class="rounded-lg border border-border bg-surface p-5">
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <p class="text-sm font-semibold uppercase text-muted">Separate operator area</p>
        <h1 class="mt-2 text-2xl font-bold">Platform Super-Administrator Dashboard</h1>
        <p class="mt-2 max-w-3xl text-sm text-muted">This area uses its own authorization, layout, and navigation. Business-owner Platform navigation is not mixed into super-administration.</p>
      </div>
      <div class="flex flex-wrap gap-2">
        <span class="rounded-md border border-border px-3 py-2 text-sm font-semibold">{{ auth()->user()->name }}</span>
        <span class="rounded-md border {{ $twoFactorReady ? 'border-success/40 text-success' : 'border-warning/40 text-warning' }} px-3 py-2 text-sm font-semibold">2FA foundation: {{ $twoFactorReady ? 'confirmed' : 'pending' }}</span>
      </div>
    </div>
  </section>

  <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
    @foreach ([
      'Total businesses' => $metrics['total_businesses'],
      'Active businesses' => $metrics['active_businesses'],
      'Trial businesses' => $metrics['trial_businesses'],
      'Suspended businesses' => $metrics['suspended_businesses'],
      'Active subscriptions' => $metrics['active_subscriptions'],
      'Expired subscriptions' => $metrics['expired_subscriptions'],
      'MRR foundation' => 'NGN '.number_format($metrics['monthly_recurring_revenue_minor'] / 100),
      'Active Shop App devices' => $metrics['active_shop_app_devices'],
      'Delayed devices' => $metrics['delayed_devices'],
      'Failed sync batches' => $metrics['failed_sync_batches'],
      'Application versions in use' => $metrics['application_versions_in_use'],
      'Open support tickets' => $metrics['open_support_tickets'],
      'Security alerts' => $metrics['security_alerts'],
    ] as $label => $value)
      <div class="rounded-lg border border-border bg-surface p-4">
        <p class="text-sm font-medium text-muted">{{ $label }}</p>
        <p class="mt-2 font-mono text-2xl font-bold">{{ is_numeric($value) ? number_format($value) : $value }}</p>
      </div>
    @endforeach
  </section>

  <section class="grid gap-4 xl:grid-cols-[0.75fr_1.25fr]">
    <div class="rounded-lg border border-border bg-surface p-4">
      <h2 class="font-semibold">Administration modules</h2>
      <div class="mt-4 grid gap-2">
        @foreach ($modules as $adminModule)
          <button type="button" wire:click="setModule('{{ $adminModule['route'] }}')" class="rounded-md border p-3 text-left {{ $module === $adminModule['route'] ? 'border-primary bg-primary/5' : 'border-border hover:border-primary/50' }}">
            <span class="flex items-center justify-between gap-3">
              <span class="font-semibold">{{ $adminModule['label'] }}</span>
              <span class="font-mono text-sm text-muted">{{ number_format($adminModule['count']) }}</span>
            </span>
            <span class="mt-1 block text-xs text-muted">{{ $adminModule['description'] }}</span>
          </button>
        @endforeach
      </div>
      @error('module') <p class="mt-3 text-sm text-danger">{{ $message }}</p> @enderror
    </div>

    <div class="rounded-lg border border-border bg-surface p-4">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 class="font-semibold">{{ $activeModule['label'] ?? 'Administration records' }}</h2>
          <p class="mt-1 text-sm text-muted">Read-only recent records. Sensitive writes require explicit confirmation and audit records.</p>
        </div>
        <span class="rounded-md border border-border px-3 py-1 text-sm font-semibold">{{ $activeModule['description'] ?? 'Module' }}</span>
      </div>

      <div class="mt-4 overflow-x-auto">
        <table class="ml-table">
          <thead><tr><th>Record</th><th>Status</th><th>Context</th><th>Created</th></tr></thead>
          <tbody>
            @forelse ($rows as $row)
              <tr>
                <td>
                  <p class="font-medium">{{ $row->name ?? $row->title ?? $row->subject ?? $row->uuid ?? ('Record #'.$row->id) }}</p>
                  <p class="font-mono text-xs text-muted">#{{ $row->id }}</p>
                </td>
                <td>{{ $row->status?->value ?? $row->status ?? ($row->is_active ?? $row->is_published ?? 'recorded') }}</td>
                <td class="text-sm text-muted">{{ $row->business?->name ?? $row->branch?->name ?? $row->email ?? $row->application ?? $row->billing_interval ?? 'Platform' }}</td>
                <td class="text-sm text-muted">{{ $row->created_at?->format('M j, Y H:i') ?? 'Unknown' }}</td>
              </tr>
            @empty
              <tr><td colspan="4" class="text-sm text-muted">No records are available for this module yet.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <section class="grid gap-4 lg:grid-cols-3">
    <div class="rounded-lg border border-border bg-surface p-4">
      <h2 class="font-semibold">Sensitive action confirmation</h2>
      <p class="mt-2 text-sm text-muted">Foundation for high-risk actions. Type the required phrase to record a confirmation event.</p>
      <form wire:submit="confirmSensitiveAction" class="mt-4 space-y-3">
        <input wire:model="confirmationPhrase" class="ml-field w-full" placeholder="CONFIRM SUPER ADMIN ACTION">
        @error('confirmationPhrase') <p class="text-sm text-danger">{{ $message }}</p> @enderror
        <button type="submit" class="rounded-md bg-primary px-3 py-2 text-sm font-semibold text-white">Record confirmation</button>
      </form>
    </div>

    <div class="rounded-lg border border-border bg-surface p-4">
      <h2 class="font-semibold">Two-factor authentication foundation</h2>
      <p class="mt-2 text-sm text-muted">Super-admin access is role-gated now. The confirmation timestamp prepares the account for stronger 2FA enforcement when the provider integration is enabled.</p>
    </div>

    <div class="rounded-lg border border-border bg-surface p-4">
      <h2 class="font-semibold">Support access workflow</h2>
      <p class="mt-2 text-sm text-muted">No unrestricted impersonation is implemented. Future customer support access must flow through support-access request records with reason, approval, expiry, and audit trail.</p>
    </div>
  </section>
</div>
