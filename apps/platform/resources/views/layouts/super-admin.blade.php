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
    <div class="min-h-screen bg-background text-foreground">
      <header class="border-b border-border bg-surface">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
          <div>
            <p class="text-xs font-semibold uppercase text-muted">MarketLens</p>
            <p class="text-lg font-bold">Super administration</p>
          </div>
          <nav class="flex flex-wrap items-center gap-2 text-sm">
            <a href="{{ route('super-admin.dashboard') }}" class="rounded-md border border-border px-3 py-2 font-semibold text-foreground hover:border-primary/50">Dashboard</a>
            <a href="{{ route('platform.dashboard') }}" class="rounded-md border border-border px-3 py-2 font-semibold text-foreground hover:border-primary/50">Business Platform</a>
            <form method="POST" action="{{ route('logout') }}">
              @csrf
              <button type="submit" class="rounded-md border border-border px-3 py-2 font-semibold text-foreground hover:border-primary/50">Logout</button>
            </form>
          </nav>
        </div>
      </header>

      <main class="mx-auto flex max-w-7xl flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
        <flux:toast position="top end" />
        {{ $slot }}
      </main>
    </div>
    @fluxScripts
  </body>
</html>
