import { apiBasePath, apiSuccessResponseSchema } from '@marketlens/api-contracts';
import { z } from 'zod';
import type { ClientRole } from '../state/client-ui-store';

const dashboardPaymentBreakdownSchema = z.object({
  paymentMethod: z.string(),
  amount: z.coerce.number(),
});

const dashboardSummarySchema = z.object({
  date: z.string(),
  todaySales: z.coerce.number(),
  paymentBreakdown: z.array(dashboardPaymentBreakdownSchema),
  expenses: z.coerce.number(),
  grossProfit: z.coerce.number(),
  estimatedNetProfit: z.coerce.number(),
  customerDebt: z.coerce.number(),
  supplierBalance: z.coerce.number(),
  stockValue: z.coerce.number(),
  lowStockCount: z.coerce.number(),
  openCashierShifts: z.coerce.number(),
  cashierShortages: z.coerce.number(),
  servicesInProgress: z.coerce.number(),
  serviceRevenue: z.coerce.number(),
  pendingStaffEarnings: z.coerce.number(),
  lastPlatformSynchronization: z.string().nullable(),
  authorizedClientApps: z.coerce.number(),
});

const dashboardResponseSchema = apiSuccessResponseSchema(dashboardSummarySchema);
const reportRowsResponseSchema = apiSuccessResponseSchema(z.array(z.record(z.string(), z.unknown())));

export interface RoleDashboardSnapshot {
  summary: z.infer<typeof dashboardSummarySchema>;
  staffPerformanceRows: Record<string, unknown>[];
  inventoryRows: Record<string, unknown>[];
  stockMovementRows: Record<string, unknown>[];
  purchasesRows: Record<string, unknown>[];
  expensesRows: Record<string, unknown>[];
  cashierSettlementRows: Record<string, unknown>[];
  serviceRevenueRows: Record<string, unknown>[];
  serviceStaffPerformanceRows: Record<string, unknown>[];
  staffEarningsRows: Record<string, unknown>[];
  staffSettlementRows: Record<string, unknown>[];
  customerDebtRows: Record<string, unknown>[];
  supplierBalancesRows: Record<string, unknown>[];
  revenueAllocationRows: Record<string, unknown>[];
}

function normalizeBaseUrl(baseUrl: string): string {
  return baseUrl.replace(/\/+$/, '');
}

function todayIsoDate(): string {
  return new Date().toISOString().slice(0, 10);
}

async function parseJson<T>(response: Response, schema: { parse(input: unknown): T }): Promise<T> {
  const payload = await response.json();

  if (!response.ok) {
    throw new Error(`Shop App returned ${response.status}`);
  }

  return schema.parse(payload);
}

async function fetchDashboardSummary(shopBaseUrl: string, accessToken: string, date: string) {
  const response = await fetch(`${normalizeBaseUrl(shopBaseUrl)}${apiBasePath}/dashboard/local?date=${encodeURIComponent(date)}`, {
    headers: {
      Accept: 'application/json',
      Authorization: `Bearer ${accessToken}`,
    },
  });

  return (await parseJson(response, dashboardResponseSchema)).data;
}

async function fetchReportRows(
  shopBaseUrl: string,
  accessToken: string,
  reportType: string,
  filters: { from: string; to: string },
) {
  const url = new URL(`${normalizeBaseUrl(shopBaseUrl)}${apiBasePath}/reports/${reportType}`);
  url.searchParams.set('from', filters.from);
  url.searchParams.set('to', filters.to);

  const response = await fetch(url, {
    headers: {
      Accept: 'application/json',
      Authorization: `Bearer ${accessToken}`,
    },
  });

  return (await parseJson(response, reportRowsResponseSchema)).data;
}

export async function loadRoleDashboardSnapshot(input: {
  shopBaseUrl: string;
  accessToken: string;
  role: ClientRole;
  currentUserUuid?: string;
  date?: string;
}): Promise<RoleDashboardSnapshot> {
  const date = input.date ?? todayIsoDate();
  const filters = { from: date, to: date };

  const [summary, staffPerformanceRows, inventoryRows, stockMovementRows, purchasesRows, expensesRows, cashierSettlementRows, serviceRevenueRows, serviceStaffPerformanceRows, staffEarningsRows, staffSettlementRows, customerDebtRows, supplierBalancesRows, revenueAllocationRows] = await Promise.all([
    fetchDashboardSummary(input.shopBaseUrl, input.accessToken, date),
    fetchReportRows(input.shopBaseUrl, input.accessToken, 'staff-performance', filters).catch(() => []),
    fetchReportRows(input.shopBaseUrl, input.accessToken, 'inventory', filters).catch(() => []),
    fetchReportRows(input.shopBaseUrl, input.accessToken, 'stock-movement', filters).catch(() => []),
    fetchReportRows(input.shopBaseUrl, input.accessToken, 'purchases', filters).catch(() => []),
    fetchReportRows(input.shopBaseUrl, input.accessToken, 'expenses', filters).catch(() => []),
    fetchReportRows(input.shopBaseUrl, input.accessToken, 'cashier-settlements', filters).catch(() => []),
    fetchReportRows(input.shopBaseUrl, input.accessToken, 'service-revenue', filters).catch(() => []),
    fetchReportRows(input.shopBaseUrl, input.accessToken, 'service-staff-performance', filters).catch(() => []),
    fetchReportRows(input.shopBaseUrl, input.accessToken, 'staff-earnings', filters).catch(() => []),
    fetchReportRows(input.shopBaseUrl, input.accessToken, 'staff-settlements', filters).catch(() => []),
    fetchReportRows(input.shopBaseUrl, input.accessToken, 'customer-debt', filters).catch(() => []),
    fetchReportRows(input.shopBaseUrl, input.accessToken, 'supplier-balances', filters).catch(() => []),
    fetchReportRows(input.shopBaseUrl, input.accessToken, 'revenue-allocations', filters).catch(() => []),
  ]);

  return {
    summary,
    staffPerformanceRows,
    inventoryRows,
    stockMovementRows,
    purchasesRows,
    expensesRows,
    cashierSettlementRows,
    serviceRevenueRows,
    serviceStaffPerformanceRows,
    staffEarningsRows,
    staffSettlementRows,
    customerDebtRows,
    supplierBalancesRows,
    revenueAllocationRows,
  };
}

export function findRowByUserUuid(rows: Record<string, unknown>[], userUuid?: string): Record<string, unknown> | undefined {
  if (!userUuid) {
    return rows[0];
  }

  return rows.find((row) => row.staffUserUuid === userUuid || row.userUuid === userUuid || row.uuid === userUuid);
}

export function asNumber(value: unknown): number {
  if (typeof value === 'number') {
    return value;
  }

  if (typeof value === 'string' && value.trim().length > 0) {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : 0;
  }

  return 0;
}

export function asText(value: unknown, fallback = '—'): string {
  if (typeof value === 'string' && value.trim().length > 0) {
    return value;
  }

  if (typeof value === 'number' && Number.isFinite(value)) {
    return String(value);
  }

  return fallback;
}

export function classifyPaymentMethod(paymentMethod: string): 'cash' | 'transfer' | 'pos' | 'credit' | 'other' {
  const normalized = paymentMethod.toLowerCase();

  if (normalized.includes('cash')) {
    return 'cash';
  }
  if (normalized.includes('transfer') || normalized.includes('bank') || normalized.includes('mobile')) {
    return 'transfer';
  }
  if (normalized.includes('pos') || normalized.includes('card') || normalized.includes('terminal')) {
    return 'pos';
  }
  if (normalized.includes('credit') || normalized.includes('debt')) {
    return 'credit';
  }

  return 'other';
}
