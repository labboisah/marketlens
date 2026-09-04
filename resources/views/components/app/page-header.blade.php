@props(['title', 'description' => null])

<header class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
  <div class="min-w-0">
    <h1 class="text-2xl font-bold text-foreground">{{ $title }}</h1>
    @if ($description)
      <p class="mt-1 max-w-3xl text-sm text-muted">{{ $description }}</p>
    @endif
  </div>

  @isset($actions)
    <div class="flex shrink-0 items-center gap-2">
      {{ $actions }}
    </div>
  @endisset
</header>
