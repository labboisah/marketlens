@props([
    'name' => 'circle',
    'class' => 'h-5 w-5',
])

@php
  $paths = [
      'activity' => '<path d="M22 12h-4l-3 8L9 4l-3 8H2" />',
      'bar-chart-3' => '<path d="M3 3v18h18" /><path d="M18 17V9" /><path d="M13 17V5" /><path d="M8 17v-3" />',
      'building-2' => '<path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18" /><path d="M6 12H4a2 2 0 0 0-2 2v8" /><path d="M18 9h2a2 2 0 0 1 2 2v11" /><path d="M10 6h4" /><path d="M10 10h4" /><path d="M10 14h4" /><path d="M10 18h4" />',
      'check' => '<path d="m20 6-11 11-5-5" />',
      'chevron-down' => '<path d="m6 9 6 6 6-6" />',
      'circle' => '<circle cx="12" cy="12" r="10" />',
      'cloud' => '<path d="M17.5 19H7a5 5 0 1 1 1.4-9.8A7 7 0 0 1 21 13.5 4.5 4.5 0 0 1 17.5 19Z" />',
      'credit-card' => '<rect width="20" height="14" x="2" y="5" rx="2" /><path d="M2 10h20" />',
      'database' => '<ellipse cx="12" cy="5" rx="9" ry="3" /><path d="M3 5v14c0 1.7 4 3 9 3s9-1.3 9-3V5" /><path d="M3 12c0 1.7 4 3 9 3s9-1.3 9-3" />',
      'facebook' => '<path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3Z" />',
      'github' => '<path d="M15 22v-4a4.8 4.8 0 0 0-1-3.5c3 0 6-2 6-5.5.1-1.3-.3-2.6-1-3.7.3-1.2.3-2.5 0-3.3 0 0-1 0-3 1.5a10.5 10.5 0 0 0-6 0C8 2 7 2 7 2c-.3.8-.3 2.1 0 3.3A5.4 5.4 0 0 0 6 9c0 3.5 3 5.5 6 5.5-.4.5-.8 1.4-.8 2.5v5" /><path d="M9 18c-4.5 2-5-2-7-2" />',
      'landmark' => '<path d="M3 22h18" /><path d="M6 18v-7" /><path d="M10 18v-7" /><path d="M14 18v-7" /><path d="M18 18v-7" /><path d="M12 2 20 7H4Z" />',
      'linkedin' => '<path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-4 0v7h-4v-7a6 6 0 0 1 6-6Z" /><rect width="4" height="12" x="2" y="9" /><circle cx="4" cy="4" r="2" />',
      'lock' => '<rect width="18" height="11" x="3" y="11" rx="2" /><path d="M7 11V7a5 5 0 0 1 10 0v4" />',
      'menu' => '<path d="M4 12h16" /><path d="M4 6h16" /><path d="M4 18h16" />',
      'receipt' => '<path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2Z" /><path d="M8 7h8" /><path d="M8 12h8" /><path d="M8 17h5" />',
      'package-check' => '<path d="m16 16 2 2 4-4" /><path d="M21 10V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l2-1.14" /><path d="m7.5 4.27 9 5.15" /><path d="M3.29 7 12 12l8.71-5" /><path d="M12 22V12" />',
      'refresh-cw' => '<path d="M3 12a9 9 0 0 1 15-6.7L21 8" /><path d="M21 3v5h-5" /><path d="M21 12a9 9 0 0 1-15 6.7L3 16" /><path d="M3 21v-5h5" />',
      'shield-check' => '<path d="M20 13c0 5-3.5 7.5-7.7 8.9a1 1 0 0 1-.6 0C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.2-2.7a1.2 1.2 0 0 1 1.6 0C14.5 3.8 17 5 19 5a1 1 0 0 1 1 1Z" /><path d="m9 12 2 2 4-4" />',
      'smartphone' => '<rect width="14" height="20" x="5" y="2" rx="2" /><path d="M12 18h.01" />',
      'store' => '<path d="m2 7 4.4-4.4A2 2 0 0 1 7.8 2h8.4a2 2 0 0 1 1.4.6L22 7" /><path d="M4 7v13a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7" /><path d="M2 7h20" /><path d="M9 22v-8h6v8" />',
      'twitter' => '<path d="M22 4.01c-.8.37-1.6.62-2.5.73.9-.53 1.6-1.38 1.9-2.39-.85.5-1.78.86-2.78 1.06A4.38 4.38 0 0 0 11.1 7.4c0 .34.04.67.11.99A12.43 12.43 0 0 1 2.2 3.82a4.38 4.38 0 0 0 1.36 5.84c-.72-.02-1.39-.22-1.98-.55v.06a4.38 4.38 0 0 0 3.51 4.29c-.37.1-.75.15-1.15.15-.28 0-.56-.03-.83-.08a4.39 4.39 0 0 0 4.09 3.04A8.78 8.78 0 0 1 2 18.33 12.38 12.38 0 0 0 8.7 20.3c8.04 0 12.44-6.66 12.44-12.44v-.57A8.9 8.9 0 0 0 22 4.01Z" />',
      'wifi' => '<path d="M5 13a10 10 0 0 1 14 0" /><path d="M8.5 16.5a5 5 0 0 1 7 0" /><path d="M12 20h.01" />',
      'x' => '<path d="M18 6 6 18" /><path d="m6 6 12 12" />',
  ];
@endphp

<svg
  {{ $attributes->merge(['class' => $class]) }}
  xmlns="http://www.w3.org/2000/svg"
  width="24"
  height="24"
  viewBox="0 0 24 24"
  fill="none"
  stroke="currentColor"
  stroke-width="2"
  stroke-linecap="round"
  stroke-linejoin="round"
  aria-hidden="true"
>
  {!! $paths[$name] ?? $paths['circle'] !!}
</svg>
