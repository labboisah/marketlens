@props(['feature', 'plan' => null])

<section {{ $attributes->merge(['class' => 'rounded-lg border border-warning/30 bg-warning/5 p-5']) }}>
  <x-app.status-badge tone="warning">Plan restriction</x-app.status-badge>
  <h2 class="mt-3 text-base font-semibold text-foreground">{{ $feature }} is not included in the current plan.</h2>
  <p class="mt-1 text-sm text-muted">
    Current plan: {{ $plan?->name ?? 'No active plan' }}. Upgrade when this module is ready for implementation.
  </p>
</section>
