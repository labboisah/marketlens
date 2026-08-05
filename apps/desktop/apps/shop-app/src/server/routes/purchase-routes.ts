import { z } from 'zod';
import type { FastifyInstance } from 'fastify';
import type { ShopApplication } from '../../services/application/shop-application';

const createSupplierSchema = z.object({
  code: z.string().min(1),
  name: z.string().min(1),
  contactName: z.string().optional(),
  contactPhone: z.string().optional(),
  contactEmail: z.string().email().optional(),
  address: z.string().optional(),
  preferred: z.boolean().optional(),
  taxNumber: z.string().optional(),
  paymentTerms: z.string().optional(),
});

const purchaseItemSchema = z.object({
  productUuid: z.string().uuid(),
  purchaseUnitUuid: z.string().uuid(),
  quantity: z.string().min(1),
  unitCost: z.string().min(1),
  batchNumber: z.string().optional(),
  expiryDate: z.string().optional(),
  serialNumbers: z.array(z.string()).optional(),
  baseUnitMultiplier: z.string().optional(),
});

const createPurchaseSchema = z.object({
  supplierUuid: z.string().uuid(),
  invoiceNumber: z.string().optional(),
  invoiceDate: z.string().optional(),
  currency: z.string().optional(),
  transportCost: z.string().optional(),
  loadingCost: z.string().optional(),
  otherLandedCost: z.string().optional(),
  tax: z.string().optional(),
  discount: z.string().optional(),
  notes: z.string().optional(),
  items: z.array(purchaseItemSchema),
});

const receivePurchaseSchema = z.object({
  warehouseUuid: z.string().uuid(),
  receivedItems: z.array(z.object({
    productUuid: z.string().uuid(),
    quantity: z.string().min(1),
    batchNumber: z.string().optional(),
    expiryDate: z.string().optional(),
    serialNumbers: z.array(z.string()).optional(),
  })),
});

const paymentSchema = z.object({
  amount: z.string().min(1),
  reference: z.string().optional(),
  paymentMethod: z.string().optional(),
});

const supplierSearchSchema = z.object({
  search: z.string().optional(),
});

const purchaseSearchSchema = z.object({
  search: z.string().optional(),
  supplierUuid: z.string().uuid().optional(),
  status: z.enum(['draft', 'completed', 'received', 'paid', 'partially_paid']).optional(),
});

const paymentSearchSchema = z.object({
  supplierUuid: z.string().uuid().optional(),
  purchaseUuid: z.string().uuid().optional(),
});

const purchaseReturnSchema = z.object({
  warehouseUuid: z.string().uuid(),
  reason: z.string().optional(),
  note: z.string().optional(),
  items: z.array(z.object({
    productUuid: z.string().uuid(),
    quantity: z.string().min(1),
    unitUuid: z.string().uuid().optional(),
  })).min(1),
});

export async function registerPurchaseRoutes(server: FastifyInstance, app: ShopApplication): Promise<void> {
  server.get('/api/v1/suppliers', async (request) => {
    const query = supplierSearchSchema.parse(request.query ?? {});
    const suppliers = app.services.suppliers.listSuppliers(query.search);
    return { success: true, data: suppliers, meta: {} };
  });

  server.post('/api/v1/suppliers', async (request) => {
    const input = createSupplierSchema.parse(request.body);
    const supplier = await app.services.suppliers.createSupplier(input);
    return { success: true, data: supplier, meta: {} };
  });

  server.get('/api/v1/suppliers/balances', async (request) => {
    const query = supplierSearchSchema.parse(request.query ?? {});
    const balances = app.services.suppliers.listSupplierBalances(query.search);
    return { success: true, data: balances, meta: {} };
  });

  server.get('/api/v1/suppliers/:supplierUuid/statement', async (request) => {
    const { supplierUuid } = request.params as { supplierUuid: string };
    const statement = app.services.purchases.getSupplierStatement(supplierUuid);
    return { success: true, data: statement, meta: {} };
  });

  server.get('/api/v1/suppliers/:supplierUuid/account', async (request) => {
    const { supplierUuid } = request.params as { supplierUuid: string };
    const account = app.services.suppliers.getSupplierAccount(supplierUuid);
    return { success: true, data: account, meta: {} };
  });

  server.get('/api/v1/purchases', async (request) => {
    const query = purchaseSearchSchema.parse(request.query ?? {});
    const purchaseRows = app.services.purchases.listPurchases(query);
    return { success: true, data: purchaseRows, meta: {} };
  });

  server.get('/api/v1/purchases/:purchaseUuid', async (request, reply) => {
    const { purchaseUuid } = request.params as { purchaseUuid: string };
    const purchase = app.services.purchases.getPurchaseDetails(purchaseUuid);
    if (!purchase) {
      return reply.code(404).send({ success: false, error: { code: 'NOT_FOUND', message: 'Purchase not found' } });
    }
    return { success: true, data: purchase, meta: {} };
  });

  server.post('/api/v1/purchases', async (request) => {
    const input = createPurchaseSchema.parse(request.body);
    const purchase = await app.services.purchases.createPurchase(input);
    return { success: true, data: purchase, meta: {} };
  });

  server.post('/api/v1/purchases/:purchaseUuid/receive', async (request) => {
    const { purchaseUuid } = request.params as { purchaseUuid: string };
    const input = receivePurchaseSchema.parse(request.body);
    const purchase = await app.services.purchases.receivePurchase(purchaseUuid, input);
    return { success: true, data: purchase, meta: {} };
  });

  server.post('/api/v1/purchases/:purchaseUuid/payment', async (request) => {
    const { purchaseUuid } = request.params as { purchaseUuid: string };
    const input = paymentSchema.parse(request.body);
    const purchase = await app.services.purchases.recordPayment(purchaseUuid, input);
    return { success: true, data: purchase, meta: {} };
  });

  server.get('/api/v1/purchase-payments', async (request) => {
    const query = paymentSearchSchema.parse(request.query ?? {});
    const payments = app.services.purchases.listPurchasePayments(query);
    return { success: true, data: payments, meta: {} };
  });

  server.post('/api/v1/purchases/:purchaseUuid/returns', async (request) => {
    const { purchaseUuid } = request.params as { purchaseUuid: string };
    const input = purchaseReturnSchema.parse(request.body);
    const result = await app.services.purchases.returnPurchase(purchaseUuid, input);
    return { success: true, data: result, meta: {} };
  });

  server.get('/api/v1/purchase-returns', async (request) => {
    const query = paymentSearchSchema.parse(request.query ?? {});
    const returns = app.services.purchases.listPurchaseReturns(query);
    return { success: true, data: returns, meta: {} };
  });
}
