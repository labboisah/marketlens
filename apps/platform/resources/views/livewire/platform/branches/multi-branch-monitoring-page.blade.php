<div class="flex flex-col gap-6">
  <x-app.page-header
    title="Multi-branch monitoring"
    description="Compare synchronized branch performance, freshness, and health indicators. Stale branches are marked before ranking decisions are made."
  />

  <x-app.card title="Comparison filters" description="Choose the reporting period and ranking metric for branch comparison.">
    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
      <label class="text-sm font-medium">
        <span class="text-muted">Period</span>
        <select wire:model.live="period" class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
          <option value="today">Today</option>
          <option value="week">Week</option>
          <option value="month">Month</option>
          <option value="quarter">Quarter</option>
          <option value="custom">Custom date range</option>
        </select>
      </label>
      <label class="text-sm font-medium"><span class="text-muted">From</span><input type="date" wire:model.live="from" class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"></label>
      <label class="text-sm font-medium"><span class="text-muted">To</span><input type="date" wire:model.live="to" class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"></label>
      <label class="text-sm font-medium">
        <span class="text-muted">Rank by</span>
        <select wire:model.live="rankingMetric" class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
          @foreach ($rankingOptions as $value => $label)
            <option value="{{ $value }}">{{ $label }}</option>
          @endforeach
        </select>
      </label>
      <label class="text-sm font-medium">
        <span class="text-muted">Branch detail</span>
        <select wire:model.live="branchFilter" class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
          <option value="">Top branch</option>
          @foreach ($branches as $branch)
            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
          @endforeach
        </select>
      </label>
    </div>
  </x-app.card>

  @if ($accessDenied ?? false)
    <x-app.permission-denied required="Multiple branches, reports, or remote dashboard" title="Multi-branch monitoring is not available" message="The current subscription or authorization does not allow access to synchronized branch performance monitoring." />
  @elseif (! $businessId)
    <x-app.empty-state title="No business selected" message="Select a business before comparing synchronized branch performance." />
  @else
    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
      @foreach ([
        'Branches compared' => number_format($data['health']['branch_count'] ?? 0),
        'Fresh branches' => number_format($data['health']['fresh_count'] ?? 0),
        'Stale branches' => number_format($data['health']['stale_count'] ?? 0),
        'Branches without data' => number_format($data['health']['missing_data_count'] ?? 0),
      ] as $label => $value)
        <x-app.card>
          <p class="text-sm font-medium text-muted">{{ $label }}</p>
          <p class="mt-2 font-mono text-2xl font-bold">{{ $value }}</p>
        </x-app.card>
      @endforeach
    </section>

    <section class="grid gap-4 xl:grid-cols-4">
      @forelse ($data['cards'] as $branch)
        <x-app.card>
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="font-semibold">{{ $branch['branch_name'] }}</p>
              <p class="text-xs text-muted">{{ $branch['branch_code'] ?? 'No code' }}</p>
            </div>
            <x-app.status-badge tone="{{ $branch['is_stale'] ? 'warning' : ($branch['health'] === 'Requires review' ? 'warning' : 'success') }}">{{ $branch['health'] }}</x-app.status-badge>
          </div>
          <dl class="mt-4 grid gap-3 text-sm">
            <div class="flex justify-between gap-4"><dt class="text-muted">Net sales</dt><dd class="font-mono">{{ $this->money($branch['net_sales_minor']) }}</dd></div>
            <div class="flex justify-between gap-4"><dt class="text-muted">Gross profit</dt><dd class="font-mono">{{ $this->money($branch['gross_profit_minor']) }}</dd></div>
            <div class="flex justify-between gap-4"><dt class="text-muted">Low stock</dt><dd class="font-mono">{{ number_format($branch['low_stock_count']) }}</dd></div>
            <div class="flex justify-between gap-4"><dt class="text-muted">Last synchronization</dt><dd>{{ $branch['last_successful_sync_at']?->format('M j, Y H:i') ?? 'Not synchronized' }}</dd></div>
          </dl>
        </x-app.card>
      @empty
        <x-app.empty-state title="No branch summaries" message="Branch performance cards appear after Shop Apps synchronize branch summary records." />
      @endforelse
    </section>

    <x-app.card title="Branch comparison table" description="Performance, balances, stock, cash variance, devices, and synchronization freshness by branch.">
      <x-app.table density="compact">
        <thead><tr><th>Branch</th><th>Gross sales</th><th>Net sales</th><th>Transactions</th><th>Gross profit</th><th>Expenses</th><th>Est. net profit</th><th>Stock value</th><th>Customer debt</th><th>Supplier balance</th><th>Cash shortage</th><th>Low stock</th><th>Last sync</th><th>Client Apps</th><th>Health</th></tr></thead>
        <tbody>
          @forelse ($data['comparison'] as $branch)
            <tr>
              <td>{{ $branch['branch_name'] }}</td>
              <td class="font-mono">{{ $this->money($branch['gross_sales_minor']) }}</td>
              <td class="font-mono">{{ $this->money($branch['net_sales_minor']) }}</td>
              <td class="font-mono">{{ number_format($branch['transaction_count']) }}</td>
              <td class="font-mono">{{ $this->money($branch['gross_profit_minor']) }}</td>
              <td class="font-mono">{{ $this->money($branch['expenses_minor']) }}</td>
              <td class="font-mono">{{ $this->money($branch['estimated_net_profit_minor']) }}</td>
              <td class="font-mono">{{ $this->money($branch['stock_value_minor']) }}</td>
              <td class="font-mono">{{ $this->money($branch['customer_debt_minor']) }}</td>
              <td class="font-mono">{{ $this->money($branch['supplier_balance_minor']) }}</td>
              <td class="font-mono">{{ $this->money($branch['cashier_variance_minor']) }}</td>
              <td class="font-mono">{{ number_format($branch['low_stock_count']) }}</td>
              <td>{{ $branch['last_successful_sync_at']?->format('M j, Y H:i') ?? 'Not synchronized' }}</td>
              <td class="font-mono">{{ number_format($branch['active_client_apps_count']) }}</td>
              <td><x-app.status-badge tone="{{ $branch['is_stale'] ? 'warning' : ($branch['health'] === 'Requires review' ? 'warning' : 'success') }}">{{ $branch['is_stale'] ? 'Stale data warning' : $branch['health'] }}</x-app.status-badge></td>
            </tr>
          @empty
            <tr><td colspan="15"><x-app.empty-state title="No branch comparison data" message="Synchronized branch summaries are not available for this period." /></td></tr>
          @endforelse
        </tbody>
      </x-app.table>
    </x-app.card>

    <section class="grid gap-4 xl:grid-cols-[0.9fr_1.1fr]">
      <x-app.card title="Branch ranking" description="Stale branches are ranked below fresh branches and marked with warnings.">
        <div class="space-y-3">
          @foreach ($data['ranking'] as $index => $branch)
            <div class="flex items-center justify-between gap-4 rounded-md border border-border p-3">
              <div>
                <p class="font-semibold">#{{ $index + 1 }} {{ $branch['branch_name'] }}</p>
                <p class="text-xs text-muted">{{ $rankingOptions[$rankingMetric] ?? 'Net sales' }}{{ $branch['is_stale'] ? ' - stale data warning' : '' }}</p>
              </div>
              <div class="text-right">
                <p class="font-mono text-sm font-semibold">
                  @if (str_ends_with($rankingMetric, '_count') || $rankingMetric === 'transaction_count' || $rankingMetric === 'active_client_apps_count')
                    {{ number_format($branch[$rankingMetric] ?? 0) }}
                  @else
                    {{ $this->money($branch[$rankingMetric] ?? 0) }}
                  @endif
                </p>
                <x-app.status-badge tone="{{ $branch['is_stale'] ? 'warning' : 'success' }}">{{ $branch['is_stale'] ? 'Stale' : 'Fresh' }}</x-app.status-badge>
              </div>
            </div>
          @endforeach
        </div>
      </x-app.card>

      <x-app.card title="Branch trend comparison" description="Daily synchronized branch trend rows for the selected period.">
        <x-app.table density="compact">
          <thead><tr><th>Date</th><th>Branch</th><th>Net sales</th><th>Gross profit</th><th>Expenses</th><th>Low stock</th></tr></thead>
          <tbody>
            @forelse ($data['trend'] as $row)
              <tr><td>{{ $row['date'] }}</td><td>{{ $row['branch_name'] }}</td><td class="font-mono">{{ $this->money($row['net_sales_minor']) }}</td><td class="font-mono">{{ $this->money($row['gross_profit_minor']) }}</td><td class="font-mono">{{ $this->money($row['expenses_minor']) }}</td><td class="font-mono">{{ number_format($row['low_stock_count']) }}</td></tr>
            @empty
              <tr><td colspan="6"><x-app.empty-state title="No branch trend" message="Branch trend summaries have not synchronized for this period." /></td></tr>
            @endforelse
          </tbody>
        </x-app.table>
      </x-app.card>
    </section>

    @if ($data['selectedBranch'])
      <x-app.card title="Branch detail dashboard" description="Focused detail for the selected branch.">
        @php($branch = $data['selectedBranch'])
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
          @foreach ([
            'Branch' => $branch['branch_name'],
            'Gross sales' => $this->money($branch['gross_sales_minor']),
            'Net sales' => $this->money($branch['net_sales_minor']),
            'Gross profit' => $this->money($branch['gross_profit_minor']),
            'Expenses' => $this->money($branch['expenses_minor']),
            'Estimated net profit' => $this->money($branch['estimated_net_profit_minor']),
            'Stock value' => $this->money($branch['stock_value_minor']),
            'Customer debt' => $this->money($branch['customer_debt_minor']),
            'Supplier balance' => $this->money($branch['supplier_balance_minor']),
            'Cash shortage' => $this->money($branch['cashier_variance_minor']),
            'Low-stock count' => number_format($branch['low_stock_count']),
            'Active Client Apps' => number_format($branch['active_client_apps_count']),
          ] as $label => $value)
            <div class="rounded-md border border-border p-3">
              <p class="text-xs font-medium text-muted">{{ $label }}</p>
              <p class="mt-1 font-mono text-lg font-semibold">{{ $value }}</p>
            </div>
          @endforeach
        </div>
        @if ($branch['is_stale'])
          <x-app.stale-data-warning class="mt-4" :last-synced-at="$branch['last_successful_sync_at']?->format('M j, Y H:i') ?? 'Not synchronized'" />
        @endif
      </x-app.card>
    @endif
  @endif
</div>
