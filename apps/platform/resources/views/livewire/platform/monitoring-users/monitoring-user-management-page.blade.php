<div class="flex flex-col gap-6">
  <x-app.page-header
    title="Monitoring users"
    description="Invite Platform users for remote visibility without creating Shop App operational users."
  />

  @if ($errors->any())
    <div class="rounded-md border border-danger/30 bg-danger/5 p-4 text-sm text-danger">
      <p class="font-semibold">Please fix the monitoring-user access details.</p>
      <ul class="mt-2 list-disc space-y-1 pl-5">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <section class="grid gap-4 xl:grid-cols-[0.95fr_1.05fr]">
    <x-app.card title="Invite monitoring user" description="Examples include co-owner, director, remote manager, accountant, and auditor.">
      <form wire:submit="invite" class="space-y-4">
        <label class="block text-sm font-medium">Email
          <input wire:model="email" type="email" class="ml-field mt-1 w-full" placeholder="person@example.com">
        </label>

        <div class="grid gap-3 sm:grid-cols-2">
          <label class="block text-sm font-medium">Role
            <select wire:model="roleId" class="ml-field mt-1 w-full">
              @foreach ($roles as $role)
                <option value="{{ $role->id }}">{{ $role->name }}</option>
              @endforeach
            </select>
          </label>
          <label class="block text-sm font-medium">Invitation expiration
            <select wire:model="expiresInDays" class="ml-field mt-1 w-full">
              <option value="1">1 day</option>
              <option value="3">3 days</option>
              <option value="7">7 days</option>
              <option value="14">14 days</option>
              <option value="30">30 days</option>
            </select>
          </label>
        </div>

        <div>
          <p class="mb-2 text-sm font-semibold">Branch access</p>
          <div class="grid gap-2 sm:grid-cols-2">
            @foreach ($branches as $branch)
              <label class="flex items-center gap-2 rounded-md border border-border p-2 text-sm">
                <input wire:model="branchIds" type="checkbox" value="{{ $branch->id }}" class="rounded border-border text-primary">
                {{ $branch->name }}
              </label>
            @endforeach
          </div>
          <p class="mt-1 text-xs text-muted">Leave all unchecked to allow all branches in this business.</p>
        </div>

        <div>
          <p class="mb-2 text-sm font-semibold">Module access</p>
          <div class="grid gap-2 sm:grid-cols-2">
            @foreach ($permissions as $slug => $label)
              <label class="flex items-center gap-2 rounded-md border border-border p-2 text-sm">
                <input wire:model="modulePermissions.{{ $slug }}" type="checkbox" class="rounded border-border text-primary">
                {{ $label }}
              </label>
            @endforeach
          </div>
          <p class="mt-1 text-xs text-muted">Role permissions apply automatically; checked modules add explicit access.</p>
        </div>

        <button type="submit" class="rounded-md bg-primary px-3 py-2 text-sm font-semibold text-white">Send invitation</button>
      </form>
    </x-app.card>

    <x-app.card title="Monitoring roles" description="Default role templates for remote Platform access.">
      <x-app.table density="compact">
        <thead><tr><th>Role</th><th>Permissions</th></tr></thead>
        <tbody>
          @foreach ($roles as $role)
            <tr>
              <td class="font-medium">{{ $role->name }}</td>
              <td>{{ $role->permissions->pluck('name')->join(', ') }}</td>
            </tr>
          @endforeach
        </tbody>
      </x-app.table>
    </x-app.card>
  </section>

  <section class="grid gap-4 xl:grid-cols-2">
    <x-app.card title="Active monitoring users">
      <div class="space-y-3">
        @forelse ($assignments as $assignment)
          <div class="rounded-md border border-border p-3 text-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <p class="font-semibold">{{ $assignment->user?->name ?? $assignment->user?->email }}</p>
                <p class="text-muted">{{ $assignment->user?->email }} - {{ $assignment->role?->name }} - {{ ucfirst($assignment->status) }}</p>
                <p class="text-xs text-muted">Branches: {{ $assignment->branches->isEmpty() ? 'All branches' : $assignment->branches->pluck('name')->join(', ') }}</p>
              </div>
              <div class="flex gap-2">
                @if ($assignment->status === 'active')
                  <button type="button" wire:click="suspend({{ $assignment->id }})" class="rounded-md border border-warning/40 px-2 py-1 text-xs font-semibold text-warning">Suspend</button>
                @endif
                @if ($assignment->status !== 'revoked')
                  <button type="button" wire:click="revoke({{ $assignment->id }})" class="rounded-md border border-danger/40 px-2 py-1 text-xs font-semibold text-danger">Revoke</button>
                @endif
              </div>
            </div>
          </div>
        @empty
          <x-app.empty-state title="No monitoring users" message="Invited Platform users will appear here after access is granted." />
        @endforelse
      </div>
    </x-app.card>

    <x-app.card title="Invitation history">
      <div class="space-y-3">
        @forelse ($invitations as $invitation)
          <div class="rounded-md border border-border p-3 text-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <p class="font-semibold">{{ $invitation->email }}</p>
                <p class="text-muted">{{ $invitation->role?->name }} - {{ ucfirst($invitation->status) }} - Expires {{ $invitation->expires_at?->format('M j, Y H:i') }}</p>
                <p class="text-xs text-muted">Sent {{ $invitation->send_count }} time{{ $invitation->send_count === 1 ? '' : 's' }}</p>
              </div>
              @if ($invitation->status === 'pending')
                <div class="flex gap-2">
                  <button type="button" wire:click="resend({{ $invitation->id }})" class="rounded-md border border-primary/40 px-2 py-1 text-xs font-semibold text-primary">Resend</button>
                  <button type="button" wire:click="cancel({{ $invitation->id }})" class="rounded-md border border-danger/40 px-2 py-1 text-xs font-semibold text-danger">Cancel</button>
                </div>
              @endif
            </div>
          </div>
        @empty
          <x-app.empty-state title="No invitations" message="Pending and historical invitations will appear here." />
        @endforelse
      </div>
    </x-app.card>
  </section>
</div>
