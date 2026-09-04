<div class="flex flex-col gap-6">
  <x-app.page-header
    title="Services and Revenue Sharing"
    description="Configure service revenue allocation in the Business Server and review synchronized service-sharing records from the Platform."
  >
    <x-slot:actions>
      <x-app.status-badge tone="success">Module available</x-app.status-badge>
    </x-slot:actions>
  </x-app.page-header>

  <x-app.card title="Configuration ready" description="Service transaction records remain owned by the Business Server and synchronize to the Platform for visibility.">
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
