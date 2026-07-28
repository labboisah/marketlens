@props([
    'question',
])

<div class="rounded-lg border border-border bg-surface" x-data="{ open: false }">
  <button
    type="button"
    class="flex w-full items-center justify-between gap-4 px-5 py-4 text-left text-sm font-semibold text-foreground"
    x-on:click="open = ! open"
    :aria-expanded="open.toString()"
  >
    <span>{{ $question }}</span>
    <x-public.icon name="chevron-down" class="h-4 w-4 shrink-0 text-muted" />
  </button>
  <div class="px-5 pb-5 text-sm leading-6 text-muted" x-cloak x-show="open">
    {{ $slot }}
  </div>
</div>
