@props(['items' => []])

<nav aria-label="Breadcrumb" class="text-sm text-muted">
  <ol class="flex flex-wrap items-center gap-2">
    @foreach ($items as $item)
      <li class="flex items-center gap-2">
        @if (! $loop->first)
          <span aria-hidden="true">/</span>
        @endif

        @if (! $loop->last && isset($item['href']))
          <a href="{{ $item['href'] }}" class="hover:text-primary focus:outline-none focus:ring-2 focus:ring-primary">{{ $item['label'] }}</a>
        @else
          <span aria-current="page" class="text-foreground">{{ $item['label'] }}</span>
        @endif
      </li>
    @endforeach
  </ol>
</nav>
