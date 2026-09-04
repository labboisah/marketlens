@props([
    'name' => 'circle',
    'class' => 'h-5 w-5',
])

@php
  $paths = [
      'badge-dollar-sign' => '<path d="M3.85 8.62a4 4 0 0 1 4.78-4.77 4 4 0 0 1 6.74 0 4 4 0 0 1 4.78 4.78 4 4 0 0 1 0 6.74 4 4 0 0 1-4.78 4.78 4 4 0 0 1-6.74 0 4 4 0 0 1-4.78-4.78 4 4 0 0 1 0-6.76Z" /><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8" /><path d="M12 18V6" />',
      'bar-chart-3' => '<path d="M3 3v18h18" /><path d="M18 17V9" /><path d="M13 17V5" /><path d="M8 17v-3" />',
      'bell-ring' => '<path d="M10.3 21a2 2 0 0 0 3.4 0" /><path d="M4 8a8 8 0 0 1 16 0c0 7 3 9 3 9H1s3-2 3-9" /><path d="M22 4 18 2" /><path d="M2 4l4-2" />',
      'building-2' => '<path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18" /><path d="M6 12H4a2 2 0 0 0-2 2v8" /><path d="M18 9h2a2 2 0 0 1 2 2v11" /><path d="M10 6h4" /><path d="M10 10h4" /><path d="M10 14h4" /><path d="M10 18h4" />',
      'check-circle' => '<path d="M9 12l2 2 4-4" /><circle cx="12" cy="12" r="10" />',
      'circle' => '<circle cx="12" cy="12" r="10" />',
      'credit-card' => '<rect width="20" height="14" x="2" y="5" rx="2" /><path d="M2 10h20" />',
      'info' => '<circle cx="12" cy="12" r="10" /><path d="M12 16v-4" /><path d="M12 8h.01" />',
      'layout-dashboard' => '<rect width="7" height="9" x="3" y="3" rx="1" /><rect width="7" height="5" x="14" y="3" rx="1" /><rect width="7" height="9" x="14" y="12" rx="1" /><rect width="7" height="5" x="3" y="16" rx="1" />',
      'life-buoy' => '<circle cx="12" cy="12" r="10" /><path d="m4.93 4.93 4.24 4.24" /><path d="m14.83 9.17 4.24-4.24" /><path d="m14.83 14.83 4.24 4.24" /><path d="m9.17 14.83-4.24 4.24" /><circle cx="12" cy="12" r="4" />',
      'menu' => '<path d="M4 6h16" /><path d="M4 12h16" /><path d="M4 18h16" />',
      'monitor' => '<rect width="20" height="14" x="2" y="3" rx="2" /><path d="M8 21h8" /><path d="M12 17v4" />',
      'monitor-smartphone' => '<path d="M18 8V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h8" /><path d="M10 19v-3.96" /><path d="M7 19h5" /><rect width="6" height="10" x="16" y="12" rx="2" />',
      'package-check' => '<path d="m16 16 2 2 4-4" /><path d="M21 10V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l2-1.14" /><path d="m7.5 4.27 9 5.15" /><path d="M3.29 7 12 12l8.71-5" /><path d="M12 22V12" />',
      'receipt' => '<path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2Z" /><path d="M8 7h8" /><path d="M8 12h8" /><path d="M8 17h5" />',
      'refresh-cw' => '<path d="M3 12a9 9 0 0 1 15-6.7L21 8" /><path d="M21 3v5h-5" /><path d="M21 12a9 9 0 0 1-15 6.7L3 16" /><path d="M3 21v-5h5" />',
      'settings' => '<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.38a2 2 0 0 0-.73-2.73l-.15-.09a2 2 0 0 1-1-1.74v-.51a2 2 0 0 1 1-1.72l.15-.1a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2Z" /><circle cx="12" cy="12" r="3" />',
      'truck' => '<path d="M10 17h4V5H2v12h3" /><path d="M14 17h1" /><path d="M19 17h3v-6h-3l-3-4h-2" /><circle cx="7.5" cy="17.5" r="2.5" /><circle cx="17.5" cy="17.5" r="2.5" />',
      'user-check' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="m16 11 2 2 4-4" />',
      'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M22 21v-2a4 4 0 0 0-3-3.87" /><path d="M16 3.13a4 4 0 0 1 0 7.75" />',
      'wallet-cards' => '<rect width="18" height="18" x="3" y="3" rx="2" /><path d="M3 9h18" /><path d="M8 15h.01" /><path d="M12 15h4" />',
      'alert-triangle' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.46 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z" /><path d="M12 9v4" /><path d="M12 17h.01" />',
      'x' => '<path d="M18 6 6 18" /><path d="m6 6 12 12" />',
      'x-circle' => '<circle cx="12" cy="12" r="10" /><path d="m15 9-6 6" /><path d="m9 9 6 6" />',
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
