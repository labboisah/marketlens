@props(['title', 'message' => null])

<section {{ $attributes->merge(['class' => 'grid place-items-center rounded-lg border border-dashed border-border bg-surface px-6 py-12 text-center']) }}>
  <div class="max-w-sm">
    <h2 class="text-base font-semibold text-foreground">{{ $title }}</h2>
    @if ($message)
      <p class="mt-2 text-sm text-muted">{{ $message }}</p>
    @endif
    @isset($action)
      <div class="mt-4">{{ $action }}</div>
    @endisset
  </div>
</section>
