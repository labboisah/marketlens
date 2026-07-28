@props([
    'eyebrow' => null,
    'title',
    'description' => null,
])

<div {{ $attributes->merge(['class' => 'max-w-3xl']) }}>
  @if ($eyebrow)
    <x-public.status-badge tone="teal">{{ $eyebrow }}</x-public.status-badge>
  @endif

  <h2 class="mt-4 text-2xl font-bold tracking-normal text-foreground sm:text-3xl">
    {{ $title }}
  </h2>

  @if ($description)
    <p class="mt-3 text-base leading-7 text-muted">
      {{ $description }}
    </p>
  @endif
</div>
