@php
  $metrics = $dashboard['today'] ?? [];
  $rangeMetrics = $dashboard['metrics'] ?? [];
  $freshness = $dashboard['freshness'] ?? [];
  $hasData = (bool) ($dashboard['hasData'] ?? false);
  $lastSync = $freshness['last_successful_sync_at'] ?? null;
@endphp

<div class="flex flex-col gap-6">
  <x-app.page-header
    title="Business overview"
    description="Synchronized sales, payment, stock, debt, cashier, and branch summaries from Business Servers."
  >
    <x-slot:actions>
      <x-app.status-badge tone="{{ ($freshness['is_stale'] ?? true) ? 'warning' : 'success' }}">
        {{ ($freshness['is_stale'] ?? true) ? 'Stale data' : 'Fresh data' }}
      </x-app.status-badge>
    </x-slot:actions>
  </x-app.page-header>

  <x-app.stale-data-warning :last-synced-at="$lastSync ? $lastSync->diffForHumans() : 'Awaiting first sync'" />

  @unless ($hasData)
    <x-app.empty-state
      title="No synchronized dashboard summaries yet"
      message="This dashboard stays empty until a Business Server synchronizes summary records for the selected business and branch."
    />
  @endunless

  <section id="performance-summary" class="grid gap-4 md:grid-cols-2 xl:grid-cols-4" aria-label="Performance summary">
    @foreach ([
      'Today gross sales' => $this->money($metrics['gross_sales_minor'] ?? 0),
      'Today net sales' => $this->money($metrics['net_sales_minor'] ?? 0),
      'Completed sales' => $this->number($metrics['completed_sales_count'] ?? 0),
      'Gross profit' => $this->money($metrics['gross_profit_minor'] ?? 0),
      'Estimated net profit' => $this->money($metrics['estimated_net_profit_minor'] ?? 0),
      'Expenses' => $this->money($metrics['expenses_minor'] ?? 0),
      'Customer debt' => $this->money($metrics['customer_debt_minor'] ?? 0),
      'Supplier balance' => $this->money($metrics['supplier_balance_minor'] ?? 0),
      'Current stock value' => $this->money($metrics['current_stock_value_minor'] ?? 0),
      'Low-stock products' => $this->number($metrics['low_stock_products_count'] ?? 0),
      'Cashier shortage/surplus' => $this->money($metrics['cashier_variance_minor'] ?? 0),
      'Last successful sync' => $lastSync ? $lastSync->format('M j, Y H:i') : 'Not synchronized',
    ] as $label => $value)
      <x-app.card>
        <p class="text-sm font-medium text-muted">{{ $label }}</p>
        <p class="mt-2 font-mono text-2xl font-bold tabular-nums text-foreground">{{ $value }}</p>
      </x-app.card>
    @endforeach
  </section>

  <section class="grid gap-4 lg:grid-cols-2">
    <x-app.card title="Payment breakdown" description="From synchronized payment summaries">
      <div class="grid gap-3 sm:grid-cols-2">
        @foreach ([
          'Cash payments' => $metrics['cash_payments_minor'] ?? 0,
          'Bank-transfer payments' => $metrics['bank_transfer_payments_minor'] ?? 0,
          'POS-terminal payments' => $metrics['pos_terminal_payments_minor'] ?? 0,
          'Credit sales' => $metrics['credit_sales_minor'] ?? 0,
        ] as $label => $amount)
          <div class="rounded-md border border-border bg-surface-muted p-3">
            <p class="text-xs font-medium text-muted">{{ $label }}</p>
            <p class="mt-1 font-mono text-lg font-semibold">{{ $this->money($amount) }}</p>
          </div>
        @endforeach
      </div>
    </x-app.card>

    <x-app.card title="Comparisons" description="Net sales from summary tables">
      <div class="space-y-3">
        @foreach ([
          'Today versus yesterday' => 'today_vs_yesterday',
          'Current week versus previous week' => 'week_vs_previous_week',
          'Current month versus previous month' => 'month_vs_previous_month',
        ] as $label => $key)
          @php($comparison = $dashboard['comparisons'][$key] ?? ['current' => 0, 'previous' => 0, 'difference' => 0])
          <div class="flex items-center justify-between gap-4 rounded-md border border-border p-3">
            <div>
              <p class="text-sm font-medium text-foreground">{{ $label }}</p>
              <p class="text-xs text-muted">Previous: {{ $this->money($comparison['previous']) }}</p>
            </div>
            <div class="text-right">
              <p class="font-mono text-sm font-semibold">{{ $this->money($comparison['current']) }}</p>
              <x-app.status-badge tone="{{ $comparison['difference'] >= 0 ? 'success' : 'danger' }}">
                {{ $this->money($comparison['difference']) }}
              </x-app.status-badge>
            </div>
          </div>
        @endforeach
      </div>
    </x-app.card>
  </section>

  <section class="grid gap-4 xl:grid-cols-[1.5fr_1fr]">
    <x-app.card title="Sales trend" description="Selected date-range summary">
      @if (empty($dashboard['salesTrend']))
        <x-app.empty-state title="No sales trend yet" message="No summary rows exist for the selected date range." />
      @else
        <div class="space-y-3">
          @foreach ($dashboard['salesTrend'] as $point)
            <div>
              <div class="flex justify-between text-sm">
                <span>{{ $point['date'] }}</span>
                <span class="font-mono">{{ $this->money($point['net_sales_minor']) }}</span>
              </div>
              <div class="mt-1 h-2 rounded bg-surface-muted">
                <div class="h-2 rounded bg-primary" style="width: {{ min(100, max(4, ($point['net_sales_minor'] / max(1, $rangeMetrics['net_sales_minor'] ?? 1)) * 100)) }}%"></div>
              </div>
            </div>
          @endforeach
        </div>
      @endif
    </x-app.card>

    <x-app.card title="Synchronization health" description="Data freshness">
      <x-app.sync-indicator state="{{ ($freshness['is_stale'] ?? true) ? 'delayed' : 'synced' }}" :last-synced-at="$lastSync ? $lastSync->format('M j, Y H:i') : 'Not synchronized'" />
      <p class="mt-3 text-sm text-muted">Dashboard metrics are read from summary tables and cache. Raw synchronized records are not scanned on page load.</p>
      <a href="{{ route('platform.support', ['category' => 'synchronization']) }}" class="mt-3 inline-flex rounded-md border border-border px-3 py-2 text-sm font-semibold text-foreground hover:border-primary/50">Get synchronization help</a>
    </x-app.card>
  </section>

  <section class="grid gap-4 lg:grid-cols-2">
    <x-app.card title="Stock alerts" description="Low-stock products">
      @forelse ($dashboard['stockAlerts'] ?? [] as $product)
        <div class="flex justify-between gap-4 border-b border-border py-2 last:border-0">
          <span class="text-sm font-medium">{{ $product['product_name'] }}</span>
          <span class="font-mono text-sm text-warning">{{ $product['stock_quantity'] }}</span>
        </div>
      @empty
        <x-app.empty-state title="No stock alerts" message="Low-stock summary records have not been synchronized." />
      @endforelse
    </x-app.card>

    <x-app.card title="Outstanding customer debts" description="Ledger summary">
      <p class="font-mono text-3xl font-bold">{{ $this->money($metrics['customer_debt_minor'] ?? 0) }}</p>
      <p class="mt-2 text-sm text-muted">Debt balances come from synchronized ledger summaries, not recalculated raw debt transactions.</p>
    </x-app.card>
  </section>

  <section class="grid gap-4 lg:grid-cols-2">
    <x-app.card title="Top-selling products" description="By quantity sold">
      @forelse ($dashboard['topProducts'] ?? [] as $product)
        <div class="flex justify-between gap-4 border-b border-border py-2 last:border-0">
          <span class="text-sm font-medium">{{ $product['product_name'] }}</span>
          <span class="font-mono text-sm">{{ $product['quantity_sold'] }}</span>
        </div>
      @empty
        <x-app.empty-state title="No top products yet" message="Product summary records are not available for this date range." />
      @endforelse
    </x-app.card>

    <x-app.card title="Slow-moving products" description="Products with days without sale">
      @forelse ($dashboard['slowProducts'] ?? [] as $product)
        <div class="flex justify-between gap-4 border-b border-border py-2 last:border-0">
          <span class="text-sm font-medium">{{ $product['product_name'] }}</span>
          <span class="font-mono text-sm">{{ $product['days_without_sale'] }} days</span>
        </div>
      @empty
        <x-app.empty-state title="No slow-moving products yet" message="Slow-moving product summaries have not been synchronized." />
      @endforelse
    </x-app.card>
  </section>

  <section class="grid gap-4 lg:grid-cols-2">
    <x-app.card title="Branch performance" description="Branch summary comparison">
      @forelse ($dashboard['branchPerformance'] ?? [] as $branchSummary)
        <div class="flex justify-between gap-4 border-b border-border py-2 last:border-0">
          <span class="text-sm font-medium">{{ $branchSummary['branch_name'] }}</span>
          <span class="font-mono text-sm">{{ $this->money($branchSummary['net_sales_minor']) }}</span>
        </div>
      @empty
        <x-app.empty-state title="No branch summaries yet" message="Branch performance requires synchronized branch summary rows." />
      @endforelse
    </x-app.card>

    <x-app.card title="Cashier exceptions" description="Shortage or surplus summaries">
      @forelse ($dashboard['cashierExceptions'] ?? [] as $exception)
        <div class="flex justify-between gap-4 border-b border-border py-2 last:border-0">
          <span class="text-sm font-medium">{{ $exception['cashier_name'] }}</span>
          <span class="font-mono text-sm">{{ $this->money($exception['variance_minor']) }}</span>
        </div>
      @empty
        <x-app.empty-state title="No cashier exceptions" message="No cashier shortage or surplus summaries are available." />
      @endforelse
    </x-app.card>
  </section>

  <section class="grid gap-4 lg:grid-cols-2">
    <x-app.card title="Recent security alerts" description="Synchronized alert activity">
      @forelse ($dashboard['securityAlerts'] ?? [] as $activity)
        <div class="border-b border-border py-2 last:border-0">
          <p class="text-sm font-medium">{{ $activity['title'] }}</p>
          <p class="text-xs text-muted">{{ $activity['occurred_at'] }}</p>
        </div>
      @empty
        <x-app.empty-state title="No security alerts" message="Security alert summaries have not been synchronized." />
      @endforelse
    </x-app.card>

    <x-app.card title="Recent business activity" description="Latest synchronized events">
      @forelse ($dashboard['recentActivity'] ?? [] as $activity)
        <div class="border-b border-border py-2 last:border-0">
          <p class="text-sm font-medium">{{ $activity['title'] }}</p>
          <p class="text-xs text-muted">{{ $activity['occurred_at'] }}</p>
        </div>
      @empty
        <x-app.empty-state title="No recent activity" message="Activity summaries will appear after synchronization." />
      @endforelse
    </x-app.card>
  </section>
</div>

