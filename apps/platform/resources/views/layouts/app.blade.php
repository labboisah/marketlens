<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'MarketLens Platform' }}</title>
    @fluxAppearance
    @vite(['resources/css/app.css', 'resources/js/app.js'])
  </head>
  <body>
    <div class="ml-page-shell" x-data="marketlensShell">
      <x-app.sidebar />

      <div class="min-h-screen lg:pl-64">
        <x-app.top-nav />

        <main class="mx-auto flex w-full max-w-7xl flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
          @isset($header)
            {{ $header }}
          @endisset

          {{ $slot }}
        </main>
      </div>
    </div>
    @fluxScripts
  </body>
</html>
