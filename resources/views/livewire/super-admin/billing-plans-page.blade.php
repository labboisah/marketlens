<div class="flex flex-col gap-6">
  <section class="rounded-lg border border-border bg-surface p-5">
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <p class="text-sm font-semibold uppercase text-muted">Billing administration</p>
        <h1 class="mt-2 text-2xl font-bold">Subscription plans</h1>
        <p class="mt-2 max-w-3xl text-sm text-muted">Manage subscription plans and toggle plan availability for business accounts.</p>
      </div>
      <span class="rounded-md border border-border px-3 py-2 text-sm font-semibold text-muted">Showing {{ $plans->count() }} plans</span>
    </div>
  </section>

  <section class="rounded-lg border border-border bg-surface p-4 overflow-x-auto">
    <table class="ml-table w-full">
      <thead>
        <tr>
          <th>Name</th>
          <th>Billing interval</th>
          <th>Price</th>
          <th>Status</th>
          <th>Features</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($plans as $plan)
          <tr>
            <td>
              <p class="font-medium">{{ $plan->name }}</p>
              <p class="text-xs text-muted">{{ $plan->slug }}</p>
            </td>
            <td class="font-medium">{{ str($plan->billing_interval)->title() }}</td>
            <td class="font-medium">{{ $plan->display_price }}</td>
            <td class="font-medium">{{ $plan->is_active ? 'Active' : 'Inactive' }}</td>
            <td class="text-xs text-muted">{{ $plan->features_count }} features</td>
            <td class="flex flex-wrap gap-2">
              <button
                type="button"
                wire:click.prevent="startEditingPlan({{ $plan->id }})"
                class="rounded-md border border-border px-3 py-2 text-sm font-semibold text-foreground"
              >
                Edit
              </button>
              <button
                type="button"
                wire:click.prevent="togglePlanActive({{ $plan->id }})"
                class="rounded-md px-3 py-2 text-sm font-semibold {{ $plan->is_active ? 'border border-danger text-danger' : 'bg-primary text-white' }}"
              >
                {{ $plan->is_active ? 'Disable' : 'Enable' }}
              </button>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="6" class="text-sm text-muted">No plans found.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </section>

  <section class="rounded-lg border border-border bg-surface p-4">
    <h2 class="font-semibold">{{ $editingPlanId ? 'Edit plan' : 'Create a new plan' }}</h2>
    <div class="grid gap-4 md:grid-cols-2">
      <div>
        <label class="ml-label">Name</label>
        <input type="text" wire:model.defer="name" class="ml-field w-full" />
      </div>
      <div>
        <label class="ml-label">Slug</label>
        <input type="text" wire:model.defer="slug" class="ml-field w-full" />
      </div>
      <div class="md:col-span-2">
        <label class="ml-label">Description</label>
        <textarea wire:model.defer="description" class="ml-field w-full" rows="3"></textarea>
      </div>
      <div>
        <label class="ml-label">Billing interval</label>
        <select wire:model.defer="billingInterval" class="ml-field w-full">
          <option value="monthly">Monthly</option>
          <option value="annual">Annual</option>
        </select>
      </div>
      <div>
        <label class="ml-label">Price (minor units)</label>
        <input type="number" wire:model.defer="priceMinor" class="ml-field w-full" />
      </div>
      <div>
        <label class="ml-label">Currency</label>
        <input type="text" wire:model.defer="currency" class="ml-field w-full" />
      </div>
      <div>
        <label class="ml-label">Trial days</label>
        <input type="number" wire:model.defer="trialDays" class="ml-field w-full" />
      </div>
      <div class="flex items-center gap-2 md:col-span-2">
        <input type="checkbox" wire:model.defer="isActive" id="plan-active" class="ml-checkbox" />
        <label for="plan-active" class="text-sm font-medium text-foreground">Create plan as active</label>
      </div>
    </div>

    <div class="mt-6 rounded-lg border border-border bg-background p-4">
      <label class="inline-flex items-center gap-2 font-semibold">
        <input type="checkbox" wire:model="productionEnabled" class="ml-checkbox" />
        Production and Profit Sharing
      </label>
      <p class="mt-2 text-sm text-muted">Include materials, recipes, machines, staff, production batches, finished stock, sales, expenses and owner-configured profit shares. Businesses enable Production in their settings; local roles control access.</p>
      @error('productionEnabled') <p class="text-sm text-danger">{{ $message }}</p> @enderror
    </div>

    <div class="mt-6 rounded-lg border border-border bg-background p-4">
      <div class="flex items-center justify-between gap-4">
        <div>
          <h3 class="text-base font-semibold">Plan features</h3>
          <p class="text-sm text-muted">Add or edit feature rows for this plan.</p>
        </div>
        <button type="button" wire:click="addFeatureRow" class="rounded-md border border-border px-3 py-2 text-sm font-semibold text-foreground">Add feature</button>
      </div>

      <div class="mt-4 space-y-4">
        @forelse ($editingFeatures as $index => $feature)
          <div class="grid gap-3 rounded-lg border border-border bg-surface p-4 md:grid-cols-4">
            <div class="md:col-span-2">
              <label class="ml-label">Feature name</label>
              <input type="text" wire:model.defer="editingFeatures.{{ $index }}.name" class="ml-field w-full" />
            </div>
            <div>
              <label class="ml-label">Tooltip</label>
              <input type="text" wire:model.defer="editingFeatures.{{ $index }}.tooltip" class="ml-field w-full" />
            </div>
            <div class="flex items-end justify-between gap-3">
              <label class="inline-flex items-center gap-2 text-sm font-medium text-foreground">
                <input type="checkbox" wire:model.defer="editingFeatures.{{ $index }}.is_highlighted" class="ml-checkbox" />
                Highlight
              </label>
              <button type="button" wire:click.prevent="removeFeatureRow({{ $index }})" class="text-sm font-semibold text-danger">Remove</button>
            </div>
          </div>
        @empty
          <p class="text-sm text-muted">No features added yet.</p>
        @endforelse
      </div>
    </div>

    <div class="mt-4 flex flex-wrap gap-3">
      @if ($editingPlanId)
        <button type="button" wire:click="saveEditedPlan" class="rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white">Save changes</button>
        <button type="button" wire:click="cancelEditing" class="rounded-md border border-border px-4 py-2 text-sm font-semibold text-foreground">Cancel</button>
      @else
        <button type="button" wire:click="createPlan" class="rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white">Create plan</button>
      @endif
    </div>
  </section>
</div>
