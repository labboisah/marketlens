@php
  $problems = [
      'Incomplete sales records',
      'Incorrect stock balances',
      'Cashier shortages',
      'Weak debt management',
      'Poor expense visibility',
      'Lack of remote monitoring',
      'Unreliable internet',
      'Difficulty comparing branches',
  ];

  $solutions = [
      ['title' => 'Offline business operation', 'body' => 'Business teams keep selling, receiving stock, and recording expenses even when internet service is unreliable.'],
      ['title' => 'Local Business Server', 'body' => 'The Business Server validates users, permissions, transactions, stock movements, debt ledgers, and local audit logs.'],
      ['title' => 'Multiple Client Apps', 'body' => 'Cashier, storekeeper, manager, and accountant computers connect to the authorized Business Server over the local network.'],
      ['title' => 'Cloud synchronization', 'body' => 'Accepted records synchronize to the Platform in batches when internet is available.'],
      ['title' => 'Remote Monitoring App', 'body' => 'Owners view synchronized summaries, alerts, and branch performance from the Platform.'],
      ['title' => 'Stock movement tracking', 'body' => 'Inventory changes are recorded as auditable movements rather than hidden quantity edits.'],
      ['title' => 'Cashier reconciliation', 'body' => 'Shifts, payments, and shortages are tracked clearly for review.'],
      ['title' => 'Debt ledger', 'body' => 'Customer and supplier balances are derived from ledger transactions.'],
      ['title' => 'Owner dashboards', 'body' => 'Business owners see sales, payments, expenses, stock alerts, and synchronization status.'],
  ];

  $steps = [
      ['title' => 'Client Apps process business operations.', 'body' => 'Cashiers, storekeepers, managers, and accountants use role-aware desktop screens.', 'icon' => 'credit-card'],
      ['title' => 'Business Server validates and stores records locally.', 'body' => 'The local source of truth applies permissions, transactions, audit logs, and SQLite persistence.', 'icon' => 'database'],
      ['title' => 'Business Server synchronizes records to the Platform.', 'body' => 'Queued sync batches move accepted records to the cloud when internet service is available.', 'icon' => 'refresh-cw'],
      ['title' => 'Monitoring App displays synchronized business records.', 'body' => 'Owners view branch summaries, alerts, and last synchronization time from the Platform.', 'icon' => 'smartphone'],
  ];

  $features = [
      ['Sales monitoring', 'bar-chart-3'],
      ['Inventory control', 'package-check'],
      ['Expenses', 'receipt'],
      ['Customer debts', 'landmark'],
      ['Supplier accounts', 'building-2'],
      ['Cashier shifts', 'activity'],
      ['Branch comparison', 'store'],
      ['Synchronization status', 'refresh-cw'],
      ['Security alerts', 'shield-check'],
      ['Owner reports', 'cloud'],
  ];

  $businessTypes = [
      'Supermarkets',
      'Provision businesses',
      'Wholesalers',
      'Pharmacies',
      'Building-material businesses',
      'Electronics businesses',
      'Cosmetics businesses',
      'Agricultural-product dealers',
      'Spare-parts businesses',
      'Bookstores',
  ];

  $metrics = [
      ['label' => 'Today\'s sales', 'value' => 'NGN 842,500', 'tone' => 'information'],
      ['label' => 'Cash received', 'value' => 'NGN 421,200', 'tone' => 'success'],
      ['label' => 'Transfer payments', 'value' => 'NGN 386,800', 'tone' => 'teal'],
      ['label' => 'Expenses', 'value' => 'NGN 54,600', 'tone' => 'warning'],
      ['label' => 'Gross profit', 'value' => 'NGN 183,900', 'tone' => 'success'],
      ['label' => 'Low-stock products', 'value' => '18', 'tone' => 'danger'],
      ['label' => 'Customer debt', 'value' => 'NGN 96,400', 'tone' => 'warning'],
      ['label' => 'Cashier shortage', 'value' => 'NGN 2,300', 'tone' => 'danger'],
      ['label' => 'Last synchronization', 'value' => 'Today, 3:42 PM', 'tone' => 'information'],
  ];
@endphp

<div>
  <section id="product" class="border-b border-border bg-background">
    <div class="mx-auto grid max-w-7xl gap-10 px-4 py-16 sm:px-6 lg:grid-cols-[1fr_0.95fr] lg:px-8 lg:py-24">
      <div class="flex flex-col justify-center">
        <div class="flex flex-wrap gap-2">
          <x-public.status-badge tone="teal">Offline-first</x-public.status-badge>
          <x-public.status-badge tone="information">Remote monitoring</x-public.status-badge>
          <x-public.status-badge tone="success">Multi-branch</x-public.status-badge>
        </div>
        <h1 class="mt-6 max-w-3xl text-4xl font-bold tracking-normal text-foreground sm:text-5xl lg:text-6xl">
          MarketLens
        </h1>
        <p class="mt-3 text-2xl font-semibold text-primary sm:text-3xl">
          See Every Sale. Control Every Stock.
        </p>
        <p class="mt-5 max-w-2xl text-lg leading-8 text-muted">
          MarketLens is an offline-first sales, inventory, debt, expense and business-monitoring platform for businesses, supermarkets, wholesalers and multi-branch retail businesses.
        </p>
        <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center">
          <a href="/register" class="inline-flex h-11 items-center justify-center rounded-md bg-accent px-5 text-sm font-semibold text-white shadow-sm">
            Get Started
          </a>
          <a href="#features" class="inline-flex h-11 items-center justify-center rounded-md border border-border bg-surface px-5 text-sm font-semibold text-foreground">
            View Features
          </a>
          <a href="/login" class="inline-flex h-11 items-center justify-center rounded-md px-4 text-sm font-semibold text-muted hover:bg-surface-muted hover:text-foreground">
            Login
          </a>
        </div>
      </div>

      <div class="rounded-lg border border-border bg-surface p-4 shadow-sm sm:p-6" aria-label="MarketLens product interface illustration">
        <div class="flex items-center justify-between border-b border-border pb-4">
          <div>
            <p class="text-sm font-semibold text-foreground">Owner dashboard</p>
            <p class="text-xs text-muted">Demonstration interface preview</p>
          </div>
          <x-public.status-badge tone="success">Synced 3:42 PM</x-public.status-badge>
        </div>
        <div class="mt-5 grid gap-3 sm:grid-cols-3">
          <div class="rounded-lg border border-border bg-background p-4">
            <p class="text-xs text-muted">Sales</p>
            <p class="mt-2 text-xl font-bold">NGN 842k</p>
          </div>
          <div class="rounded-lg border border-border bg-background p-4">
            <p class="text-xs text-muted">Low stock</p>
            <p class="mt-2 text-xl font-bold">18 items</p>
          </div>
          <div class="rounded-lg border border-border bg-background p-4">
            <p class="text-xs text-muted">Branches</p>
            <p class="mt-2 text-xl font-bold">4 active</p>
          </div>
        </div>
        <div class="mt-5 grid gap-3">
          <div class="h-3 rounded-md bg-surface-muted"><div class="h-3 w-4/5 rounded-md bg-accent"></div></div>
          <div class="h-3 rounded-md bg-surface-muted"><div class="h-3 w-2/3 rounded-md bg-teal"></div></div>
          <div class="h-3 rounded-md bg-surface-muted"><div class="h-3 w-1/2 rounded-md bg-success"></div></div>
        </div>
        <div class="mt-5 grid gap-3 sm:grid-cols-2">
          <div class="rounded-lg border border-border bg-background p-4">
            <p class="text-sm font-semibold">Branch comparison</p>
            <p class="mt-2 text-xs leading-5 text-muted">Main branch is 18% ahead of yesterday.</p>
          </div>
          <div class="rounded-lg border border-border bg-background p-4">
            <p class="text-sm font-semibold">Security alerts</p>
            <p class="mt-2 text-xs leading-5 text-muted">Two manager approvals pending review.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section id="business-problems" class="bg-surface py-16">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
      <x-public.section-heading
        eyebrow="Business problems"
        title="Retail teams lose visibility when records are scattered"
        description="MarketLens is designed around the daily operational problems that make sales, stock, cash, and branch decisions difficult."
      />
      <div class="mt-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($problems as $problem)
          <div class="rounded-lg border border-border bg-background p-4 text-sm font-semibold text-foreground">
            {{ $problem }}
          </div>
        @endforeach
      </div>
    </div>
  </section>

  <section id="product-solution" class="border-y border-border bg-background py-16">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
      <x-public.section-heading
        eyebrow="Product solution"
        title="One platform for local operations and owner visibility"
        description="The Business Server keeps the business running locally while the Platform and Monitoring App provide synchronized oversight."
      />
      <div class="mt-8 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        @foreach ($solutions as $solution)
          <article class="rounded-lg border border-border bg-surface p-5 shadow-sm">
            <h3 class="text-base font-semibold text-foreground">{{ $solution['title'] }}</h3>
            <p class="mt-2 text-sm leading-6 text-muted">{{ $solution['body'] }}</p>
          </article>
        @endforeach
      </div>
    </div>
  </section>

  <section id="how-it-works" class="bg-surface py-16" x-data="{ activeStep: 0 }">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
      <x-public.section-heading
        eyebrow="How MarketLens works"
        title="Four applications, clear responsibilities"
        description="Use the stepper to see how business operations move from local computers to remote owner monitoring."
      />
      <div class="mt-8 grid gap-4 lg:grid-cols-4">
        @foreach ($steps as $index => $step)
          <button
            type="button"
            class="rounded-lg border p-5 text-left shadow-sm"
            x-on:click="activeStep = {{ $index }}"
            x-bind:class="activeStep === {{ $index }} ? 'border-accent bg-accent/10' : 'border-border bg-background'"
            :aria-current="activeStep === {{ $index }} ? 'step' : false"
          >
            <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-surface text-accent">
              <x-public.icon :name="$step['icon']" class="h-5 w-5" />
            </span>
            <span class="mt-4 block text-sm font-bold text-foreground">Step {{ $index + 1 }}</span>
            <span class="mt-2 block text-base font-semibold text-foreground">{{ $step['title'] }}</span>
            <span class="mt-2 block text-sm leading-6 text-muted">{{ $step['body'] }}</span>
          </button>
        @endforeach
      </div>
    </div>
  </section>

  <section id="features" class="border-y border-border bg-background py-16">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
      <x-public.section-heading
        eyebrow="Core features"
        title="Operational controls and owner reports in one architecture"
        description="The welcome page lists the core capability areas without implementing the full business workflows yet."
      />
      <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        @foreach ($features as [$feature, $icon])
          <div class="rounded-lg border border-border bg-surface p-4 shadow-sm">
            <x-public.icon :name="$icon" class="h-5 w-5 text-accent" />
            <p class="mt-3 text-sm font-semibold text-foreground">{{ $feature }}</p>
          </div>
        @endforeach
      </div>
    </div>
  </section>

  <section id="business-types" class="bg-surface py-16">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
      <x-public.section-heading
        eyebrow="Business types"
        title="Designed for practical retail environments"
        description="MarketLens supports the kinds of businesses where stock accuracy, cash control, and simple staff workflows matter every day."
      />
      <div class="mt-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        @foreach ($businessTypes as $type)
          <div class="rounded-lg border border-border bg-background px-4 py-3 text-sm font-semibold text-foreground">
            {{ $type }}
          </div>
        @endforeach
      </div>
    </div>
  </section>

  <section id="offline-first" class="border-y border-border bg-background py-16">
    <div class="mx-auto grid max-w-7xl gap-8 px-4 sm:px-6 lg:grid-cols-3 lg:px-8">
      <x-public.section-heading
        class="lg:col-span-1"
        eyebrow="Offline-first"
        title="The business keeps working when the internet does not"
      />
      <div class="grid gap-4 lg:col-span-2">
        <article class="rounded-lg border border-border bg-surface p-5 shadow-sm">
          <h3 class="text-base font-semibold">Business operations continue without internet.</h3>
          <p class="mt-2 text-sm leading-6 text-muted">Sales, inventory movements, expenses, shifts, and local reports remain available through the Business Server.</p>
        </article>
        <article class="rounded-lg border border-border bg-surface p-5 shadow-sm">
          <h3 class="text-base font-semibold">Cloud monitoring reflects the last synchronized records.</h3>
          <p class="mt-2 text-sm leading-6 text-muted">Owners see synchronized Platform data, so stale information is labeled clearly rather than hidden.</p>
        </article>
        <article class="rounded-lg border border-border bg-surface p-5 shadow-sm">
          <h3 class="text-base font-semibold">The Platform reports the last synchronization time.</h3>
          <p class="mt-2 text-sm leading-6 text-muted">Monitoring screens must show when each branch last synchronized successfully.</p>
        </article>
      </div>
    </div>
  </section>

  <section id="remote-monitoring" class="bg-surface py-16">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <x-public.section-heading
          eyebrow="Remote monitoring"
          title="Owner dashboard demonstration data"
          description="These metrics are clearly marked as sample data for the public welcome page."
        />
        <x-public.status-badge tone="warning">Demonstration data</x-public.status-badge>
      </div>
      <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($metrics as $metric)
          <article class="rounded-lg border border-border bg-background p-5 shadow-sm">
            <div class="flex items-start justify-between gap-3">
              <p class="text-sm font-medium text-muted">{{ $metric['label'] }}</p>
              <x-public.status-badge :tone="$metric['tone']">Demo</x-public.status-badge>
            </div>
            <p class="mt-3 text-2xl font-bold text-foreground">{{ $metric['value'] }}</p>
          </article>
        @endforeach
      </div>
    </div>
  </section>

  <section id="pricing" class="border-y border-border bg-background py-16">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
      <x-public.section-heading
        eyebrow="Pricing preview"
        title="Plan previews before final payment integration"
        description="These cards show intended subscription tiers only. Payment integration and final pricing are not implemented yet."
      />
      <div class="mt-8 grid gap-4 md:grid-cols-3">
        <x-public.pricing-card name="Starter" price="Preview" description="For one branch getting started.">Business Server, local users, products, sales, expenses, and basic synchronization.</x-public.pricing-card>
        <x-public.pricing-card name="Growth" price="Preview" description="For expanding retail teams." featured>Multiple Client Apps, owner dashboards, branch reports, and alerts.</x-public.pricing-card>
        <x-public.pricing-card name="Multi-branch" price="Preview" description="For businesses comparing branches.">Branch comparison, consolidated summaries, device activation, and monitoring access.</x-public.pricing-card>
      </div>
    </div>
  </section>

  <section id="faq" class="bg-surface py-16">
    <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
      <x-public.section-heading eyebrow="FAQ" title="Questions business owners ask first" />
      <div class="mt-8 grid gap-3">
        <x-public.faq-item question="Does each business need a domain?">No. Business operations run locally through the Business Server and authorized Client Apps.</x-public.faq-item>
        <x-public.faq-item question="Does each business need a cloud server?">No. The Platform is the cloud service; individual shops use their local Business Server.</x-public.faq-item>
        <x-public.faq-item question="Can the business operate without internet?">Yes. Daily transactions continue locally, then synchronize later.</x-public.faq-item>
        <x-public.faq-item question="Can multiple computers connect?">Yes. Multiple authorized Client Apps can connect to the Business Server over the local network.</x-public.faq-item>
        <x-public.faq-item question="Where are staff users created?">Operational staff users are created in the Business Server, not the Platform.</x-public.faq-item>
        <x-public.faq-item question="Can the owner monitor remotely?">Yes. Owners use the Monitoring App through synchronized Platform data.</x-public.faq-item>
        <x-public.faq-item question="What happens when synchronization is delayed?">The Monitoring App shows the last successful synchronization time so stale data is visible.</x-public.faq-item>
        <x-public.faq-item question="Is business data separated securely?">Yes. Platform records are tenant-scoped and branch-scoped by design.</x-public.faq-item>
        <x-public.faq-item question="Can a business manage multiple branches?">Yes. Multi-branch visibility and branch comparison are core MarketLens goals.</x-public.faq-item>
      </div>
    </div>
  </section>

  <section id="contact" class="border-t border-border bg-background py-16">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
      <x-public.section-heading
        eyebrow="Contact"
        title="Ready for pilot conversations"
        description="Use the calls to action below for onboarding, demonstration, and existing-user access once those flows are implemented."
      />
    </div>
  </section>

  <x-public.cta-section
    title="Start with clearer sales, stock, and branch visibility."
    description="Begin a free trial, request a demonstration, or log in when your MarketLens account is ready."
  >
  </x-public.cta-section>

  <section class="bg-primary px-4 pb-12 text-white sm:px-6 lg:px-8">
    <div class="mx-auto flex max-w-7xl flex-col gap-3 sm:flex-row">
      <a href="/register" class="inline-flex h-11 items-center justify-center rounded-md bg-white px-5 text-sm font-semibold text-primary">
        Start Free Trial
      </a>
      <a href="#contact" class="inline-flex h-11 items-center justify-center rounded-md border border-white/30 px-5 text-sm font-semibold text-white">
        Request Demonstration
      </a>
      <a href="/login" class="inline-flex h-11 items-center justify-center rounded-md px-5 text-sm font-semibold text-white hover:bg-white/10">
        Login
      </a>
    </div>
  </section>
</div>
