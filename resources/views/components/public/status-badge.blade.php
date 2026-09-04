@props([
    'tone' => 'information',
])

@php
  $tones = [
      'primary' => 'border-primary/20 bg-primary/10 text-primary',
      'teal' => 'border-teal/20 bg-teal/10 text-teal',
      'success' => 'border-success/20 bg-success/10 text-success',
      'warning' => 'border-warning/25 bg-warning/10 text-warning',
      'danger' => 'border-danger/20 bg-danger/10 text-danger',
      'information' => 'border-information/20 bg-information/10 text-information',
  ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-xs font-semibold '.$tones[$tone]]) }}>
  {{ $slot }}
</span>
