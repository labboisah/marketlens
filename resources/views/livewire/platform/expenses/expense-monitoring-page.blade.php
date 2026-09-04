@php
  $views = [
    'overview' => 'Expense overview',
    'category' => 'Expenses by category',
    'branch' => 'Expenses by branch',
    'creator' => 'Expenses by creator',
    'approved' => 'Approved expenses',
    'pending_rejected' => 'Pending or rejected expenses',
    'recurring' => 'Recurring-expense summaries',
    'trend' => 'Expense trend',
    'comparison' => 'Expense-to-sales comparison',
    'details' => 'Expense details',
  ];
@endphp

<div class="flex flex-col gap-6">
  <x-app.page-header
    title="Expense monitoring"
    description="Read-only expense reporting from synchronized Business Server records. Expense corrections and approvals remain local Business Server operations."
  >
    <x-slot:actions>
      <button type="button" wire:click="exportCsv" class="rounded-md border border-border px-3 py-2 text-sm font-semibold hover:bg-surface-muted focus:outline-none focus:ring-2 focus:ring-primary">CSV export</button>
      <button type="button" wire:click="exportPdfFoundation" class="rounded-md border border-border px-3 py-2 text-sm font-semibold hover:bg-surface-muted focus:outline-none focus:ring-2 focus:ring-primary">PDF foundation</button>
    </x-slot:actions>
  </x-app.page-header>

  <x-app.card title="Filters" description="Filter synchronized expenses by date range, branch, category, creator, status, or expense number.">
    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
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
      <label class="text-sm font-medium"><span class="text-muted">Category</span><input type="search" wire:model.live.debounce.300ms="category" placeholder="Name or UUID" class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"></label>
      <label class="text-sm font-medium"><span class="text-muted">Creator</span><input type="search" wire:model.live.debounce.300ms="creator" placeholder="Name or UUID" class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"></label>
      <label class="text-sm font-medium">
        <span class="text-muted">Status</span>
        <select wire:model.live="status" class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary">
          <option value="">Any</option>
          @foreach (['approved', 'pending', 'rejected', 'voided'] as $statusOption)
            <option value="{{ $statusOption }}">{{ str($statusOption)->replace('_', ' ')->title() }}</option>
          @endforeach
        </select>
      </label>
      <label class="text-sm font-medium"><span class="text-muted">Expense number</span><input type="search" wire:model.live.debounce.300ms="expenseNumber" class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"></label>
    </div>
  </x-app.card>

  <nav class="flex flex-wrap gap-2" aria-label="Expense monitoring views">
    @foreach ($views as $key => $label)
      <button type="button" wire:click="$set('view', '{{ $key }}')" class="rounded-md border px-3 py-2 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-primary {{ $view === $key ? 'border-primary bg-primary text-white' : 'border-border bg-surface hover:bg-surface-muted' }}">{{ $label }}</button>
    @endforeach
  </nav>

  @if ($accessDenied ?? false)
    <x-app.permission-denied required="Expenses or reports" title="Expense monitoring is not available" message="The current subscription or authorization does not allow access to synchronized expense monitoring." />
  @elseif (! $businessId)
    <x-app.empty-state title="No business selected" message="Select a business before viewing synchronized expense records." />
  @else
    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
      @foreach ([
        'Expense count' => number_format($overview['expense_count'] ?? 0),
        'Total expenses' => $this->money($overview['total_minor'] ?? 0),
        'Approved expenses' => $this->money($overview['approved_minor'] ?? 0),
        'Pending expenses' => $this->money($overview['pending_minor'] ?? 0),
        'Rejected expenses' => $this->money($overview['rejected_minor'] ?? 0),
        'Recurring expenses' => $this->money($overview['recurring_minor'] ?? 0),
        'Sales total' => $this->money($expenseToSales['sales_minor'] ?? 0),
        'Expense to sales' => isset($expenseToSales['ratio']) ? $expenseToSales['ratio'].'%' : 'No sales',
      ] as $label => $value)
        <x-app.card><p class="text-sm font-medium text-muted">{{ $label }}</p><p class="mt-2 font-mono text-2xl font-bold">{{ $value }}</p></x-app.card>
      @endforeach
    </section>

    @if ($view === 'details')
      <x-app.card title="Expense details" description="Synchronized expense metadata. This screen is read-only.">
        @if ($selectedExpense)
          <div class="grid gap-4 lg:grid-cols-3">
            <div>
              <p class="text-sm text-muted">Expense number</p><p class="font-semibold">{{ $selectedExpense->expense_number }}</p>
              <p class="text-sm text-muted">Branch</p><p>{{ $selectedExpense->branch?->name }}</p>
              <p class="text-sm text-muted">Category</p><p>{{ $selectedExpense->category_name }}</p>
              <p class="text-sm text-muted">Description</p><p>{{ $selectedExpense->description }}</p>
            </div>
            <div>
              <p class="text-sm text-muted">Amount</p><p class="font-mono">{{ $this->money($selectedExpense->amount_minor) }}</p>
              <p class="text-sm text-muted">Payment method</p><p>{{ str($selectedExpense->payment_method ?? 'not specified')->replace('_', ' ')->title() }}</p>
              <p class="text-sm text-muted">Creator</p><p>{{ $selectedExpense->creator_name ?? 'Unknown' }}</p>
              <p class="text-sm text-muted">Approver</p><p>{{ $selectedExpense->approver_name ?? 'Not approved' }}</p>
            </div>
            <div>
              <p class="text-sm text-muted">Status</p><p><x-app.status-badge tone="{{ $selectedExpense->status === 'approved' ? 'success' : ($selectedExpense->status === 'rejected' ? 'danger' : 'warning') }}">{{ str($selectedExpense->status)->title() }}</x-app.status-badge></p>
              <p class="text-sm text-muted">Date</p><p>{{ $selectedExpense->expense_date?->format('M j, Y H:i') }}</p>
              <p class="text-sm text-muted">Receipt attachment</p><p>{{ $selectedExpense->receipt_attachment_path ?? 'No receipt synchronized' }}</p>
              <p class="text-sm text-muted">Source device</p><p>{{ $selectedExpense->device?->name ?? $selectedExpense->device?->uuid ?? 'Unknown device' }}</p>
              <p class="text-sm text-muted">Synchronization timestamp</p><p>{{ $selectedExpense->updated_at?->format('M j, Y H:i') }}</p>
            </div>
          </div>
        @else
          <x-app.empty-state title="Expense not found" message="Search by expense number or UUID, then open the expense details." />
        @endif
      </x-app.card>
    @elseif ($view === 'category')
      <x-app.card title="Expenses by category" description="Synchronized expenses grouped by category.">
        <x-app.table density="compact"><thead><tr><th>Category</th><th>Count</th><th>Amount</th></tr></thead><tbody>@foreach ($categoryBreakdown as $row)<tr><td>{{ $row->category_name }}</td><td class="font-mono">{{ number_format($row->expense_count) }}</td><td class="font-mono">{{ $this->money($row->amount_minor) }}</td></tr>@endforeach</tbody></x-app.table>
      </x-app.card>
    @elseif ($view === 'branch')
      <x-app.card title="Expenses by branch" description="Branch comparison from synchronized expenses.">
        <x-app.table density="compact"><thead><tr><th>Branch</th><th>Count</th><th>Amount</th></tr></thead><tbody>@foreach ($branchComparison as $row)<tr><td>{{ $row->branch_name }}</td><td class="font-mono">{{ number_format($row->expense_count) }}</td><td class="font-mono">{{ $this->money($row->amount_minor) }}</td></tr>@endforeach</tbody></x-app.table>
      </x-app.card>
    @elseif ($view === 'creator')
      <x-app.card title="Expenses by creator" description="Expenses grouped by synchronized creator profile.">
        <x-app.table density="compact"><thead><tr><th>Creator</th><th>Count</th><th>Amount</th></tr></thead><tbody>@foreach ($creatorBreakdown as $row)<tr><td>{{ $row->creator_name }}</td><td class="font-mono">{{ number_format($row->expense_count) }}</td><td class="font-mono">{{ $this->money($row->amount_minor) }}</td></tr>@endforeach</tbody></x-app.table>
      </x-app.card>
    @elseif ($view === 'recurring')
      <x-app.card title="Recurring-expense summaries" description="Recurring expense totals when recurrence metadata is synchronized.">
        <x-app.table density="compact"><thead><tr><th>Period</th><th>Count</th><th>Amount</th></tr></thead><tbody>@foreach ($recurringSummary as $row)<tr><td>{{ str($row->recurring_period)->title() }}</td><td class="font-mono">{{ number_format($row->expense_count) }}</td><td class="font-mono">{{ $this->money($row->amount_minor) }}</td></tr>@endforeach</tbody></x-app.table>
      </x-app.card>
    @elseif ($view === 'trend')
      <x-app.card title="Expense trend" description="Daily synchronized expense totals for the selected date range.">
        <x-app.table density="compact"><thead><tr><th>Date</th><th>Count</th><th>Amount</th></tr></thead><tbody>@foreach ($trend as $row)<tr><td>{{ $row->expense_day }}</td><td class="font-mono">{{ number_format($row->expense_count) }}</td><td class="font-mono">{{ $this->money($row->amount_minor) }}</td></tr>@endforeach</tbody></x-app.table>
      </x-app.card>
    @elseif ($view === 'comparison')
      <x-app.card title="Expense-to-sales comparison" description="Compares synchronized expenses to synchronized completed sales for the same filter period.">
        <x-app.table density="compact"><thead><tr><th>Sales</th><th>Expenses</th><th>Expense ratio</th></tr></thead><tbody><tr><td class="font-mono">{{ $this->money($expenseToSales['sales_minor'] ?? 0) }}</td><td class="font-mono">{{ $this->money($expenseToSales['expenses_minor'] ?? 0) }}</td><td class="font-mono">{{ isset($expenseToSales['ratio']) ? $expenseToSales['ratio'].'%' : 'No sales' }}</td></tr></tbody></x-app.table>
      </x-app.card>
    @else
      <x-app.card title="{{ $views[$view] }}" description="Synchronized expenses. Monitoring is read-only.">
        <x-app.table density="compact">
          <thead><tr><th>Expense</th><th>Branch</th><th>Category</th><th>Description</th><th>Amount</th><th>Payment</th><th>Creator</th><th>Approver</th><th>Status</th><th>Date</th><th>Receipt</th><th>Sync</th></tr></thead>
          <tbody>
            @forelse ($transactions as $expense)
              <tr>
                <td><button type="button" wire:click="showDetails('{{ $expense->uuid }}')" class="font-semibold text-primary hover:underline">{{ $expense->expense_number }}</button></td>
                <td>{{ $expense->branch?->name }}</td>
                <td>{{ $expense->category_name }}</td>
                <td>{{ $expense->description }}</td>
                <td class="font-mono">{{ $this->money($expense->amount_minor) }}</td>
                <td>{{ str($expense->payment_method ?? 'not specified')->replace('_', ' ')->title() }}</td>
                <td>{{ $expense->creator_name }}</td>
                <td>{{ $expense->approver_name }}</td>
                <td><x-app.status-badge tone="{{ $expense->status === 'approved' ? 'success' : ($expense->status === 'rejected' ? 'danger' : 'warning') }}">{{ str($expense->status)->title() }}</x-app.status-badge></td>
                <td>{{ $expense->expense_date?->format('M j, Y H:i') }}</td>
                <td>{{ $expense->receipt_attachment_path ? 'Receipt synchronized' : 'No receipt' }}</td>
                <td>{{ $expense->updated_at?->format('M j, Y H:i') }}</td>
              </tr>
            @empty
              <tr><td colspan="12"><x-app.empty-state title="No synchronized expenses" message="Expenses appear here after Business Servers synchronize expense records." /></td></tr>
            @endforelse
          </tbody>
        </x-app.table>
        <div class="mt-4">{{ $transactions?->links() }}</div>
      </x-app.card>
    @endif
  @endif
</div>
