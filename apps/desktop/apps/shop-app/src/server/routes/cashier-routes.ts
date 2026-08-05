import { z } from 'zod';
import type { FastifyInstance } from 'fastify';
import type { FastifyReply, FastifyRequest } from 'fastify';
import type { ShopApplication } from '../../services/application/shop-application';
import { verifyAccessToken } from '../../services/security/token-service';

const money = z.string().regex(/^\d+(\.\d+)?$/);
const cashDenominationsSchema = z.array(z.object({ denomination: z.string().min(1), count: z.coerce.number().int().nonnegative() })).default([]);
const expenseFilterSchema = z.object({
  status: z.string().optional(),
  categoryUuid: z.string().uuid().optional(),
  from: z.string().optional(),
  to: z.string().optional(),
  creatorUserUuid: z.string().uuid().optional(),
});

function parseBearer(header?: string): string | null {
  if (!header || !header.startsWith('Bearer ')) {
    return null;
  }

  return header.slice('Bearer '.length);
}

async function authorizeCashierRequest(request: FastifyRequest, reply: FastifyReply, app: ShopApplication, permission: string): Promise<{ userUuid: string; deviceUuid: string }> {
  const token = parseBearer(request.headers.authorization as string | undefined);
  const deviceUuid = String(request.headers['x-marketlens-device-uuid'] ?? '').trim();

  if (!token || !deviceUuid) {
    reply.code(401).send({ success: false, error: { code: 'AUTHENTICATION_REQUIRED', message: 'A valid workstation session is required' } });
    throw new Error('Authorization failed');
  }

  const payload = verifyAccessToken(token, app.environment.SHOP_JWT_SECRET);
  const userUuid = typeof payload?.sub === 'string' ? payload.sub : undefined;
  const sessionUuid = typeof payload?.session === 'string' ? payload.session : undefined;

  if (!userUuid || !sessionUuid) {
    reply.code(401).send({ success: false, error: { code: 'AUTHENTICATION_REQUIRED', message: 'A valid workstation session is required' } });
    throw new Error('Authorization failed');
  }

  const session = app.services.sessions.findByUuid(sessionUuid);
  if (!session || session.revokedAt || session.deviceUuid !== deviceUuid) {
    reply.code(403).send({ success: false, error: { code: 'AUTHORIZATION_DENIED', message: 'This device is not authorized for cashier shift operations' } });
    throw new Error('Authorization failed');
  }

  const user = await app.services.users.findByUuid(userUuid);
  if (!user || user.status !== 'active') {
    reply.code(403).send({ success: false, error: { code: 'AUTHORIZATION_DENIED', message: 'This account cannot operate cashier shifts' } });
    throw new Error('Authorization failed');
  }

  const allowed = app.database.sqlite.prepare(`
    SELECT 1
    FROM user_roles ur
    JOIN roles r ON r.uuid = ur.role_uuid
    LEFT JOIN role_permissions rp ON rp.role_uuid = r.uuid
    LEFT JOIN permissions p ON p.uuid = rp.permission_uuid
    WHERE ur.user_uuid = ? AND (p.key = ? OR r.name IN ('Owner', 'Administrator'))
    LIMIT 1
  `).get(userUuid, permission);

  if (!allowed) {
    reply.code(403).send({ success: false, error: { code: 'AUTHORIZATION_DENIED', message: 'You are not permitted to operate cashier shifts' } });
    throw new Error('Authorization failed');
  }

  const device = app.services.clientDevices.findByUuid(deviceUuid);
  if (!device || device.status !== 'authorized') {
    reply.code(403).send({ success: false, error: { code: 'AUTHORIZATION_DENIED', message: 'This workstation device is not authorized' } });
    throw new Error('Authorization failed');
  }

  return { userUuid, deviceUuid };
}

function toNumber(value: string | null | undefined): number {
  return Number(value ?? 0);
}

function shiftNumber(shiftUuid: string): string {
  return `CSH-${shiftUuid.slice(0, 8).toUpperCase()}`;
}

export async function registerCashierRoutes(server: FastifyInstance, app: ShopApplication): Promise<void> {
  server.get('/api/v1/terminals', async (request, reply) => {
    await authorizeCashierRequest(request, reply, app, 'finance.open_cashier_shift').catch(() => undefined);
    if (reply.sent) return;

    const data = app.database.sqlite.prepare("SELECT uuid, code, name, status FROM terminals WHERE status = 'active' ORDER BY name ASC").all();
    return { success: true, data, meta: {} };
  });

  server.get('/api/v1/cash-accounts', async (request, reply) => {
    await authorizeCashierRequest(request, reply, app, 'finance.open_cashier_shift').catch(() => undefined);
    if (reply.sent) return;

    const data = app.database.sqlite.prepare("SELECT uuid, code, name, current_balance AS currentBalance, active FROM cash_accounts WHERE active = '1' ORDER BY name ASC").all();
    return { success: true, data, meta: {} };
  });

  server.get('/api/v1/bank-accounts', async (request, reply) => {
    await authorizeCashierRequest(request, reply, app, 'finance.open_cashier_shift').catch(() => undefined);
    if (reply.sent) return;

    const data = app.database.sqlite.prepare("SELECT uuid, code, name, bank_name AS bankName, account_number_masked AS accountNumberMasked, current_balance AS currentBalance, active FROM bank_accounts WHERE active = '1' ORDER BY name ASC").all();
    return { success: true, data, meta: {} };
  });

  server.get('/api/v1/expense-categories', async (request, reply) => {
    await authorizeCashierRequest(request, reply, app, 'finance.open_cashier_shift').catch(() => undefined);
    if (reply.sent) return;

    return { success: true, data: app.services.cashier.listExpenseCategories(), meta: {} };
  });

  server.get('/api/v1/cashier-shifts/current', async (request, reply) => {
    const { userUuid } = await authorizeCashierRequest(request, reply, app, 'finance.open_cashier_shift').catch(() => ({ userUuid: '', deviceUuid: '' }));
    if (reply.sent) return;

    const shift = app.database.sqlite.prepare(`
      SELECT cs.uuid, cs.terminal_uuid AS terminalUuid, cs.cash_account_uuid AS cashAccountUuid, cs.cashier_user_uuid AS cashierUserUuid,
        cs.status, cs.opening_cash AS openingCash, cs.notes, cs.expected_cash AS expectedCash, cs.actual_cash AS actualCash,
        cs.variance, cs.opened_at AS openedAt, cs.closed_at AS closedAt,
        t.name AS terminalName, t.code AS terminalCode, ca.name AS cashAccountName
      FROM cashier_shifts cs
      JOIN terminals t ON t.uuid = cs.terminal_uuid
      JOIN cash_accounts ca ON ca.uuid = cs.cash_account_uuid
      WHERE cs.cashier_user_uuid = ? AND cs.status = 'open'
      ORDER BY cs.opened_at DESC
      LIMIT 1
    `).get(userUuid) as Record<string, unknown> | undefined;

    if (!shift) {
      return { success: true, data: { active: false, shift: null }, meta: {} };
    }

    const shiftUuid = String(shift.uuid);
    const sales = app.database.sqlite.prepare("SELECT COUNT(*) AS salesCount, COALESCE(SUM(CAST(total_amount AS REAL)), 0) AS salesValue FROM sales WHERE shift_uuid = ? AND status = 'completed'").get(shiftUuid) as { salesCount: number; salesValue: number };
    const refunds = app.database.sqlite.prepare("SELECT COALESCE(SUM(CAST(refund_amount AS REAL)), 0) AS refunds FROM sale_returns WHERE sale_uuid IN (SELECT uuid FROM sales WHERE shift_uuid = ?)").get(shiftUuid) as { refunds: number };
    const expenses = app.database.sqlite.prepare("SELECT COALESCE(SUM(CAST(amount AS REAL)), 0) AS expenses FROM expenses WHERE shift_uuid = ? AND status = 'approved'").get(shiftUuid) as { expenses: number };
    const payments = app.database.sqlite.prepare("SELECT pm.name AS paymentMethod, COALESCE(SUM(CAST(sp.amount AS REAL)), 0) AS amount FROM sale_payments sp JOIN sales s ON s.uuid = sp.sale_uuid JOIN payment_methods pm ON pm.uuid = sp.payment_method_uuid WHERE s.shift_uuid = ? AND s.status = 'completed' GROUP BY pm.name ORDER BY amount DESC").all(shiftUuid) as Array<{ paymentMethod: string; amount: number }>;
    const durationMs = Date.now() - new Date(String(shift.openedAt)).getTime();

    return {
      success: true,
      data: {
        active: true,
        shift: {
          ...shift,
          shiftNumber: shiftNumber(shiftUuid),
          salesCount: sales.salesCount,
          salesValue: sales.salesValue,
          refunds: refunds.refunds,
          expenses: expenses.expenses,
          expectedCash: toNumber(String(shift.expectedCash ?? null)) || toNumber(String(shift.openingCash)) + payments.filter((payment) => payment.paymentMethod.toLowerCase().includes('cash')).reduce((total, payment) => total + Number(payment.amount), 0) - expenses.expenses,
          currentDurationMinutes: Math.max(0, Math.floor(durationMs / 60000)),
          paymentMethodSummary: payments,
        },
      },
      meta: {},
    };
  });

  server.post('/api/v1/terminals', async request => ({ success: true, data: await app.services.cashier.createTerminal(z.object({ code: z.string().min(1), name: z.string().min(1) }).parse(request.body)), meta: {} }));
  server.post('/api/v1/cash-accounts', async request => ({ success: true, data: await app.services.cashier.createCashAccount(z.object({ code: z.string().min(1), name: z.string().min(1) }).parse(request.body)), meta: {} }));
  server.post('/api/v1/expense-categories', async request => ({ success: true, data: await app.services.cashier.createExpenseCategory(z.object({ name: z.string().min(1), requiresApproval: z.boolean() }).parse(request.body)), meta: {} }));
  server.get('/api/v1/expenses', async (request, reply) => {
    await authorizeCashierRequest(request, reply, app, 'finance.open_cashier_shift').catch(() => undefined);
    if (reply.sent) return;

    const query = expenseFilterSchema.parse(request.query ?? {});
    return { success: true, data: app.services.cashier.listExpenses(query), meta: {} };
  });

  server.get('/api/v1/expenses/summary/by-category', async (request, reply) => {
    await authorizeCashierRequest(request, reply, app, 'finance.open_cashier_shift').catch(() => undefined);
    if (reply.sent) return;

    const query = expenseFilterSchema.parse(request.query ?? {});
    return { success: true, data: app.services.cashier.summarizeExpensesByCategory({ from: query.from, to: query.to }), meta: {} };
  });

  server.get('/api/v1/expenses/summary/by-date', async (request, reply) => {
    await authorizeCashierRequest(request, reply, app, 'finance.open_cashier_shift').catch(() => undefined);
    if (reply.sent) return;

    const query = expenseFilterSchema.parse(request.query ?? {});
    return { success: true, data: app.services.cashier.summarizeExpensesByDate({ from: query.from, to: query.to }), meta: {} };
  });

  server.get('/api/v1/expenses/summary/by-creator', async (request, reply) => {
    await authorizeCashierRequest(request, reply, app, 'finance.open_cashier_shift').catch(() => undefined);
    if (reply.sent) return;

    const query = expenseFilterSchema.parse(request.query ?? {});
    return { success: true, data: app.services.cashier.summarizeExpensesByCreator({ from: query.from, to: query.to }), meta: {} };
  });

  server.post('/api/v1/cashier-shifts', async (request, reply) => {
    const { userUuid } = await authorizeCashierRequest(request, reply, app, 'finance.open_cashier_shift').catch(() => ({ userUuid: '', deviceUuid: '' }));
    if (reply.sent) return;

    const input = z.object({ terminalUuid: z.string().uuid(), cashAccountUuid: z.string().uuid(), openingCash: money, notes: z.string().optional() }).parse(request.body);
    const conflict = app.database.sqlite.prepare("SELECT uuid FROM cashier_shifts WHERE status = 'open' AND (cashier_user_uuid = ? OR terminal_uuid = ? OR cash_account_uuid = ?) LIMIT 1").get(userUuid, input.terminalUuid, input.cashAccountUuid);
    if (conflict) {
      return reply.code(409).send({ success: false, error: { code: 'CONFLICT', message: 'Another active shift already conflicts with this workstation' } });
    }

    return {
      success: true,
      data: await app.services.cashier.openShift({
        terminalUuid: input.terminalUuid,
        cashAccountUuid: input.cashAccountUuid,
        cashierUserUuid: userUuid,
        openingCash: input.openingCash,
        notes: input.notes,
      }),
      meta: {},
    };
  });
  server.post('/api/v1/cashier-shifts/:shiftUuid/payments', async request => { const { shiftUuid } = request.params as { shiftUuid: string }; return { success: true, data: await app.services.cashier.recordShiftPayment(shiftUuid, z.object({ type: z.enum(['cash_sale', 'cash_refund']), amount: money, reference: z.string().optional() }).parse(request.body)), meta: {} }; });
  server.post('/api/v1/cashier-shifts/:shiftUuid/close', async (request, reply) => {
    const { userUuid } = await authorizeCashierRequest(request, reply, app, 'finance.close_cashier_shift').catch(() => ({ userUuid: '', deviceUuid: '' }));
    if (reply.sent) return;

    const { shiftUuid } = request.params as { shiftUuid: string };
    const shift = app.database.sqlite.prepare("SELECT uuid, cashier_user_uuid AS cashierUserUuid FROM cashier_shifts WHERE uuid = ? AND status = 'open'").get(shiftUuid) as { uuid: string; cashierUserUuid: string } | undefined;
    if (!shift || shift.cashierUserUuid !== userUuid) {
      return reply.code(404).send({ success: false, error: { code: 'NOT_FOUND', message: 'Open cashier shift was not found' } });
    }

    const input = z.object({ actualCash: money, notes: z.string().optional(), cashDenominations: cashDenominationsSchema, supportingExplanation: z.string().optional() }).parse(request.body);
    return {
      success: true,
      data: await app.services.cashier.closeShift(shiftUuid, input),
      meta: {},
    };
  });
  server.post('/api/v1/cashier-settlements/:settlementUuid/verify', async request => { const { settlementUuid } = request.params as { settlementUuid: string }; const { verifiedByUserUuid } = z.object({ verifiedByUserUuid: z.string().uuid() }).parse(request.body); return { success: true, data: await app.services.cashier.verifySettlement(settlementUuid, verifiedByUserUuid), meta: {} }; });
  server.post('/api/v1/expenses', async request => ({ success: true, data: await app.services.cashier.recordExpense(z.object({ categoryUuid: z.string().uuid(), amount: money, paymentMethod: z.enum(['cash', 'bank']), description: z.string().min(1), cashAccountUuid: z.string().uuid().optional(), bankAccountUuid: z.string().uuid().optional(), shiftUuid: z.string().uuid().optional(), receiptAttachmentPath: z.string().min(1).optional(), approvalRequested: z.boolean().optional(), createdByUserUuid: z.string().uuid().optional(), createdByName: z.string().min(1).optional() }).parse(request.body)), meta: {} }));
  server.post('/api/v1/expenses/:expenseUuid/approve', async request => { const { expenseUuid } = request.params as { expenseUuid: string }; const { approvedByUserUuid } = z.object({ approvedByUserUuid: z.string().uuid() }).parse(request.body); return { success: true, data: await app.services.cashier.approveExpense(expenseUuid, approvedByUserUuid), meta: {} }; });
  server.post('/api/v1/expenses/:expenseUuid/reject', async request => { const { expenseUuid } = request.params as { expenseUuid: string }; const { rejectedByUserUuid, reason } = z.object({ rejectedByUserUuid: z.string().uuid(), reason: z.string().optional() }).parse(request.body); return { success: true, data: await app.services.cashier.rejectExpense(expenseUuid, rejectedByUserUuid, reason), meta: {} }; });
  server.post('/api/v1/expenses/:expenseUuid/cancel', async request => { const { expenseUuid } = request.params as { expenseUuid: string }; const { reason } = z.object({ reason: z.string().min(1) }).parse(request.body); return { success: true, data: await app.services.cashier.cancelExpense(expenseUuid, reason), meta: {} }; });
  server.post('/api/v1/expenses/:expenseUuid/reversal-requests', async request => { const { expenseUuid } = request.params as { expenseUuid: string }; const { reason, requestedByUserUuid } = z.object({ reason: z.string().min(1), requestedByUserUuid: z.string().uuid().optional() }).parse(request.body); return { success: true, data: await app.services.cashier.requestExpenseReversal(expenseUuid, reason, requestedByUserUuid), meta: {} }; });
  server.post('/api/v1/expense-reversal-requests/:requestUuid/approve', async request => { const { requestUuid } = request.params as { requestUuid: string }; const { approvedByUserUuid } = z.object({ approvedByUserUuid: z.string().uuid() }).parse(request.body); return { success: true, data: await app.services.cashier.approveExpenseReversalRequest(requestUuid, approvedByUserUuid), meta: {} }; });
  server.post('/api/v1/expense-reversal-requests/:requestUuid/reject', async request => { const { requestUuid } = request.params as { requestUuid: string }; const { rejectedByUserUuid, reason } = z.object({ rejectedByUserUuid: z.string().uuid(), reason: z.string().optional() }).parse(request.body); return { success: true, data: await app.services.cashier.rejectExpenseReversalRequest(requestUuid, rejectedByUserUuid, reason), meta: {} }; });
  server.post('/api/v1/owner-withdrawals', async request => ({ success: true, data: await app.services.cashier.recordOwnerWithdrawal(z.object({ type: z.enum(['cash', 'product', 'personal_expense', 'family_consumption']), cashAccountUuid: z.string().uuid().optional(), amount: money.optional(), reason: z.string().min(1), items: z.array(z.object({ productUuid: z.string().uuid().optional(), quantity: money.optional(), description: z.string().optional() })).optional() }).parse(request.body)), meta: {} }));
}