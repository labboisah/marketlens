@props(['title' => 'Something went wrong', 'message' => 'The request could not be completed.'])

<section {{ $attributes->merge(['class' => 'rounded-lg border border-danger/30 bg-danger/5 p-5']) }}>
  <h2 class="text-sm font-semibold text-danger">{{ $title }}</h2>
  <p class="mt-1 text-sm text-muted">{{ $message }}</p>
</section>
