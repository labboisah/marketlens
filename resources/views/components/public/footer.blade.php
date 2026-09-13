<footer class="border-t border-border bg-surface" aria-labelledby="public-footer-heading">
  <h2 id="public-footer-heading" class="sr-only">MarketLens footer</h2>

  <div class="mx-auto grid max-w-7xl gap-10 px-4 py-10 sm:px-6 lg:grid-cols-[1.5fr_1fr_1fr] lg:px-8">
    <div class="max-w-sm">
      <div class="flex items-center gap-3">
        <span class="grid h-10 w-10 place-items-center rounded-lg bg-primary text-sm font-bold text-white">ML</span>
        <span class="text-base font-bold">MarketLens</span>
      </div>
      <p class="mt-4 text-sm leading-6 text-muted">
        Professional retail technology for clear business visibility, inventory control, and sales intelligence.
      </p>
      <div class="mt-5 flex gap-3" aria-label="Social links">
        <a href="#" class="rounded-md p-2 text-muted hover:bg-surface-muted hover:text-foreground" aria-label="MarketLens on LinkedIn">
          <x-public.icon name="linkedin" class="h-5 w-5" />
        </a>
        <a href="#" class="rounded-md p-2 text-muted hover:bg-surface-muted hover:text-foreground" aria-label="MarketLens on X">
          <x-public.icon name="twitter" class="h-5 w-5" />
        </a>
        <a href="#" class="rounded-md p-2 text-muted hover:bg-surface-muted hover:text-foreground" aria-label="MarketLens on Facebook">
          <x-public.icon name="facebook" class="h-5 w-5" />
        </a>
      </div>
    </div>

    <nav class="grid gap-3 text-sm" aria-label="Footer product links">
      <a class="font-semibold text-foreground" href="{{ route('public.product') }}">Product</a>
      <a class="text-muted hover:text-foreground" href="{{ route('public.features') }}">Features</a>
      <a class="text-muted hover:text-foreground" href="{{ route('public.business-types') }}">Business types</a>
      <a class="text-muted hover:text-foreground" href="{{ route('public.pricing') }}">Pricing</a>
      <a class="text-muted hover:text-foreground" href="{{ route('welcome') }}#contact">Contact</a>
    </nav>

    <nav class="grid gap-3 text-sm" aria-label="Footer support links">
      <a class="text-muted hover:text-foreground" href="{{ route('public.deployment') }}">Deployment and upgrades</a>
      <a class="text-muted hover:text-foreground" href="{{ route('public.setup-kits') }}">Setup kits and templates</a>
      <a class="font-semibold text-foreground" href="/support">Support</a>
      <a class="text-muted hover:text-foreground" href="/privacy">Privacy</a>
      <a class="text-muted hover:text-foreground" href="/terms">Terms</a>
      <a class="text-muted hover:text-foreground" href="{{ route('welcome') }}#faq">FAQ</a>
    </nav>
  </div>

  <div class="border-t border-border px-4 py-5 text-center text-xs text-muted">
    &copy; {{ now()->year }} MarketLens. All rights reserved.
  </div>
</footer>
