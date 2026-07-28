@props([
    'icon' => 'circle',
    'title',
    'description',
    'tone' => 'information',
])

<article {{ $attributes->merge(['class' => 'rounded-lg border border-border bg-surface p-5 shadow-sm']) }}>
  <div class="mb-4 inline-flex h-10 w-10 items-center justify-center rounded-lg bg-surface-muted text-accent">
    <x-public.icon :name="$icon" class="h-5 w-5" />
  </div>
  <h3 class="text-base font-semibold text-foreground">{{ $title }}</h3>
  <p class="mt-2 text-sm leading-6 text-muted">{{ $description }}</p>
  <div class="mt-4">
    <x-public.status-badge :tone="$tone">{{ $slot }}</x-public.status-badge>
  </div>
</article>
