<section class="bg-background py-12 sm:py-16">
  <div class="mx-auto flex max-w-6xl flex-col gap-8 px-4 sm:px-6 lg:px-8">
    <div>
      <x-public.breadcrumbs :items="[['label' => 'Legal']]" />
      <div class="mt-5 max-w-3xl">
        <x-public.status-badge tone="information">Legal centre</x-public.status-badge>
        <h1 class="mt-4 text-4xl font-bold tracking-normal text-foreground sm:text-5xl">MarketLens legal documents</h1>
        <p class="mt-4 text-lg text-muted">Published policies for public visitors, business owners, monitoring users, subscriptions, support, security, privacy, and synchronized business data.</p>
      </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
      @foreach ($documents as $document)
        @php($version = $document->publishedVersion)
        <a href="{{ route('legal.show', $document->slug) }}" class="rounded-lg border border-border bg-surface p-5 shadow-sm transition hover:border-primary/60 hover:shadow-md">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-semibold text-foreground">{{ $document->title }}</h2>
            <x-public.status-badge tone="teal">v{{ $version?->version }}</x-public.status-badge>
          </div>
          <p class="mt-3 text-sm text-muted">{{ $document->description }}</p>
          <dl class="mt-4 grid gap-2 text-xs text-muted sm:grid-cols-2">
            <div><dt class="font-semibold text-foreground">Published</dt><dd>{{ $version?->published_at?->format('M j, Y') ?? 'Pending' }}</dd></div>
            <div><dt class="font-semibold text-foreground">Effective</dt><dd>{{ $version?->effective_at?->format('M j, Y') ?? 'Immediately' }}</dd></div>
          </dl>
        </a>
      @endforeach
    </div>
  </div>
</section>
