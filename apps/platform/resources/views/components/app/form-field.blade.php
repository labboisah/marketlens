@props(['label', 'for' => null, 'help' => null, 'error' => null])

<div class="grid gap-1.5">
  <label @if($for) for="{{ $for }}" @endif class="text-sm font-medium text-foreground">
    {{ $label }}
  </label>

  {{ $slot }}

  @if ($error)
    <p class="text-xs font-medium text-danger">{{ $error }}</p>
  @elseif ($help)
    <p class="text-xs text-muted">{{ $help }}</p>
  @endif
</div>
