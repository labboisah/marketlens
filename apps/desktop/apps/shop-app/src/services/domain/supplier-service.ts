import { randomUUID } from 'node:crypto';
import { eq } from 'drizzle-orm';
import { supplierAccounts, suppliers } from '../../database/schema';
import type { AuditLogRepository } from '../../repositories/audit-log-repository';
import type { ShopDatabase } from '../../database/sqlite';
import type { SyncQueueRepository } from '../../repositories/sync-queue-repository';
import type { ShopEnvironment } from '../../shared/environment';

export type CreateSupplierInput = {
  code: string;
  name: string;
  contactName?: string;
  contactPhone?: string;
  contactEmail?: string;
  address?: string;
  preferred?: boolean;
  taxNumber?: string;
  paymentTerms?: string;
};

export type SupplierRecord = {
  uuid: string;
  code: string;
  name: string;
  contactName: string | null;
  contactPhone: string | null;
  contactEmail: string | null;
  address: string | null;
  preferred: string;
  taxNumber: string | null;
  paymentTerms: string | null;
  createdAt: string;
  updatedAt: string;
};

export class SupplierService {
  constructor(
    private readonly db: ShopDatabase,
    private readonly auditLogs: AuditLogRepository,
    private readonly syncQueue: SyncQueueRepository,
    private readonly environment: ShopEnvironment,
  ) {}

  async createSupplier(input: CreateSupplierInput): Promise<SupplierRecord> {
    const now = new Date().toISOString();
    const supplier = {
      uuid: randomUUID(),
      code: input.code,
      name: input.name,
      contactName: input.contactName ?? null,
      contactPhone: input.contactPhone ?? null,
      contactEmail: input.contactEmail ?? null,
      address: input.address ?? null,
      preferred: input.preferred ? '1' : '0',
      taxNumber: input.taxNumber ?? null,
      paymentTerms: input.paymentTerms ?? null,
      createdAt: now,
      updatedAt: now,
    };

    this.db.insert(suppliers).values(supplier).run();
    this.db.insert(supplierAccounts).values({ uuid: randomUUID(), supplierUuid: supplier.uuid, currentBalance: '0', createdAt: now, updatedAt: now }).run();

    this.auditLogs.record({ action: 'supplier.created', subjectType: 'supplier', subjectUuid: supplier.uuid, metadata: { code: supplier.code } });
    this.syncQueue.enqueue({ aggregateType: 'supplier', aggregateUuid: supplier.uuid, payload: supplier, idempotencyKey: `supplier:${supplier.uuid}` });

    return supplier;
  }

  getSupplierAccount(supplierUuid: string) {
    const row = this.db.select().from(supplierAccounts).where(eq(supplierAccounts.supplierUuid, supplierUuid)).get();
    return row ?? null;
  }

  updateSupplierBalance(supplierUuid: string, delta: string) {
    const row = this.db.select().from(supplierAccounts).where(eq(supplierAccounts.supplierUuid, supplierUuid)).get();
    if (!row) {
      return null;
    }

    const nextBalance = String(Number(row.currentBalance ?? '0') + Number(delta));
    this.db.update(supplierAccounts).set({ currentBalance: nextBalance, updatedAt: new Date().toISOString() }).where(eq(supplierAccounts.supplierUuid, supplierUuid)).run();
    return nextBalance;
  }
}
