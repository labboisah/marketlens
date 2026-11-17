@props(['label', 'icon', 'open' => false])

<details class="group/sidebar" @if ($open) open @endif>
  <summary class="flex cursor-pointer list-none items-center gap-3 rounded-md px-3 py-2 text-sm font-semibold text-foreground hover:bg-surface-muted focus:outline-none focus:ring-2 focus:ring-primary [&::-webkit-details-marker]:hidden">
    <x-app.icon :name="$icon" class="h-5 w-5 shrink-0 text-muted" />
    <span class="flex-1 uppercase tracking-wide">{{ $label }}</span>
    <x-app.icon name="chevron-down" class="h-4 w-4 shrink-0 text-muted transition-transform group-open/sidebar:rotate-180" />
  </summary>
  <div class="ml-5 mt-1 flex flex-col gap-1 border-l border-border pl-2">
    {{ $slot }}
  </div>
</details>
