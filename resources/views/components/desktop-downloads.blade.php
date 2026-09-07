<nav {{ $attributes->merge(['class' => 'flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-sm']) }} aria-label="Desktop downloads">
  <a href="{{ route('public.desktop-download', ['application' => 'business-server']) }}" class="rounded-md py-2 font-medium text-accent hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-accent" title="Download Business Server for Windows (x64)">
    Download Business Server
  </a>
  <a href="{{ route('public.desktop-download', ['application' => 'business-app']) }}" class="rounded-md py-2 font-medium text-accent hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-accent" title="Download Business App for Windows (x64)">
    Download Business App
  </a>
</nav>