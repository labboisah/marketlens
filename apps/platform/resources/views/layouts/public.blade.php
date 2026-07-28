<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">

    @php
      $pageTitle = $title ?? 'MarketLens | See Every Sale. Control Every Stock.';
      $pageDescription = 'MarketLens is an offline-first sales, inventory, debt, expense and business-monitoring platform for shops, supermarkets, wholesalers and multi-branch retail businesses.';
      $canonicalUrl = url()->current();
      $structuredData = [
          '@context' => 'https://schema.org',
          '@type' => 'SoftwareApplication',
          'name' => 'MarketLens',
          'applicationCategory' => 'BusinessApplication',
          'operatingSystem' => 'Windows, Android, Web',
          'description' => $pageDescription,
      ];
    @endphp

    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $pageDescription }}">
    <link rel="canonical" href="{{ $canonicalUrl }}">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $pageDescription }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:image" content="{{ asset('images/marketlens-og-placeholder.png') }}">
    <meta name="twitter:card" content="summary_large_image">
    <script type="application/ld+json">
      {!! json_encode($structuredData, JSON_UNESCAPED_SLASHES) !!}
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
  </head>
  <body class="min-h-screen bg-background text-foreground">
    <div class="flex min-h-screen flex-col">
      <x-public.header />

      <main id="main-content" class="flex-1">
        {{ $slot }}
      </main>

      <x-public.footer />
    </div>
  </body>
</html>
