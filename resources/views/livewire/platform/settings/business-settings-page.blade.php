<div class="flex flex-col gap-6">
  <x-app.page-header
    title="Business settings"
    description="Manage business profile defaults, monitoring preferences, alert delivery, security access, and data controls."
  />

  @if ($errors->any())
    <div class="rounded-md border border-danger/30 bg-danger/5 p-4 text-sm text-danger">
      <p class="font-semibold">Please fix the highlighted settings fields.</p>
      <ul class="mt-2 list-disc space-y-1 pl-5">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <section class="grid gap-4 xl:grid-cols-[1fr_0.9fr]">
    <x-app.card title="General settings" description="Business identity and localization for owner-facing Platform screens.">
      <form wire:submit="saveGeneral" class="space-y-4">
        <div class="grid gap-3 md:grid-cols-2">
          <label class="block text-sm font-medium">Business name<input wire:model="name" class="ml-field mt-1 w-full"></label>
          <label class="block text-sm font-medium">Legal name<input wire:model="legalName" class="ml-field mt-1 w-full"></label>
          <label class="block text-sm font-medium">Phone<input wire:model="phone" class="ml-field mt-1 w-full"></label>
          <label class="block text-sm font-medium">Email<input wire:model="email" class="ml-field mt-1 w-full"></label>
          <label class="block text-sm font-medium md:col-span-2">Address<textarea wire:model="address" class="ml-field mt-1 w-full" rows="3"></textarea></label>
          <label class="block text-sm font-medium">State<input wire:model="state" class="ml-field mt-1 w-full"></label>
          <label class="block text-sm font-medium">Local government<input wire:model="localGovernmentArea" class="ml-field mt-1 w-full"></label>
          <label class="block text-sm font-medium">Business type<input wire:model="businessType" class="ml-field mt-1 w-full"></label>
          <label class="block text-sm font-medium">Logo<input wire:model="logo" type="file" accept="image/*" class="ml-field mt-1 w-full"></label>
          <label class="block text-sm font-medium">Currency<input wire:model="defaultCurrency" class="ml-field mt-1 w-full" maxlength="3"></label>
          <label class="block text-sm font-medium">Timezone<input wire:model="timezone" class="ml-field mt-1 w-full"></label>
          <label class="block text-sm font-medium">Preferred language<input wire:model="preferredLanguage" class="ml-field mt-1 w-full"></label>
        </div>
        <button type="submit" class="rounded-md bg-primary px-3 py-2 text-sm font-semibold text-white">Save general settings</button>
      </form>
    </x-app.card>

    <x-app.card title="Monitoring settings" description="Defaults used by monitoring dashboards and summary cards.">
      <form wire:submit="saveMonitoring" class="space-y-4">
        <label class="block text-sm font-medium">Default branch
          <select wire:model="defaultBranchId" class="ml-field mt-1 w-full">
            <option value="">All branches</option>
            @foreach ($branches as $branch)
              <option value="{{ $branch->id }}">{{ $branch->name }}</option>
            @endforeach
          </select>
        </label>
        <div class="grid gap-3 sm:grid-cols-2">
          <label class="block text-sm font-medium">Default date range
            <select wire:model="defaultDateRange" class="ml-field mt-1 w-full">
              <option value="today">Today</option>
              <option value="7_days">Last 7 days</option>
              <option value="30_days">Last 30 days</option>
              <option value="this_month">This month</option>
            </select>
          </label>
          <label class="block text-sm font-medium">Dashboard refresh preference
            <select wire:model="dashboardRefreshPreference" class="ml-field mt-1 w-full">
              <option value="manual">Manual</option>
              <option value="30s">30 seconds</option>
              <option value="60s">60 seconds</option>
              <option value="5m">5 minutes</option>
            </select>
          </label>
        </div>
        <label class="block text-sm font-medium">Stale-data warning threshold, minutes<input wire:model="staleDataWarningThreshold" type="number" min="5" max="1440" class="ml-field mt-1 w-full"></label>
        <div class="grid gap-2 sm:grid-cols-2">
          @foreach ($summaryDisplayPreferences as $key => $enabled)
            <label class="flex items-center gap-2 rounded-md border border-border p-2 text-sm font-medium">
              <input wire:model="summaryDisplayPreferences.{{ $key }}" type="checkbox" class="rounded border-border text-primary">
              {{ str($key)->replace('_', ' ')->title() }} summaries
            </label>
          @endforeach
        </div>
        <button type="submit" class="rounded-md bg-primary px-3 py-2 text-sm font-semibold text-white">Save monitoring settings</button>
      </form>
    </x-app.card>
  </section>


  <section>
    <x-app.card title="Modules and Business Capabilities" description="Plan availability, business enablement, and branch inheritance combine to determine module access.">
      <form wire:submit="saveModules" class="space-y-5">
        <div class="rounded-md border border-border p-4">
          <label class="flex items-center justify-between gap-3"><span><span class="font-semibold">Production and Profit Sharing</span><span class="mt-1 block text-xs text-muted">Plan: {{ $moduleAccess['production']->planAllows ? 'Allowed' : 'Unavailable' }}</span></span><input wire:model="moduleSettings.production.enabled" type="checkbox" class="rounded border-border text-primary" @disabled(! $moduleAccess['production']->planAllows)></label>
          <p class="mt-2 text-sm text-muted">Enable production for this business. Owners register machines and staff and configure share percentages locally in the Business Server.</p>
          <label class="mt-3 flex items-center gap-2 text-sm"><input wire:model="moduleSettings.production.business_level_locks.prevent_branch_overrides" type="checkbox">Prevent branch overrides</label>
        </div>
        <div class="grid gap-4 xl:grid-cols-2">
          <div class="space-y-3 rounded-md border border-border p-4">
            <div class="flex items-center justify-between gap-3">
              <div>
                <p class="font-semibold">Services and Revenue Sharing</p>
                <p class="text-xs text-muted">Plan: {{ $moduleAccess['services']->planAllows ? 'Allowed' : 'Unavailable' }}</p>
              </div>
              <input wire:model="moduleSettings.services.enabled" type="checkbox" class="rounded border-border text-primary" @disabled(! $moduleAccess['services']->planAllows)>
            </div>
            @unless($moduleAccess['services']->planAllows)
              <x-app.subscription-feature-restriction feature="Services and Revenue Sharing" :plan="$business->currentSubscription?->plan" />
            @endunless
            <div class="grid gap-3 md:grid-cols-2">
              <label class="flex items-center gap-2 text-sm"><input wire:model="moduleSettings.services.staff_revenue_sharing_enabled" type="checkbox" class="rounded border-border text-primary"> Enable staff revenue sharing</label>
              <label class="flex items-center gap-2 text-sm"><input wire:model="moduleSettings.services.deduct_direct_expenses_before_sharing" type="checkbox" class="rounded border-border text-primary"> Deduct direct expenses before sharing</label>
              <label class="block text-sm font-medium">Default calculation basis
                <select wire:model="moduleSettings.services.default_calculation_basis" class="ml-field mt-1 w-full">
                  @foreach ($calculationBases as $basis)
                    <option value="{{ $basis->value }}">{{ str($basis->value)->replace('_', ' ')->title() }}</option>
                  @endforeach
                </select>
              </label>
              <label class="block text-sm font-medium">Settlement frequency
                <select wire:model="moduleSettings.services.settlement_frequency" class="ml-field mt-1 w-full">
                  @foreach ($settlementFrequencies as $frequency)
                    <option value="{{ $frequency->value }}">{{ str($frequency->value)->title() }}</option>
                  @endforeach
                </select>
              </label>
              <label class="block text-sm font-medium">Staff percentage<input wire:model="moduleSettings.services.staff_percentage" type="number" min="0" max="100" class="ml-field mt-1 w-full"></label>
              <label class="block text-sm font-medium">Business percentage<input wire:model="moduleSettings.services.business_percentage" type="number" min="0" max="100" class="ml-field mt-1 w-full"></label>
              <label class="block text-sm font-medium">Expense-reserve percentage<input wire:model="moduleSettings.services.expense_reserve_percentage" type="number" min="0" max="100" class="ml-field mt-1 w-full"></label>
            </div>
            <div class="grid gap-2 md:grid-cols-2">
              @foreach ([
                'require_service_completion_before_allocation' => 'Require service completion before allocation',
                'require_confirmed_payment_before_allocation' => 'Require confirmed payment before allocation',
                'require_manager_approval' => 'Require manager approval',
                'allow_multiple_staff_per_service' => 'Allow multiple staff per service',
                'allow_service_specific_rules' => 'Allow service-specific rules',
                'allow_branch_specific_rules' => 'Allow branch-specific rules',
                'business_level_locks.prevent_branch_overrides' => 'Lock branch overrides',
              ] as $key => $label)
                <label class="flex items-center gap-2 text-sm"><input wire:model="moduleSettings.services.{{ $key }}" type="checkbox" class="rounded border-border text-primary"> {{ $label }}</label>
              @endforeach
            </div>
          </div>

          <div class="space-y-3 rounded-md border border-border p-4">
            <div class="flex items-center justify-between gap-3">
              <div>
                <p class="font-semibold">Shareholders and Profit Distribution</p>
                <p class="text-xs text-muted">Plan: {{ $moduleAccess['shareholders']->planAllows ? 'Allowed' : 'Unavailable' }}</p>
              </div>
              <input wire:model="moduleSettings.shareholders.enabled" type="checkbox" class="rounded border-border text-primary" @disabled(! $moduleAccess['shareholders']->planAllows)>
            </div>
            @unless($moduleAccess['shareholders']->planAllows)
              <x-app.subscription-feature-restriction feature="Shareholders and Profit Distribution" :plan="$business->currentSubscription?->plan" />
            @endunless
            <div class="grid gap-3 md:grid-cols-2">
              <label class="block text-sm font-medium">Ownership type
                <select wire:model="moduleSettings.shareholders.ownership_type" class="ml-field mt-1 w-full">
                  @foreach ($ownershipTypes as $type)
                    <option value="{{ $type->value }}">{{ str($type->value)->replace('_', ' ')->title() }}</option>
                  @endforeach
                </select>
              </label>
              <label class="block text-sm font-medium">Distribution basis
                <select wire:model="moduleSettings.shareholders.distribution_basis" class="ml-field mt-1 w-full">
                  @foreach ($distributionBases as $basis)
                    <option value="{{ $basis->value }}">{{ str($basis->value)->replace('_', ' ')->title() }}</option>
                  @endforeach
                </select>
              </label>
            </div>
            <div class="grid gap-2 md:grid-cols-2">
              @foreach ([
                'require_approved_resolution' => 'Require approved resolution',
                'require_closed_accounting_period' => 'Require closed accounting period',
                'require_retained_profit_allocation' => 'Require retained-profit allocation',
                'require_tax_provision' => 'Require tax provision',
                'allow_partial_payments' => 'Allow partial payments',
                'allow_shareholder_loans' => 'Allow shareholder loans',
                'allow_capital_contributions' => 'Allow capital contributions',
                'business_level_locks.prevent_branch_overrides' => 'Lock branch overrides',
              ] as $key => $label)
                <label class="flex items-center gap-2 text-sm"><input wire:model="moduleSettings.shareholders.{{ $key }}" type="checkbox" class="rounded border-border text-primary"> {{ $label }}</label>
              @endforeach
            </div>
          </div>
        </div>
        <button type="submit" class="rounded-md bg-primary px-3 py-2 text-sm font-semibold text-white">Save module settings</button>
      </form>
    </x-app.card>
  </section>
  <section class="grid gap-4 xl:grid-cols-[1fr_0.9fr]">
    <x-app.card title="Notification settings" description="Channel preferences for business-owner alerts. SMS and WhatsApp are placeholders for future gateway adapters.">
      <form wire:submit="saveNotifications" class="space-y-3">
        <x-app.table density="compact">
          <thead>
            <tr>
              <th>Alert</th>
              @foreach ($notificationChannels as $channelLabel)
                <th>{{ $channelLabel }}</th>
              @endforeach
            </tr>
          </thead>
          <tbody>
            @foreach ($notificationCategories as $category => $categoryLabel)
              <tr>
                <td class="font-medium">{{ $categoryLabel }}</td>
                @foreach ($notificationChannels as $channel => $channelLabel)
                  @php($key = $category.'|'.$channel)
                  <td>
                    <input wire:model="notificationPreferences.{{ $key }}" type="checkbox" class="rounded border-border text-primary" aria-label="{{ $categoryLabel }} {{ $channelLabel }}">
                  </td>
                @endforeach
              </tr>
            @endforeach
          </tbody>
        </x-app.table>
        <button type="submit" class="rounded-md bg-primary px-3 py-2 text-sm font-semibold text-white">Save notification settings</button>
      </form>
    </x-app.card>

    <x-app.card title="Security settings" description="Account security controls stay available regardless of premium feature status.">
      <div class="space-y-5">
        <form wire:submit="changePassword" class="space-y-3">
          <p class="text-sm font-semibold">Password change</p>
          <input wire:model="currentPassword" type="password" class="ml-field w-full" placeholder="Current password">
          <input wire:model="password" type="password" class="ml-field w-full" placeholder="New password">
          <input wire:model="passwordConfirmation" type="password" class="ml-field w-full" placeholder="Confirm new password">
          <button type="submit" class="rounded-md border border-primary/40 px-3 py-2 text-sm font-semibold text-primary">Change password</button>
        </form>

        <div class="rounded-md border border-border p-3 text-sm">
          <div class="flex items-center justify-between gap-3">
            <div>
              <p class="font-semibold">Two-factor authentication foundation</p>
              <p class="text-muted">{{ $securitySetting->two_factor_enabled ? 'Two-factor authentication is enabled.' : 'Authenticator enrollment fields are ready for provider integration.' }}</p>
            </div>
            <x-app.status-badge tone="{{ $securitySetting->two_factor_enabled ? 'success' : 'warning' }}">{{ $securitySetting->two_factor_enabled ? 'Enabled' : 'Foundation' }}</x-app.status-badge>
          </div>
        </div>

        <div>
          <div class="mb-2 flex items-center justify-between gap-3">
            <p class="text-sm font-semibold">Trusted devices</p>
            <button type="button" wire:click="trustCurrentDevice" class="rounded-md border border-border px-2 py-1 text-xs font-semibold">Trust this device</button>
          </div>
          <div class="space-y-2">
            @forelse ($trustedDevices as $device)
              <div class="flex items-center justify-between gap-3 rounded-md border border-border p-2 text-sm">
                <div>
                  <p class="font-medium">{{ $device->name }}</p>
                  <p class="text-xs text-muted">{{ $device->trusted_at?->format('M j, Y H:i') }} @if($device->revoked_at) - Revoked @endif</p>
                </div>
                @unless ($device->revoked_at)
                  <button type="button" wire:click="revokeTrustedDevice({{ $device->id }})" class="text-xs font-semibold text-danger">Revoke</button>
                @endunless
              </div>
            @empty
              <x-app.empty-state title="No trusted devices" message="Trusted browsers will appear here." />
            @endforelse
          </div>
        </div>
      </div>
    </x-app.card>
  </section>

  <section class="grid gap-4 xl:grid-cols-2">
    <x-app.card title="Active Platform sessions">
      <div class="space-y-2">
        @forelse ($platformSessions as $session)
          <div class="flex items-center justify-between gap-3 rounded-md border border-border p-3 text-sm">
            <div>
              <p class="font-medium">{{ $session->ip_address ?? 'Unknown IP' }}</p>
              <p class="text-xs text-muted">{{ str($session->user_agent ?? 'Unknown browser')->limit(80) }} - {{ $session->last_seen_at->format('M j, Y H:i') }}</p>
            </div>
            <button type="button" wire:click="revokePlatformSession('{{ $session->id }}')" class="text-xs font-semibold text-danger">Revoke</button>
          </div>
        @empty
          <x-app.empty-state title="No active sessions" message="Database-backed Platform sessions will appear here." />
        @endforelse
      </div>
    </x-app.card>

    <x-app.card title="Monitoring App sessions">
      <div class="space-y-2">
        @forelse ($monitoringSessions as $token)
          <div class="flex items-center justify-between gap-3 rounded-md border border-border p-3 text-sm">
            <div>
              <p class="font-medium">{{ $token->name }}</p>
              <p class="text-xs text-muted">Last used {{ $token->last_used_at?->format('M j, Y H:i') ?? 'never' }}</p>
            </div>
            <button type="button" wire:click="revokeMonitoringSession({{ $token->id }})" class="text-xs font-semibold text-danger">Revoke</button>
          </div>
        @empty
          <x-app.empty-state title="No Monitoring App sessions" message="Monitoring tokens will appear here after sign-in." />
        @endforelse
      </div>
    </x-app.card>
  </section>

  <section class="grid gap-4 xl:grid-cols-[0.9fr_1.1fr]">
    <x-app.card title="Data settings" description="Exports and closure requests preserve synchronized data according to retention policy.">
      <div class="space-y-4">
        <div class="rounded-md border border-border p-3 text-sm">
          <p class="font-semibold">Retention policy display</p>
          <p class="text-muted">Synchronized business records are retained for {{ $retentionDays }} days unless a stricter legal or operational policy applies.</p>
        </div>
        <div class="rounded-md border border-border p-3 text-sm">
          <p class="font-semibold">Privacy information</p>
          <p class="text-muted">Business settings exports contain owner-managed profile records and counts. Operational transactional history remains immutable and scoped by tenant.</p>
        </div>
        <button type="button" wire:click="requestDataExport" class="rounded-md border border-primary/40 px-3 py-2 text-sm font-semibold text-primary">Export business records</button>
        <div class="space-y-2">
          <p class="text-sm font-semibold">Request account closure</p>
          <textarea wire:model="closureReason" class="ml-field w-full" rows="3" placeholder="Optional reason"></textarea>
          <button type="button" wire:click="requestAccountClosure" class="rounded-md border border-danger/40 px-3 py-2 text-sm font-semibold text-danger">Request account closure</button>
        </div>
      </div>
    </x-app.card>

    <x-app.card title="Report history and audit history">
      <div class="grid gap-4 lg:grid-cols-2">
        <div>
          <p class="mb-2 text-sm font-semibold">Export history</p>
          <div class="space-y-2">
            @forelse ($dataExports as $export)
              <div class="rounded-md border border-border p-2 text-sm">
                <p class="font-medium">{{ strtoupper($export->format) }} - {{ ucfirst($export->status) }}</p>
                <p class="text-xs text-muted">Requested {{ $export->requested_at?->format('M j, Y H:i') }} - Expires {{ $export->expires_at?->format('M j, Y') }}</p>
              </div>
            @empty
              <x-app.empty-state title="No exports" message="Business export requests will appear here." />
            @endforelse
          </div>
        </div>
        <div>
          <p class="mb-2 text-sm font-semibold">Audit history</p>
          <div class="space-y-2">
            @forelse ($auditLogs as $log)
              <div class="rounded-md border border-border p-2 text-sm">
                <p class="font-medium">{{ str($log->action)->replace('_', ' ')->replace('.', ' / ')->title() }}</p>
                <p class="text-xs text-muted">{{ $log->created_at?->format('M j, Y H:i') }}</p>
              </div>
            @empty
              <x-app.empty-state title="No audit entries" message="Settings changes will be recorded here." />
            @endforelse
          </div>
        </div>
      </div>
    </x-app.card>
  </section>
</div>
