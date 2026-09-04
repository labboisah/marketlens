<div class="flex flex-col gap-6">
  <x-app.page-header
    title="Business Server device activation"
    description="Generate short-lived, single-use activation codes and monitor activated Business Server devices."
  >
    <x-slot:actions>
      <a href="{{ route('platform.support', ['category' => 'device']) }}" class="rounded-md border border-border px-3 py-2 text-sm font-semibold text-foreground hover:border-primary/50">Get device support</a>
    </x-slot:actions>
  </x-app.page-header>

  @if (session('device_status'))
    <x-app.status-badge tone="success">{{ session('device_status') }}</x-app.status-badge>
  @endif

  <section class="grid gap-4 xl:grid-cols-[0.9fr_1.5fr]">
    <x-app.card title="Generate activation" description="Codes are hashed at rest and expire automatically.">
      <form wire:submit.prevent="generate" class="space-y-3">
        @if ($errors->any())
          <div class="rounded-lg border border-danger/30 bg-danger/10 p-3 text-sm text-danger">
            <p class="font-semibold">Activation code could not be generated.</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
              @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
              @endforeach
            </ul>
          </div>
        @endif

        <div>
          <label class="text-sm font-medium" for="device-branch">Branch</label>
          <select id="device-branch" wire:model="branchId" class="ml-field mt-1 w-full">
            @foreach ($branches as $branch)
              <option value="{{ $branch->id }}">{{ $branch->name }} - {{ ucfirst($branch->status->value) }}</option>
            @endforeach
          </select>
          @error('branchId') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
        </div>

        <div>
          <label class="text-sm font-medium" for="device-label">Device label</label>
          <input id="device-label" wire:model="deviceLabel" class="ml-field mt-1 w-full" placeholder="Main Business Server">
          @error('deviceLabel') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
          <div>
            <label class="text-sm font-medium" for="device-type">Device type</label>
            <select id="device-type" wire:model="deviceType" class="ml-field mt-1 w-full">
              <option value="shop_app">Business Server</option>
              <option value="client_app">Business App</option>
            </select>
          </div>
          <div>
            <label class="text-sm font-medium" for="expires-in">Expires</label>
            <select id="expires-in" wire:model="expiresInMinutes" class="ml-field mt-1 w-full">
              <option value="15">15 minutes</option>
              <option value="30">30 minutes</option>
              <option value="60">1 hour</option>
              <option value="1440">24 hours</option>
            </select>
          </div>
        </div>

        <div>
          <label class="text-sm font-medium" for="application-version">Allowed application version</label>
          <select id="application-version" wire:model="applicationVersionId" class="ml-field mt-1 w-full">
            <option value="">Any published version</option>
            @foreach ($versions as $version)
              <option value="{{ $version->id }}">{{ $version->version }}</option>
            @endforeach
          </select>
          @error('applicationVersionId') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
        </div>

        @if ($generatedCode)
          <div wire:key="generated-activation-code" class="rounded-lg border border-warning/40 bg-warning/10 p-4">
            <p class="text-xs font-bold uppercase text-warning">One-time activation code</p>
            <p class="mt-2 select-all rounded-md border border-warning/20 bg-white px-3 py-2 font-mono text-2xl font-bold tracking-wide text-primary">
              {{ $generatedCode }}
            </p>
            <dl class="mt-3 grid gap-2 text-sm text-muted sm:grid-cols-2">
              <div>
                <dt class="font-medium text-foreground">Device</dt>
                <dd>{{ $generatedDeviceLabel }}</dd>
              </div>
              <div>
                <dt class="font-medium text-foreground">Branch</dt>
                <dd>{{ $generatedBranchName }}</dd>
              </div>
              <div class="sm:col-span-2">
                <dt class="font-medium text-foreground">Expires</dt>
                <dd>{{ $generatedExpiresAt }}</dd>
              </div>
            </dl>
            <p class="mt-3 text-sm text-muted">This code is shown once. Device credentials are only returned to the Business Server during activation.</p>
          </div>
        @endif

        <button type="submit" class="rounded-md bg-primary px-3 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-60" wire:loading.attr="disabled" wire:target="generate">
          <span wire:loading.remove wire:target="generate">Generate code</span>
          <span wire:loading wire:target="generate">Generating...</span>
        </button>
      </form>
    </x-app.card>

    <x-app.card title="Devices and activations" description="Credentials are stored as hashes and can be revoked.">
      @if ($devices->isEmpty())
        <x-app.empty-state title="No device activations yet" message="Generate an activation code for a Business Server device." />
      @else
        <div class="overflow-x-auto">
          <table class="ml-table">
            <thead>
              <tr>
                <th>Device</th>
                <th>Status</th>
                <th>Last contact</th>
                <th>Last sync</th>
                <th>Version / OS</th>
                <th>Errors</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($devices as $device)
                @php($activation = $device->activations->first())
                <tr>
                  <td>
                    <p class="font-medium">{{ $device->name }}</p>
                    <p class="font-mono text-xs text-muted">{{ $device->uuid }}</p>
                  </td>
                  <td><x-app.status-badge>{{ ucfirst($device->status->value) }}</x-app.status-badge></td>
                  <td class="text-sm text-muted">{{ $device->last_seen_at?->diffForHumans() ?? 'No contact' }}</td>
                  <td class="text-sm text-muted">{{ $device->last_synchronized_at?->diffForHumans() ?? 'No sync' }}</td>
                  <td class="text-sm">
                    <p>{{ $device->app_version ?: 'Pending' }}</p>
                    <p class="text-xs text-muted">{{ data_get($device->operating_system, 'name', 'OS unknown') }}</p>
                  </td>
                  <td class="text-sm text-muted">{{ count($device->synchronization_errors ?? []) }}</td>
                  <td>
                    <div class="flex flex-wrap gap-2">
                      <a href="{{ route('platform.support', ['category' => 'device', 'device_id' => $device->id, 'branch_id' => $device->branch_id]) }}" class="rounded-md border border-border px-2 py-1 text-xs font-medium text-foreground hover:border-primary/50">Support</a>
                      @if ($activation && ! $activation->used_at && ! $activation->revoked_at)
                        <button type="button" wire:click="revokeActivation({{ $activation->id }})" class="rounded-md border border-warning/40 px-2 py-1 text-xs font-medium text-warning">
                          Revoke code
                        </button>
                      @endif
                      @if ($device->status->value !== 'revoked')
                        <button type="button" wire:click="revokeDevice({{ $device->id }})" class="rounded-md border border-danger/40 px-2 py-1 text-xs font-medium text-danger">
                          Revoke device
                        </button>
                      @endif
                    </div>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </x-app.card>
  </section>
</div>

