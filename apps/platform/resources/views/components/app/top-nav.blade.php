<header class="ml-top-nav sticky top-0 z-30">
  <div class="flex h-16 items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
    <div class="flex min-w-0 items-center gap-3">
      <button type="button" class="rounded-md p-2 text-muted hover:bg-surface-muted hover:text-foreground lg:hidden" aria-label="Open sidebar">
        <span aria-hidden="true">Menu</span>
      </button>
      <div class="min-w-0">
        <p class="truncate text-sm font-semibold text-foreground">Cloud Platform</p>
        <p class="truncate text-xs text-muted">Synchronized business visibility</p>
      </div>
    </div>

    <div class="flex items-center gap-3">
      <x-app.status-badge tone="success">Online</x-app.status-badge>
      <button type="button" class="rounded-md border border-border px-3 py-2 text-sm font-medium" x-on:click="toggleTheme">
        Theme
      </button>
    </div>
  </div>
</header>
