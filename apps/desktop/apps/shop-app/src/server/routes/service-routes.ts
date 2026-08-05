import { z } from 'zod';
import type { FastifyInstance } from 'fastify';
import type { ShopApplication } from '../../services/application/shop-application';

const money = z.string().regex(/^\d+(\.\d+)?$/);
const orderStatusSchema = z.enum(['pending', 'assigned', 'in_progress', 'completed', 'cancelled', 'refunded']);

export async function registerServiceRoutes(server: FastifyInstance, app: ShopApplication): Promise<void> {
  server.get('/api/v1/services/module-access', async () => ({
    success: true,
    data: app.services.serviceRevenue.listModuleAccess('services'),
    meta: {},
  }));

  server.get('/api/v1/services/dashboard', async () => ({
    success: true,
    data: app.services.serviceRevenue.dashboard(),
    meta: {},
  }));

  server.get('/api/v1/services/catalog', async () => ({
    success: true,
    data: app.services.serviceRevenue.listCatalog(),
    meta: {},
  }));

  server.get('/api/v1/services/customers', async () => ({
    success: true,
    data: app.services.serviceRevenue.listCustomers(),
    meta: {},
  }));

  server.get('/api/v1/services/staff', async () => ({
    success: true,
    data: app.services.serviceRevenue.listServiceStaff(),
    meta: {},
  }));

  server.get('/api/v1/service-orders', async (request) => {
    const query = z.object({
      status: orderStatusSchema.optional(),
      customerUuid: z.string().uuid().optional(),
      from: z.string().optional(),
      to: z.string().optional(),
    }).parse(request.query ?? {});

    return {
      success: true,
      data: app.services.serviceRevenue.listOrders(query),
      meta: {},
    };
  });

  server.get('/api/v1/service-orders/:orderUuid', async (request) => {
    const { orderUuid } = request.params as { orderUuid: string };
    return {
      success: true,
      data: app.services.serviceRevenue.getOrderDetail(orderUuid),
      meta: {},
    };
  });

  server.get('/api/v1/service-assignments', async (request) => {
    const query = z.object({
      staffUserUuid: z.string().uuid().optional(),
    }).parse(request.query ?? {});

    return {
      success: true,
      data: app.services.serviceRevenue.listAssignedServices(query.staffUserUuid),
      meta: {},
    };
  });

  server.get('/api/v1/service-payments', async (request) => {
    const query = z.object({
      orderUuid: z.string().uuid().optional(),
      from: z.string().optional(),
      to: z.string().optional(),
    }).parse(request.query ?? {});

    return {
      success: true,
      data: app.services.serviceRevenue.listPayments(query),
      meta: {},
    };
  });

  server.get('/api/v1/service-expenses', async (request) => {
    const query = z.object({
      orderUuid: z.string().uuid().optional(),
      from: z.string().optional(),
      to: z.string().optional(),
      approvalStatus: z.string().optional(),
    }).parse(request.query ?? {});

    return {
      success: true,
      data: app.services.serviceRevenue.listDirectExpenses(query),
      meta: {},
    };
  });

  server.get('/api/v1/service-revenue-allocations', async (request) => {
    const query = z.object({
      orderUuid: z.string().uuid().optional(),
      status: z.string().optional(),
      from: z.string().optional(),
      to: z.string().optional(),
    }).parse(request.query ?? {});

    return {
      success: true,
      data: app.services.serviceRevenue.listRevenueAllocations(query),
      meta: {},
    };
  });

  server.get('/api/v1/staff-earnings', async () => ({
    success: true,
    data: app.services.serviceRevenue.listStaffEarnings(),
    meta: {},
  }));

  server.get('/api/v1/staff-settlements', async (request) => {
    const query = z.object({
      staffUserUuid: z.string().uuid().optional(),
      from: z.string().optional(),
      to: z.string().optional(),
    }).parse(request.query ?? {});

    return {
      success: true,
      data: app.services.serviceRevenue.listStaffSettlements(query),
      meta: {},
    };
  });

  server.post('/api/v1/service-categories', async (request) => ({
    success: true,
    data: await app.services.serviceRevenue.createCategory(z.object({ name: z.string().min(1) }).parse(request.body)),
    meta: {},
  }));

  server.post('/api/v1/services', async (request) => ({
    success: true,
    data: await app.services.serviceRevenue.createService(z.object({ categoryUuid: z.string().uuid(), code: z.string().min(1), name: z.string().min(1) }).parse(request.body)),
    meta: {},
  }));

  server.post('/api/v1/services/prices', async (request) => ({
    success: true,
    data: await app.services.serviceRevenue.setPrice(z.object({ serviceUuid: z.string().uuid(), branchUuid: z.string().uuid().optional(), amount: money }).parse(request.body)),
    meta: {},
  }));

  server.post('/api/v1/service-revenue-rules', async (request) => ({
    success: true,
    data: await app.services.serviceRevenue.createRule(z.object({
      serviceUuid: z.string().uuid().optional(),
      branchUuid: z.string().uuid().optional(),
      basis: z.enum(['gross_amount', 'net_after_discount', 'net_after_direct_expenses', 'net_after_discount_and_direct_expenses', 'fixed_amount']),
      fixedAmount: money.optional(),
      requiresApproval: z.boolean().optional(),
      components: z.array(z.object({ type: z.enum(['staff', 'business', 'expense_reserve', 'partner', 'other']), percentage: money })).min(1),
    }).parse(request.body)),
    meta: {},
  }));

  server.post('/api/v1/service-orders', async (request) => ({
    success: true,
    data: await app.services.serviceRevenue.createOrder(z.object({
      customerUuid: z.string().uuid().optional(),
      branchUuid: z.string().uuid(),
      discountAmount: money.optional(),
      items: z.array(z.object({ serviceUuid: z.string().uuid(), quantity: money, unitPrice: money })).min(1),
    }).parse(request.body)),
    meta: {},
  }));

  server.post('/api/v1/service-order-items/:orderItemUuid/staff-assignments', async (request) => {
    const { orderItemUuid } = request.params as { orderItemUuid: string };
    const { assignments } = z.object({
      assignments: z.array(z.object({
        staffUserUuid: z.string().uuid(),
        contributionPercentage: money,
        assignmentRole: z.string().optional(),
        startedAt: z.string().optional(),
        completedAt: z.string().optional(),
        notes: z.string().optional(),
      })).min(1),
    }).parse(request.body);

    return {
      success: true,
      data: await app.services.serviceRevenue.assignStaff(orderItemUuid, assignments),
      meta: {},
    };
  });

  server.post('/api/v1/service-orders/:orderUuid/start', async (request) => {
    const { orderUuid } = request.params as { orderUuid: string };
    return { success: true, data: await app.services.serviceRevenue.startOrder(orderUuid), meta: {} };
  });

  server.post('/api/v1/service-orders/:orderUuid/payments', async (request) => {
    const { orderUuid } = request.params as { orderUuid: string };
    return {
      success: true,
      data: await app.services.serviceRevenue.recordPayment(orderUuid, z.object({ amount: money, paymentMethod: z.string().min(1), reference: z.string().optional() }).parse(request.body)),
      meta: {},
    };
  });

  server.post('/api/v1/service-orders/:orderUuid/confirm-payment', async (request) => {
    const { orderUuid } = request.params as { orderUuid: string };
    return { success: true, data: await app.services.serviceRevenue.confirmOrderPayment(orderUuid), meta: {} };
  });

  server.post('/api/v1/service-orders/:orderUuid/expenses', async (request) => {
    const { orderUuid } = request.params as { orderUuid: string };
    return {
      success: true,
      data: await app.services.serviceRevenue.recordDirectExpense(orderUuid, z.object({
        category: z.string().min(1),
        description: z.string().min(1),
        amount: money,
        paidBy: z.string().min(1),
        paymentMethod: z.string().min(1),
        receiptAttachmentPath: z.string().optional(),
        approvalStatus: z.string().optional(),
      }).parse(request.body)),
      meta: {},
    };
  });

  server.post('/api/v1/service-orders/:orderUuid/complete', async (request) => {
    const { orderUuid } = request.params as { orderUuid: string };
    return { success: true, data: await app.services.serviceRevenue.completeOrder(orderUuid), meta: {} };
  });

  server.post('/api/v1/service-orders/:orderUuid/cancel', async (request) => {
    const { orderUuid } = request.params as { orderUuid: string };
    const { reason } = z.object({ reason: z.string().min(1) }).parse(request.body);
    return { success: true, data: await app.services.serviceRevenue.cancelOrder(orderUuid, reason), meta: {} };
  });

  server.get('/api/v1/service-orders/:orderUuid/allocation-preview', async (request) => {
    const { orderUuid } = request.params as { orderUuid: string };
    return { success: true, data: await app.services.serviceRevenue.previewRevenueAllocation(orderUuid), meta: {} };
  });

  server.post('/api/v1/service-orders/:orderUuid/request-allocation', async (request) => {
    const { orderUuid } = request.params as { orderUuid: string };
    const { requestedByUserUuid } = z.object({ requestedByUserUuid: z.string().uuid().optional() }).parse(request.body ?? {});
    return { success: true, data: await app.services.serviceRevenue.requestRevenueAllocation(orderUuid, requestedByUserUuid), meta: {} };
  });

  server.post('/api/v1/service-orders/:orderUuid/approve-allocation', async (request) => {
    const { orderUuid } = request.params as { orderUuid: string };
    const { approvedByUserUuid } = z.object({ approvedByUserUuid: z.string().uuid() }).parse(request.body);
    return { success: true, data: await app.services.serviceRevenue.approveRevenueAllocation(orderUuid, approvedByUserUuid), meta: {} };
  });

  server.post('/api/v1/service-orders/:orderUuid/refund', async (request) => {
    const { orderUuid } = request.params as { orderUuid: string };
    const { reason } = z.object({ reason: z.string().min(1) }).parse(request.body);
    return { success: true, data: await app.services.serviceRevenue.refundOrder(orderUuid, reason), meta: {} };
  });

  server.post('/api/v1/staff/:staffUserUuid/settlements', async (request) => {
    const { staffUserUuid } = request.params as { staffUserUuid: string };
    const { amount } = z.object({ amount: money }).parse(request.body);
    return { success: true, data: await app.services.serviceRevenue.settleStaff(staffUserUuid, amount), meta: {} };
  });
}
