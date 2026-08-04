import { randomUUID } from 'node:crypto';
import { and, eq } from 'drizzle-orm';
import { purchaseItems, purchases } from '../../database/schema';
import type { AuditLogRepository } from '../../repositories/audit-log-repository';
import type { InventoryRepository } from '../../repositories/inventory-repository';
import type { ProductRepository } from '../../repositories/product-repository';
import type { SyncQueueRepository } from '../../repositories/sync-queue-repository';
import type { ShopDatabase } from '../../database/sqlite';
import type { ShopEnvironment } from '../../shared/environment';
import { SupplierService } from './supplier-service';

export type CreatePurchaseInput = {
  supplierUuid: string;
  invoiceNumber?: string;
  invoiceDate?: string;
  currency?: string;
  transportCost?: string;
  loadingCost?: string;
  otherLandedCost?: string;
  tax?: string;
  discount?: string;
  notes?: string;
  items: Array<{
    productUuid: string;
    purchaseUnitUuid: string;
    quantity: string;
    unitCost: string;
    batchNumber?: string;
    expiryDate?: string;
    serialNumbers?: string[];
    baseUnitMultiplier?: string;
  }>;
};

export type ReceivePurchaseInput = {
  warehouseUuid: string;
  receivedItems: Array<{
    productUuid: string;
    quantity: string;
    batchNumber?: string;
    expiryDate?: string;
    serialNumbers?: string[];
  }>;
};

export type RecordPaymentInput = {
  amount: string;
  reference?: string;
};

export class PurchaseService {
  constructor(
    private readonly db: ShopDatabase,
    private readonly auditLogs: AuditLogRepository,
    private readonly syncQueue: SyncQueueRepository,
    private readonly environment: ShopEnvironment,
    private readonly products: ProductRepository,
    private readonly inventory: InventoryRepository,
    private readonly suppliers?: SupplierService,
  ) {}

  async createPurchase(input: CreatePurchaseInput) {
    const now = new Date().toISOString();
    const purchase = {
      uuid: randomUUID(),
      supplierUuid: input.supplierUuid,
      invoiceNumber: input.invoiceNumber ?? null,
      invoiceDate: input.invoiceDate ?? null,
      status: 'draft',
      currency: input.currency ?? 'NGN',
      transportCost: input.transportCost ?? '0',
      loadingCost: input.loadingCost ?? '0',
      otherLandedCost: input.otherLandedCost ?? '0',
      tax: input.tax ?? '0',
      discount: input.discount ?? '0',
      notes: input.notes ?? null,
      totalAmount: '0',
      amountPaid: '0',
      createdAt: now,
      updatedAt: now,
    };

    this.db.insert(purchases).values(purchase).run();

    const subtotal = input.items.reduce((sum, item) => sum + Number(item.unitCost) * Number(item.quantity), 0);
    const total = subtotal + Number(input.transportCost ?? '0') + Number(input.loadingCost ?? '0') + Number(input.otherLandedCost ?? '0') + Number(input.tax ?? '0') - Number(input.discount ?? '0');

    this.db.update(purchases).set({ totalAmount: String(total), updatedAt: now }).where(eq(purchases.uuid, purchase.uuid)).run();

    for (const item of input.items) {
      const purchaseItem = {
        uuid: randomUUID(),
        purchaseUuid: purchase.uuid,
        productUuid: item.productUuid,
        purchaseUnitUuid: item.purchaseUnitUuid,
        quantity: item.quantity,
        unitCost: item.unitCost,
        batchNumber: item.batchNumber ?? null,
        expiryDate: item.expiryDate ?? null,
        serialNumbersJson: item.serialNumbers ? JSON.stringify(item.serialNumbers) : null,
        baseUnitMultiplier: item.baseUnitMultiplier ?? '1',
        receivedQuantity: '0',
        createdAt: now,
        updatedAt: now,
      };
      this.db.insert(purchaseItems).values(purchaseItem).run();
    }

    this.suppliers?.updateSupplierBalance(input.supplierUuid, String(total));
    this.auditLogs.record({ action: 'purchase.created', subjectType: 'purchase', subjectUuid: purchase.uuid, metadata: { supplierUuid: input.supplierUuid } });
    this.syncQueue.enqueue({ aggregateType: 'purchase', aggregateUuid: purchase.uuid, payload: purchase, idempotencyKey: `purchase:${purchase.uuid}` });

    return { ...purchase, status: purchase.status, totalAmount: String(total) };
  }

  async receivePurchase(purchaseUuid: string, input: ReceivePurchaseInput) {
    const now = new Date().toISOString();
    const purchase = this.db.select().from(purchases).where(eq(purchases.uuid, purchaseUuid)).get();
    if (!purchase) {
      throw new Error('Purchase not found');
    }

    for (const item of input.receivedItems) {
      const purchaseItemRow = this.db.select().from(purchaseItems).where(and(eq(purchaseItems.purchaseUuid, purchaseUuid), eq(purchaseItems.productUuid, item.productUuid))).get();
      if (purchaseItemRow) {
        const receivedQuantity = String(Number(purchaseItemRow.receivedQuantity) + Number(item.quantity));
        this.db.update(purchaseItems).set({ receivedQuantity, updatedAt: now }).where(eq(purchaseItems.uuid, purchaseItemRow.uuid)).run();
      }

      const stock = this.inventory.createStock({
        productUuid: item.productUuid,
        warehouseUuid: input.warehouseUuid,
        unitUuid: '00000000-0000-0000-0000-000000000001',
        quantity: item.quantity,
      });

      this.inventory.createStockMovement({
        productUuid: item.productUuid,
        toWarehouseUuid: input.warehouseUuid,
        quantityBase: item.quantity,
        unitUuid: '00000000-0000-0000-0000-000000000001',
        movementType: 'purchase_receipt',
        reference: purchase.invoiceNumber ?? purchase.uuid,
        metadata: { batchNumber: item.batchNumber, expiryDate: item.expiryDate, serialNumbers: item.serialNumbers },
      });

      this.products.createBatch({ productUuid: item.productUuid, batchNumber: item.batchNumber ?? 'DEFAULT', quantity: item.quantity, expiryAt: item.expiryDate });
      for (const serial of item.serialNumbers ?? []) {
        this.products.createSerialNumber({ productUuid: item.productUuid, serial });
      }

      this.auditLogs.record({ action: 'purchase.received', subjectType: 'purchase', subjectUuid: purchase.uuid, metadata: { productUuid: item.productUuid, quantity: item.quantity } });
    }

    this.db.update(purchases).set({ status: 'received', updatedAt: now }).where(eq(purchases.uuid, purchaseUuid)).run();

    return { ...purchase, status: 'received' };
  }

  async recordPayment(purchaseUuid: string, input: RecordPaymentInput) {
    const now = new Date().toISOString();
    const purchase = this.db.select().from(purchases).where(eq(purchases.uuid, purchaseUuid)).get();
    if (!purchase) {
      throw new Error('Purchase not found');
    }

    const currentPaid = Number(purchase.amountPaid ?? '0');
    const total = Number(purchase.totalAmount ?? '0');
    const nextPaid = currentPaid + Number(input.amount);
    const status = nextPaid >= total ? 'paid' : 'partially_paid';

    this.db.update(purchases).set({ amountPaid: String(nextPaid), status, updatedAt: now }).where(eq(purchases.uuid, purchaseUuid)).run();
    this.suppliers?.updateSupplierBalance(purchase.supplierUuid, `-${input.amount}`);
    this.auditLogs.record({ action: 'purchase.payment_recorded', subjectType: 'purchase', subjectUuid: purchaseUuid, metadata: { amount: input.amount, reference: input.reference } });

    return { ...purchase, amountPaid: String(nextPaid), status };
  }
}
