import { z } from 'zod';
import type { FastifyInstance } from 'fastify';
import type { ShopApplication } from '../../services/application/shop-application';

const customerSchema = z.object({
  code: z.string().min(1),
  name: z.string().min(1),
  type: z.enum(['retail', 'wholesale', 'corporate']),
  phone: z.string().optional(),
  address: z.string().optional(),
  creditLimit: z.string().regex(/^\d+(\.\d+)?$/).optional(),
  openingBalance: z.string().regex(/^\d+(\.\d+)?$/).optional(),
  contacts: z.array(z.object({ name: z.string().min(1), phone: z.string().optional(), email: z.string().email().optional(), role: z.string().optional() })).optional(),
  guarantor: z.object({ name: z.string().min(1), phone: z.string().optional(), address: z.string().optional(), relationship: z.string().optional() }).optional(),
});

const creditSaleSchema = z.object({
  amount: z.string().regex(/^\d+(\.\d+)?$/),
  dueDate: z.string().date(),
  reference: z.string().optional(),
  creditApprovalUuid: z.string().uuid().optional(),
});

const paymentSchema = z.object({
  amount: z.string().regex(/^\d+(\.\d+)?$/),
  reference: z.string().optional(),
  paymentMethod: z.string().optional(),
  allocations: z.array(z.object({ accountTransactionUuid: z.string().uuid(), amount: z.string().regex(/^\d+(\.\d+)?$/) })).optional(),
});

const creditApprovalSchema = z.object({
  requestedAmount: z.string().regex(/^\d+(\.\d+)?$/),
  approvedByUserUuid: z.string().uuid(),
  reason: z.string().min(1),
});

const writeOffSchema = z.object({
  accountTransactionUuid: z.string().uuid(),
  amount: z.string().regex(/^\d+(\.\d+)?$/),
  approvedByUserUuid: z.string().uuid(),
  reason: z.string().min(1),
});

const reminderSchema = z.object({
  accountTransactionUuid: z.string().uuid().optional(),
  reminderAt: z.string().datetime(),
  channel: z.string().min(1),
});

const customerStatusSchema = z.object({ status: z.enum(['active', 'blocked']) });
const creditSettingsSchema = z.object({ creditLimit: z.string().regex(/^\d+(\.\d+)?$/), additionalCreditAllowed: z.boolean() });

const correctionSchema = z.object({
  transactionType: z.enum(['refund', 'adjustment', 'reversal']),
  amount: z.string().regex(/^-?\d+(\.\d+)?$/),
  reference: z.string().optional(),
  reversalOfUuid: z.string().uuid().optional(),
});

const updateCustomerSchema = z.object({
  name: z.string().min(1).optional(),
  type: z.enum(['retail', 'wholesale', 'corporate']).optional(),
  phone: z.string().optional(),
  address: z.string().optional(),
});

const creditEligibilityQuerySchema = z.object({
  requestedAmount: z.string().regex(/^\d+(\.\d+)?$/),
  dueDate: z.string().date(),
});

export async function registerCustomerRoutes(server: FastifyInstance, app: ShopApplication): Promise<void> {
  server.get('/api/v1/customers', async (request) => {
    const search = typeof request.query.search === 'string' ? request.query.search.trim() : '';
    const query = app.database.sqlite.prepare(`
      SELECT uuid, code, name, type, phone, address, status
      FROM customers
      WHERE ? = '' OR code LIKE ? OR name LIKE ? OR phone LIKE ?
      ORDER BY name ASC
      LIMIT 200
    `);
    const like = `%${search}%`;
    const data = query.all(search, like, like, like);
    return { success: true, data, meta: {} };
  });

  server.post('/api/v1/customers', async (request) => ({ success: true, data: await app.services.customers.createCustomer(customerSchema.parse(request.body)), meta: {} }));

  server.patch('/api/v1/customers/:customerUuid', async (request) => {
    const { customerUuid } = request.params as { customerUuid: string };
    return { success: true, data: await app.services.customers.updateCustomer(customerUuid, updateCustomerSchema.parse(request.body)), meta: {} };
  });

  server.get('/api/v1/customers/:customerUuid/account', async (request) => {
    const { customerUuid } = request.params as { customerUuid: string };
    return { success: true, data: app.services.customers.getAccount(customerUuid) ?? null, meta: {} };
  });

  server.get('/api/v1/customers/:customerUuid/debt-profile', async (request) => {
    const { customerUuid } = request.params as { customerUuid: string };
    return { success: true, data: app.services.customers.getDebtProfile(customerUuid), meta: {} };
  });

  server.get('/api/v1/customers/:customerUuid/credit-sale-eligibility', async (request) => {
    const { customerUuid } = request.params as { customerUuid: string };
    const query = creditEligibilityQuerySchema.parse(request.query);
    return { success: true, data: app.services.customers.getCreditSaleEligibility(customerUuid, query), meta: {} };
  });

  server.get('/api/v1/customers/:customerUuid/statement', async (request) => {
    const { customerUuid } = request.params as { customerUuid: string };
    return { success: true, data: app.services.customers.getStatement(customerUuid), meta: {} };
  });

  server.post('/api/v1/customers/:customerUuid/credit-sales', async (request) => {
    const { customerUuid } = request.params as { customerUuid: string };
    return { success: true, data: await app.services.customers.recordCreditSale(customerUuid, creditSaleSchema.parse(request.body)), meta: {} };
  });

  server.post('/api/v1/customers/:customerUuid/payments', async (request) => {
    const { customerUuid } = request.params as { customerUuid: string };
    return { success: true, data: await app.services.customers.recordPayment(customerUuid, paymentSchema.parse(request.body)), meta: {} };
  });

  server.post('/api/v1/customers/:customerUuid/credit-approvals', async (request) => {
    const { customerUuid } = request.params as { customerUuid: string };
    return { success: true, data: await app.services.customers.approveCredit(customerUuid, creditApprovalSchema.parse(request.body)), meta: {} };
  });

  server.post('/api/v1/customers/:customerUuid/debt-write-offs', async (request) => {
    const { customerUuid } = request.params as { customerUuid: string };
    return { success: true, data: await app.services.customers.writeOffDebt(customerUuid, writeOffSchema.parse(request.body)), meta: {} };
  });

  server.post('/api/v1/customers/:customerUuid/debt-reminders', async (request) => {
    const { customerUuid } = request.params as { customerUuid: string };
    return { success: true, data: await app.services.customers.createDebtReminder(customerUuid, reminderSchema.parse(request.body)), meta: {} };
  });

  server.post('/api/v1/customers/:customerUuid/ledger-corrections', async (request) => {
    const { customerUuid } = request.params as { customerUuid: string };
    return { success: true, data: await app.services.customers.recordLedgerCorrection(customerUuid, correctionSchema.parse(request.body)), meta: {} };
  });

  server.patch('/api/v1/customers/:customerUuid/status', async (request) => {
    const { customerUuid } = request.params as { customerUuid: string };
    return { success: true, data: await app.services.customers.setCustomerStatus(customerUuid, customerStatusSchema.parse(request.body).status), meta: {} };
  });

  server.patch('/api/v1/customers/:customerUuid/credit-settings', async (request) => {
    const { customerUuid } = request.params as { customerUuid: string };
    return { success: true, data: await app.services.customers.setCreditSettings(customerUuid, creditSettingsSchema.parse(request.body)), meta: {} };
  });
}