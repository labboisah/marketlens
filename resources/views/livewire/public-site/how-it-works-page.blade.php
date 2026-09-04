@php
  $statements = [
      'Business users are registered in the Business Server.',
      'Multiple Business Apps can connect through LAN or Wi-Fi.',
      'The Business Server owns the local database.',
      'Business operations continue offline.',
      'The Business Server synchronizes records to the Platform.',
      'The Monitoring App reads from the Platform.',
      'The Monitoring App does not connect directly to the business.',
  ];

  $steps = [
      ['title' => 'Staff work on Business Apps', 'body' => 'Cashiers, storekeepers, managers, and accountants use role-based Business Apps connected to the Business Server over the local network.'],
      ['title' => 'The Business Server controls the business', 'body' => 'The Business Server registers operational users, checks permissions, validates transactions, writes the local database, and keeps the business running offline.'],
      ['title' => 'Records synchronize to the Platform', 'body' => 'When internet is available, the Business Server sends accepted batches to the Platform for cloud reporting and device/subscription management.'],
      ['title' => 'Owners monitor from the Platform', 'body' => 'The Monitoring App shows synchronized records, branch summaries, alerts, and last synchronization time from the Platform only.'],
  ];
@endphp

<div>
  <section class="border-b border-border bg-background">
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
      <x-public.breadcrumbs :items="[['label' => 'How it works']]" />
      <div class="mt-8 max-w-3xl">
        <x-public.status-badge tone="information">How it works</x-public.status-badge>
        <h1 class="mt-5 text-4xl font-bold tracking-normal text-foreground sm:text-5xl">A simple architecture for shops that cannot stop selling.</h1>
        <p class="mt-5 text-lg leading-8 text-muted">MarketLens keeps daily business operations local, then synchronizes records to the cloud so owners can monitor the business remotely.</p>
      </div>
    </div>
  </section>

  <section class="bg-surface py-16">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
      <x-public.section-heading title="In plain business language" description="The architecture rules below keep operational control, cloud reporting, and remote monitoring clear." />
      <div class="mt-8 grid gap-3 md:grid-cols-2">
        @foreach ($statements as $statement)
          <div class="rounded-lg border border-border bg-background p-4 text-sm font-semibold text-foreground">
            {{ $statement }}
          </div>
        @endforeach
      </div>
    </div>
  </section>

  <section class="border-y border-border bg-background py-16">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
      <x-public.section-heading title="The operating flow" description="From business computer to owner phone, every application has a focused job." />
      <div class="mt-8 grid gap-4 lg:grid-cols-4">
        @foreach ($steps as $index => $step)
          <article class="rounded-lg border border-border bg-surface p-5 shadow-sm">
            <x-public.status-badge tone="teal">Step {{ $index + 1 }}</x-public.status-badge>
            <h2 class="mt-4 text-lg font-semibold text-foreground">{{ $step['title'] }}</h2>
            <p class="mt-2 text-sm leading-6 text-muted">{{ $step['body'] }}</p>
          </article>
        @endforeach
      </div>
    </div>
  </section>
</div>
