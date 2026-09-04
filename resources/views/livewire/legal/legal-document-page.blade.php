<section class="bg-background py-12 sm:py-16">
  <div class="mx-auto grid max-w-7xl gap-8 px-4 sm:px-6 lg:grid-cols-[280px_1fr] lg:px-8">
    <aside class="lg:sticky lg:top-24 lg:self-start">
      <x-public.breadcrumbs :items="[['label' => 'Legal', 'href' => route('legal.index')], ['label' => $document->title]]" />
      <nav class="mt-5 rounded-lg border border-border bg-surface p-4" aria-label="Legal documents">
        <p class="text-sm font-semibold text-foreground">Documents</p>
        <div class="mt-3 grid gap-2 text-sm">
          @foreach ($documents as $item)
            <a href="{{ route('legal.show', $item->slug) }}" class="rounded-md px-2 py-1.5 {{ $item->id === $document->id ? 'bg-primary text-white' : 'text-muted hover:bg-surface-muted hover:text-foreground' }}">{{ $item->title }}</a>
          @endforeach
        </div>
      </nav>
    </aside>

    <article class="rounded-lg border border-border bg-surface p-5 shadow-sm sm:p-8">
      <div class="flex flex-wrap items-start justify-between gap-4 border-b border-border pb-5">
        <div>
          <x-public.status-badge tone="information">Legal document</x-public.status-badge>
          <h1 class="mt-4 text-3xl font-bold tracking-normal text-foreground sm:text-4xl">{{ $document->title }}</h1>
          <p class="mt-3 text-muted">{{ $document->description }}</p>
        </div>
        <dl class="grid min-w-48 gap-2 rounded-md border border-border bg-background p-3 text-sm">
          <div><dt class="text-muted">Version</dt><dd class="font-mono font-semibold">{{ $version->version }}</dd></div>
          <div><dt class="text-muted">Published</dt><dd>{{ $version->published_at?->format('M j, Y') ?? 'Pending' }}</dd></div>
          <div><dt class="text-muted">Effective</dt><dd>{{ $version->effective_at?->format('M j, Y') ?? 'Immediately' }}</dd></div>
        </dl>
      </div>

      <div class="legal-document mt-6 space-y-4 text-sm leading-7 text-foreground">
        {!! \Illuminate\Support\Str::markdown($version->body) !!}
      </div>
    </article>
  </div>
</section>
