@props([
    'items' => [],
])

<div
  id="public-mobile-menu"
  class="border-t border-border bg-surface lg:hidden"
  x-cloak
  x-show="mobileOpen"
  x-on:keydown.escape.window="mobileOpen = false"
>
  <nav class="mx-auto flex max-w-7xl flex-col gap-1 px-4 py-4 sm:px-6" aria-label="Mobile navigation">
    @foreach ($items as $item)
      <a href="{{ $item['href'] }}" class="rounded-md px-3 py-3 text-base font-medium text-muted hover:bg-surface-muted hover:text-foreground" x-on:click="mobileOpen = false">
        {{ $item['label'] }}
      </a>
    @endforeach

    <div class="mt-3 grid gap-2 border-t border-border pt-4">
      <a href="/login" class="inline-flex h-11 items-center justify-center rounded-md border border-border bg-surface px-4 text-sm font-semibold text-foreground">
        Login
      </a>
      <a href="/register" class="inline-flex h-11 items-center justify-center rounded-md bg-accent px-4 text-sm font-semibold text-white">
        Get Started
      </a>
    </div>
  </nav>
</div>
