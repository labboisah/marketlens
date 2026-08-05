import { afterEach, describe, expect, it, vi } from 'vitest';
import { classifyPaymentMethod, loadRoleDashboardSnapshot } from '../src/renderer/src/services/dashboard-summary-service';

const originalFetch = global.fetch;

function jsonResponse(status: number, body: unknown): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: {
      'Content-Type': 'application/json',
    },
  });
}

afterEach(() => {
  vi.restoreAllMocks();
  global.fetch = originalFetch;
});

describe('Client App dashboard summary service', () => {
  it('loads role dashboard data from the Shop App summary and report endpoints', async () => {
    global.fetch = vi
      .fn()
      .mockImplementation(async (input: RequestInfo | URL) => {
        const url = String(input);

        if (url.includes('/dashboard/local')) {
          return jsonResponse(200, {
            success: true,
            message: 'Operation completed',
            data: {
              date: '2026-08-05',
              todaySales: 1250,
              paymentBreakdown: [
                { paymentMethod: 'Cash', amount: 500 },
                { paymentMethod: 'POS Terminal', amount: 750 },
              ],
              expenses: 120,
              grossProfit: 800,
              estimatedNetProfit: 680,
              customerDebt: 300,
              supplierBalance: 150,
              stockValue: 5000,
              lowStockCount: 2,
              openCashierShifts: 1,
              cashierShortages: 0,
              servicesInProgress: 3,
              serviceRevenue: 250,
              pendingStaffEarnings: 75,
              lastPlatformSynchronization: null,
              authorizedClientApps: 4,
            },
            meta: {},
          });
        }

        return jsonResponse(200, {
          success: true,
          message: 'Operation completed',
          data: [
            {
              staffUserUuid: '00000000-0000-4000-8000-000000000001',
              staffName: 'Ada Cashier',
              transactionCount: 3,
              salesValue: 250,
              returns: 10,
              cancellations: 1,
              expectedCash: 100,
              actualCash: 95,
              cashVariance: -5,
              servicesAssigned: 4,
              servicesCompleted: 2,
              servicesCancelled: 1,
              serviceRevenue: 60,
              directServiceExpenses: 5,
              staffShareEarned: 35,
              staffShareApproved: 35,
              staffShareSettled: 10,
              outstandingEarnings: 25,
              mostRenderedService: 'Repair',
            },
          ],
          meta: {},
        });
      }) as typeof fetch;

    const result = await loadRoleDashboardSnapshot({
      shopBaseUrl: 'http://127.0.0.1:4090',
      accessToken: 'token',
      role: 'cashier',
    });

    expect(result.summary.todaySales).toBe(1250);
    expect(result.staffPerformanceRows).toHaveLength(1);
    expect(result.staffPerformanceRows[0].staffName).toBe('Ada Cashier');
    expect(classifyPaymentMethod('POS Terminal')).toBe('pos');
  });
});
