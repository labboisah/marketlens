@props([
    'label' => 'Loading',
    'size' => 'md',
])

@php
  $sizes = [
      'sm' => 'h-4 w-4',
      'md' => 'h-6 w-6',
      'lg' => 'h-10 w-10',
  ];
@endphp

<span {{ $attributes->merge(['class' => 'ml-spinner inline-flex items-center justify-center ' . ($sizes[$size] ?? $sizes['md'])]) }} role="status" aria-label="{{ $label }}">
  <span class="sr-only">{{ $label }}</span>
</span>
