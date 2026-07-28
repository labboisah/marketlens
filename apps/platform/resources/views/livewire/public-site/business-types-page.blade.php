@php
  $profiles = [
      ['name' => 'Supermarket', 'challenges' => 'High sales volume, many cashiers, fast-moving stock, mixed payments, returns, and branch comparison.', 'modules' => 'POS, inventory, cashier shifts, expenses, customer debts, reports, synchronization, remote monitoring.', 'later' => 'Loyalty programs, shelf-label printing, advanced promotion rules, and automated reorder suggestions.'],
      ['name' => 'Provision shop', 'challenges' => 'Simple teams still need accurate sales, stock, debt, and expense records.', 'modules' => 'Sales, products, units, inventory movements, customer debts, expenses, local reports.', 'later' => 'Ultra-simple cashier mode, low-cost receipt templates, and guided stock count screens.'],
      ['name' => 'Wholesale distributor', 'challenges' => 'Bulk quantities, customer credit, supplier balances, warehouse stock, and delivery coordination.', 'modules' => 'Purchases, supplier accounts, customer debts, inventory, warehouses, reports.', 'later' => 'Delivery manifests, bulk price tiers, van-sales support, and customer order reservations.'],
      ['name' => 'Pharmacy', 'challenges' => 'Batch tracking, expiry awareness, controlled stock, and high accuracy requirements.', 'modules' => 'Products, product batches, inventory movements, purchases, sales, audit logs.', 'later' => 'Prescription processing, expiry-first picking, regulated product controls, and pharmacist approvals.'],
      ['name' => 'Building materials', 'challenges' => 'Large items, partial quantities, supplier credit, delivery arrangements, and stock yard differences.', 'modules' => 'Units, inventory, purchases, supplier accounts, customer debts, expenses.', 'later' => 'Delivery scheduling, measurement conversions, project quotations, and yard stock maps.'],
      ['name' => 'Electronics', 'challenges' => 'Serial numbers, warranty expectations, returns, supplier records, and expensive inventory.', 'modules' => 'Product serial numbers, sales, purchases, returns, audit logs, reports.', 'later' => 'Warranty registry, repair intake, serial-number scanning, and accessory bundles.'],
      ['name' => 'Cosmetics', 'challenges' => 'Variants, brands, batches, promotions, expiry dates, and many small SKUs.', 'modules' => 'Brands, categories, product units, barcodes, stock movements, sales reports.', 'later' => 'Variant matrix, promotion campaigns, beauty-service add-ons, and expiry alerts.'],
      ['name' => 'Agricultural products', 'challenges' => 'Seasonal demand, weighted quantities, supplier purchases, and variable packaging.', 'modules' => 'Units, purchases, inventory, supplier accounts, expenses, branch reporting.', 'later' => 'Seasonal forecasting, weight-scale integration, lot grading, and farmer supplier profiles.'],
      ['name' => 'Spare parts', 'challenges' => 'Many SKUs, compatibility questions, customer credit, damaged goods, and slow-moving stock.', 'modules' => 'Product search, categories, stock counts, customer debts, damaged goods, reports.', 'later' => 'Vehicle compatibility, substitute parts, special orders, and barcode relabeling.'],
      ['name' => 'Bookshop and stationery', 'challenges' => 'School-season spikes, ISBN/barcode handling, supplier credit, and category-heavy stock.', 'modules' => 'Products, barcodes, purchases, sales, inventory counts, expenses, reports.', 'later' => 'ISBN lookup, class-list bundles, school account management, and seasonal pack templates.'],
  ];
@endphp

<div>
  <section class="border-b border-border bg-background">
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
      <x-public.breadcrumbs :items="[['label' => 'Business types']]" />
      <div class="mt-8 max-w-3xl">
        <x-public.status-badge tone="success">Business types</x-public.status-badge>
        <h1 class="mt-5 text-4xl font-bold tracking-normal text-foreground sm:text-5xl">Retail profiles MarketLens is designed to support.</h1>
        <p class="mt-5 text-lg leading-8 text-muted">Reusable business-type profiles explain common challenges, useful MarketLens modules, and later specialized features without adding those modules yet.</p>
      </div>
    </div>
  </section>

  <section class="bg-surface py-16">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
      <div class="grid gap-4 lg:grid-cols-2">
        @foreach ($profiles as $profile)
          <article class="rounded-lg border border-border bg-background p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-foreground">{{ $profile['name'] }}</h2>
            <dl class="mt-4 grid gap-3 text-sm leading-6">
              <div><dt class="font-semibold text-foreground">Common operational challenges</dt><dd class="text-muted">{{ $profile['challenges'] }}</dd></div>
              <div><dt class="font-semibold text-foreground">Useful MarketLens modules</dt><dd class="text-muted">{{ $profile['modules'] }}</dd></div>
              <div><dt class="font-semibold text-foreground">Specialized features that may be added later</dt><dd class="text-muted">{{ $profile['later'] }}</dd></div>
            </dl>
          </article>
        @endforeach
      </div>
    </div>
  </section>
</div>
