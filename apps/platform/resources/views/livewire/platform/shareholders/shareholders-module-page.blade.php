<div class="flex flex-col gap-6">
  <x-app.page-header
    title="Shareholders and Profit Distribution"
    description="Manage cloud-side settings for ownership and profit-distribution controls while preserving immutable synchronized history."
  >
    <x-slot:actions>
      <x-app.status-badge tone="success">Module available</x-app.status-badge>
    </x-slot:actions>
  </x-app.page-header>

  <x-app.card title="Governance foundation" description="Distribution workflows require the configured approvals before synchronized payments can be exposed to owners.">
    <dl class="grid gap-3 text-sm md:grid-cols-2">
      <div class="rounded-md border border-border p-3">
        <dt class="font-semibold">Effective source</dt>
        <dd class="mt-1 text-muted">{{ ucfirst($access->source) }}</dd>
      </div>
      <div class="rounded-md border border-border p-3">
        <dt class="font-semibold">Branch status</dt>
        <dd class="mt-1 text-muted">{{ $access->branchStatus ? str($access->branchStatus)->replace('_', ' ')->title() : 'Business level' }}</dd>
      </div>
    </dl>
  </x-app.card>
</div>
