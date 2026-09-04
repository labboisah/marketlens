@php
  $comparisonFeatures = $plans
      ->flatMap(fn ($planGroup) => $planGroup->first()?->features ?? collect())
      ->pluck('name')
      ->unique()
      ->values();
@endphp

<div>
  <section class="border-b border-border bg-background">
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
      <x-public.breadcrumbs :items="[['label' => 'Pricing']]" />
      <div class="mt-8 max-w-3xl">
        <x-public.status-badge tone="information">Pricing</x-public.status-badge>
        <h1 class="mt-5 text-4xl font-bold tracking-normal text-foreground sm:text-5xl">Plans for businesses, supermarkets, wholesalers, and growing branches.</h1>
        <p class="mt-5 text-lg leading-8 text-muted">
          Prices and included features are loaded from configured Platform plan data. Hosting and Platform access are included, and customers do not need to purchase a domain for each business.
        </p>
      </div>

      @auth
        <div class="mt-8 rounded-lg border border-information/30 bg-information/10 p-4 text-sm text-foreground">
          <p class="font-semibold">Current plan status</p>
          <p class="mt-1 text-muted">Your subscription status will appear here once business subscription management is connected.</p>
        </div>
      @endauth
    </div>
  </section>

  <section class="bg-surface py-16">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
      <div class="grid gap-4 lg:grid-cols-3">
        @forelse ($plans as $planName => $planGroup)
          @php
            $monthlyPlan = $planGroup->firstWhere('billing_interval', 'monthly');
            $annualPlan = $planGroup->firstWhere('billing_interval', 'annual');
            $displayPlan = $monthlyPlan ?? $annualPlan ?? $planGroup->first();
          @endphp

          <article class="rounded-lg border border-border bg-background p-6 shadow-sm">
            <div class="flex items-start justify-between gap-4">
              <div>
                <h2 class="text-xl font-bold text-foreground">{{ $planName }}</h2>
                <p class="mt-2 text-sm leading-6 text-muted">{{ $displayPlan->description }}</p>
              </div>
              <x-public.status-badge :tone="$planName === 'Professional' ? 'success' : 'teal'">
                {{ $displayPlan->trial_days }}-day trial
              </x-public.status-badge>
            </div>

            <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
              @if ($monthlyPlan)
                <div class="rounded-lg border border-border bg-surface p-4">
                  <p class="text-xs font-semibold uppercase text-muted">Monthly</p>
                  <p class="mt-2 text-2xl font-bold text-foreground">{{ $monthlyPlan->display_price }}</p>
                </div>
              @endif

              @if ($annualPlan)
                <div class="rounded-lg border border-border bg-surface p-4">
                  <p class="text-xs font-semibold uppercase text-muted">Annual</p>
                  <p class="mt-2 text-2xl font-bold text-foreground">{{ $annualPlan->display_price }}</p>
                </div>
              @endif
            </div>

            <ul class="mt-6 grid gap-3 text-sm">
              @foreach ($displayPlan->features as $feature)
                <li class="flex items-start gap-2">
                  <x-public.icon name="check" class="mt-0.5 h-4 w-4 shrink-0 text-success" />
                  <span>
                    <span class="font-medium text-foreground">{{ $feature->name }}</span>
                    @if ($feature->tooltip)
                      <button type="button" class="ml-1 rounded-sm text-information" title="{{ $feature->tooltip }}" aria-label="{{ $feature->name }} details">?</button>
                    @endif
                  </span>
                </li>
              @endforeach
            </ul>

            <a href="/register?plan={{ $displayPlan->slug }}" class="mt-6 inline-flex h-11 w-full items-center justify-center rounded-md bg-accent px-5 text-sm font-semibold text-white">
              Start {{ $planName }}
            </a>
          </article>
        @empty
          <div class="rounded-lg border border-border bg-background p-6 text-sm text-muted">
            Pricing plans are being configured.
          </div>
        @endforelse
      </div>
    </div>
  </section>

  <section class="border-y border-border bg-background py-16">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
      <x-public.section-heading title="Plan comparison" description="Compare active plans using the configured plan feature records." />
      <div class="mt-8 overflow-x-auto rounded-lg border border-border bg-surface">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="border-b border-border">
              <th class="px-4 py-3 text-left font-semibold text-foreground">Feature</th>
              @foreach ($plans as $planName => $planGroup)
                <th class="px-4 py-3 text-left font-semibold text-foreground">{{ $planName }}</th>
              @endforeach
            </tr>
          </thead>
          <tbody>
            @foreach ($comparisonFeatures as $featureName)
              <tr class="border-b border-border last:border-0">
                <td class="px-4 py-3 font-medium text-foreground">{{ $featureName }}</td>
                @foreach ($plans as $planGroup)
                  @php $feature = $planGroup->first()?->features->firstWhere('name', $featureName); @endphp
                  <td class="px-4 py-3 text-muted">
                    @if ($feature)
                      <span title="{{ $feature->tooltip }}">Included</span>
                    @else
                      <span>Not included</span>
                    @endif
                  </td>
                @endforeach
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <section class="bg-surface py-16">
    <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
      <x-public.section-heading eyebrow="Pricing FAQ" title="Frequently asked pricing questions" />
      <div class="mt-8 grid gap-3">
        <x-public.faq-item question="Is hosting included?">Yes. Hosting and Platform access are included in MarketLens plans.</x-public.faq-item>
        <x-public.faq-item question="Does each business need to buy a domain?">No. Customers do not need to purchase a domain for each business.</x-public.faq-item>
        <x-public.faq-item question="Is there a trial?">Yes. Trial length comes from configured plan data and is shown on each plan.</x-public.faq-item>
        <x-public.faq-item question="Can I pay monthly or annually?">Yes. Active monthly and annual plan prices are displayed when configured.</x-public.faq-item>
        <x-public.faq-item question="Can I change plans later?">Plan changes will be handled through Platform subscription management when that workflow is implemented.</x-public.faq-item>
      </div>
    </div>
  </section>
</div>
