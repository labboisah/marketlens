@php
  $canCreateBranch = $activeBranchCount < $maxBranches;
  $statusTone = [
      'active' => 'success',
      'inactive' => 'neutral',
      'suspended' => 'warning',
      'archived' => 'danger',
  ];
@endphp

<div class="flex flex-col gap-6">
  <x-app.page-header
    title="Branches"
    description="Manage cloud-side branch records, synchronization state, device counts, and head-office designation."
  >
    <x-slot:actions>
      <x-app.status-badge tone="{{ $canCreateBranch ? 'success' : 'warning' }}">
        {{ $activeBranchCount }} / {{ $maxBranches }} branches
      </x-app.status-badge>
    </x-slot:actions>
  </x-app.page-header>

  @if (session('branch_status'))
    <x-app.status-badge tone="success">{{ session('branch_status') }}</x-app.status-badge>
  @endif

  @error('branch')
    <x-app.error-state title="Branch action blocked" :message="$message" />
  @enderror

  <section class="grid gap-4 xl:grid-cols-[1.4fr_1fr]">
    <x-app.card title="Branch listing" description="Codes are unique within {{ $business->name }}.">
      @if ($branches->isEmpty())
        <x-app.empty-state title="No branches yet" message="Create a first branch to complete cloud-side branch setup." />
      @else
        <div class="overflow-x-auto">
          <table class="ml-table">
            <thead>
              <tr>
                <th>Branch</th>
                <th>Status</th>
                <th>Devices</th>
                <th>Last sync</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($branches as $branch)
                <tr>
                  <td>
                    <button type="button" wire:click="selectBranch({{ $branch->id }})" class="text-left font-medium text-foreground hover:text-primary">
                      {{ $branch->name }}
                    </button>
                    <p class="text-xs text-muted">
                      {{ $branch->code ?: 'No code' }}
                      @if ($branch->is_head_office)
                        <span class="ml-2 font-semibold text-primary">Head office</span>
                      @endif
                    </p>
                  </td>
                  <td>
                    <x-app.status-badge :tone="$statusTone[$branch->status->value] ?? 'neutral'">
                      {{ ucfirst($branch->status->value) }}
                    </x-app.status-badge>
                  </td>
                  <td class="font-mono">{{ $branch->devices_count }}</td>
                  <td class="text-sm text-muted">
                    {{ $branch->last_synchronized_at?->diffForHumans() ?? 'Not synchronized' }}
                  </td>
                  <td>
                    <div class="flex flex-wrap gap-2">
                      <button type="button" wire:click="editBranch({{ $branch->id }})" class="rounded-md border border-border px-2 py-1 text-xs font-medium hover:bg-surface-muted">Edit</button>
                      @if (! $branch->is_head_office && $branch->status->value === 'active')
                        <button type="button" wire:click="designateHeadOffice({{ $branch->id }})" class="rounded-md border border-border px-2 py-1 text-xs font-medium hover:bg-surface-muted">Head office</button>
                      @endif
                      @if ($branch->status->value === 'active')
                        <button type="button" wire:click="suspendBranch({{ $branch->id }})" class="rounded-md border border-warning/40 px-2 py-1 text-xs font-medium text-warning hover:bg-warning/10">Suspend</button>
                      @elseif ($branch->status->value !== 'archived')
                        <button type="button" wire:click="activateBranch({{ $branch->id }})" class="rounded-md border border-success/40 px-2 py-1 text-xs font-medium text-success hover:bg-success/10">Activate</button>
                      @endif
                      @if ($branch->status->value !== 'archived')
                        <button type="button" wire:click="archiveBranch({{ $branch->id }})" class="rounded-md border border-danger/40 px-2 py-1 text-xs font-medium text-danger hover:bg-danger/10">Archive</button>
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

    <div class="flex flex-col gap-4">
      <x-app.card title="{{ $editingBranchId ? 'Edit branch' : 'Create branch' }}" description="Cloud branch records do not operate sales directly.">
        @unless ($canCreateBranch || $editingBranchId)
          <x-app.subscription-feature-restriction feature="Additional branches" :plan="$business->currentSubscription?->plan" />
        @else
          <form wire:submit="{{ $editingBranchId ? 'updateBranch' : 'createBranch' }}" class="space-y-3">
            <div>
              <label for="branch-name" class="text-sm font-medium">Name</label>
              <input id="branch-name" wire:model="name" class="ml-field mt-1 w-full" required>
              @error('name') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
              <div>
                <label for="branch-code" class="text-sm font-medium">Code</label>
                <input id="branch-code" wire:model="code" class="ml-field mt-1 w-full">
                @error('code') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
              </div>
              <div>
                <label for="branch-phone" class="text-sm font-medium">Phone</label>
                <input id="branch-phone" wire:model="phone" class="ml-field mt-1 w-full">
              </div>
            </div>
            <div>
              <label for="branch-email" class="text-sm font-medium">Email</label>
              <input id="branch-email" type="email" wire:model="email" class="ml-field mt-1 w-full">
              @error('email') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
            </div>
            <div>
              <label for="branch-address" class="text-sm font-medium">Address</label>
              <textarea id="branch-address" wire:model="address" class="mt-1 min-h-20 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm"></textarea>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
              <div>
                <label for="branch-state" class="text-sm font-medium">State</label>
                <input id="branch-state" wire:model="state" class="ml-field mt-1 w-full">
              </div>
              <div>
                <label for="branch-lga" class="text-sm font-medium">Local government</label>
                <input id="branch-lga" wire:model="localGovernmentArea" class="ml-field mt-1 w-full">
              </div>
            </div>
            <label class="flex items-center gap-2 text-sm">
              <input type="checkbox" wire:model="isHeadOffice" class="rounded border-border">
              Head office
            </label>
            <div class="flex gap-2">
              <button type="submit" class="rounded-md bg-primary px-3 py-2 text-sm font-semibold text-white">
                {{ $editingBranchId ? 'Save changes' : 'Create branch' }}
              </button>
              @if ($editingBranchId)
                <button type="button" wire:click="cancelEdit" class="rounded-md border border-border px-3 py-2 text-sm font-semibold">
                  Cancel
                </button>
              @endif
            </div>
          </form>
        @endunless
      </x-app.card>

      <x-app.card title="Branch details" description="Selected branch state">
        @if ($selectedBranch)
          <dl class="space-y-3 text-sm">
            <div class="flex justify-between gap-4">
              <dt class="text-muted">UUID</dt>
              <dd class="font-mono text-xs">{{ $selectedBranch->uuid }}</dd>
            </div>
            <div class="flex justify-between gap-4">
              <dt class="text-muted">Business ID</dt>
              <dd class="font-mono">{{ $selectedBranch->business_id }}</dd>
            </div>
            <div class="flex justify-between gap-4">
              <dt class="text-muted">State/LGA</dt>
              <dd>{{ $selectedBranch->state ?: 'Not set' }} / {{ $selectedBranch->local_government_area ?: 'Not set' }}</dd>
            </div>
            <div class="flex justify-between gap-4">
              <dt class="text-muted">Sync state</dt>
              <dd>{{ $selectedBranch->last_synchronized_at ? 'Synchronized' : 'Awaiting sync' }}</dd>
            </div>
            <div class="flex justify-between gap-4">
              <dt class="text-muted">Device activation</dt>
              <dd>{{ $selectedBranch->canActivateShopDevices() ? 'Allowed' : 'Blocked' }}</dd>
            </div>
            <div class="flex justify-between gap-4">
              <dt class="text-muted">Archived at</dt>
              <dd>{{ $selectedBranch->archived_at?->format('M j, Y H:i') ?? 'Not archived' }}</dd>
            </div>
          </dl>
        @else
          <x-app.empty-state title="No branch selected" message="Select a branch to view its cloud-side details." />
        @endif
      </x-app.card>
    </div>
  </section>
</div>
