@props(['title', 'description' => null, 'open' => false])

@if ($open)
  <div class="fixed inset-0 z-50 grid place-items-center bg-primary/40 p-4" role="presentation">
    <section
      {{ $attributes->merge(['class' => 'w-full max-w-lg rounded-lg border border-border bg-surface shadow-md']) }}
      role="dialog"
      aria-modal="true"
      aria-labelledby="modal-title"
    >
      <header class="border-b border-border px-4 py-3">
        <h2 id="modal-title" class="text-lg font-semibold text-foreground">{{ $title }}</h2>
        @if ($description)
          <p class="mt-1 text-sm text-muted">{{ $description }}</p>
        @endif
      </header>

      <div class="p-4">
        {{ $slot }}
      </div>

      @isset($footer)
        <footer class="flex justify-end gap-2 border-t border-border px-4 py-3">
          {{ $footer }}
        </footer>
      @endisset
    </section>
  </div>
@endif
