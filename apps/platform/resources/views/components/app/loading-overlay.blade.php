<div
  wire:loading.delay.flex
  class="pointer-events-none fixed inset-0 z-40 hidden items-start justify-center bg-background/35 px-4 pt-24 backdrop-blur-[2px]"
  aria-live="polite"
  aria-busy="true"
>
  <div class="flex items-center gap-3 rounded-lg border border-border bg-surface px-4 py-3 shadow-md">
    <x-app.spinner size="md" label="Loading Platform data" />
    <div>
      <p class="text-sm font-semibold text-foreground">Working</p>
      <p class="text-xs text-muted">Updating MarketLens data...</p>
    </div>
  </div>
</div>

<div
  x-data="{ ready: false }"
  x-init="$nextTick(() => { ready = true })"
  x-show="! ready"
  x-cloak
  class="fixed inset-0 z-50 flex items-center justify-center bg-background"
  aria-live="polite"
  aria-busy="true"
>
  <div class="flex flex-col items-center gap-3">
    <x-app.spinner size="lg" label="Loading MarketLens Platform" />
    <p class="text-sm font-semibold text-muted">Loading MarketLens</p>
  </div>
</div>
