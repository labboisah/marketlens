<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'MarketLens Account' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
  </head>
  <body class="min-h-screen bg-background text-foreground">
    <main class="flex min-h-screen items-center justify-center px-4 py-8 sm:px-6 lg:px-8">
      <section class="grid w-full max-w-7xl grid-cols-12">
        <div class="col-span-12 sm:col-start-2 sm:col-span-10 lg:col-start-4 lg:col-span-6 xl:col-start-4 xl:col-span-6">
          <div class="rounded-lg border border-border bg-surface px-5 py-7 shadow-sm sm:px-8 sm:py-8">
            <div class="mb-7 flex flex-col items-center text-center">
              <a href="{{ route('welcome') }}" class="inline-flex flex-col items-center gap-3 rounded-md" aria-label="MarketLens home">
                <img src="{{ asset('images/logo.png') }}" alt="MarketLens logo" class="h-20 w-20 rounded-full object-contain sm:h-24 sm:w-24">
                <span class="text-xl font-bold text-foreground">MarketLens</span>
              </a>
              <p class="mt-2 max-w-sm text-xs leading-5 text-muted">
                Platform and Monitoring access for business owners. Operational Business Server users are created inside the Business Server.
              </p>
            </div>

          {{ $slot ?? '' }}
          @yield('content')
          </div>
        </div>
      </section>
    </main>
  </body>
</html>
