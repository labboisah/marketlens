import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { mkdirSync, rmSync } from 'node:fs';
import { join } from 'node:path';
import { tmpdir } from 'node:os';
import { createShopApplication } from '../src/services/application/shop-application';
import type { ShopEnvironment } from '../src/shared/environment';

const tempDir = join(tmpdir(), 'marketlens-shop-app-purchases-tests');
const sqlitePath = join(tempDir, 'shop-test.sqlite');
const backupPath = join(tempDir, 'backups');
const exportPath = join(tempDir, 'exports');

const testEnvironment: ShopEnvironment = {
  SHOP_APP_ENV: 'test',
  SHOP_APP_LOG_LEVEL: 'debug',
  SHOP_LOCAL_API_HOST: '127.0.0.1',
  SHOP_LOCAL_API_PORT: 5002,
  SHOP_LOCAL_API_PUBLIC_HOST: '',
  SHOP_LOCAL_API_ALLOWED_ORIGINS: 'http://127.0.0.1:4091',
  SHOP_SOCKET_IO_PATH: '/socket.io',
  SHOP_DEVICE_NAME: 'MarketLens Test Shop',
  SHOP_DEVICE_PAIRING_CODE: 'test-pairing-code',
  SHOP_INITIAL_ADMIN_USERNAME: 'admin',
  SHOP_INITIAL_ADMIN_PASSWORD: 'test-password',
  SHOP_BRANCH_UUID: '00000000-0000-4000-8000-000000000001',
  SHOP_TENANT_UUID: '00000000-0000-4000-8000-000000000001',
  SHOP_SQLITE_PATH: sqlitePath,
  SHOP_TEST_SQLITE_PATH: sqlitePath,
  SHOP_AUTO_START_ENABLED: false,
  SHOP_BACKUP_RETENTION_DAYS: 14,
  PLATFORM_SYNC_ENABLED: false,
  PLATFORM_SYNC_BASE_URL: 'http://localhost:8000/api',
  PLATFORM_SYNC_DEVICE_TOKEN: '',
  PLATFORM_SYNC_INTERVAL_SECONDS: 60,
  LOCAL_BACKUP_PATH: backupPath,
  LOCAL_EXPORT_PATH: exportPath,
};

describe('Shop App purchasing and receiving', () => {
  beforeEach(() => {
    rmSync(tempDir, { recursive: true, force: true });
    mkdirSync(tempDir, { recursive: true });
    mkdirSync(backupPath, { recursive: true });
    mkdirSync(exportPath, { recursive: true });
  });

  afterEach(() => {
    rmSync(tempDir, { recursive: true, force: true });
  });

  it('creates a supplier, receives stock, and updates the ledger from a purchase', async () => {
    const app = await createShopApplication(testEnvironment);

    const unit = app.services.products.createUnit({ code: 'PCS', name: 'Piece', isBase: true });
    const product = app.services.products.createProduct({ sku: 'SKU-1001', name: 'Widget', baseUnitUuid: unit.uuid, active: true });
    const warehouse = app.services.inventory.createWarehouse({ code: 'WH1', name: 'Main Warehouse' });

    const supplier = await app.services.suppliers.createSupplier({
      code: 'SUP-001',
      name: 'Acme Supplies',
      preferred: true,
      contactEmail: 'ops@acme.test',
    });

    const purchase = await app.services.purchases.createPurchase({
      supplierUuid: supplier.uuid,
      invoiceNumber: 'INV-1001',
      invoiceDate: '2026-08-01',
      currency: 'NGN',
      transportCost: '250',
      loadingCost: '75',
      otherLandedCost: '50',
      tax: '125',
      discount: '0',
      items: [
        {
          productUuid: product.uuid,
          purchaseUnitUuid: unit.uuid,
          quantity: '10',
          unitCost: '100',
          batchNumber: 'B-100',
          expiryDate: '2028-01-01',
          serialNumbers: ['SER-1', 'SER-2'],
          baseUnitMultiplier: '1',
        },
      ],
    });

    expect(purchase.status).toBe('draft');

    const received = await app.services.purchases.receivePurchase(purchase.uuid, {
      warehouseUuid: warehouse.uuid,
      receivedItems: [{ productUuid: product.uuid, quantity: '10', batchNumber: 'B-100', expiryDate: '2028-01-01', serialNumbers: ['SER-1', 'SER-2'] }],
    });

    expect(received.status).toBe('received');

    const stocks = app.services.inventory.listStocks(product.uuid, warehouse.uuid);
    expect(stocks).toHaveLength(1);
    expect(stocks[0]?.quantity).toBe('10');

    const account = app.services.suppliers.getSupplierAccount(supplier.uuid);
    expect(account?.currentBalance).toBe('1000');

    const pendingSyncCountRow = app.database.sqlite.prepare('select count(*) as count from sync_queue').get();
    expect(pendingSyncCountRow.count).toBeGreaterThan(0);
  });

  it('records a partial payment and keeps the supplier ledger balanced', async () => {
    const app = await createShopApplication(testEnvironment);

    const supplier = await app.services.suppliers.createSupplier({
      code: 'SUP-002',
      name: 'Northwind Traders',
      preferred: false,
    });

    const purchase = await app.services.purchases.createPurchase({
      supplierUuid: supplier.uuid,
      invoiceNumber: 'INV-2001',
      invoiceDate: '2026-08-02',
      currency: 'NGN',
      items: [{ productUuid: '00000000-0000-0000-0000-000000000001', purchaseUnitUuid: '00000000-0000-0000-0000-000000000001', quantity: '1', unitCost: '200' }],
    });

    const paid = await app.services.purchases.recordPayment(purchase.uuid, { amount: '80', reference: 'PARTIAL' });

    expect(paid.status).toBe('partially_paid');

    const account = app.services.suppliers.getSupplierAccount(supplier.uuid);
    expect(account?.currentBalance).toBe('120');
  });
});
