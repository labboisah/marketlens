@props([
    'quote',
    'name',
    'role',
])

<figure {{ $attributes->merge(['class' => 'rounded-lg border border-border bg-surface p-5 shadow-sm']) }}>
  <blockquote class="text-sm leading-6 text-foreground">
    "{{ $quote }}"
  </blockquote>
  <figcaption class="mt-5 border-t border-border pt-4">
    <p class="text-sm font-semibold text-foreground">{{ $name }}</p>
    <p class="text-xs text-muted">{{ $role }}</p>
  </figcaption>
</figure>
