<?php

namespace App\Domain\Reporting\Services;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Reporting\Jobs\GenerateReportExport;
use App\Domain\Reporting\Models\ReportExport;
use App\Domain\Reporting\Models\ScheduledReport;
use App\Domain\Tenancy\Models\Business;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

class ReportCentreService
{
    private const EXPORT_ROW_LIMIT = 50000;

    /**
     * @return array<string, array<string, string>>
     */
    public function groups(): array
    {
        return [
            'sales' => [
                'daily_sales' => 'Daily sales',
                'sales_by_branch' => 'Sales by branch',
                'sales_by_cashier' => 'Sales by cashier',
                'sales_by_product' => 'Sales by product',
                'sales_by_category' => 'Sales by category',
                'sales_by_payment_method' => 'Sales by payment method',
            ],
            'inventory' => [
                'current_stock' => 'Current stock',
                'low_stock' => 'Low stock',
                'out_of_stock' => 'Out of stock',
                'stock_value' => 'Stock value',
                'fast_moving_stock' => 'Fast-moving stock',
                'slow_moving_stock' => 'Slow-moving stock',
                'damaged_expired_stock' => 'Damaged and expired stock',
            ],
            'financial' => [
                'gross_profit' => 'Gross profit',
                'estimated_net_profit' => 'Estimated net profit',
                'expenses' => 'Expenses',
                'customer_debt' => 'Customer debt',
                'supplier_balance' => 'Supplier balance',
                'cashier_shortages' => 'Cashier shortages',
                'payment_breakdown' => 'Payment breakdown',
            ],
            'operational' => [
                'synchronization_health' => 'Synchronization health',
                'device_status' => 'Device status',
                'cashier_performance' => 'Cashier performance',
                'security_alerts' => 'Security alerts',
                'branch_comparison' => 'Branch comparison',
            ],
        ];
    }

    public function reportLabel(string $reportKey): string
    {
        foreach ($this->groups() as $reports) {
            if (isset($reports[$reportKey])) {
                return $reports[$reportKey];
            }
        }

        return str($reportKey)->replace('_', ' ')->title()->toString();
    }

    public function reportGroup(string $reportKey): string
    {
        foreach ($this->groups() as $group => $reports) {
            if (isset($reports[$reportKey])) {
                return $group;
            }
        }

        throw ValidationException::withMessages(['report' => 'The selected report is not supported.']);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function previewRows(int $businessId, string $reportKey, array $filters): Collection
    {
        return $this->query($businessId, $reportKey, $filters)->limit(25)->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function queueExport(Business $business, User $actor, string $reportKey, string $format, array $filters): ReportExport
    {
        if (! in_array($format, ['csv', 'pdf'], true)) {
            throw ValidationException::withMessages(['format' => 'Reports can be exported as CSV or PDF.']);
        }

        $group = $this->reportGroup($reportKey);

        $export = ReportExport::query()->create([
            'business_id' => $business->id,
            'branch_id' => $filters['branch_id'] ?? null,
            'requested_by' => $actor->id,
            'report_key' => $reportKey,
            'report_group' => $group,
            'format' => $format,
            'status' => 'queued',
            'filters' => $filters,
            'queued_at' => now(),
        ]);

        GenerateReportExport::dispatch($export->id);

        AuditLog::query()->create([
            'business_id' => $business->id,
            'branch_id' => $export->branch_id,
            'actor_id' => $actor->id,
            'action' => 'report.export_queued',
            'subject_type' => ReportExport::class,
            'subject_id' => $export->id,
            'metadata' => ['report_key' => $reportKey, 'format' => $format],
        ]);

        return $export;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function createSchedule(Business $business, User $actor, string $reportKey, string $format, string $frequency, array $filters): ScheduledReport
    {
        $this->reportGroup($reportKey);

        return ScheduledReport::query()->create([
            'business_id' => $business->id,
            'branch_id' => $filters['branch_id'] ?? null,
            'created_by' => $actor->id,
            'name' => $this->reportLabel($reportKey).' schedule',
            'report_key' => $reportKey,
            'format' => $format,
            'frequency' => $frequency,
            'filters' => $filters,
            'recipients' => [$actor->email],
            'is_active' => true,
            'next_run_at' => match ($frequency) {
                'daily' => now()->addDay(),
                'monthly' => now()->addMonth(),
                default => now()->addWeek(),
            },
        ]);
    }

    /**
     * @return array{content: string, rows_count: int}
     */
    public function generate(ReportExport $export): array
    {
        $rows = $this->query($export->business_id, $export->report_key, $export->filters ?? [])->limit(self::EXPORT_ROW_LIMIT)->get();

        if ($export->format === 'pdf') {
            return [
                'content' => $this->pdf($export, $rows),
                'rows_count' => $rows->count(),
            ];
        }

        return [
            'content' => $this->csv($rows),
            'rows_count' => $rows->count(),
        ];
    }

    public function signedDownloadUrl(ReportExport $export): ?string
    {
        if ($export->status !== 'completed' || ! $export->expires_at || $export->expires_at->isPast()) {
            return null;
        }

        return URL::temporarySignedRoute('platform.reports.download', $export->expires_at, ['export' => $export->uuid]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function query(int $businessId, string $reportKey, array $filters): Builder
    {
        $query = match ($reportKey) {
            'daily_sales' => DB::table('business_dashboard_daily_summaries')
                ->leftJoin('branches', 'branches.id', '=', 'business_dashboard_daily_summaries.branch_id')
                ->selectRaw('summary_date as date, coalesce(branches.name, \'All branches\') as branch, gross_sales_minor, net_sales_minor, completed_sales_count, gross_profit_minor')
                ->where('business_dashboard_daily_summaries.business_id', $businessId),
            'sales_by_branch' => DB::table('dashboard_branch_summaries')
                ->join('branches', 'branches.id', '=', 'dashboard_branch_summaries.branch_id')
                ->selectRaw('branches.name as branch, sum(gross_sales_minor) as gross_sales_minor, sum(net_sales_minor) as net_sales_minor, sum(completed_sales_count) as sales_count, sum(gross_profit_minor) as gross_profit_minor')
                ->where('dashboard_branch_summaries.business_id', $businessId)
                ->groupBy('branches.name'),
            'sales_by_cashier' => DB::table('replicated_sales')
                ->selectRaw('coalesce(cashier_name, \'Unknown cashier\') as cashier, count(*) as sales_count, sum(total_minor) as sales_minor, sum(gross_profit_minor) as gross_profit_minor')
                ->where('business_id', $businessId)
                ->groupBy('cashier_name'),
            'sales_by_product', 'fast_moving_stock', 'slow_moving_stock' => DB::table('replicated_sale_items')
                ->selectRaw('product_name as product, sku, sum(quantity) as quantity, sum(line_total_minor) as sales_minor, sum(gross_profit_minor) as gross_profit_minor')
                ->where('business_id', $businessId)
                ->groupBy('product_name', 'sku')
                ->when($reportKey === 'slow_moving_stock', fn (Builder $query): Builder => $query->orderBy('quantity'))
                ->when($reportKey !== 'slow_moving_stock', fn (Builder $query): Builder => $query->orderByDesc('quantity')),
            'sales_by_category' => DB::table('replicated_sale_items')
                ->selectRaw('coalesce(category_name, \'Uncategorized\') as category, sum(quantity) as quantity, sum(line_total_minor) as sales_minor')
                ->where('business_id', $businessId)
                ->groupBy('category_name'),
            'sales_by_payment_method', 'payment_breakdown' => DB::table('replicated_sale_payments')
                ->selectRaw('method, status, count(*) as payment_count, sum(amount_minor) as amount_minor')
                ->where('business_id', $businessId)
                ->groupBy('method', 'status'),
            'current_stock', 'stock_value' => DB::table('replicated_inventory_stocks')
                ->leftJoin('branches', 'branches.id', '=', 'replicated_inventory_stocks.branch_id')
                ->selectRaw('branches.name as branch, product_name as product, sku, warehouse_name as warehouse, available_quantity, stock_status, stock_value_minor, replicated_inventory_stocks.last_synchronized_at')
                ->where('replicated_inventory_stocks.business_id', $businessId),
            'low_stock' => $this->stockStatusQuery($businessId, 'low'),
            'out_of_stock' => $this->stockStatusQuery($businessId, 'out_of_stock'),
            'damaged_expired_stock' => DB::table('replicated_stock_alerts')
                ->leftJoin('branches', 'branches.id', '=', 'replicated_stock_alerts.branch_id')
                ->selectRaw('branches.name as branch, product_name as product, alert_type, severity, replicated_stock_alerts.status, message, occurred_at')
                ->where('replicated_stock_alerts.business_id', $businessId)
                ->whereIn('alert_type', ['damaged', 'expired', 'expiring']),
            'gross_profit', 'estimated_net_profit', 'cashier_shortages', 'branch_comparison' => DB::table('dashboard_branch_summaries')
                ->join('branches', 'branches.id', '=', 'dashboard_branch_summaries.branch_id')
                ->selectRaw('branches.name as branch, summary_date, gross_profit_minor, estimated_net_profit_minor, cashier_variance_minor, net_sales_minor, expenses_minor, low_stock_count')
                ->where('dashboard_branch_summaries.business_id', $businessId),
            'expenses' => DB::table('replicated_expenses')
                ->leftJoin('branches', 'branches.id', '=', 'replicated_expenses.branch_id')
                ->selectRaw('branches.name as branch, expense_number, category_name as category, description, amount_minor, payment_method, replicated_expenses.status, expense_date')
                ->where('replicated_expenses.business_id', $businessId),
            'customer_debt' => DB::table('replicated_customer_debt_summaries')
                ->leftJoin('branches', 'branches.id', '=', 'replicated_customer_debt_summaries.branch_id')
                ->selectRaw('branches.name as branch, customer_name as customer, replicated_customer_debt_summaries.phone, current_balance_minor, overdue_amount_minor, debt_status, replicated_customer_debt_summaries.last_synchronized_at')
                ->where('replicated_customer_debt_summaries.business_id', $businessId),
            'supplier_balance' => DB::table('replicated_supplier_balances')
                ->leftJoin('branches', 'branches.id', '=', 'replicated_supplier_balances.branch_id')
                ->selectRaw('branches.name as branch, supplier_name as supplier, replicated_supplier_balances.phone, current_balance_minor, overdue_balance_minor, replicated_supplier_balances.status, replicated_supplier_balances.last_synchronized_at')
                ->where('replicated_supplier_balances.business_id', $businessId),
            'synchronization_health' => DB::table('sync_batches')
                ->leftJoin('branches', 'branches.id', '=', 'sync_batches.branch_id')
                ->selectRaw('branches.name as branch, sync_batches.uuid as batch_uuid, sync_batches.status, protocol_version, records_count, received_at, processed_at')
                ->where('sync_batches.business_id', $businessId),
            'device_status' => DB::table('devices')
                ->leftJoin('branches', 'branches.id', '=', 'devices.branch_id')
                ->selectRaw('branches.name as branch, devices.uuid as device_uuid, devices.name, devices.type, devices.status, app_version, devices.last_seen_at, devices.last_synchronized_at')
                ->where('devices.business_id', $businessId),
            'cashier_performance' => DB::table('staff_activity_summaries')
                ->leftJoin('branches', 'branches.id', '=', 'staff_activity_summaries.branch_id')
                ->selectRaw('branches.name as branch, display_name as staff_name, role_name, gross_sales_minor, sales_count, cashier_variance_minor, last_activity_at')
                ->where('staff_activity_summaries.business_id', $businessId),
            'security_alerts' => DB::table('platform_notifications')
                ->leftJoin('branches', 'branches.id', '=', 'platform_notifications.branch_id')
                ->selectRaw('branches.name as branch, title, body, severity, category, platform_notifications.status, platform_notifications.created_at, resolved_at')
                ->where('platform_notifications.business_id', $businessId)
                ->whereIn('category', ['security', 'devices', 'synchronization']),
            default => throw ValidationException::withMessages(['report' => 'The selected report is not supported.']),
        };

        return $this->applyFilters($query, $filters, $reportKey);
    }

    private function stockStatusQuery(int $businessId, string $status): Builder
    {
        return DB::table('replicated_inventory_stocks')
            ->leftJoin('branches', 'branches.id', '=', 'replicated_inventory_stocks.branch_id')
            ->selectRaw('branches.name as branch, product_name as product, sku, warehouse_name as warehouse, available_quantity, reorder_level, stock_status, replicated_inventory_stocks.last_synchronized_at')
            ->where('replicated_inventory_stocks.business_id', $businessId)
            ->where('stock_status', $status);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters, string $reportKey): Builder
    {
        $columns = $this->filterColumns($reportKey);

        return $query
            ->when(($filters['branch_id'] ?? null) && isset($columns['branch']), fn (Builder $query) => $query->where($columns['branch'], $filters['branch_id']))
            ->when(($filters['status'] ?? null) && isset($columns['status']), fn (Builder $query) => $query->where($columns['status'], $filters['status']))
            ->when(($filters['product'] ?? null) && isset($columns['product']), fn (Builder $query) => $this->likeAny($query, $columns['product'], $filters['product']))
            ->when(($filters['cashier'] ?? null) && isset($columns['cashier']), fn (Builder $query) => $this->likeAny($query, $columns['cashier'], $filters['cashier']))
            ->when(($filters['customer'] ?? null) && isset($columns['customer']), fn (Builder $query) => $this->likeAny($query, $columns['customer'], $filters['customer']))
            ->when(($filters['supplier'] ?? null) && isset($columns['supplier']), fn (Builder $query) => $this->likeAny($query, $columns['supplier'], $filters['supplier']))
            ->when(($filters['from'] ?? null) && isset($columns['date']), fn (Builder $query) => $query->where($columns['date'], '>=', Carbon::parse($filters['from'])->startOfDay()))
            ->when(($filters['to'] ?? null) && isset($columns['date']), fn (Builder $query) => $query->where($columns['date'], '<=', Carbon::parse($filters['to'])->endOfDay()));
    }

    /**
     * @return array<string, string|array<int, string>>
     */
    private function filterColumns(string $reportKey): array
    {
        return match ($reportKey) {
            'daily_sales' => ['branch' => 'business_dashboard_daily_summaries.branch_id', 'date' => 'summary_date'],
            'sales_by_branch', 'gross_profit', 'estimated_net_profit', 'cashier_shortages', 'branch_comparison' => ['branch' => 'dashboard_branch_summaries.branch_id', 'date' => 'summary_date'],
            'sales_by_cashier' => ['branch' => 'replicated_sales.branch_id', 'date' => 'sold_at', 'status' => 'status', 'cashier' => ['cashier_name', 'cashier_uuid'], 'customer' => ['customer_name', 'customer_uuid']],
            'sales_by_product', 'sales_by_category', 'fast_moving_stock', 'slow_moving_stock' => ['branch' => 'replicated_sale_items.branch_id', 'product' => ['product_name', 'sku']],
            'sales_by_payment_method', 'payment_breakdown' => ['branch' => 'replicated_sale_payments.branch_id', 'date' => 'paid_at', 'status' => 'status'],
            'current_stock', 'stock_value', 'low_stock', 'out_of_stock' => ['branch' => 'replicated_inventory_stocks.branch_id', 'status' => 'stock_status', 'product' => ['product_name', 'sku', 'barcode']],
            'damaged_expired_stock' => ['branch' => 'replicated_stock_alerts.branch_id', 'date' => 'occurred_at', 'status' => 'replicated_stock_alerts.status', 'product' => ['product_name']],
            'expenses' => ['branch' => 'replicated_expenses.branch_id', 'date' => 'expense_date', 'status' => 'replicated_expenses.status'],
            'customer_debt' => ['branch' => 'replicated_customer_debt_summaries.branch_id', 'status' => 'debt_status', 'customer' => ['customer_name', 'replicated_customer_debt_summaries.phone']],
            'supplier_balance' => ['branch' => 'replicated_supplier_balances.branch_id', 'status' => 'replicated_supplier_balances.status', 'supplier' => ['supplier_name', 'replicated_supplier_balances.phone']],
            'synchronization_health' => ['branch' => 'sync_batches.branch_id', 'date' => 'received_at', 'status' => 'sync_batches.status'],
            'device_status' => ['branch' => 'devices.branch_id', 'date' => 'devices.last_seen_at', 'status' => 'devices.status'],
            'cashier_performance' => ['branch' => 'staff_activity_summaries.branch_id', 'date' => 'last_activity_at', 'cashier' => ['display_name', 'role_name']],
            'security_alerts' => ['branch' => 'platform_notifications.branch_id', 'date' => 'platform_notifications.created_at', 'status' => 'platform_notifications.status'],
            default => [],
        };
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function likeAny(Builder $query, array $columns, string $value): Builder
    {
        return $query->where(function (Builder $query) use ($columns, $value): void {
            foreach ($columns as $column) {
                $query->orWhere($column, 'like', "%{$value}%");
            }
        });
    }

    private function csv(Collection $rows): string
    {
        if ($rows->isEmpty()) {
            return "No data\n";
        }

        $handle = fopen('php://temp', 'r+');
        $first = (array) $rows->first();
        fputcsv($handle, array_keys($first));

        foreach ($rows as $row) {
            fputcsv($handle, array_values((array) $row));
        }

        rewind($handle);

        return stream_get_contents($handle) ?: '';
    }

    private function pdf(ReportExport $export, Collection $rows): string
    {
        $lines = [
            'MarketLens report export',
            'Report: '.$this->reportLabel($export->report_key),
            'Rows: '.$rows->count(),
            'Generated at: '.now()->toDateTimeString(),
            'Filters: '.json_encode($export->filters ?: [], JSON_UNESCAPED_SLASHES),
            '',
        ];

        foreach ($rows->take(42) as $row) {
            $lines[] = str((string) json_encode((array) $row, JSON_UNESCAPED_SLASHES))
                ->limit(115)
                ->toString();
        }

        if ($rows->count() > 42) {
            $lines[] = 'Additional rows are available in CSV export.';
        }

        return $this->singlePagePdf($lines);
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function singlePagePdf(array $lines): string
    {
        $text = "BT\n/F1 10 Tf\n50 790 Td\n14 TL\n";

        foreach ($lines as $line) {
            $text .= '('.$this->escapePdfText($line).") Tj\nT*\n";
        }

        $text .= "ET\n";

        $objects = [
            '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj',
            '2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj',
            '3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 612 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >> endobj',
            '4 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj',
            '5 0 obj << /Length '.strlen($text)." >> stream\n".$text.'endstream endobj',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object."\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= "trailer << /Size ".(count($objects) + 1)." /Root 1 0 R >>\n";
        $pdf .= "startxref\n".$xrefOffset."\n%%EOF\n";

        return $pdf;
    }

    private function escapePdfText(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }
}





