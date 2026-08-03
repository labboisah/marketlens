<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'MarketLens Admin' }}</title>
    @fluxAppearance
    @vite(['resources/css/app.css', 'resources/js/app.js'])
  </head>
  <body>
    <div class="ml-page-shell" x-data="marketlensShell">
      <flux:toast position="top end" />
      <x-app.loading-overlay />

      <div class="flex min-h-screen">
        <aside
          class="ml-sidebar sticky top-0 flex h-screen w-44 shrink-0 flex-col border-r border-border bg-surface sm:w-56"
          aria-label="Admin navigation"
        >
        <div class="flex h-16 items-center justify-between border-b border-border px-4">
          <a href="{{ route('super-admin.dashboard') }}" class="flex min-w-0 items-center gap-3 text-primary">
            <img
              src="{{ asset('images/logo.png') }}"
              x-bind:src="theme === 'dark' ? '{{ asset('images/black.png') }}' : (theme === 'royal-blue' ? '{{ asset('images/blue.png') }}' : '{{ asset('images/logo.png') }}')"
              alt="MarketLens logo"
              class="h-9 w-9 rounded-md object-contain"
            >
            <span class="min-w-0">
              <span class="block truncate text-base font-bold text-foreground">MarketLens</span>
              <span class="block truncate text-xs font-semibold uppercase text-muted">Admin</span>
            </span>
          </a>
        </div>

        <nav class="flex flex-1 flex-col gap-5 overflow-y-auto px-3 py-4" aria-label="Admin navigation">
          <div>
            <p class="px-3 text-xs font-semibold uppercase tracking-wide text-muted">Administration</p>
            <div class="mt-2 flex flex-col gap-1">
              <a
                href="{{ route('super-admin.dashboard') }}"
                class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('super-admin.dashboard') ? 'bg-primary text-white' : 'text-muted hover:bg-surface-muted hover:text-foreground' }} focus:outline-none focus:ring-2 focus:ring-primary"
              >
                <x-app.icon name="layout-dashboard" class="h-4 w-4 shrink-0" />
                <span class="truncate">Business owners</span>
              </a>
              <a
                href="{{ route('super-admin.application-versions') }}"
                class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('super-admin.application-versions') ? 'bg-primary text-white' : 'text-muted hover:bg-surface-muted hover:text-foreground' }} focus:outline-none focus:ring-2 focus:ring-primary"
              >
                <x-app.icon name="package-check" class="h-4 w-4 shrink-0" />
                <span class="truncate">Application versions</span>
              </a>
              <a
                href="{{ route('super-admin.plans') }}"
                class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('super-admin.plans') ? 'bg-primary text-white' : 'text-muted hover:bg-surface-muted hover:text-foreground' }} focus:outline-none focus:ring-2 focus:ring-primary"
              >
                <x-app.icon name="wallet-cards" class="h-4 w-4 shrink-0" />
                <span class="truncate">Plans</span>
              </a>
              <a
                href="{{ route('super-admin.subscription-requests') }}"
                class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('super-admin.subscription-requests') ? 'bg-primary text-white' : 'text-muted hover:bg-surface-muted hover:text-foreground' }} focus:outline-none focus:ring-2 focus:ring-primary"
              >
                <x-app.icon name="badge-dollar-sign" class="h-4 w-4 shrink-0" />
                <span class="truncate">Subscription requests</span>
              </a>
              <a
                href="{{ route('super-admin.invoices') }}"
                class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('super-admin.invoices') ? 'bg-primary text-white' : 'text-muted hover:bg-surface-muted hover:text-foreground' }} focus:outline-none focus:ring-2 focus:ring-primary"
              >
                <x-app.icon name="receipt" class="h-4 w-4 shrink-0" />
                <span class="truncate">Invoices</span>
              </a>
              <a
                href="{{ route('super-admin.system-health') }}"
                class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium {{ request()->routeIs('super-admin.system-health') ? 'bg-primary text-white' : 'text-muted hover:bg-surface-muted hover:text-foreground' }} focus:outline-none focus:ring-2 focus:ring-primary"
              >
                <x-app.icon name="monitor" class="h-4 w-4 shrink-0" />
                <span class="truncate">System health</span>
              </a>
            </div>
          </div>
        </nav>
        </aside>

        <div class="min-w-0 flex-1">
        <header class="ml-top-nav sticky top-0 z-30 border-b border-border bg-background/95 backdrop-blur">
          <div class="flex min-h-16 w-full items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
            <div class="flex min-w-0 items-center gap-3">
              <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-foreground">{{ $title ?? 'Business Owner Account Administration' }}</p>
                <p class="truncate text-xs text-muted">Account management</p>
              </div>
            </div>

            <div class="flex shrink-0 items-center gap-2">
              <button type="button" class="rounded-md border border-border px-3 py-2 text-sm font-semibold hover:bg-surface-muted" x-on:click="toggleTheme" aria-label="Cycle theme">
                <span class="hidden sm:inline">Theme: </span><span x-text="themeLabel()">Light</span>
              </button>
              <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="rounded-md border border-border px-3 py-2 text-sm font-semibold text-foreground hover:bg-surface-muted">Logout</button>
              </form>
            </div>
          </div>
          <x-app.page-progress />
        </header>

        <main class="flex w-full flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
          {{ $slot }}
        </main>
        </div>
      </div>
    </div>
    @fluxScripts
  </body>
</html>

