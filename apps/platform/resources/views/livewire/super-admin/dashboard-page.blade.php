<div class="flex flex-col gap-6">
  <section class="rounded-lg border border-border bg-surface p-5">
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <p class="text-sm font-semibold uppercase text-muted">Business-owner account administration</p>
        <h1 class="mt-2 text-2xl font-bold">Manage business owner accounts</h1>
        <p class="mt-2 max-w-3xl text-sm text-muted">Super admins can review owner account status, linked businesses, verification state, and account-risk signals. This page does not include business operations or general Platform modules.</p>
      </div>
      <div class="flex flex-wrap gap-2">
        <span class="rounded-md border border-border px-3 py-2 text-sm font-semibold">{{ auth()->user()->name }}</span>
        <span class="rounded-md border {{ $twoFactorReady ? 'border-success/40 text-success' : 'border-warning/40 text-warning' }} px-3 py-2 text-sm font-semibold">2FA foundation: {{ $twoFactorReady ? 'confirmed' : 'pending' }}</span>
      </div>
    </div>
  </section>

  <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
    @foreach ([
      'Business-owner accounts' => $ownerMetrics['owner_accounts'],
      'Active owner accounts' => $ownerMetrics['active_owner_accounts'],
      'Accounts needing review' => $ownerMetrics['accounts_requiring_attention'],
      'Businesses linked to owners' => $ownerMetrics['businesses_under_management'],
    ] as $label => $value)
      <div class="rounded-lg border border-border bg-surface p-4">
        <p class="text-sm font-medium text-muted">{{ $label }}</p>
        <p class="mt-2 font-mono text-2xl font-bold">{{ number_format($value) }}</p>
      </div>
    @endforeach
  </section>

  <section class="rounded-lg border border-border bg-surface p-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h2 class="font-semibold">Owner Account Review</h2>
        <p class="mt-1 text-sm text-muted">Read-only administrative view of business owners and their linked business accounts.</p>
      </div>
      <span class="rounded-md border border-border px-3 py-2 text-sm font-semibold text-muted">{{ number_format($ownerAccounts->count()) }} shown</span>
    </div>

    <div class="mt-4 overflow-x-auto">
      <table class="ml-table">
        <thead>
          <tr><th>Owner</th><th>Account status</th><th>Linked businesses</th><th>Primary business</th><th>Primary plan</th></tr>
        </thead>
        <tbody>
          @forelse ($ownerAccounts as $account)
            <tr>
              <td>
                <p class="font-medium">{{ $account['owner']->name }}</p>
                <p class="text-xs text-muted">{{ $account['owner']->email }}</p>
              </td>
              <td>
                <p class="font-medium">{{ str($account['account_status'])->replace('_', ' ')->title() }}</p>
                <p class="text-xs text-muted">{{ $account['verified_at'] ? 'Email verified '.$account['verified_at']->format('M j, Y') : 'Email verification pending' }}</p>
              </td>
              <td class="font-mono text-sm">{{ number_format($account['business_count']) }}</td>
              <td>
                <p class="font-medium">{{ $account['primary_business']?->name ?? 'No business linked' }}</p>
                <p class="text-xs text-muted">{{ number_format($account['branch_count']) }} branches / {{ number_format($account['device_count']) }} devices</p>
              </td>
              <td>
                <p class="font-medium">{{ $account['primary_plan'] }}</p>
                <p class="text-xs text-muted">{{ str($account['primary_subscription_status'])->replace('_', ' ')->title() }}</p>
              </td>
            </tr>
          @empty
            <tr><td colspan="5" class="text-sm text-muted">No business-owner accounts are available yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>

  <section class="grid gap-4 lg:grid-cols-2">
    <div class="rounded-lg border border-border bg-surface p-4">
      <h2 class="font-semibold">Sensitive account action confirmation</h2>
      <p class="mt-2 text-sm text-muted">Use this checkpoint before future high-risk account actions such as suspending an owner account or approving support access.</p>
      <form wire:submit="confirmSensitiveAction" class="mt-4 space-y-3">
        <input wire:model="confirmationPhrase" class="ml-field w-full" placeholder="CONFIRM SUPER ADMIN ACTION">
        @error('confirmationPhrase') <p class="text-sm text-danger">{{ $message }}</p> @enderror
        <button type="submit" class="rounded-md bg-primary px-3 py-2 text-sm font-semibold text-white">Record confirmation</button>
      </form>
    </div>

    <div class="rounded-lg border border-border bg-surface p-4">
      <h2 class="font-semibold">Account access guardrails</h2>
      <p class="mt-2 text-sm text-muted">No unrestricted impersonation is available. Admin review stays centered on owner account status, linked businesses, and audit-ready confirmation records.</p>
    </div>
  </section>
</div>
