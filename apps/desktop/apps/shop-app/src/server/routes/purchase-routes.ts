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
});

export async function registerPurchaseRoutes(server: FastifyInstance, app: ShopApplication): Promise<void> {
  server.post('/api/v1/suppliers', async (request) => {
    const input = createSupplierSchema.parse(request.body);
    const supplier = await app.services.suppliers.createSupplier(input);
    return { success: true, data: supplier, meta: {} };
  });

  server.get('/api/v1/suppliers/:supplierUuid/account', async (request) => {
    const { supplierUuid } = request.params as { supplierUuid: string };
    const account = app.services.suppliers.getSupplierAccount(supplierUuid);
    return { success: true, data: account, meta: {} };
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
}
