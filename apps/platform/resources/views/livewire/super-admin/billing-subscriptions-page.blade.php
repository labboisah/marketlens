<div class="flex flex-col gap-6">
  <section class="rounded-lg border border-border bg-surface p-5">
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <p class="text-sm font-semibold uppercase text-muted">Billing administration</p>
        <h1 class="mt-2 text-2xl font-bold">Subscription requests</h1>
        <p class="mt-2 max-w-3xl text-sm text-muted">Review pending subscription and cancellation requests from business accounts.</p>
      </div>
      <span class="rounded-md border border-border px-3 py-2 text-sm font-semibold text-muted">Showing {{ $requests->count() }} requests</span>
    </div>
  </section>

  <section class="rounded-lg border border-border bg-surface p-4 overflow-x-auto">
    <table class="ml-table w-full">
      <thead>
        <tr>
          <th>Business</th>
          <th>Request type</th>
          <th>Requested plan</th>
          <th>Requester</th>
          <th>Status</th>
          <th>Requested at</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($requests as $request)
          <tr>
            <td>
              <p class="font-medium">{{ $request->business->name }}</p>
              <p class="text-xs text-muted">{{ $request->business->uuid }}</p>
            </td>
            <td class="font-medium">{{ str($request->type)->replace('_', ' ')->title() }}</td>
            <td>
              <p class="font-medium">{{ $request->requestedPlan?->name ?? '—' }}</p>
              <p class="text-xs text-muted">{{ $request->subscription?->plan?->name ?? 'Current plan' }}</p>
            </td>
            <td>
              <p class="font-medium">{{ $request->requester?->name ?? 'System' }}</p>
              <p class="text-xs text-muted">{{ $request->requester?->email ?? 'N/A' }}</p>
            </td>
            <td class="font-medium">{{ str($request->status)->replace('_', ' ')->title() }}</td>
            <td class="text-xs text-muted">{{ $request->requested_at?->format('M j, Y H:i') ?? 'N/A' }}</td>
            <td>
              @if ($request->status === 'pending')
                <div class="flex flex-wrap gap-2">
                  <button
                    type="button"
                    wire:click="approveRequest({{ $request->id }})"
                    wire:loading.attr="disabled"
                    class="rounded-md bg-success px-3 py-2 text-sm font-semibold text-white"
                  >
                    Approve
                  </button>
                  <button
                    type="button"
                    wire:click="rejectRequest({{ $request->id }})"
                    wire:loading.attr="disabled"
                    class="rounded-md border border-border px-3 py-2 text-sm font-semibold text-muted"
                  >
                    Reject
                  </button>
                </div>
              @else
                <span class="text-xs uppercase tracking-wide text-muted">{{ str($request->status)->replace('_', ' ')->title() }}</span>
              @endif
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="6" class="text-sm text-muted">No subscription requests found.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </section>
</div>
