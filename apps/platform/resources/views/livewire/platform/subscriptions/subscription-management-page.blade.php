<div class="flex flex-col gap-6">
  <x-app.page-header
    title="Subscription management"
    description="Review the current plan, usage, billing records, and safe subscription change requests."
  >
    <x-slot:actions>
      <a href="{{ route('platform.support', ['category' => 'billing']) }}" class="rounded-md border border-border px-3 py-2 text-sm font-semibold text-foreground hover:border-primary/50">Billing support</a>
    </x-slot:actions>
  </x-app.page-header>

  <section class="grid gap-4 xl:grid-cols-3">
    <x-app.card title="Current plan" description="Billing access stays available even when premium features are restricted.">
      <div class="space-y-3">
        <div>
          <p class="text-sm text-muted">Plan</p>
          <p class="text-xl font-semibold">{{ $overview['plan']?->name ?? 'No plan' }}</p>
          <p class="text-sm text-muted">{{ $overview['plan']?->display_price ?? 'Manual billing' }} / {{ $overview['plan']?->billing_label ?? 'None' }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
          <x-app.status-badge tone="{{ $overview['operationalAccess'] ? 'success' : 'warning' }}">
            {{ $overview['operationalAccess'] ? 'Operational access allowed' : 'Premium access restricted' }}
          </x-app.status-badge>
          <x-app.status-badge>{{ ucfirst($overview['subscription']?->status?->value ?? 'none') }}</x-app.status-badge>
        </div>
        <p class="text-sm text-muted">Expiration: {{ $overview['expiration']['date']?->format('M j, Y') ?? 'Not scheduled' }}</p>
        <p class="text-sm text-muted">Grace period: {{ $overview['graceDays'] }} days. Retention policy: {{ $overview['retentionDays'] }} days.</p>
      </div>
    </x-app.card>

    <x-app.card title="Trial status" description="Trial state is based on subscription status and end date.">
      <div class="space-y-2">
        <p class="text-2xl font-semibold">{{ str($overview['trial']['status'])->replace('_', ' ')->title() }}</p>
        <p class="text-sm text-muted">
          @if ($overview['trial']['days_remaining'] !== null)
            {{ $overview['trial']['days_remaining'] }} days remaining.
          @else
            This business is not currently trialing.
          @endif
        </p>
      </div>
    </x-app.card>

    <x-app.card title="Plan features" description="Features control premium Platform modules and report access.">
      <div class="flex flex-wrap gap-2">
        @forelse ($overview['features'] as $feature)
          <span class="rounded-md border border-border px-2 py-1 text-xs font-medium">{{ $feature->name }}</span>
        @empty
          <span class="text-sm text-muted">No features are attached to this plan.</span>
        @endforelse
      </div>
    </x-app.card>
  </section>

  <section class="grid gap-4 xl:grid-cols-[1.2fr_0.8fr]">
    <x-app.card title="Usage against limits" description="Downgrades warn when current usage exceeds the target plan.">
      <x-app.table density="compact">
        <thead><tr><th>Limit</th><th>Used</th><th>Plan limit</th><th>Status</th></tr></thead>
        <tbody>
          @foreach ($overview['usageRows'] as $row)
            <tr>
              <td>{{ $row['label'] }}</td>
              <td class="font-mono">{{ number_format($row['used']) }}</td>
              <td class="font-mono">{{ $row['limit'] === PHP_INT_MAX ? 'Unlimited' : number_format($row['limit']) }}</td>
              <td><x-app.status-badge tone="{{ $row['exceeded'] ? 'warning' : 'success' }}">{{ $row['exceeded'] ? 'Exceeded' : 'Within limit' }}</x-app.status-badge></td>
            </tr>
          @endforeach
        </tbody>
      </x-app.table>
    </x-app.card>

    <x-app.card title="Billing profile" description="Clean billing data for invoices and payment gateway adapters.">
      <form wire:submit="saveBillingProfile" class="space-y-3">
        <label class="block text-sm font-medium">Billing name<input wire:model="billingName" class="ml-field mt-1 w-full"></label>
        <label class="block text-sm font-medium">Billing email<input wire:model="billingEmail" class="ml-field mt-1 w-full"></label>
        <label class="block text-sm font-medium">Billing phone<input wire:model="billingPhone" class="ml-field mt-1 w-full"></label>
        <label class="block text-sm font-medium">Billing address<textarea wire:model="billingAddress" class="ml-field mt-1 w-full" rows="3"></textarea></label>
        <div class="grid gap-3 sm:grid-cols-2">
          <label class="block text-sm font-medium">Tax number<input wire:model="taxNumber" class="ml-field mt-1 w-full"></label>
          <label class="block text-sm font-medium">Currency<input wire:model="currency" class="ml-field mt-1 w-full" maxlength="3"></label>
        </div>
        <button type="submit" class="rounded-md bg-primary px-3 py-2 text-sm font-semibold text-white">Save billing profile</button>
      </form>
    </x-app.card>
  </section>

  <section class="grid gap-4 xl:grid-cols-[1fr_1fr]">
    <x-app.card title="Upgrade and downgrade options" description="Requests are recorded first; no provider is hard-coded into this domain.">
      <div class="space-y-3">
        @foreach ($plans as $plan)
          @php($warnings = app(\App\Domain\Billing\Services\SubscriptionManagementService::class)->downgradeWarnings($business, $plan))
          <div class="rounded-md border border-border p-3">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <p class="font-semibold">{{ $plan->name }}</p>
                <p class="text-sm text-muted">{{ $plan->display_price }} / {{ $plan->billing_label }}</p>
              </div>
              <button type="button" wire:click="requestPlanChange({{ $plan->id }})" class="rounded-md border border-primary/40 px-3 py-2 text-sm font-semibold text-primary">
                Request {{ $overview['plan'] && $plan->getRawOriginal('price_minor') < $overview['plan']->getRawOriginal('price_minor') ? 'downgrade' : 'upgrade' }}
              </button>
            </div>
            @if ($warnings)
              <div class="mt-3 rounded-md border border-warning/30 bg-warning/5 p-2 text-xs text-warning">
                <p class="font-semibold">Limit warning</p>
                @foreach ($warnings as $warning)
                  <p>{{ $warning }}</p>
                @endforeach
              </div>
            @endif
          </div>
        @endforeach
      </div>
    </x-app.card>

    <x-app.card title="Cancellation request" description="Cancellation does not immediately delete data or synchronized history.">
      <div class="space-y-3">
        <p class="text-sm text-muted">Requests preserve synchronized records according to the retention policy and keep safe account and billing access available.</p>
        <label class="block text-sm font-medium">Reason<textarea wire:model="cancellationReason" class="ml-field mt-1 w-full" rows="4"></textarea></label>
        <button type="button" wire:click="requestCancellation" class="rounded-md border border-danger/40 px-3 py-2 text-sm font-semibold text-danger">Request cancellation</button>
      </div>
    </x-app.card>
  </section>

  <section class="grid gap-4 xl:grid-cols-3">
    <x-app.card title="Renewal history">
      <div class="space-y-3">
        @forelse ($renewals as $subscription)
          <div class="rounded-md border border-border p-3 text-sm">
            <p class="font-semibold">{{ $subscription->plan?->name ?? 'Unknown plan' }}</p>
            <p class="text-muted">{{ ucfirst($subscription->status->value) }} - {{ $subscription->starts_at?->format('M j, Y') ?? 'No start' }} to {{ $subscription->ends_at?->format('M j, Y') ?? 'Open' }}</p>
          </div>
        @empty
          <x-app.empty-state title="No renewal history" message="Subscription periods will appear here." />
        @endforelse
      </div>
    </x-app.card>

    <x-app.card title="Payment history">
      <div class="space-y-3">
        @forelse ($payments as $payment)
          <div class="rounded-md border border-border p-3 text-sm">
            <p class="font-semibold">{{ $payment->currency }} {{ number_format($payment->getRawOriginal('amount_minor') / 100) }}</p>
            <p class="text-muted">{{ ucfirst($payment->status->value) }} - {{ $payment->paid_at?->format('M j, Y') ?? 'Pending' }}</p>
          </div>
        @empty
          <x-app.empty-state title="No payment history" message="Subscription payments will appear here." />
        @endforelse
      </div>
    </x-app.card>

    <x-app.card title="Request history">
      <div class="space-y-3">
        @forelse ($changeRequests as $request)
          <div class="rounded-md border border-border p-3 text-sm">
            <p class="font-semibold">{{ str($request->type)->replace('_', ' ')->title() }} - {{ ucfirst($request->status) }}</p>
            <p class="text-muted">{{ $request->requestedPlan?->name ?? 'No target plan' }} - {{ $request->requested_at?->format('M j, Y H:i') }}</p>
          </div>
        @empty
          <x-app.empty-state title="No requests" message="Upgrade, downgrade, and cancellation requests appear here." />
        @endforelse
      </div>
    </x-app.card>
  </section>
</div>

