@props(['title' => null, 'description' => null])

<section {{ $attributes->merge(['class' => 'ml-card']) }}>
  @if ($title || $description || isset($actions))
    <div class="flex items-start justify-between gap-4 border-b border-border px-4 py-3">
      <div>
        @if ($title)
          <h2 class="text-base font-semibold text-foreground">{{ $title }}</h2>
        @endif
        @if ($description)
          <p class="mt-1 text-sm text-muted">{{ $description }}</p>
        @endif
      </div>

      @isset($actions)
        <div class="flex items-center gap-2">{{ $actions }}</div>
      @endisset
    </div>
  @endif

  <div class="p-4">
    {{ $slot }}
  </div>
</section>
