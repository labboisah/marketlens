@props([
    'items' => [],
])

<nav {{ $attributes->merge(['class' => 'text-sm']) }} aria-label="Breadcrumb">
  <ol class="flex flex-wrap items-center gap-2 text-muted">
    <li>
      <a href="{{ route('welcome') }}" class="rounded-md hover:text-foreground">Home</a>
    </li>
    @foreach ($items as $item)
      <li aria-hidden="true">/</li>
      <li>
        @if ($loop->last)
          <span class="font-medium text-foreground" aria-current="page">{{ $item['label'] }}</span>
        @else
          <a href="{{ $item['href'] }}" class="rounded-md hover:text-foreground">{{ $item['label'] }}</a>
        @endif
      </li>
    @endforeach
  </ol>
</nav>
