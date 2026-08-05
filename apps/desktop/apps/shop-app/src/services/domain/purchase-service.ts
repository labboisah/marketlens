import { randomUUID } from 'node:crypto';
import { and, eq } from 'drizzle-orm';
import { purchaseItems, purchasePayments, purchaseReturns, purchases } from '../../database/schema';
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
  paymentMethod?: string;
};

export type ReturnPurchaseInput = {
  warehouseUuid: string;
  reason?: string;
  note?: string;
  items: Array<{
    productUuid: string;
    quantity: string;
    unitUuid?: string;
  }>;
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

  private upsertStock(input: { productUuid: string; warehouseUuid: string; unitUuid: string; quantityDelta: number }) {
    const stockRows = this.inventory.listStocks(input.productUuid, input.warehouseUuid);
    const existing = stockRows.find((stock) => stock.unitUuid === input.unitUuid);
    if (!existing) {
      return this.inventory.createStock({
        productUuid: input.productUuid,
        warehouseUuid: input.warehouseUuid,
        unitUuid: input.unitUuid,
        quantity: String(input.quantityDelta),
      });
    }

    const nextQuantity = Number(existing.quantity ?? '0') + input.quantityDelta;
    this.inventory.adjustStock(existing.uuid, String(nextQuantity), existing.reservedQuantity ?? '0');
    return { ...existing, quantity: String(nextQuantity) };
  }

  listPurchases(input?: { status?: 'draft' | 'completed' | 'received' | 'paid' | 'partially_paid'; supplierUuid?: string; search?: string }) {
    const rows = this.db.select().from(purchases).all();
    const suppliers = this.suppliers?.listSuppliers() ?? [];

    const normalizedSearch = input?.search?.trim().toLowerCase();
    const filtered = rows.filter((row) => {
      const status = row.status;
      if (input?.status === 'draft' && status !== 'draft') return false;
      if (input?.status === 'completed' && status === 'draft') return false;
      if (input?.status && input.status !== 'draft' && input.status !== 'completed' && status !== input.status) return false;
      if (input?.supplierUuid && row.supplierUuid !== input.supplierUuid) return false;

      if (!normalizedSearch) return true;
      const supplierName = suppliers.find((supplier) => supplier.uuid === row.supplierUuid)?.name ?? '';
      const fields = [row.invoiceNumber ?? '', row.uuid, supplierName].map((value) => value.toLowerCase());
      return fields.some((value) => value.includes(normalizedSearch));
    });

    return filtered
      .map((row) => ({
        ...row,
        supplierName: suppliers.find((supplier) => supplier.uuid === row.supplierUuid)?.name ?? 'Unknown supplier',
        balanceAmount: String(Math.max(0, Number(row.totalAmount ?? '0') - Number(row.amountPaid ?? '0'))),
      }))
      .sort((a, b) => b.createdAt.localeCompare(a.createdAt));
  }

  getPurchaseDetails(purchaseUuid: string) {
    const purchase = this.db.select().from(purchases).where(eq(purchases.uuid, purchaseUuid)).get();
    if (!purchase) return null;
    const items = this.db.select().from(purchaseItems).where(eq(purchaseItems.purchaseUuid, purchaseUuid)).all();
    const payments = this.db.select().from(purchasePayments).where(eq(purchasePayments.purchaseUuid, purchaseUuid)).all();
    const returns = this.db.select().from(purchaseReturns).where(eq(purchaseReturns.purchaseUuid, purchaseUuid)).all();
    const supplier = this.suppliers?.getSupplierByUuid(purchase.supplierUuid);
    return {
      ...purchase,
      supplierName: supplier?.name ?? 'Unknown supplier',
      items,
      payments,
      returns,
      balanceAmount: String(Math.max(0, Number(purchase.totalAmount ?? '0') - Number(purchase.amountPaid ?? '0'))),
    };
  }

  listPurchasePayments(input?: { supplierUuid?: string; purchaseUuid?: string }) {
    const payments = this.db.select().from(purchasePayments).all();
    return payments
      .filter((row) => {
        if (input?.supplierUuid && row.supplierUuid !== input.supplierUuid) return false;
        if (input?.purchaseUuid && row.purchaseUuid !== input.purchaseUuid) return false;
        return true;
      })
      .sort((a, b) => b.createdAt.localeCompare(a.createdAt));
  }

  listPurchaseReturns(input?: { supplierUuid?: string; purchaseUuid?: string }) {
    const returns = this.db.select().from(purchaseReturns).all();
    return returns
      .filter((row) => {
        if (input?.supplierUuid && row.supplierUuid !== input.supplierUuid) return false;
        if (input?.purchaseUuid && row.purchaseUuid !== input.purchaseUuid) return false;
        return true;
      })
      .sort((a, b) => b.createdAt.localeCompare(a.createdAt));
  }

  getSupplierStatement(supplierUuid: string) {
    const supplier = this.suppliers?.getSupplierByUuid(supplierUuid);
    if (!supplier) {
      throw new Error('Supplier not found');
    }

    const supplierPurchases = this.db.select().from(purchases).where(eq(purchases.supplierUuid, supplierUuid)).all();
    const supplierPayments = this.db.select().from(purchasePayments).where(eq(purchasePayments.supplierUuid, supplierUuid)).all();
    const supplierReturns = this.db.select().from(purchaseReturns).where(eq(purchaseReturns.supplierUuid, supplierUuid)).all();

    const entries = [
      ...supplierPurchases.map((row) => ({
        uuid: row.uuid,
        occurredAt: row.createdAt,
        entryType: 'purchase' as const,
        debit: row.totalAmount,
        credit: '0',
        reference: row.invoiceNumber ?? row.uuid,
      })),
      ...supplierPayments.map((row) => ({
        uuid: row.uuid,
        occurredAt: row.createdAt,
        entryType: 'payment' as const,
        debit: '0',
        credit: row.amount,
        reference: row.reference ?? row.purchaseUuid,
      })),
      ...supplierReturns.map((row) => ({
        uuid: row.uuid,
        occurredAt: row.createdAt,
        entryType: 'return' as const,
        debit: '0',
        credit: row.amount,
        reference: row.purchaseUuid,
      })),
    ].sort((a, b) => a.occurredAt.localeCompare(b.occurredAt));

    let runningBalance = 0;
    const transactions = entries.map((entry) => {
      runningBalance += Number(entry.debit) - Number(entry.credit);
      return {
        ...entry,
        runningBalance: String(runningBalance),
      };
    });

    const account = this.suppliers?.getSupplierAccount(supplierUuid);
    return {
      supplier: {
        uuid: supplier.uuid,
        code: supplier.code,
        name: supplier.name,
        contactPhone: supplier.contactPhone,
      },
      account: {
        currentBalance: account?.currentBalance ?? String(runningBalance),
      },
      transactions,
    };
  }

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

      const unitUuid = purchaseItemRow?.purchaseUnitUuid ?? '00000000-0000-0000-0000-000000000001';
      this.upsertStock({
        productUuid: item.productUuid,
        warehouseUuid: input.warehouseUuid,
        unitUuid,
        quantityDelta: Number(item.quantity),
      });

      this.inventory.createStockMovement({
        productUuid: item.productUuid,
        toWarehouseUuid: input.warehouseUuid,
        quantityBase: item.quantity,
        unitUuid,
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

    const payment = {
      uuid: randomUUID(),
      purchaseUuid,
      supplierUuid: purchase.supplierUuid,
      amount: input.amount,
      reference: input.reference ?? null,
      paymentMethod: input.paymentMethod ?? null,
      createdAt: now,
    };

    this.db.insert(purchasePayments).values(payment).run();

    this.db.update(purchases).set({ amountPaid: String(nextPaid), status, updatedAt: now }).where(eq(purchases.uuid, purchaseUuid)).run();
    this.suppliers?.updateSupplierBalance(purchase.supplierUuid, `-${input.amount}`);
    this.auditLogs.record({ action: 'purchase.payment_recorded', subjectType: 'purchase', subjectUuid: purchaseUuid, metadata: { amount: input.amount, reference: input.reference } });
    this.syncQueue.enqueue({ aggregateType: 'purchase_payment', aggregateUuid: payment.uuid, payload: payment, idempotencyKey: `purchase_payment:${payment.uuid}` });

    return { ...purchase, amountPaid: String(nextPaid), status };
  }

  async returnPurchase(purchaseUuid: string, input: ReturnPurchaseInput) {
    const purchase = this.db.select().from(purchases).where(eq(purchases.uuid, purchaseUuid)).get();
    if (!purchase) {
      throw new Error('Purchase not found');
    }

    const purchaseRows = this.db.select().from(purchaseItems).where(eq(purchaseItems.purchaseUuid, purchaseUuid)).all();
    if (purchaseRows.length === 0) {
      throw new Error('Purchase has no items');
    }

    const now = new Date().toISOString();
    let returnTotal = 0;
    const createdRows: Array<Record<string, unknown>> = [];

    for (const line of input.items) {
      const purchaseItem = purchaseRows.find((row) => row.productUuid === line.productUuid);
      if (!purchaseItem) {
        throw new Error('Purchase item not found for return');
      }

      const unitUuid = line.unitUuid ?? purchaseItem.purchaseUnitUuid;
      const quantity = Number(line.quantity);
      const unitCost = Number(purchaseItem.unitCost);
      const amount = quantity * unitCost;

      const stockRows = this.inventory.listStocks(line.productUuid, input.warehouseUuid);
      const stock = stockRows.find((row) => row.unitUuid === unitUuid);
      const available = Number(stock?.quantity ?? '0');
      if (!stock || available < quantity) {
        throw new Error('Insufficient stock for purchase return');
      }

      this.inventory.adjustStock(stock.uuid, String(available - quantity), stock.reservedQuantity ?? '0');
      this.inventory.createStockMovement({
        productUuid: line.productUuid,
        fromWarehouseUuid: input.warehouseUuid,
        quantityBase: line.quantity,
        unitUuid,
        movementType: 'purchase_return',
        reference: purchase.invoiceNumber ?? purchase.uuid,
        metadata: {
          reason: input.reason,
          note: input.note,
        },
      });

      const returnRow = {
        uuid: randomUUID(),
        purchaseUuid,
        supplierUuid: purchase.supplierUuid,
        warehouseUuid: input.warehouseUuid,
        productUuid: line.productUuid,
        unitUuid,
        quantity: line.quantity,
        unitCost: purchaseItem.unitCost,
        amount: String(amount),
        reason: input.reason ?? null,
        note: input.note ?? null,
        createdAt: now,
      };
      this.db.insert(purchaseReturns).values(returnRow).run();
      createdRows.push(returnRow);
      returnTotal += amount;

      this.auditLogs.record({
        action: 'purchase.returned',
        subjectType: 'purchase',
        subjectUuid: purchaseUuid,
        metadata: { productUuid: line.productUuid, quantity: line.quantity, amount: String(amount) },
      });
      this.syncQueue.enqueue({
        aggregateType: 'purchase_return',
        aggregateUuid: returnRow.uuid,
        payload: returnRow,
        idempotencyKey: `purchase_return:${returnRow.uuid}`,
      });
    }

    const nextTotal = Math.max(0, Number(purchase.totalAmount ?? '0') - returnTotal);
    const nextPaid = Math.min(Number(purchase.amountPaid ?? '0'), nextTotal);
    const nextStatus = nextTotal === 0 ? 'returned' : (nextPaid >= nextTotal ? 'paid' : (nextPaid > 0 ? 'partially_paid' : 'received'));

    this.db.update(purchases)
      .set({
        totalAmount: String(nextTotal),
        amountPaid: String(nextPaid),
        status: nextStatus,
        updatedAt: now,
      })
      .where(eq(purchases.uuid, purchaseUuid))
      .run();

    this.suppliers?.updateSupplierBalance(purchase.supplierUuid, `-${String(returnTotal)}`);

    return {
      purchaseUuid,
      supplierUuid: purchase.supplierUuid,
      returnedAmount: String(returnTotal),
      items: createdRows,
      status: nextStatus,
    };
  }
}
