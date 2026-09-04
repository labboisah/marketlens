<div class="flex flex-col gap-6">
  <x-app.page-header
    title="Legal acceptance required"
    description="A material legal-document version changed. Accept the current effective versions to continue using authenticated Platform features."
  />

  @if ($requiredVersions->isEmpty())
    <x-app.card title="You are up to date" description="There are no required legal document versions awaiting acceptance.">
      <a href="{{ route('platform.dashboard') }}" class="inline-flex rounded-md bg-primary px-3 py-2 text-sm font-semibold text-white">Continue to dashboard</a>
    </x-app.card>
  @else
    <form wire:submit="accept" class="grid gap-4 xl:grid-cols-[1fr_0.7fr]">
      <x-app.card title="Required documents" description="Version, publication, effective date, and material-change status are shown for each required document.">
        <div class="space-y-4">
          @foreach ($requiredVersions as $version)
            <section class="rounded-md border border-border p-4">
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <p class="font-semibold">{{ $version->document->title }}</p>
                  <p class="mt-1 text-sm text-muted">{{ $version->summary }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                  <x-app.status-badge>v{{ $version->version }}</x-app.status-badge>
                  <x-app.status-badge tone="warning">Material</x-app.status-badge>
                </div>
              </div>
              <dl class="mt-3 grid gap-2 text-xs text-muted sm:grid-cols-2">
                <div><dt class="font-semibold text-foreground">Published</dt><dd>{{ $version->published_at?->format('M j, Y') ?? 'Pending' }}</dd></div>
                <div><dt class="font-semibold text-foreground">Effective</dt><dd>{{ $version->effective_at?->format('M j, Y') ?? 'Immediately' }}</dd></div>
              </dl>
              <label class="mt-4 flex gap-3 text-sm font-medium">
                <input type="checkbox" wire:model="acceptedVersions.{{ $version->id }}" class="mt-1 h-4 w-4 rounded border-border">
                <span>I accept {{ $version->document->title }} version {{ $version->version }}.</span>
              </label>
            </section>
          @endforeach
          @error('acceptedVersions') <p class="text-sm text-danger">{{ $message }}</p> @enderror
          @error('acceptance') <p class="text-sm text-danger">{{ $message }}</p> @enderror
        </div>
      </x-app.card>

      <x-app.card title="Legal document library" description="Open the current published text in a separate legal page before accepting.">
        <div class="grid gap-2 text-sm">
          @foreach ($documents as $document)
            <a href="{{ route('legal.show', $document->slug) }}" target="_blank" class="rounded-md border border-border px-3 py-2 font-medium text-foreground hover:border-primary/50">{{ $document->title }}</a>
          @endforeach
        </div>
        <button type="submit" class="mt-4 rounded-md bg-primary px-3 py-2 text-sm font-semibold text-white">Accept and continue</button>
      </x-app.card>
    </form>
  @endif
</div>
