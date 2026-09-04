@props([
    'title',
    'description',
])

<section {{ $attributes->merge(['class' => 'bg-primary text-white']) }}>
  <div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 py-12 sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:px-8">
    <div class="max-w-2xl">
      <h2 class="text-2xl font-bold sm:text-3xl">{{ $title }}</h2>
      <p class="mt-3 text-sm leading-6 text-white/80">{{ $description }}</p>
    </div>
    <div class="flex flex-col gap-3 sm:flex-row">
      <a href="/register" class="inline-flex h-11 items-center justify-center rounded-md bg-white px-5 text-sm font-semibold text-primary">
        Get Started
      </a>
      <a href="#contact" class="inline-flex h-11 items-center justify-center rounded-md border border-white/30 px-5 text-sm font-semibold text-white">
        Contact
      </a>
    </div>
  </div>
</section>
