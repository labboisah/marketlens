<?php

namespace App\Domain\Reporting\Jobs;

use App\Domain\Customers\Models\ReplicatedCustomerDebtSummary;
use App\Domain\Expenses\Models\ReplicatedExpense;
use App\Domain\Inventory\Models\ReplicatedInventoryStock;
use App\Domain\Payments\Models\ReplicatedCashierSettlement;
use App\Domain\Purchases\Models\ReplicatedSupplierBalance;
use App\Domain\Reporting\Models\BusinessDashboardDailySummary;
use App\Domain\Reporting\Models\DashboardBranchSummary;
use App\Domain\Sales\Models\ReplicatedSale;
use App\Domain\Sales\Models\ReplicatedSalePayment;
use App\Domain\Synchronization\Models\SyncCheckpoint;
use App\Domain\Synchronization\Services\SynchronizedOperationalData;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class RefreshDashboardSummaries implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $businessId,
        public readonly ?int $branchId,
        public readonly string $summaryDate,
    ) {}

    public function handle(): void
    {
        if (! $this->branchId) {
            return;
        }

        $day = CarbonImmutable::parse($this->summaryDate);
        $start = $day->startOfDay();
        $end = $day->endOfDay();

        $sales = ReplicatedSale::query()
            ->withoutGlobalScopes()
            ->where('business_id', $this->businessId)
            ->where('branch_id', $this->branchId)
            ->where('status', 'completed')
            ->whereBetween('sold_at', [$start, $end]);

        $payments = ReplicatedSalePayment::query()
            ->withoutGlobalScopes()
            ->where('business_id', $this->businessId)
            ->where('branch_id', $this->branchId)
            ->whereIn('status', ['completed', 'confirmed', 'paid'])
            ->whereBetween('paid_at', [$start, $end]);

        $expenses = ReplicatedExpense::query()
            ->withoutGlobalScopes()
            ->where('business_id', $this->businessId)
            ->where('branch_id', $this->branchId)
            ->whereIn('status', ['approved', 'paid', 'completed'])
            ->whereBetween('expense_date', [$start, $end]);

        $settlements = ReplicatedCashierSettlement::query()
            ->withoutGlobalScopes()
            ->where('business_id', $this->businessId)
            ->where('branch_id', $this->branchId)
            ->whereBetween('settled_at', [$start, $end]);

        $grossSales = (int) (clone $sales)->sum('subtotal_minor');
        $netSales = (int) (clone $sales)->sum('total_minor');
        $completedSales = (int) (clone $sales)->count();
        $grossProfit = (int) (clone $sales)->sum('gross_profit_minor');
        $expensesTotal = (int) (clone $expenses)->sum('amount_minor');
        $cashPayments = $this->paymentTotal($payments, ['cash']);
        $bankPayments = $this->paymentTotal($payments, ['bank_transfer', 'transfer']);
        $posPayments = $this->paymentTotal($payments, ['pos', 'pos_terminal', 'card']);
        $confirmedPayments = (int) (clone $payments)->sum('amount_minor');
        $creditSales = max(0, $netSales - $confirmedPayments);
        $stockValue = (int) ReplicatedInventoryStock::query()
            ->withoutGlobalScopes()
            ->where('business_id', $this->businessId)
            ->where('branch_id', $this->branchId)
            ->sum('stock_value_minor');
        $lowStock = (int) ReplicatedInventoryStock::query()
            ->withoutGlobalScopes()
            ->where('business_id', $this->businessId)
            ->where('branch_id', $this->branchId)
            ->whereIn('stock_status', ['low', 'low_stock', 'out_of_stock'])
            ->count();
        if ($stockValue === 0) {
            [$stockValue, $lowStock] = $this->operationalStockTotals();
        }
        $customerDebt = (int) ReplicatedCustomerDebtSummary::query()
            ->withoutGlobalScopes()
            ->where('business_id', $this->businessId)
            ->where('branch_id', $this->branchId)
            ->sum('current_balance_minor');
        $supplierBalance = (int) ReplicatedSupplierBalance::query()
            ->withoutGlobalScopes()
            ->where('business_id', $this->businessId)
            ->where('branch_id', $this->branchId)
            ->sum('current_balance_minor');
        $cashierVariance = (int) (clone $settlements)->sum('surplus_minor')
            - (int) (clone $settlements)->sum('shortage_minor');
        $lastSync = SyncCheckpoint::query()
            ->withoutGlobalScopes()
            ->where('business_id', $this->businessId)
            ->where('branch_id', $this->branchId)
            ->max('last_successful_sync_at');

        $shared = [
            'business_id' => $this->businessId,
            'branch_id' => $this->branchId,
            'summary_date' => $day->toDateString(),
            'gross_sales_minor' => $grossSales,
            'net_sales_minor' => $netSales,
            'completed_sales_count' => $completedSales,
            'gross_profit_minor' => $grossProfit,
            'expenses_minor' => $expensesTotal,
            'estimated_net_profit_minor' => $grossProfit - $expensesTotal,
            'customer_debt_minor' => $customerDebt,
            'supplier_balance_minor' => $supplierBalance,
            'cashier_variance_minor' => $cashierVariance,
            'last_successful_sync_at' => $lastSync,
            'summarized_at' => now(),
        ];

        BusinessDashboardDailySummary::query()->updateOrCreate(
            [
                'business_id' => $this->businessId,
                'branch_id' => $this->branchId,
                'summary_date' => $day->toDateString(),
            ],
            [
                ...$shared,
                'cash_payments_minor' => $cashPayments,
                'bank_transfer_payments_minor' => $bankPayments,
                'pos_terminal_payments_minor' => $posPayments,
                'credit_sales_minor' => $creditSales,
                'current_stock_value_minor' => $stockValue,
                'low_stock_products_count' => $lowStock,
            ],
        );

        DashboardBranchSummary::query()->updateOrCreate(
            [
                'business_id' => $this->businessId,
                'branch_id' => $this->branchId,
                'summary_date' => $day->toDateString(),
            ],
            [
                ...$shared,
                'stock_value_minor' => $stockValue,
                'low_stock_count' => $lowStock,
            ],
        );

        $this->clearOverviewCache();
    }

    /** @return array{0: int, 1: int} */
    private function operationalStockTotals(): array
    {
        $records = app(SynchronizedOperationalData::class)
            ->records($this->businessId, ['product', 'inventory_stock'], $this->branchId);
        $products = $records->where('_entity', 'product')
            ->keyBy(fn (array $product): string => (string) ($product['uuid'] ?? $product['_record_uuid']));
        $stockValue = 0;
        $lowStockProducts = [];

        foreach ($records->where('_entity', 'inventory_stock') as $stock) {
            $productUuid = (string) ($stock['product_uuid'] ?? '');
            $product = $products->get($productUuid);
            if (! $product) {
                continue;
            }

            $quantity = max(0, (float) ($stock['quantity'] ?? 0) - (float) ($stock['reserved_quantity'] ?? 0));
            $stockValue += (int) round($quantity * (float) ($product['cost_price'] ?? 0) * 100);

            if ($quantity <= (float) ($product['reorder_level'] ?? 0)) {
                $lowStockProducts[$productUuid] = true;
            }
        }

        return [$stockValue, count($lowStockProducts)];
    }

    private function paymentTotal(mixed $query, array $methods): int
    {
        return (int) (clone $query)->whereIn('method', $methods)->sum('amount_minor');
    }

    private function clearOverviewCache(): void
    {
        $today = CarbonImmutable::today();
        $ranges = [
            'today' => [$today, $today],
            '7_days' => [$today->subDays(6), $today],
            '30_days' => [$today->subDays(29), $today],
            'month' => [$today->startOfMonth(), $today->endOfMonth()],
        ];

        foreach ($ranges as $name => [$start, $end]) {
            foreach ([$this->branchId, null] as $branchId) {
                Cache::forget(implode(':', [
                    'business-overview-dashboard',
                    $this->businessId,
                    $branchId ?: 'all',
                    $name,
                    $start->toDateString(),
                    $end->toDateString(),
                ]));
            }
        }
    }
}
