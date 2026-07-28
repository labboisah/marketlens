@props([
    'name',
    'price',
    'description',
    'featured' => false,
])

<article {{ $attributes->merge(['class' => 'rounded-lg border p-6 shadow-sm '.($featured ? 'border-accent bg-accent/5' : 'border-border bg-surface')]) }}>
  <div class="flex items-start justify-between gap-4">
    <div>
      <h3 class="text-lg font-semibold text-foreground">{{ $name }}</h3>
      <p class="mt-2 text-sm leading-6 text-muted">{{ $description }}</p>
    </div>
    @if ($featured)
      <x-public.status-badge tone="success">Popular</x-public.status-badge>
    @endif
  </div>

  <p class="mt-6 text-3xl font-bold text-foreground">{{ $price }}</p>
  <div class="mt-6 text-sm leading-6 text-muted">
    {{ $slot }}
  </div>
</article>
