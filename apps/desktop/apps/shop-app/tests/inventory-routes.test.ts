import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { mkdirSync, rmSync } from 'node:fs';
import { join } from 'node:path';
import { tmpdir } from 'node:os';
import { randomUUID } from 'node:crypto';
import { createShopApplication } from '../src/services/application/shop-application';
import { createLocalServer } from '../src/server/local-server';
import type { ShopEnvironment } from '../src/shared/environment';

const tempDir = join(tmpdir(), 'marketlens-shop-app-inventory-routes-tests');
const sqlitePath = join(tempDir, 'shop-test.sqlite');
const backupPath = join(tempDir, 'backups');
const exportPath = join(tempDir, 'exports');

const testEnvironment: ShopEnvironment = {
  SHOP_APP_ENV: 'test',
  SHOP_APP_LOG_LEVEL: 'debug',
  SHOP_LOCAL_API_HOST: '127.0.0.1',
  SHOP_LOCAL_API_PORT: 5016,
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

describe('Shop App inventory routes', () => {
  beforeEach(() => {
    rmSync(tempDir, { recursive: true, force: true });
    mkdirSync(tempDir, { recursive: true });
    mkdirSync(backupPath, { recursive: true });
    mkdirSync(exportPath, { recursive: true });
  });

  afterEach(() => {
    rmSync(tempDir, { recursive: true, force: true });
  });

  it('applies stock count variance only after approval workflow', async () => {
    const app = await createShopApplication(testEnvironment);
    const server = await createLocalServer(app);

    const unit = app.services.products.createUnit({ code: 'PCS', name: 'Pieces', isBase: true });
    const product = app.services.products.createProduct({
      sku: 'INV-001',
      name: 'Counted Product',
      baseUnitUuid: unit.uuid,
      defaultSalesUnitUuid: unit.uuid,
      active: true,
      retailPrice: '100',
      minSellingPrice: '90',
    });

    const warehouse = app.services.inventory.createWarehouse({ code: 'W-INV', name: 'Inventory Warehouse' });
    app.services.inventory.createStock({ productUuid: product.uuid, warehouseUuid: warehouse.uuid, unitUuid: unit.uuid, quantity: '10' });

    const createSessionResponse = await server.server.inject({
      method: 'POST',
      url: '/api/v1/inventory/stock-count-sessions',
      payload: { warehouseUuid: warehouse.uuid, notes: 'Cycle count' },
    });
    expect(createSessionResponse.statusCode).toBe(200);
    const sessionUuid = (createSessionResponse.json() as { data: { uuid: string } }).data.uuid;

    const saveItemResponse = await server.server.inject({
      method: 'POST',
      url: `/api/v1/inventory/stock-count-sessions/${sessionUuid}/items`,
      payload: {
        productUuid: product.uuid,
        unitUuid: unit.uuid,
        countedQuantity: '7',
        barcode: '1234567890',
      },
    });
    expect(saveItemResponse.statusCode).toBe(200);

    const stockBeforeApproval = app.database.sqlite.prepare('SELECT quantity FROM stocks WHERE product_uuid = ? AND warehouse_uuid = ? AND unit_uuid = ?')
      .get(product.uuid, warehouse.uuid, unit.uuid) as { quantity: string };
    expect(stockBeforeApproval.quantity).toBe('10');

    await server.server.inject({ method: 'POST', url: `/api/v1/inventory/stock-count-sessions/${sessionUuid}/submit` });
    const approveResponse = await server.server.inject({ method: 'POST', url: `/api/v1/inventory/stock-count-sessions/${sessionUuid}/approve`, payload: { approvedByUserUuid: randomUUID() } });
    expect(approveResponse.statusCode).toBe(200);

    const stockAfterApproval = app.database.sqlite.prepare('SELECT quantity FROM stocks WHERE product_uuid = ? AND warehouse_uuid = ? AND unit_uuid = ?')
      .get(product.uuid, warehouse.uuid, unit.uuid) as { quantity: string };
    expect(stockAfterApproval.quantity).toBe('7');

    const movement = app.database.sqlite.prepare("SELECT movement_type AS movementType FROM stock_movements WHERE reference = ? ORDER BY id DESC LIMIT 1")
      .get(sessionUuid) as { movementType: string };
    expect(['stock_count_increase', 'stock_count_decrease']).toContain(movement.movementType);

    await server.stop();
  });

  it('keeps stock unchanged for pending adjustment and applies on approval', async () => {
    const app = await createShopApplication(testEnvironment);
    const server = await createLocalServer(app);

    const unit = app.services.products.createUnit({ code: 'PCS', name: 'Pieces', isBase: true });
    const product = app.services.products.createProduct({
      sku: 'INV-002',
      name: 'Adjustable Product',
      baseUnitUuid: unit.uuid,
      defaultSalesUnitUuid: unit.uuid,
      active: true,
      retailPrice: '120',
      minSellingPrice: '100',
    });
    const warehouse = app.services.inventory.createWarehouse({ code: 'W-ADJ', name: 'Adjustment Warehouse' });
    app.services.inventory.createStock({ productUuid: product.uuid, warehouseUuid: warehouse.uuid, unitUuid: unit.uuid, quantity: '15' });

    const createAdjustmentResponse = await server.server.inject({
      method: 'POST',
      url: '/api/v1/inventory/stock-adjustments',
      payload: {
        warehouseUuid: warehouse.uuid,
        productUuid: product.uuid,
        unitUuid: unit.uuid,
        adjustmentType: 'decrease',
        quantity: '4',
        reason: 'Damaged pieces',
        supportingNote: 'Found during shelf inspection',
        approvalRequested: true,
      },
    });
    expect(createAdjustmentResponse.statusCode).toBe(200);
    const adjustmentUuid = (createAdjustmentResponse.json() as { data: { uuid: string } }).data.uuid;

    const stockBeforeApproval = app.database.sqlite.prepare('SELECT quantity FROM stocks WHERE product_uuid = ? AND warehouse_uuid = ? AND unit_uuid = ?')
      .get(product.uuid, warehouse.uuid, unit.uuid) as { quantity: string };
    expect(stockBeforeApproval.quantity).toBe('15');

    const approveAdjustmentResponse = await server.server.inject({
      method: 'POST',
      url: `/api/v1/inventory/stock-adjustments/${adjustmentUuid}/approve`,
      payload: { approvedByUserUuid: randomUUID() },
    });
    expect(approveAdjustmentResponse.statusCode).toBe(200);

    const stockAfterApproval = app.database.sqlite.prepare('SELECT quantity FROM stocks WHERE product_uuid = ? AND warehouse_uuid = ? AND unit_uuid = ?')
      .get(product.uuid, warehouse.uuid, unit.uuid) as { quantity: string };
    expect(stockAfterApproval.quantity).toBe('11');

    await server.stop();
  });

  it('applies stock transfer balances only through transfer approval', async () => {
    const app = await createShopApplication(testEnvironment);
    const server = await createLocalServer(app);

    const unit = app.services.products.createUnit({ code: 'PCS', name: 'Pieces', isBase: true });
    const product = app.services.products.createProduct({
      sku: 'INV-003',
      name: 'Transfer Product',
      baseUnitUuid: unit.uuid,
      defaultSalesUnitUuid: unit.uuid,
      active: true,
      retailPrice: '80',
      minSellingPrice: '60',
    });

    const fromWarehouse = app.services.inventory.createWarehouse({ code: 'W-FROM', name: 'From Warehouse' });
    const toWarehouse = app.services.inventory.createWarehouse({ code: 'W-TO', name: 'To Warehouse' });
    app.services.inventory.createStock({ productUuid: product.uuid, warehouseUuid: fromWarehouse.uuid, unitUuid: unit.uuid, quantity: '20' });

    const transferCreateResponse = await server.server.inject({
      method: 'POST',
      url: '/api/v1/inventory/stock-transfers',
      payload: {
        fromWarehouseUuid: fromWarehouse.uuid,
        toWarehouseUuid: toWarehouse.uuid,
        reason: 'Rebalancing stock',
        approvalRequested: true,
        items: [{ productUuid: product.uuid, unitUuid: unit.uuid, quantity: '6' }],
      },
    });
    expect(transferCreateResponse.statusCode).toBe(200);
    const transferUuid = (transferCreateResponse.json() as { data: { uuid: string } }).data.uuid;

    const beforeSubmit = app.database.sqlite.prepare('SELECT quantity FROM stocks WHERE product_uuid = ? AND warehouse_uuid = ? AND unit_uuid = ?')
      .get(product.uuid, fromWarehouse.uuid, unit.uuid) as { quantity: string };
    expect(beforeSubmit.quantity).toBe('20');

    await server.server.inject({ method: 'POST', url: `/api/v1/inventory/stock-transfers/${transferUuid}/submit` });
    const approveTransferResponse = await server.server.inject({
      method: 'POST',
      url: `/api/v1/inventory/stock-transfers/${transferUuid}/approve`,
      payload: { approvedByUserUuid: randomUUID() },
    });
    expect(approveTransferResponse.statusCode).toBe(200);

    const fromAfter = app.database.sqlite.prepare('SELECT quantity FROM stocks WHERE product_uuid = ? AND warehouse_uuid = ? AND unit_uuid = ?')
      .get(product.uuid, fromWarehouse.uuid, unit.uuid) as { quantity: string };
    const toAfter = app.database.sqlite.prepare('SELECT quantity FROM stocks WHERE product_uuid = ? AND warehouse_uuid = ? AND unit_uuid = ?')
      .get(product.uuid, toWarehouse.uuid, unit.uuid) as { quantity: string };

    expect(fromAfter.quantity).toBe('14');
    expect(toAfter.quantity).toBe('6');

    await server.stop();
  });
});
