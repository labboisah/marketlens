@props(['density' => 'comfortable'])

@php
  $densityClass = [
    'compact' => '[&_td]:py-2',
    'comfortable' => '[&_td]:py-3',
    'spacious' => '[&_td]:py-4',
  ][$density] ?? '[&_td]:py-3';
@endphp

<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-lg border border-border bg-surface']) }}>
  <div class="overflow-x-auto">
    <table class="ml-table {{ $densityClass }}">
      {{ $slot }}
    </table>
  </div>
</div>
