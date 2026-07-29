@php
  $views = [
    'cashiers' => 'Cashier performance',
    'storekeepers' => 'Storekeeper activity',
    'managers' => 'Manager activity',
    'exceptions' => 'Review indicators',
    'activity' => 'Activity summaries',
  ];
@endphp

<div class="flex flex-col gap-6">
  <x-app.page-header
    title="Staff performance monitoring"
    description="Read-only cashier and operational-staff indicators from synchronized Shop App records. Indicators highlight activity that may require review; they are not automatic accusations of fraud."
  >
    <x-slot:actions>
      <button type="button" wire:click="exportCsv" class="rounded-md border border-border px-3 py-2 text-sm font-semibold hover:bg-surface-muted focus:outline-none focus:ring-2 focus:ring-primary">CSV export</button>
      <button type="button" wire:click="exportPdfFoundation" class="rounded-md border border-border px-3 py-2 text-sm font-semibold hover:bg-surface-muted focus:outline-none focus:ring-2 focus:ring-primary">PDF foundation</button>
    </x-slot:actions>
  </x-app.page-header>

  <x-app.card title="Filters" description="Filter synchronized staff monitoring by branch, date range, role, or staff profile.">
    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
      <label class="text-sm font-medium"><span class="text-muted">From</span><input type="date" wire:model.live="from" class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"></label>
      <label class="text-sm font-medium"><span class="text-muted">To</span><input type="date" wire:model.live="to" class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"></label>
      <label class="text-sm font-medium">
        <span class="text-muted">Branch</span>
        <select wire:model.live="branchFilter" class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
          <option value="">Selected branch</option>
          @foreach ($branches as $branch)
            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
          @endforeach
        </select>
      </label>
      <label class="text-sm font-medium"><span class="text-muted">Role</span><input type="search" wire:model.live.debounce.300ms="role" placeholder="Cashier, storekeeper, manager" class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"></label>
      <label class="text-sm font-medium"><span class="text-muted">Staff</span><input type="search" wire:model.live.debounce.300ms="staff" placeholder="Name, code, or UUID" class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"></label>
    </div>
  </x-app.card>

  <nav class="flex flex-wrap gap-2" aria-label="Staff monitoring views">
    @foreach ($views as $key => $label)
      <button type="button" wire:click="$set('view', '{{ $key }}')" class="rounded-md border px-3 py-2 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-primary {{ $view === $key ? 'border-primary bg-primary text-white' : 'border-border bg-surface hover:bg-surface-muted' }}">{{ $label }}</button>
    @endforeach
  </nav>

  @if ($accessDenied ?? false)
    <x-app.permission-denied required="Cashier shifts, reports, or staff accountability" title="Staff performance monitoring is not available" message="The current subscription or authorization does not allow access to synchronized staff monitoring." />
  @elseif (! $businessId)
    <x-app.empty-state title="No business selected" message="Select a business before viewing synchronized staff monitoring." />
  @else
    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
      @foreach ([
        'Staff profiles' => number_format($overview['staff_count'] ?? 0),
        'Cashiers' => number_format($overview['cashier_count'] ?? 0),
        'Storekeepers' => number_format($overview['storekeeper_count'] ?? 0),
        'Managers' => number_format($overview['manager_count'] ?? 0),
        'Total cashier sales' => $this->money($overview['total_sales_minor'] ?? 0),
        'Cash shortages' => $this->money($overview['shortage_minor'] ?? 0),
        'Cash surplus' => $this->money($overview['surplus_minor'] ?? 0),
        'Last synchronization' => $latestSynchronization?->format('M j, Y H:i') ?? 'No synchronized activity',
      ] as $label => $value)
        <x-app.card>
          <p class="text-sm font-medium text-muted">{{ $label }}</p>
          <p class="mt-2 font-mono text-2xl font-bold">{{ $value }}</p>
        </x-app.card>
      @endforeach
    </section>

    @if ($view === 'cashiers')
      <x-app.card title="Cashier performance" description="Sales, cash reconciliation, shift duration, and synchronization freshness by cashier.">
        <x-app.table density="compact">
          <thead><tr><th>Cashier</th><th>Total sales</th><th>Transactions</th><th>Average sale</th><th>Discounts</th><th>Returns</th><th>Cancelled</th><th>Expected cash</th><th>Actual cash</th><th>Shortage</th><th>Surplus</th><th>Shift duration</th><th>Last active shift</th><th>Last synchronization</th></tr></thead>
          <tbody>
            @forelse ($cashiers as $cashier)
              <tr>
                <td>{{ $cashier['cashier_name'] }}</td>
                <td class="font-mono">{{ $this->money($cashier['total_sales_minor']) }}</td>
                <td class="font-mono">{{ number_format($cashier['transaction_count']) }}</td>
                <td class="font-mono">{{ $this->money($cashier['average_sale_minor']) }}</td>
                <td class="font-mono">{{ $this->money($cashier['discounts_minor']) }}</td>
                <td class="font-mono">{{ number_format($cashier['returned_count']) }}</td>
                <td class="font-mono">{{ number_format($cashier['cancelled_count']) }}</td>
                <td class="font-mono">{{ $this->money($cashier['expected_cash_minor']) }}</td>
                <td class="font-mono">{{ $this->money($cashier['actual_cash_minor']) }}</td>
                <td class="font-mono">{{ $this->money($cashier['shortage_minor']) }}</td>
                <td class="font-mono">{{ $this->money($cashier['surplus_minor']) }}</td>
                <td>{{ $this->minutes($cashier['shift_duration_minutes']) }}</td>
                <td>{{ $cashier['last_active_shift_at'] ? \Illuminate\Support\Carbon::parse($cashier['last_active_shift_at'])->format('M j, Y H:i') : 'No shift synced' }}</td>
                <td>{{ $cashier['last_synchronized_at'] ? \Illuminate\Support\Carbon::parse($cashier['last_synchronized_at'])->format('M j, Y H:i') : 'No sync yet' }}</td>
              </tr>
            @empty
              <tr><td colspan="14"><x-app.empty-state title="No cashier activity" message="Cashier metrics appear after Shop Apps synchronize sales, shifts, and settlements." /></td></tr>
            @endforelse
          </tbody>
        </x-app.table>
      </x-app.card>
    @elseif ($view === 'storekeepers')
      <x-app.card title="Storekeeper activity" description="Purchases and stock-control activity summarized from synchronized operational staff records.">
        <x-app.table density="compact">
          <thead><tr><th>Staff</th><th>Role</th><th>Purchases recorded</th><th>Stock counts</th><th>Stock adjustments</th><th>Damaged-stock records</th><th>Transfers processed</th><th>Last activity</th></tr></thead>
          <tbody>
            @forelse ($storekeepers as $row)
              <tr><td>{{ $row->display_name }}</td><td>{{ $row->role_name }}</td><td class="font-mono">{{ number_format($row->purchases_recorded_count) }}</td><td class="font-mono">{{ number_format($row->stock_counts_count) }}</td><td class="font-mono">{{ number_format($row->stock_adjustments_count) }}</td><td class="font-mono">{{ number_format($row->damaged_stock_records_count) }}</td><td class="font-mono">{{ number_format($row->transfers_processed_count) }}</td><td>{{ $row->last_activity_at ? \Illuminate\Support\Carbon::parse($row->last_activity_at)->format('M j, Y H:i') : 'No activity synced' }}</td></tr>
            @empty
              <tr><td colspan="8"><x-app.empty-state title="No storekeeper activity" message="Storekeeper monitoring appears after activity summaries synchronize from Shop Apps." /></td></tr>
            @endforelse
          </tbody>
        </x-app.table>
      </x-app.card>
    @elseif ($view === 'managers')
      <x-app.card title="Manager activity" description="Approval and review actions summarized from synchronized operational staff records.">
        <x-app.table density="compact">
          <thead><tr><th>Staff</th><th>Role</th><th>Discounts approved</th><th>Expenses approved</th><th>Returns approved</th><th>Stock adjustments approved</th><th>Cashier settlements reviewed</th><th>Last activity</th></tr></thead>
          <tbody>
            @forelse ($managers as $row)
              <tr><td>{{ $row->display_name }}</td><td>{{ $row->role_name }}</td><td class="font-mono">{{ number_format($row->discounts_approved_count) }}</td><td class="font-mono">{{ number_format($row->expenses_approved_count) }}</td><td class="font-mono">{{ number_format($row->returns_approved_count) }}</td><td class="font-mono">{{ number_format($row->stock_adjustments_approved_count) }}</td><td class="font-mono">{{ number_format($row->cashier_settlements_reviewed_count) }}</td><td>{{ $row->last_activity_at ? \Illuminate\Support\Carbon::parse($row->last_activity_at)->format('M j, Y H:i') : 'No activity synced' }}</td></tr>
            @empty
              <tr><td colspan="8"><x-app.empty-state title="No manager activity" message="Manager approval monitoring appears after Shop Apps synchronize staff summaries." /></td></tr>
            @endforelse
          </tbody>
        </x-app.table>
      </x-app.card>
    @elseif ($view === 'exceptions')
      <section class="grid gap-4 lg:grid-cols-2">
        @foreach ([
          'Frequent shortages' => ['rows' => $exceptions['frequentShortages'] ?? collect(), 'tone' => 'warning', 'message' => 'Requires review'],
          'Excessive discounts' => ['rows' => $exceptions['excessiveDiscounts'] ?? collect(), 'tone' => 'warning', 'message' => 'Outside expected range'],
          'High cancellation rates' => ['rows' => $exceptions['highCancellations'] ?? collect(), 'tone' => 'danger', 'message' => 'Unusual activity'],
          'High return rates' => ['rows' => $exceptions['highReturns'] ?? collect(), 'tone' => 'warning', 'message' => 'Requires review'],
          'Repeated stock adjustments' => ['rows' => $exceptions['repeatedStockAdjustments'] ?? collect(), 'tone' => 'warning', 'message' => 'Outside expected range'],
          'Activity outside normal hours' => ['rows' => $exceptions['outsideNormalHours'] ?? collect(), 'tone' => 'warning', 'message' => 'Unusual activity'],
        ] as $title => $group)
          <x-app.card :title="$title" description="Monitoring indicator only. Review the synchronized Shop App records before taking action.">
            @forelse ($group['rows'] as $row)
              <div class="flex items-center justify-between gap-4 border-b border-border py-2 text-sm last:border-0">
                <div>
                  <p class="font-semibold">{{ is_array($row) ? $row['cashier_name'] : $row->display_name }}</p>
                  <p class="text-muted">{{ $group['message'] }}</p>
                </div>
                <x-app.status-badge :tone="$group['tone']">{{ $group['message'] }}</x-app.status-badge>
              </div>
            @empty
              <x-app.empty-state title="No review indicators" message="No synchronized staff activity currently falls outside the configured monitoring range." />
            @endforelse
          </x-app.card>
        @endforeach
      </section>
    @else
      <x-app.card title="Activity summaries" description="Latest synchronized operational staff summaries across cashier, storekeeper, and manager roles.">
        <x-app.table density="compact">
          <thead><tr><th>Staff</th><th>Role</th><th>Sales</th><th>Purchases</th><th>Stock adjustments</th><th>Approvals</th><th>Review indicators</th><th>Last activity</th></tr></thead>
          <tbody>
            @forelse ($activitySummaries as $row)
              <tr>
                <td>{{ $row->display_name }}</td>
                <td>{{ $row->role_name }}</td>
                <td class="font-mono">{{ number_format($row->sales_count) }}</td>
                <td class="font-mono">{{ number_format($row->purchases_recorded_count) }}</td>
                <td class="font-mono">{{ number_format($row->stock_adjustments_count) }}</td>
                <td class="font-mono">{{ number_format($row->discounts_approved_count + $row->expenses_approved_count + $row->returns_approved_count + $row->stock_adjustments_approved_count + $row->cashier_settlements_reviewed_count) }}</td>
                <td class="font-mono">{{ number_format($row->outside_normal_hours_count + $row->excessive_discount_count + $row->high_cancellation_count + $row->high_return_count + $row->repeated_stock_adjustment_count) }}</td>
                <td>{{ $row->last_activity_at?->format('M j, Y H:i') ?? 'No activity synced' }}</td>
              </tr>
            @empty
              <tr><td colspan="8"><x-app.empty-state title="No staff summaries" message="Staff summaries appear after Shop Apps synchronize operational activity summaries." /></td></tr>
            @endforelse
          </tbody>
        </x-app.table>
      </x-app.card>
    @endif
  @endif
</div>
