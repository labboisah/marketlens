@props(['tone' => 'information', 'title', 'message' => null])

@php
  $classes = [
    'success' => 'border-success/30 text-success',
    'warning' => 'border-warning/30 text-warning',
    'danger' => 'border-danger/30 text-danger',
    'information' => 'border-information/30 text-information',
  ][$tone] ?? 'border-information/30 text-information';
@endphp

<aside {{ $attributes->merge(['class' => 'rounded-lg border bg-surface p-4 shadow-md ' . $classes]) }} role="status">
  <p class="text-sm font-semibold">{{ $title }}</p>
  @if ($message)
    <p class="mt-1 text-sm text-muted">{{ $message }}</p>
  @endif
</aside>
