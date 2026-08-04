import { z } from 'zod';
import type { FastifyInstance } from 'fastify';
import type { ShopApplication } from '../../services/application/shop-application';

const money = z.string().regex(/^\d+(\.\d+)?$/);
export async function registerSalesRoutes(server: FastifyInstance, app: ShopApplication): Promise<void> {
  server.post('/api/v1/payment-methods', async request => ({ success: true, data: await app.services.sales.createPaymentMethod(z.object({ code: z.string().min(1), name: z.string().min(1), type: z.enum(['cash', 'bank_transfer', 'pos_terminal', 'mobile_money', 'credit']) }).parse(request.body)), meta: {} }));
  server.post('/api/v1/pos/sales', async request => ({ success: true, data: await app.services.sales.completeSale(z.object({ idempotencyKey: z.string().min(1), shiftUuid: z.string().uuid(), warehouseUuid: z.string().uuid(), customerUuid: z.string().uuid().optional(), discountAmount: money.optional(), receiptFormat: z.enum(['thermal', 'a4']).optional(), items: z.array(z.object({ productUuid: z.string().uuid(), unitUuid: z.string().uuid(), quantity: money, unitPrice: money })).min(1), payments: z.array(z.object({ paymentMethodUuid: z.string().uuid(), amount: money, reference: z.string().optional() })).min(1) }).parse(request.body)), meta: {} }));
  server.post('/api/v1/receipts/:receiptUuid/reprints', async request => { const { receiptUuid } = request.params as { receiptUuid: string }; return { success: true, data: await app.services.sales.reprintReceipt(receiptUuid, z.object({ reason: z.string().min(1), approvedByUserUuid: z.string().uuid().optional() }).parse(request.body)), meta: {} }; });
}