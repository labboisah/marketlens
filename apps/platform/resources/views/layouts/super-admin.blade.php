<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'MarketLens Super Admin' }}</title>
    @fluxAppearance
    @vite(['resources/css/app.css', 'resources/js/app.js'])
  </head>
  <body>
    @php($admin = auth()->user())

    <div class="ml-page-shell" x-data="marketlensShell">
      <flux:toast position="top end" />
      <x-app.loading-overlay />

      <div x-cloak x-show="sidebarOpen" class="fixed inset-0 z-40 bg-slate-950/40 lg:hidden" x-on:click="sidebarOpen = false"></div>

      <aside
        class="ml-sidebar fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col border-r border-border bg-surface transition-transform duration-200 lg:translate-x-0"
        x-bind:class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
        aria-label="Admin profile"
      >
        <div class="flex h-20 items-center justify-between border-b border-border px-5">
          <a href="{{ route('super-admin.dashboard') }}" class="flex min-w-0 items-center gap-3 text-primary">
            <img
              src="{{ asset('images/logo.png') }}"
              x-bind:src="theme === 'dark' ? '{{ asset('images/black.png') }}' : (theme === 'royal-blue' ? '{{ asset('images/blue.png') }}' : '{{ asset('images/logo.png') }}')"
              alt="MarketLens logo"
              class="h-11 w-11 rounded-md object-contain"
            >
            <span class="min-w-0">
              <span class="block truncate text-lg font-bold text-foreground">MarketLens</span>
              <span class="block truncate text-xs font-semibold uppercase text-muted">Admin account</span>
            </span>
          </a>
          <button type="button" class="rounded-md p-2 text-muted hover:bg-surface-muted lg:hidden" x-on:click="sidebarOpen = false" aria-label="Close admin panel">
            <x-app.icon name="x" class="h-5 w-5" />
          </button>
        </div>

        <div class="flex flex-1 flex-col gap-4 overflow-y-auto px-4 py-5">
          <section class="rounded-lg border border-border bg-background p-4">
            <p class="text-xs font-semibold uppercase text-muted">Signed-in admin</p>
            <p class="mt-2 truncate text-base font-bold text-foreground">{{ $admin?->name }}</p>
            <p class="mt-1 truncate text-sm text-muted">{{ $admin?->email }}</p>
          </section>

          <section class="rounded-lg border border-border bg-background p-4">
            <p class="text-xs font-semibold uppercase text-muted">Admin status</p>
            <div class="mt-3 grid gap-3 text-sm">
              <div class="flex items-center justify-between gap-3">
                <span class="text-muted">Role</span>
                <span class="font-semibold text-foreground">Super admin</span>
              </div>
              <div class="flex items-center justify-between gap-3">
                <span class="text-muted">Account</span>
                <span class="font-semibold text-success">{{ str($admin?->account_status?->value ?? $admin?->account_status)->replace('_', ' ')->title() }}</span>
              </div>
              <div class="flex items-center justify-between gap-3">
                <span class="text-muted">2FA foundation</span>
                <span class="font-semibold {{ $admin?->super_admin_confirmed_at ? 'text-success' : 'text-warning' }}">{{ $admin?->super_admin_confirmed_at ? 'Confirmed' : 'Pending' }}</span>
              </div>
            </div>
          </section>

          <section class="rounded-lg border border-border bg-background p-4">
            <p class="text-xs font-semibold uppercase text-muted">Responsibility</p>
            <p class="mt-2 text-sm leading-5 text-muted">Manage business-owner accounts and review their linked businesses without entering shop operations.</p>
          </section>
        </div>

        <div class="grid gap-3 border-t border-border p-4">
          <button type="button" class="flex w-full items-center justify-between rounded-md border border-border px-3 py-2 text-sm font-semibold hover:bg-surface-muted" x-on:click="toggleTheme" aria-label="Cycle theme">
            <span>Theme</span>
            <span class="text-muted" x-text="themeLabel()">Light</span>
          </button>
          <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="w-full rounded-md border border-border px-3 py-2 text-sm font-semibold text-foreground hover:bg-surface-muted">Logout</button>
          </form>
        </div>
      </aside>

      <div class="min-h-screen lg:pl-72">
        <header class="ml-top-nav sticky top-0 z-30 border-b border-border bg-background/95 backdrop-blur">
          <div class="mx-auto flex min-h-16 w-full max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
            <div class="flex min-w-0 items-center gap-3">
              <button type="button" class="rounded-md p-2 text-muted hover:bg-surface-muted hover:text-foreground focus:outline-none focus:ring-2 focus:ring-primary lg:hidden" x-on:click="sidebarOpen = true" aria-label="Open admin panel">
                <x-app.icon name="menu" class="h-5 w-5" />
              </button>
              <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-foreground">{{ $title ?? 'Business Owner Account Administration' }}</p>
                <p class="truncate text-xs text-muted">Super-admin account management</p>
              </div>
            </div>
          </div>
          <x-app.page-progress />
        </header>

        <main class="mx-auto flex w-full max-w-6xl flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
          {{ $slot }}
        </main>
      </div>
    </div>
    @fluxScripts
  </body>
</html>
