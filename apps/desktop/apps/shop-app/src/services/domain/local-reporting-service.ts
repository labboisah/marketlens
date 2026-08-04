import { mkdirSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { randomUUID } from 'node:crypto';
import type Database from 'better-sqlite3';

export const reportTypes = [
  'daily-sales', 'sales-by-product', 'sales-by-category', 'sales-by-cashier', 'payment-breakdown',
  'inventory', 'stock-movement', 'purchases', 'customer-debt', 'supplier-balances', 'expenses',
  'cashier-settlements', 'service-revenue', 'service-staff-performance', 'revenue-allocations',
  'staff-earnings', 'staff-settlements',
] as const;

export type LocalReportType = (typeof reportTypes)[number];
export type ReportFilters = { from: string; to: string; branchUuid?: string };
type Row = Record<string, string | number | null>;

export class LocalReportingService {
  constructor(
    private readonly sqlite: Database,
    private readonly branchUuid: string,
    private readonly exportPath: string,
  ) {}

  dashboard(date = this.today()) {
    const filters = { from: `${date}T00:00:00.000Z`, to: `${date}T23:59:59.999Z` };
    const sales = this.total("SELECT COALESCE(SUM(CAST(total_amount AS REAL)), 0) AS total FROM sales WHERE status = 'completed' AND created_at BETWEEN ? AND ?", filters);
    const cost = this.total("SELECT COALESCE(SUM(CAST(si.quantity AS REAL) * CAST(p.cost_price AS REAL)), 0) AS total FROM sale_items si JOIN sales s ON s.uuid = si.sale_uuid JOIN products p ON p.uuid = si.product_uuid WHERE s.status = 'completed' AND s.created_at BETWEEN ? AND ?", filters);
    const expenses = this.total("SELECT COALESCE(SUM(CAST(amount AS REAL)), 0) AS total FROM expenses WHERE status = 'approved' AND created_at BETWEEN ? AND ?", filters);
    const serviceRevenue = this.total("SELECT COALESCE(SUM(CAST(amount AS REAL)), 0) AS total FROM service_payments WHERE created_at BETWEEN ? AND ?", filters);
    const paymentBreakdown = this.rows("SELECT pm.name AS paymentMethod, COALESCE(SUM(CAST(sp.amount AS REAL)), 0) AS amount FROM sale_payments sp JOIN sales s ON s.uuid = sp.sale_uuid JOIN payment_methods pm ON pm.uuid = sp.payment_method_uuid WHERE s.status = 'completed' AND s.created_at BETWEEN ? AND ? GROUP BY pm.uuid, pm.name ORDER BY amount DESC", filters);
    const grossProfit = sales - cost;
    const stockValue = this.total('SELECT COALESCE(SUM(CAST(st.quantity AS REAL) * CAST(p.cost_price AS REAL)), 0) AS total FROM stocks st JOIN products p ON p.uuid = st.product_uuid');

    return {
      date,
      todaySales: sales,
      paymentBreakdown,
      expenses,
      grossProfit,
      estimatedNetProfit: grossProfit + serviceRevenue - expenses,
      customerDebt: this.total('SELECT COALESCE(SUM(CAST(current_balance AS REAL)), 0) AS total FROM customer_accounts'),
      supplierBalance: this.total('SELECT COALESCE(SUM(CAST(current_balance AS REAL)), 0) AS total FROM supplier_accounts'),
      stockValue,
      lowStockCount: this.count('SELECT COUNT(*) AS count FROM stocks st JOIN products p ON p.uuid = st.product_uuid WHERE CAST(st.quantity AS REAL) <= CAST(COALESCE(p.reorder_level, 0) AS REAL)'),
      openCashierShifts: this.count("SELECT COUNT(*) AS count FROM cashier_shifts WHERE status = 'open'"),
      cashierShortages: this.total("SELECT COALESCE(SUM(ABS(CAST(variance AS REAL))), 0) AS total FROM cashier_settlements WHERE CAST(variance AS REAL) < 0"),
      servicesInProgress: this.count("SELECT COUNT(*) AS count FROM service_orders WHERE status IN ('pending', 'assigned', 'in_progress')"),
      serviceRevenue,
      pendingStaffEarnings: this.total("SELECT COALESCE(SUM(CAST(amount AS REAL)), 0) AS total FROM service_revenue_allocations WHERE component_type = 'staff' AND status = 'pending'"),
      lastPlatformSynchronization: (this.sqlite.prepare("SELECT updated_at AS value FROM sync_queue WHERE status = 'completed' ORDER BY updated_at DESC LIMIT 1").get() as { value: string } | undefined)?.value ?? null,
      authorizedClientApps: this.count("SELECT COUNT(*) AS count FROM devices WHERE kind = 'client_app' AND status IN ('authorized', 'paired')"),
    };
  }

  staffPerformance(filters: ReportFilters) {
    this.assertBranch(filters.branchUuid);
    const range = this.range(filters);
    const staff = this.rows(`
      SELECT u.uuid AS staffUserUuid, u.display_name AS staffName,
        COUNT(DISTINCT s.uuid) AS transactionCount,
        COALESCE(SUM(CASE WHEN s.status = 'completed' THEN CAST(s.total_amount AS REAL) ELSE 0 END), 0) AS salesValue,
        COALESCE(SUM(CAST(sd.amount AS REAL)), 0) AS discounts,
        COALESCE((SELECT SUM(CAST(sr.refund_amount AS REAL)) FROM sale_returns sr WHERE sr.sale_uuid IN (SELECT uuid FROM sales WHERE shift_uuid IN (SELECT uuid FROM cashier_shifts WHERE cashier_user_uuid = u.uuid)) AND sr.created_at BETWEEN ? AND ?), 0) AS returns,
        COALESCE((SELECT COUNT(*) FROM sale_cancellations sc JOIN sales cancelled ON cancelled.uuid = sc.sale_uuid JOIN cashier_shifts cancelled_shift ON cancelled_shift.uuid = cancelled.shift_uuid WHERE cancelled_shift.cashier_user_uuid = u.uuid AND sc.created_at BETWEEN ? AND ?), 0) AS cancellations,
        COALESCE(SUM(CASE WHEN cs.status = 'closed' THEN CAST(cs.expected_cash AS REAL) ELSE 0 END), 0) AS expectedCash,
        COALESCE(SUM(CASE WHEN cs.status = 'closed' THEN CAST(cs.actual_cash AS REAL) ELSE 0 END), 0) AS actualCash,
        COALESCE(SUM(CASE WHEN cs.status = 'closed' THEN CAST(cs.variance AS REAL) ELSE 0 END), 0) AS cashVariance,
        COALESCE((SELECT COUNT(*) FROM service_staff_assignments ssa WHERE ssa.staff_user_uuid = u.uuid), 0) AS servicesAssigned,
        COALESCE((SELECT COUNT(*) FROM service_staff_assignments ssa JOIN service_order_items soi ON soi.uuid = ssa.order_item_uuid JOIN service_orders so ON so.uuid = soi.order_uuid WHERE ssa.staff_user_uuid = u.uuid AND so.status = 'completed' AND so.completed_at BETWEEN ? AND ?), 0) AS servicesCompleted,
        COALESCE((SELECT COUNT(*) FROM service_staff_assignments ssa JOIN service_order_items soi ON soi.uuid = ssa.order_item_uuid JOIN service_orders so ON so.uuid = soi.order_uuid WHERE ssa.staff_user_uuid = u.uuid AND so.status IN ('cancelled', 'refunded') AND so.created_at BETWEEN ? AND ?), 0) AS servicesCancelled,
        COALESCE((SELECT SUM(CAST(sp.amount AS REAL)) FROM service_staff_assignments ssa JOIN service_order_items soi ON soi.uuid = ssa.order_item_uuid JOIN service_payments sp ON sp.order_uuid = soi.order_uuid WHERE ssa.staff_user_uuid = u.uuid AND sp.created_at BETWEEN ? AND ?), 0) AS serviceRevenue,
        COALESCE((SELECT SUM(CAST(se.amount AS REAL)) FROM service_staff_assignments ssa JOIN service_order_items soi ON soi.uuid = ssa.order_item_uuid JOIN service_expenses se ON se.order_uuid = soi.order_uuid WHERE ssa.staff_user_uuid = u.uuid AND se.created_at BETWEEN ? AND ?), 0) AS directServiceExpenses,
        COALESCE((SELECT SUM(CAST(sra.amount AS REAL)) FROM service_revenue_allocations sra WHERE sra.staff_user_uuid = u.uuid AND sra.component_type = 'staff' AND sra.created_at BETWEEN ? AND ?), 0) AS staffShareEarned,
        COALESCE((SELECT SUM(CAST(sra.amount AS REAL)) FROM service_revenue_allocations sra WHERE sra.staff_user_uuid = u.uuid AND sra.component_type = 'staff' AND sra.status = 'approved' AND sra.created_at BETWEEN ? AND ?), 0) AS staffShareApproved,
        COALESCE((SELECT SUM(CAST(ss.amount AS REAL)) FROM staff_settlements ss WHERE ss.staff_user_uuid = u.uuid AND ss.status = 'completed' AND ss.created_at BETWEEN ? AND ?), 0) AS staffShareSettled,
        COALESCE((SELECT current_balance FROM staff_earning_accounts sea WHERE sea.staff_user_uuid = u.uuid), 0) AS outstandingEarnings,
        COALESCE((SELECT s.name FROM service_staff_assignments ssa JOIN service_order_items soi ON soi.uuid = ssa.order_item_uuid JOIN services s ON s.uuid = soi.service_uuid WHERE ssa.staff_user_uuid = u.uuid GROUP BY s.uuid, s.name ORDER BY COUNT(*) DESC LIMIT 1), '') AS mostRenderedService
      FROM users u
      LEFT JOIN cashier_shifts cs ON cs.cashier_user_uuid = u.uuid AND cs.opened_at BETWEEN ? AND ?
      LEFT JOIN sales s ON s.shift_uuid = cs.uuid AND s.created_at BETWEEN ? AND ?
      LEFT JOIN sale_discounts sd ON sd.sale_uuid = s.uuid
      GROUP BY u.uuid, u.display_name
      HAVING transactionCount > 0 OR servicesAssigned > 0 OR outstandingEarnings <> 0
      ORDER BY salesValue DESC, serviceRevenue DESC`, [
      range.from, range.to, range.from, range.to, range.from, range.to, range.from, range.to, range.from, range.to, range.from, range.to, range.from, range.to, range.from, range.to, range.from, range.to, range.from, range.to, range.from, range.to, range.from, range.to,
    ]);

    return staff.map((row) => ({ ...row, indicators: this.indicators(row) }));
  }

  report(type: LocalReportType, filters: ReportFilters): Row[] {
    this.assertBranch(filters.branchUuid);
    const range = this.range(filters);
    const queries: Record<LocalReportType, { sql: string; params?: unknown[] }> = {
      'daily-sales': { sql: "SELECT substr(created_at, 1, 10) AS date, COUNT(*) AS transactionCount, SUM(CAST(total_amount AS REAL)) AS salesValue, SUM(CAST(discount_amount AS REAL)) AS discounts FROM sales WHERE status = 'completed' AND created_at BETWEEN ? AND ? GROUP BY date ORDER BY date", params: [range.from, range.to] },
      'sales-by-product': { sql: "SELECT p.sku, p.name AS product, SUM(CAST(si.quantity AS REAL)) AS quantity, SUM(CAST(si.line_total AS REAL)) AS salesValue FROM sale_items si JOIN sales s ON s.uuid = si.sale_uuid JOIN products p ON p.uuid = si.product_uuid WHERE s.status = 'completed' AND s.created_at BETWEEN ? AND ? GROUP BY p.uuid, p.sku, p.name ORDER BY salesValue DESC", params: [range.from, range.to] },
      'sales-by-category': { sql: "SELECT COALESCE(pc.name, 'Uncategorized') AS category, SUM(CAST(si.line_total AS REAL)) AS salesValue FROM sale_items si JOIN sales s ON s.uuid = si.sale_uuid JOIN products p ON p.uuid = si.product_uuid LEFT JOIN product_categories pc ON pc.uuid = p.category_uuid WHERE s.status = 'completed' AND s.created_at BETWEEN ? AND ? GROUP BY pc.uuid, pc.name ORDER BY salesValue DESC", params: [range.from, range.to] },
      'sales-by-cashier': { sql: "SELECT u.display_name AS cashier, COUNT(s.uuid) AS transactionCount, SUM(CAST(s.total_amount AS REAL)) AS salesValue FROM sales s JOIN cashier_shifts cs ON cs.uuid = s.shift_uuid JOIN users u ON u.uuid = cs.cashier_user_uuid WHERE s.status = 'completed' AND s.created_at BETWEEN ? AND ? GROUP BY u.uuid, u.display_name ORDER BY salesValue DESC", params: [range.from, range.to] },
      'payment-breakdown': { sql: "SELECT pm.name AS paymentMethod, SUM(CAST(sp.amount AS REAL)) AS amount FROM sale_payments sp JOIN sales s ON s.uuid = sp.sale_uuid JOIN payment_methods pm ON pm.uuid = sp.payment_method_uuid WHERE s.status = 'completed' AND s.created_at BETWEEN ? AND ? GROUP BY pm.uuid, pm.name ORDER BY amount DESC", params: [range.from, range.to] },
      inventory: { sql: "SELECT p.sku, p.name AS product, SUM(CAST(st.quantity AS REAL)) AS quantity, SUM(CAST(st.quantity AS REAL) * CAST(p.cost_price AS REAL)) AS stockValue, p.reorder_level AS reorderLevel FROM stocks st JOIN products p ON p.uuid = st.product_uuid GROUP BY p.uuid, p.sku, p.name, p.reorder_level ORDER BY p.name" },
      'stock-movement': { sql: 'SELECT sm.created_at AS date, sm.movement_type AS movementType, p.name AS product, sm.quantity_base AS quantity, sm.reference FROM stock_movements sm JOIN products p ON p.uuid = sm.product_uuid WHERE sm.created_at BETWEEN ? AND ? ORDER BY sm.created_at DESC', params: [range.from, range.to] },
      purchases: { sql: 'SELECT p.invoice_number AS invoiceNumber, s.name AS supplier, p.status, p.total_amount AS totalAmount, p.amount_paid AS amountPaid, p.created_at AS date FROM purchases p JOIN suppliers s ON s.uuid = p.supplier_uuid WHERE p.created_at BETWEEN ? AND ? ORDER BY p.created_at DESC', params: [range.from, range.to] },
      'customer-debt': { sql: 'SELECT c.code, c.name AS customer, ca.current_balance AS balance FROM customer_accounts ca JOIN customers c ON c.uuid = ca.customer_uuid WHERE CAST(ca.current_balance AS REAL) <> 0 ORDER BY CAST(ca.current_balance AS REAL) DESC' },
      'supplier-balances': { sql: 'SELECT s.code, s.name AS supplier, sa.current_balance AS balance FROM supplier_accounts sa JOIN suppliers s ON s.uuid = sa.supplier_uuid WHERE CAST(sa.current_balance AS REAL) <> 0 ORDER BY CAST(sa.current_balance AS REAL) DESC' },
      expenses: { sql: 'SELECT e.created_at AS date, ec.name AS category, e.description, e.amount, e.payment_method AS paymentMethod, e.status FROM expenses e JOIN expense_categories ec ON ec.uuid = e.category_uuid WHERE e.created_at BETWEEN ? AND ? ORDER BY e.created_at DESC', params: [range.from, range.to] },
      'cashier-settlements': { sql: 'SELECT u.display_name AS cashier, cs.opened_at, cs.closed_at, st.expected_cash AS expectedCash, st.actual_cash AS actualCash, st.variance, st.status FROM cashier_settlements st JOIN cashier_shifts cs ON cs.uuid = st.shift_uuid JOIN users u ON u.uuid = cs.cashier_user_uuid WHERE st.created_at BETWEEN ? AND ? ORDER BY st.created_at DESC', params: [range.from, range.to] },
      'service-revenue': { sql: "SELECT so.order_number AS orderNumber, so.status, SUM(CAST(sp.amount AS REAL)) AS revenue, SUM(CAST(se.amount AS REAL)) AS directExpenses FROM service_orders so LEFT JOIN service_payments sp ON sp.order_uuid = so.uuid LEFT JOIN service_expenses se ON se.order_uuid = so.uuid WHERE so.created_at BETWEEN ? AND ? GROUP BY so.uuid, so.order_number, so.status ORDER BY revenue DESC", params: [range.from, range.to] },
      'service-staff-performance': { sql: "SELECT u.display_name AS staff, COUNT(DISTINCT ssa.order_item_uuid) AS servicesAssigned, SUM(CASE WHEN so.status = 'completed' THEN 1 ELSE 0 END) AS servicesCompleted, SUM(CASE WHEN so.status IN ('cancelled', 'refunded') THEN 1 ELSE 0 END) AS servicesCancelled FROM service_staff_assignments ssa JOIN users u ON u.uuid = ssa.staff_user_uuid JOIN service_order_items soi ON soi.uuid = ssa.order_item_uuid JOIN service_orders so ON so.uuid = soi.order_uuid WHERE so.created_at BETWEEN ? AND ? GROUP BY u.uuid, u.display_name ORDER BY servicesCompleted DESC", params: [range.from, range.to] },
      'revenue-allocations': { sql: 'SELECT sra.created_at AS date, sra.component_type AS componentType, u.display_name AS staff, sra.amount, sra.status FROM service_revenue_allocations sra LEFT JOIN users u ON u.uuid = sra.staff_user_uuid WHERE sra.created_at BETWEEN ? AND ? ORDER BY sra.created_at DESC', params: [range.from, range.to] },
      'staff-earnings': { sql: 'SELECT u.display_name AS staff, sea.current_balance AS outstandingBalance FROM staff_earning_accounts sea JOIN users u ON u.uuid = sea.staff_user_uuid ORDER BY CAST(sea.current_balance AS REAL) DESC' },
      'staff-settlements': { sql: 'SELECT ss.created_at AS date, u.display_name AS staff, ss.amount, ss.status FROM staff_settlements ss JOIN users u ON u.uuid = ss.staff_user_uuid WHERE ss.created_at BETWEEN ? AND ? ORDER BY ss.created_at DESC', params: [range.from, range.to] },
    };
    const query = queries[type];
    return this.rows(query.sql, query.params ?? []);
  }

  refreshDailySummary(date = this.today()) {
    const dashboard = this.dashboard(date);
    const now = new Date().toISOString();
    this.sqlite.prepare(`INSERT INTO daily_report_summaries (uuid, branch_uuid, report_date, sales_total, expenses_total, gross_profit, net_profit, payment_breakdown_json, generated_at)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
      ON CONFLICT(branch_uuid, report_date) DO UPDATE SET sales_total = excluded.sales_total, expenses_total = excluded.expenses_total, gross_profit = excluded.gross_profit, net_profit = excluded.net_profit, payment_breakdown_json = excluded.payment_breakdown_json, generated_at = excluded.generated_at`)
      .run(randomUUID(), this.branchUuid, date, String(dashboard.todaySales), String(dashboard.expenses), String(dashboard.grossProfit), String(dashboard.estimatedNetProfit), JSON.stringify(dashboard.paymentBreakdown), now);
    return dashboard;
  }

  createReportJob(type: LocalReportType, filters: ReportFilters, format: 'csv' | 'pdf', requestedByUserUuid: string) {
    this.assertBranch(filters.branchUuid);
    const job = { uuid: randomUUID(), type, filters, format, requestedByUserUuid };
    this.sqlite.prepare('INSERT INTO report_jobs (uuid, report_type, requested_by_user_uuid, filters_json, format, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
      .run(job.uuid, type, requestedByUserUuid, JSON.stringify(filters), format, 'queued', new Date().toISOString());
    return job;
  }

  processReportJob(jobUuid: string) {
    const job = this.sqlite.prepare("SELECT report_type AS type, filters_json AS filters, format FROM report_jobs WHERE uuid = ? AND status = 'queued'").get(jobUuid) as { type: LocalReportType; filters: string; format: 'csv' | 'pdf' } | undefined;
    if (!job) throw new Error('Queued report job was not found');
    this.sqlite.prepare("UPDATE report_jobs SET status = 'processing' WHERE uuid = ?").run(jobUuid);
    try {
      const rows = this.report(job.type, JSON.parse(job.filters) as ReportFilters);
      if (job.format === 'pdf') throw new Error('PDF export foundation is registered; a PDF renderer must be configured before this report can be generated.');
      mkdirSync(this.exportPath, { recursive: true });
      const path = join(this.exportPath, `${job.type}-${jobUuid}.csv`);
      writeFileSync(path, this.toCsv(rows), 'utf8');
      this.sqlite.prepare("UPDATE report_jobs SET status = 'completed', result_path = ?, completed_at = ? WHERE uuid = ?").run(path, new Date().toISOString(), jobUuid);
      return { jobUuid, path };
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Report processing failed';
      this.sqlite.prepare("UPDATE report_jobs SET status = 'failed', error_message = ?, completed_at = ? WHERE uuid = ?").run(message, new Date().toISOString(), jobUuid);
      throw error;
    }
  }

  private range(filters: ReportFilters) { return { from: `${filters.from}T00:00:00.000Z`, to: `${filters.to}T23:59:59.999Z` }; }
  private today() { return new Date().toISOString().slice(0, 10); }
  private assertBranch(branchUuid: string | undefined) { if (branchUuid && branchUuid !== this.branchUuid) throw new Error('This Shop App can only report on its configured branch'); }
  private rows(sql: string, params: unknown[] | { from: string; to: string } = []): Row[] { const values = Array.isArray(params) ? params : [params.from, params.to]; return this.sqlite.prepare(sql).all(...values) as Row[]; }
  private total(sql: string, filters?: { from: string; to: string }) { const row = this.sqlite.prepare(sql).get(...(filters ? [filters.from, filters.to] : [])) as { total: number }; return row.total; }
  private count(sql: string) { return (this.sqlite.prepare(sql).get() as { count: number }).count; }
  private indicators(row: Row) { const variance = Number(row.cashVariance ?? 0); return [variance < 0 ? 'Requires review' : null, Number(row.discounts ?? 0) > Number(row.salesValue ?? 0) * 0.15 ? 'Higher than usual' : null, Number(row.transactionCount ?? 0) === 0 ? 'Lower than expected' : null, Math.abs(variance) > Number(row.expectedCash ?? 0) * 0.05 ? 'Outside expected range' : null].filter((indicator): indicator is string => indicator !== null); }
  private toCsv(rows: Row[]) { if (rows.length === 0) return ''; const headers = Object.keys(rows[0]); const escape = (value: unknown) => `"${String(value ?? '').replaceAll('"', '""')}"`; return [headers.map(escape).join(','), ...rows.map((row) => headers.map((header) => escape(row[header])).join(','))].join('\n'); }
}