@props(['shell'])

@php
  $items = collect($shell['navigation'])->filter->allowed->groupBy('section');
@endphp

<div x-cloak x-show="sidebarOpen" class="fixed inset-0 z-40 bg-slate-950/40 lg:hidden" x-on:click="sidebarOpen = false"></div>

<aside
  class="ml-sidebar fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col border-r border-border bg-surface transition-transform duration-200 lg:translate-x-0"
  x-bind:class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
  aria-label="Platform navigation"
>
  <div class="flex h-16 items-center justify-between border-b border-border px-5">
    <a href="{{ route('platform.dashboard') }}" class="flex items-center gap-3 text-lg font-bold text-primary">
      <img src="{{ asset('images/logo.png') }}" alt="" class="h-9 w-9 rounded-md object-contain">
      <span>MarketLens</span>
    </a>
    <button type="button" class="rounded-md p-2 text-muted hover:bg-surface-muted lg:hidden" x-on:click="sidebarOpen = false" aria-label="Close navigation">
      <x-app.icon name="x" class="h-5 w-5" />
    </button>
  </div>

  <nav class="flex flex-1 flex-col gap-5 overflow-y-auto px-3 py-4" aria-label="Primary">
    @foreach ($items as $section => $sectionItems)
      <div>
        <p class="px-3 text-xs font-semibold uppercase tracking-wide text-muted">{{ $section }}</p>
        <div class="mt-2 flex flex-col gap-1">
          @foreach ($sectionItems as $item)
            <a
              class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium {{ request()->url() === $item->href ? 'bg-primary text-white' : 'text-muted hover:bg-surface-muted hover:text-foreground' }} focus:outline-none focus:ring-2 focus:ring-primary"
              href="{{ $item->href }}"
            >
              <x-app.icon :name="$item->icon" class="h-4 w-4 shrink-0" />
              <span class="truncate">{{ $item->label }}</span>
            </a>
          @endforeach
        </div>
      </div>
    @endforeach
  </nav>

  <div class="border-t border-border p-4">
    <x-app.sync-indicator state="delayed" last-synced-at="Awaiting first sync" />
  </div>
</aside>
