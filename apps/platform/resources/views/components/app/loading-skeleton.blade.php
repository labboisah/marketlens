@props(['lines' => 3])

<div {{ $attributes->merge(['class' => 'animate-pulse rounded-lg border border-border bg-surface p-4']) }} aria-busy="true">
  <div class="h-4 w-1/3 rounded bg-surface-muted"></div>
  <div class="mt-4 space-y-3">
    @for ($i = 0; $i < $lines; $i++)
      <div class="h-3 rounded bg-surface-muted" style="width: {{ 90 - ($i * 12) }}%"></div>
    @endfor
  </div>
</div>
