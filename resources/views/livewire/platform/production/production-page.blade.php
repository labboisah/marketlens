<div class="space-y-6">
    @php($recordCounts = $data['records']->countBy('kind'))
    <div>
        <h1 class="text-2xl font-semibold">{{ $sectionTitle }}</h1>
        @if (auth()->user()->hasBusinessPermission(app(\App\Domain\Tenancy\Services\BusinessContext::class)->id(), 'export-production'))<a class="mt-2 inline-block rounded border border-border px-3 py-2 text-sm" href="{{ route('platform.production.export') }}">Export permitted production data</a>@endif
        <p class="mt-2 text-muted">Synchronized production monitoring. Operational records remain authoritative in the Business Server.</p>
        <p class="mt-1 text-sm text-muted">Latest synchronized activity: {{ $data['lastSyncedAt'] ?? 'Awaiting first synchronization' }}</p>
    </div>
    @if ($section === 'all')
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([['Production batches', 'batch'], ['Items', 'material'], ['Machines', 'machine'], ['Expenses', 'expense']] as [$label, $kind])
                <div class="rounded-lg border border-border bg-surface p-4"><p class="text-sm text-muted">{{ $label }}</p><p class="mt-2 text-2xl font-semibold">{{ $recordCounts[$kind] ?? 0 }}</p><p class="mt-1 text-xs text-muted">Synchronized records</p></div>
            @endforeach
        </div>
    @endif
    @if ($data['finance'] && in_array($section, ['all', 'benefits'], true))
        <div class="grid gap-4 md:grid-cols-3">
            @foreach (['revenueMinor' => 'Net production sales', 'costMinor' => 'Cost sold and batch losses', 'profitMinor' => 'Profit before shares', 'approvedShareMinor' => 'Approved shares', 'businessRemainderMinor' => 'Business remainder after approved shares', 'outstandingShareMinor' => 'Outstanding shares'] as $key => $label)
                <div class="rounded-lg border border-border bg-surface p-4"><p class="text-sm text-muted">{{ $label }}</p><p class="mt-2 text-xl font-semibold">{{ $currency }} {{ number_format($data[$key] / 100, 2) }}</p></div>
            @endforeach
        </div>
    @endif
    @forelse ($data['records']->groupBy('kind') as $kind => $records)
        <section class="rounded-lg border border-border bg-surface p-4">
            <h2 class="mb-3 text-lg font-semibold">{{ ucfirst($kind) }}</h2>
            <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr><th class="p-2">Record</th><th class="p-2">Status / quantity</th>@if ($data['finance'])<th class="p-2">Amounts ({{ $currency }})</th>@endif</tr></thead><tbody>
                @foreach ($records as $record)
                    @php($row = $record['data'])
                    <tr class="border-t border-border">
                        <td class="p-2">{{ $row['code'] ?? $row['sku'] ?? '' }} @if (isset($row['code']) || isset($row['sku'])) | @endif {{ $row['name'] ?? $row['reference'] ?? $row['description'] ?? $row['saleUuid'] ?? $record['uuid'] }}</td>
                        <td class="p-2">{{ $row['state'] ?? (isset($row['active']) ? ($row['active'] ? 'Active' : 'Inactive') : '') }} {{ $row['goodQuantity'] ?? $row['quantity'] ?? '' }}</td>
                        @if ($data['finance'])<td class="p-2">
                            @foreach (['totalCostMinor' => 'Batch cost', 'amountMinor' => 'Amount', 'profitMinor' => 'Profit'] as $key => $label)
                                @if (isset($row[$key]))<p>{{ $label }}: {{ number_format($row[$key] / 100, 2) }}</p>@endif
                            @endforeach
                            @foreach (['grossRevenueMinor' => 'Gross revenue', 'itemCostMinor' => 'Item cost', 'otherExpensesMinor' => 'Other expenses', 'totalExpensesMinor' => 'Total expenses', 'netBenefitMinor' => 'Net benefit', 'businessBenefitMinor' => 'Business benefit'] as $key => $label)
                                @if (isset($row[$key]))<p>{{ $label }}: {{ number_format($row[$key] / 100, 2) }}</p>@endif
                            @endforeach
                            @foreach ($row['shares'] ?? $row['entries'] ?? [] as $share)
                                <p>{{ $share['name'] ?? '' }}: {{ $share['percent'] ?? 0 }}% @if (isset($share['cumulativeMinor'])) / {{ number_format($share['cumulativeMinor'] / 100, 2) }} @endif</p>
                            @endforeach
                            @foreach (['staffPercentage' => 'Staff', 'machinePercentage' => 'Machines', 'businessPercentage' => 'Business'] as $key => $label)
                                @if (isset($row[$key]))<p>{{ $label }}: {{ $row[$key] }}%</p>@endif
                            @endforeach
                        </td>@endif
                    </tr>
                @endforeach
            </tbody></table></div>
        </section>
    @empty
        <p class="rounded-lg border border-border p-6 text-muted">Production records will appear after the Business Server synchronizes.</p>
    @endforelse
</div>
