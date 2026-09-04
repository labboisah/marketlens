@php
  $navItems = [
      ['label' => 'Product', 'href' => route('public.product')],
      ['label' => 'Features', 'href' => route('public.features')],
      ['label' => 'Business types', 'href' => route('public.business-types')],
      ['label' => 'Pricing', 'href' => route('public.pricing')],
      ['label' => 'How it works', 'href' => route('public.how-it-works')],
      ['label' => 'FAQ', 'href' => route('welcome').'#faq'],
      ['label' => 'Contact', 'href' => route('welcome').'#contact'],
  ];
@endphp

<header
  class="sticky top-0 z-40 border-b border-border bg-surface/95 backdrop-blur supports-[backdrop-filter]:bg-surface/80"
  x-data="{ mobileOpen: false }"
>
  <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-surface focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-foreground">
    Skip to content
  </a>

  <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
    <a href="{{ route('welcome') }}" class="flex items-center gap-3 rounded-md focus-visible:ring-offset-surface" aria-label="MarketLens home">
      <span class="grid h-10 w-10 place-items-center rounded-lg bg-primary text-sm font-bold tracking-normal text-white shadow-sm">
        ML
      </span>
      <span class="flex flex-col leading-none">
        <span class="text-base font-bold text-foreground">MarketLens</span>
        <span class="text-xs font-medium text-muted">Retail intelligence</span>
      </span>
    </a>

    <nav class="hidden items-center gap-1 lg:flex" aria-label="Main navigation">
      @foreach ($navItems as $item)
        <a href="{{ $item['href'] }}" class="rounded-md px-3 py-2 text-sm font-medium text-muted hover:bg-surface-muted hover:text-foreground focus-visible:ring-offset-surface motion-reduce:transition-none">
          {{ $item['label'] }}
        </a>
      @endforeach
    </nav>

    <div class="hidden items-center gap-3 lg:flex">
      <a href="/login" class="rounded-md px-3 py-2 text-sm font-semibold text-muted hover:bg-surface-muted hover:text-foreground focus-visible:ring-offset-surface">
        Login
      </a>
      <a href="/register" class="inline-flex h-10 items-center justify-center rounded-md bg-accent px-4 text-sm font-semibold text-white shadow-sm hover:bg-accent/90 focus-visible:ring-offset-surface">
        Get Started
      </a>
    </div>

    <button
      type="button"
      class="inline-flex h-10 w-10 items-center justify-center rounded-md border border-border bg-surface text-foreground lg:hidden"
      aria-label="Toggle main navigation"
      aria-controls="public-mobile-menu"
      :aria-expanded="mobileOpen.toString()"
      x-on:click="mobileOpen = ! mobileOpen"
    >
      <span class="sr-only">Open menu</span>
      <x-public.icon name="menu" class="h-5 w-5" x-show="! mobileOpen" />
      <x-public.icon name="x" class="h-5 w-5" x-cloak x-show="mobileOpen" />
    </button>
  </div>

  <x-public.mobile-menu :items="$navItems" />
</header>
