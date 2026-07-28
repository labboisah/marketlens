@props(['lastSyncedAt' => 'Awaiting first sync'])

<section {{ $attributes->merge(['class' => 'rounded-lg border border-warning/30 bg-warning/5 p-4 text-sm']) }}>
  <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
    <p class="font-semibold text-foreground">Synchronized data may be stale</p>
    <p class="text-muted">Last successful shop sync: {{ $lastSyncedAt }}</p>
  </div>
</section>
