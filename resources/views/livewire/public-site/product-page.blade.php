@php
  $applications = [
      ['name' => 'Platform', 'body' => 'The cloud Platform manages businesses, branches, subscriptions, Business Server devices, synchronization receipts, and Monitoring App authentication.'],
      ['name' => 'Business Server', 'body' => 'The local Business Server is installed inside the business, owns the local database, registers operational staff users, validates transactions, and keeps the business working offline.'],
      ['name' => 'Business App', 'body' => 'Business Apps run on cashier, storekeeper, manager, and accountant computers. They connect to the Business Server over LAN or Wi-Fi and never open SQLite directly.'],
      ['name' => 'Monitoring App', 'body' => 'The Monitoring App connects to the Platform only. It shows synchronized sales, payment, stock, expense, debt, and branch summaries for owners.'],
  ];

  $details = [
      ['title' => 'Data flow', 'body' => 'Business Apps send operational requests to the Business Server. The Business Server stores accepted records locally, then synchronizes batches to the Platform. The Monitoring App reads from the Platform.'],
      ['title' => 'Offline-first approach', 'body' => 'Daily business work continues when internet service is slow or unavailable. Cloud dashboards clearly reflect the last synchronized records.'],
      ['title' => 'Remote monitoring', 'body' => 'Owners can monitor sales, stock alerts, debts, expenses, cashier performance, and branch comparison from synchronized Platform data.'],
      ['title' => 'Security', 'body' => 'Operational users belong to the Business Server, devices are authorized, synchronized records use UUIDs, and tenant and branch records stay scoped.'],
      ['title' => 'Multi-branch operation', 'body' => 'Each branch keeps operating locally while the Platform consolidates authorized summaries for owners and managers.'],
  ];
@endphp

<div>
  <section class="border-b border-border bg-background">
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
      <x-public.breadcrumbs :items="[['label' => 'Product']]" />
      <div class="mt-8 max-w-3xl">
        <x-public.status-badge tone="information">Product</x-public.status-badge>
        <h1 class="mt-5 text-4xl font-bold tracking-normal text-foreground sm:text-5xl">A retail platform built around local business reliability.</h1>
        <p class="mt-5 text-lg leading-8 text-muted">
          MarketLens combines a local Business Server, role-based Business Apps, a cloud Platform, and an owner Monitoring App so businesses can operate offline while business owners retain remote visibility.
        </p>
      </div>
    </div>
  </section>

  <section class="bg-surface py-16">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
      <x-public.section-heading title="MarketLens applications" description="Each application has a clear responsibility so operational control stays local and reporting stays secure." />
      <div class="mt-8 grid gap-4 md:grid-cols-2">
        @foreach ($applications as $application)
          <article class="rounded-lg border border-border bg-background p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-foreground">{{ $application['name'] }}</h2>
            <p class="mt-2 text-sm leading-6 text-muted">{{ $application['body'] }}</p>
          </article>
        @endforeach
      </div>
    </div>
  </section>

  <section class="border-y border-border bg-background py-16">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
      <x-public.section-heading title="Data flow and operating model" description="MarketLens is designed to keep sales, stock, debt, and cash control dependable even when connectivity is imperfect." />
      <div class="mt-8 grid gap-4 lg:grid-cols-5">
        @foreach ($details as $detail)
          <article class="rounded-lg border border-border bg-surface p-5 shadow-sm">
            <h2 class="text-base font-semibold text-foreground">{{ $detail['title'] }}</h2>
            <p class="mt-2 text-sm leading-6 text-muted">{{ $detail['body'] }}</p>
          </article>
        @endforeach
      </div>
    </div>
  </section>
</div>
