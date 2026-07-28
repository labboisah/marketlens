@php
  $featureGroups = [
      ['name' => 'Sales', 'problem' => 'Sales can be missed, edited informally, or recorded without enough detail.', 'solution' => 'MarketLens records sales through controlled Shop App workflows and keeps completed sales as business history.', 'benefit' => 'Owners and managers see clearer daily revenue and cashier activity.'],
      ['name' => 'Payments', 'problem' => 'Cash, transfer, POS, credit, and mixed payments are hard to reconcile.', 'solution' => 'Payment records are linked to sales and cashier shifts with append-only handling.', 'benefit' => 'Cashier settlement and shortage review become easier.'],
      ['name' => 'Inventory', 'problem' => 'Direct quantity edits make stock balances unreliable.', 'solution' => 'MarketLens uses stock movements for sales, purchases, adjustments, counts, transfers, returns, and damage.', 'benefit' => 'Stock changes become auditable and easier to explain.'],
      ['name' => 'Purchases', 'problem' => 'Incoming stock and supplier transactions may not update stock and balances consistently.', 'solution' => 'Purchases create stock and supplier-account records through validated workflows.', 'benefit' => 'Managers understand stock replenishment and supplier exposure.'],
      ['name' => 'Expenses', 'problem' => 'Shop expenses are often outside the sales system.', 'solution' => 'Expenses are recorded locally and synchronized for owner reporting.', 'benefit' => 'Profit visibility improves because costs are not hidden.'],
      ['name' => 'Customer debts', 'problem' => 'Customer debt balances can become disputed when updates are informal.', 'solution' => 'Customer debt is ledger-based with transaction history.', 'benefit' => 'Balances are easier to trace and collect.'],
      ['name' => 'Supplier balances', 'problem' => 'Supplier obligations are difficult to monitor across purchases and payments.', 'solution' => 'Supplier accounts use transactions rather than manual balance overwrites.', 'benefit' => 'Purchasing decisions can consider current supplier exposure.'],
      ['name' => 'Staff accountability', 'problem' => 'It is hard to know who approved, sold, adjusted, or changed records.', 'solution' => 'Roles, permissions, sessions, and audit logs support accountable workflows.', 'benefit' => 'Managers can review important actions with context.'],
      ['name' => 'Cashier shifts', 'problem' => 'Shortages and settlements are difficult without shift boundaries.', 'solution' => 'Cashier shifts group sales, payments, expected cash, counted cash, and settlement review.', 'benefit' => 'Cash control becomes structured and fairer.'],
      ['name' => 'Reporting', 'problem' => 'Owners need summaries without pulling the full transaction database.', 'solution' => 'MarketLens prepares operational and cloud summaries for reports.', 'benefit' => 'Reports stay faster and easier to read.'],
      ['name' => 'Synchronization', 'problem' => 'Unreliable internet can duplicate or lose records.', 'solution' => 'Queued synchronization uses batches, UUIDs, versions, and idempotency.', 'benefit' => 'Cloud reporting catches up safely after outages.'],
      ['name' => 'Remote monitoring', 'problem' => 'Owners cannot always visit every branch.', 'solution' => 'The Monitoring App reads Platform summaries and last synchronization status.', 'benefit' => 'Owners can compare branches and spot issues remotely.'],
      ['name' => 'Security', 'problem' => 'Unauthorized devices and weak access control create business risk.', 'solution' => 'Device authorization, role permissions, validation, tenant scoping, and branch scoping protect records.', 'benefit' => 'Access is controlled at the right boundary.'],
      ['name' => 'Backup and recovery', 'problem' => 'Local shop data must survive machine problems.', 'solution' => 'The Shop App foundation includes backup and restore services with documented recovery expectations.', 'benefit' => 'Business continuity improves after device failure.'],
  ];
@endphp

<div>
  <section class="border-b border-border bg-background">
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
      <x-public.breadcrumbs :items="[['label' => 'Features']]" />
      <div class="mt-8 max-w-3xl">
        <x-public.status-badge tone="teal">Features</x-public.status-badge>
        <h1 class="mt-5 text-4xl font-bold tracking-normal text-foreground sm:text-5xl">Features organized around real retail control.</h1>
        <p class="mt-5 text-lg leading-8 text-muted">Each MarketLens feature group connects a business problem to a practical operational workflow and a clear owner benefit.</p>
      </div>
    </div>
  </section>

  <section class="bg-surface py-16">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($featureGroups as $feature)
          <article class="rounded-lg border border-border bg-background p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-foreground">{{ $feature['name'] }}</h2>
            <dl class="mt-4 grid gap-3 text-sm leading-6">
              <div><dt class="font-semibold text-foreground">Business problem</dt><dd class="text-muted">{{ $feature['problem'] }}</dd></div>
              <div><dt class="font-semibold text-foreground">MarketLens solution</dt><dd class="text-muted">{{ $feature['solution'] }}</dd></div>
              <div><dt class="font-semibold text-foreground">User benefit</dt><dd class="text-muted">{{ $feature['benefit'] }}</dd></div>
            </dl>
          </article>
        @endforeach
      </div>
    </div>
  </section>
</div>
