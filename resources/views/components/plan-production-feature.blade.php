@props(['plan'])
@php
  $productionIncluded = $plan->features->contains(fn ($feature) =>
      strtolower((string) $feature->key) === 'module:production'
      || str_contains(strtolower((string) $feature->name), 'production'));
@endphp
<div class="mt-3 rounded-md border border-border p-3 text-sm" data-production-plan="{{ $plan->id }}" data-production-included="{{ $productionIncluded ? 'true' : 'false' }}">
  <p class="font-semibold">Production module: {{ $productionIncluded ? 'Included' : 'Not included' }}</p>
  @if ($productionIncluded)
    <p class="mt-2 text-muted">Production materials and stores, recipes and batches, machines and staff, finished-goods sales, production expenses, and owner-configured machine and staff profit shares.</p>
  @endif
</div>
