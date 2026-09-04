<div class="flex flex-col gap-6">
  <section class="rounded-lg border border-border bg-surface p-5">
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <p class="text-sm font-semibold uppercase text-muted">Billing administration</p>
        <h1 class="mt-2 text-2xl font-bold">Subscriptions</h1>
        <p class="mt-2 max-w-3xl text-sm text-muted">Manage business subscriptions, plans, dates, and pending change requests.</p>
      </div>
      <div class="flex flex-wrap gap-2">
        <span class="rounded-md border border-border px-3 py-2 text-sm font-semibold text-muted">{{ $subscriptions->count() }} subscriptions</span>
        <span class="rounded-md border border-border px-3 py-2 text-sm font-semibold text-muted">{{ $requests->count() }} requests</span>
      </div>
    </div>
  </section>

  @error('subscriptionAction')
    <div class="rounded-lg border border-danger/30 bg-danger/10 p-3 text-sm font-semibold text-danger">{{ $message }}</div>
  @enderror

  @if ($editingSubscriptionId)
    <section class="rounded-lg border border-border bg-surface p-4">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 class="text-lg font-bold">Edit subscription</h2>
          <p class="mt-1 text-sm text-muted">Update the selected business subscription record.</p>
        </div>
        <button type="button" wire:click="cancelEdit" class="rounded-md border border-border px-3 py-2 text-sm font-semibold text-muted">Close</button>
      </div>

      <form wire:submit.prevent="saveSubscription" class="mt-4 grid gap-3 lg:grid-cols-5">
        <label class="grid gap-1 text-sm font-medium">
          Plan
          <select wire:model="planId" class="ml-field">
            @foreach ($plans as $plan)
              <option value="{{ $plan->id }}">{{ $plan->name }} - {{ $plan->currency }} {{ number_format((int) $plan->getRawOriginal('price_minor') / 100, 2) }}</option>
            @endforeach
          </select>
          @error('planId') <span class="text-xs text-danger">{{ $message }}</span> @enderror
        </label>

        <label class="grid gap-1 text-sm font-medium">
          Status
          <select wire:model="status" class="ml-field">
            @foreach ($statuses as $option)
              <option value="{{ $option->value }}">{{ str($option->value)->replace('_', ' ')->title() }}</option>
            @endforeach
          </select>
          @error('status') <span class="text-xs text-danger">{{ $message }}</span> @enderror
        </label>

        <label class="grid gap-1 text-sm font-medium">
          Starts
          <input type="date" wire:model="startsAt" class="ml-field">
          @error('startsAt') <span class="text-xs text-danger">{{ $message }}</span> @enderror
        </label>

        <label class="grid gap-1 text-sm font-medium">
          Ends
          <input type="date" wire:model="endsAt" class="ml-field">
          @error('endsAt') <span class="text-xs text-danger">{{ $message }}</span> @enderror
        </label>

        <div class="flex items-end gap-2">
          <button type="submit" wire:loading.attr="disabled" wire:target="saveSubscription" class="rounded-md bg-primary px-3 py-2 text-sm font-semibold text-white disabled:opacity-60">
            Save
          </button>
          <button type="button" wire:click="cancelEdit" class="rounded-md border border-border px-3 py-2 text-sm font-semibold text-muted">Cancel</button>
        </div>
      </form>
    </section>
  @endif

  <section class="rounded-lg border border-border bg-surface p-4 overflow-x-auto">
    <div class="mb-4">
      <h2 class="font-bold">All subscriptions</h2>
      <p class="mt-1 text-sm text-muted">Current subscription records across all businesses.</p>
    </div>

    <table class="ml-table w-full">
      <thead>
        <tr>
          <th>Business</th>
          <th>Plan</th>
          <th>Status</th>
          <th>Period</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($subscriptions as $subscription)
          <tr>
            <td>
              <p class="font-medium">{{ $subscription->business?->name ?? 'Unknown business' }}</p>
              <p class="text-xs text-muted">{{ $subscription->business?->uuid ?? 'N/A' }}</p>
            </td>
            <td>
              <p class="font-medium">{{ $subscription->plan?->name ?? 'No plan' }}</p>
              <p class="text-xs text-muted">{{ $subscription->plan?->billing_label ?? 'N/A' }}</p>
            </td>
            <td class="font-medium">{{ str($subscription->status->value)->replace('_', ' ')->title() }}</td>
            <td class="text-xs text-muted">
              {{ $subscription->starts_at?->format('M j, Y') ?? 'No start' }}
              -
              {{ $subscription->ends_at?->format('M j, Y') ?? 'No end' }}
            </td>
            <td>
              <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="editSubscription({{ $subscription->id }})" class="rounded-md border border-border px-2 py-1 text-xs font-semibold text-foreground hover:border-primary/50">
                  Edit
                </button>
                @if ($subscription->status !== \App\Enums\SubscriptionStatus::Active)
                  <button type="button" wire:click="setSubscriptionStatus({{ $subscription->id }}, 'active')" wire:confirm="Set this subscription active?" class="rounded-md border border-success/40 px-2 py-1 text-xs font-semibold text-success">
                    Activate
                  </button>
                @endif
                @if ($subscription->status !== \App\Enums\SubscriptionStatus::PastDue)
                  <button type="button" wire:click="setSubscriptionStatus({{ $subscription->id }}, 'past_due')" wire:confirm="Mark this subscription past due?" class="rounded-md border border-warning/40 px-2 py-1 text-xs font-semibold text-warning">
                    Past due
                  </button>
                @endif
                @if ($subscription->status !== \App\Enums\SubscriptionStatus::Cancelled)
                  <button type="button" wire:click="setSubscriptionStatus({{ $subscription->id }}, 'cancelled')" wire:confirm="Cancel this subscription?" class="rounded-md border border-danger/40 px-2 py-1 text-xs font-semibold text-danger">
                    Cancel
                  </button>
                @endif
              </div>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="5" class="text-sm text-muted">No subscriptions found.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </section>

  <section class="rounded-lg border border-border bg-surface p-4 overflow-x-auto">
    <div class="mb-4">
      <h2 class="font-bold">Subscription requests</h2>
      <p class="mt-1 text-sm text-muted">Review pending plan changes and cancellations from business accounts.</p>
    </div>

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
              <p class="font-medium">{{ $request->requestedPlan?->name ?? 'N/A' }}</p>
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
            <td colspan="7" class="text-sm text-muted">No subscription requests found.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </section>
</div>
