<div>
  <div class="mb-6 text-center">
    <h1 class="text-2xl font-bold">Business onboarding</h1>
    <p class="mt-2 text-sm text-muted">Step {{ $step }} of 4. Your progress is saved safely to your account.</p>
  </div>

  <ol class="mb-6 grid grid-cols-4 gap-2 text-center text-xs font-semibold">
    @foreach ([1 => 'Business', 2 => 'Branch', 3 => 'Monitoring', 4 => 'Review'] as $number => $label)
      <li class="rounded-md border px-2 py-2 {{ $step === $number ? 'border-accent bg-accent/10 text-accent' : 'border-border text-muted' }}">
        {{ $label }}
      </li>
    @endforeach
  </ol>

  @if ($errors->any())
    <div class="mb-5 rounded-md border border-danger/20 bg-danger/10 p-3 text-sm text-danger">
      Please review the highlighted fields before continuing.
    </div>
  @endif

  <form wire:submit.prevent="{{ $step === 4 ? 'complete' : 'nextStep' }}" class="grid gap-5">
    @if ($step === 1)
      <section class="grid gap-4">
        <label class="grid gap-2 text-sm font-medium">Business name <input wire:model.defer="business.name" class="ml-field"> @error('business.name') <span class="text-xs text-danger">{{ $message }}</span> @enderror</label>
        <label class="grid gap-2 text-sm font-medium">Legal name, optional <input wire:model.defer="business.legal_name" class="ml-field"></label>
        <label class="grid gap-2 text-sm font-medium">Business type
          <select wire:model.defer="business.business_type" class="ml-field">
            <option value="">Select type</option>
            @foreach ($businessTypes as $value => $label)
              <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
          </select>
          @error('business.business_type') <span class="text-xs text-danger">{{ $message }}</span> @enderror
        </label>
        <label class="grid gap-2 text-sm font-medium">Registration number, optional <input wire:model.defer="business.registration_number" class="ml-field"></label>
        <label class="grid gap-2 text-sm font-medium">Tax number, optional <input wire:model.defer="business.tax_number" class="ml-field"></label>
        <label class="grid gap-2 text-sm font-medium">Phone <input wire:model.defer="business.phone" class="ml-field"> @error('business.phone') <span class="text-xs text-danger">{{ $message }}</span> @enderror</label>
        <label class="grid gap-2 text-sm font-medium">Alternative phone, optional <input wire:model.defer="business.alternative_phone" class="ml-field"></label>
        <label class="grid gap-2 text-sm font-medium">Email <input wire:model.defer="business.email" type="email" class="ml-field"> @error('business.email') <span class="text-xs text-danger">{{ $message }}</span> @enderror</label>
        <label class="grid gap-2 text-sm font-medium">Address <textarea wire:model.defer="business.address" class="min-h-20 rounded-md border border-border bg-surface px-3 py-2 text-sm"></textarea> @error('business.address') <span class="text-xs text-danger">{{ $message }}</span> @enderror</label>
        <div class="grid gap-4 sm:grid-cols-2">
          <label class="grid gap-2 text-sm font-medium">State <input wire:model.defer="business.state" class="ml-field"> @error('business.state') <span class="text-xs text-danger">{{ $message }}</span> @enderror</label>
          <label class="grid gap-2 text-sm font-medium">Local government area <input wire:model.defer="business.local_government_area" class="ml-field"> @error('business.local_government_area') <span class="text-xs text-danger">{{ $message }}</span> @enderror</label>
        </div>
        <div class="grid gap-4 sm:grid-cols-3">
          <label class="grid gap-2 text-sm font-medium">Default currency <input wire:model.defer="business.default_currency" class="ml-field"></label>
          <label class="grid gap-2 text-sm font-medium">Timezone <input wire:model.defer="business.timezone" class="ml-field"></label>
          <label class="grid gap-2 text-sm font-medium">Preferred language
            <select wire:model.defer="business.preferred_language" class="ml-field">
              <option value="en">English</option>
              <option value="ha">Hausa</option>
            </select>
          </label>
        </div>
        <label class="grid gap-2 text-sm font-medium">Logo, optional <input wire:model.defer="business.logo_path" class="ml-field" placeholder="Logo upload path placeholder"></label>
      </section>
    @endif

    @if ($step === 2)
      <section class="grid gap-4">
        <label class="grid gap-2 text-sm font-medium">Branch name <input wire:model.defer="branch.name" class="ml-field"> @error('branch.name') <span class="text-xs text-danger">{{ $message }}</span> @enderror</label>
        <label class="grid gap-2 text-sm font-medium">Branch code <input wire:model.defer="branch.code" class="ml-field"> @error('branch.code') <span class="text-xs text-danger">{{ $message }}</span> @enderror</label>
        <label class="grid gap-2 text-sm font-medium">Phone <input wire:model.defer="branch.phone" class="ml-field"> @error('branch.phone') <span class="text-xs text-danger">{{ $message }}</span> @enderror</label>
        <label class="grid gap-2 text-sm font-medium">Email, optional <input wire:model.defer="branch.email" type="email" class="ml-field"></label>
        <label class="grid gap-2 text-sm font-medium">Address <textarea wire:model.defer="branch.address" class="min-h-20 rounded-md border border-border bg-surface px-3 py-2 text-sm"></textarea> @error('branch.address') <span class="text-xs text-danger">{{ $message }}</span> @enderror</label>
        <div class="grid gap-4 sm:grid-cols-2">
          <label class="grid gap-2 text-sm font-medium">State <input wire:model.defer="branch.state" class="ml-field"> @error('branch.state') <span class="text-xs text-danger">{{ $message }}</span> @enderror</label>
          <label class="grid gap-2 text-sm font-medium">Local government area <input wire:model.defer="branch.local_government_area" class="ml-field"> @error('branch.local_government_area') <span class="text-xs text-danger">{{ $message }}</span> @enderror</label>
        </div>
        <fieldset class="space-y-2">
          <legend class="text-sm font-medium">Branch operating modes</legend>
          <div class="flex flex-wrap gap-x-4 gap-y-2">
            @foreach ($branchOperatingModes as $mode)
              <label class="flex items-center gap-2 text-sm" wire:key="operating-mode-{{ $mode->value }}">
                <input type="checkbox" wire:model.live="branch.operating_modes" value="{{ $mode->value }}" class="rounded border-border">
                {{ $mode->displayName() }}
              </label>
            @endforeach
          </div>
          @error('branch.operating_modes') <span class="text-xs text-danger">{{ $message }}</span> @enderror
          @error('branch.operating_modes.*') <span class="text-xs text-danger">{{ $message }}</span> @enderror
        </fieldset>
      </section>
    @endif

    @if ($step === 3)
      <section class="grid gap-4">
        <label class="grid gap-2 text-sm font-medium">Daily report preference
          <select wire:model.defer="monitoring.daily_report_preference" class="ml-field">
            <option value="daily">Daily</option>
            <option value="weekly">Weekly</option>
            <option value="none">None</option>
          </select>
        </label>
        @foreach ([
          'low_stock_alerts' => 'Low-stock alerts',
          'cashier_shortage_alerts' => 'Cashier-shortage alerts',
          'synchronization_delay_alerts' => 'Synchronization-delay alerts',
          'security_alerts' => 'Security alerts',
          'email_notifications' => 'Email notifications',
          'push_notification_readiness' => 'Push-notification readiness',
          'sms_placeholder' => 'SMS placeholder, not yet implemented',
          'whatsapp_placeholder' => 'WhatsApp placeholder, not yet implemented',
        ] as $field => $label)
          <label class="flex items-center gap-2 text-sm text-muted"><input type="checkbox" wire:model.defer="monitoring.{{ $field }}" class="h-4 w-4 rounded border-border"> {{ $label }}</label>
        @endforeach
      </section>
    @endif

    @if ($step === 4)
      <section class="grid gap-4 text-sm">
        <div class="rounded-lg border border-border bg-background p-4">
          <h2 class="font-semibold">Business information</h2>
          <p class="mt-2 text-muted">{{ $business['name'] }} &middot; {{ $business['business_type'] }} &middot; {{ $business['email'] }}</p>
        </div>
        <div class="rounded-lg border border-border bg-background p-4">
          <h2 class="font-semibold">Primary branch</h2>
          <p class="mt-2 text-muted">{{ $branch['name'] }} &middot; {{ $branch['code'] }} &middot; {{ $branch['state'] }}</p>
        </div>
        <div class="rounded-lg border border-border bg-background p-4">
          <h2 class="font-semibold">Monitoring preferences</h2>
          <p class="mt-2 text-muted">Daily reports: {{ $monitoring['daily_report_preference'] }}. Email notifications: {{ $monitoring['email_notifications'] ? 'enabled' : 'disabled' }}.</p>
        </div>
        <div class="rounded-lg border border-border bg-background p-4">
          <h2 class="font-semibold">Product license</h2>
          <p class="mt-2 text-muted">Connect your MarketLens license after creating the business. KernelBridge manages the plan and entitlements.</p>
        </div>
      </section>
    @endif

    <div class="flex items-center justify-between gap-3 border-t border-border pt-5">
      @if ($step > 1)
        <button type="button" wire:click="previousStep" class="inline-flex h-10 items-center justify-center rounded-md border border-border px-4 text-sm font-semibold">Back</button>
      @else
        <span></span>
      @endif
      <button type="submit" class="inline-flex h-10 items-center justify-center rounded-md bg-accent px-4 text-sm font-semibold text-white">
        {{ $step === 4 ? 'Create business' : 'Continue' }}
      </button>
    </div>
  </form>
</div>
