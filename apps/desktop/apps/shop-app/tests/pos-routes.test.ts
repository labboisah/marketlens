import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { mkdirSync, rmSync } from 'node:fs';
import { join } from 'node:path';
import { tmpdir } from 'node:os';
import { randomUUID } from 'node:crypto';
import { createShopApplication } from '../src/services/application/shop-application';
import { createLocalServer } from '../src/server/local-server';
import type { ShopEnvironment } from '../src/shared/environment';

const tempDir = join(tmpdir(), 'marketlens-shop-app-pos-route-tests');
const sqlitePath = join(tempDir, 'shop-test.sqlite');
const backupPath = join(tempDir, 'backups');
const exportPath = join(tempDir, 'exports');

const testEnvironment: ShopEnvironment = {
  SHOP_APP_ENV: 'test',
  SHOP_APP_LOG_LEVEL: 'debug',
  SHOP_LOCAL_API_HOST: '127.0.0.1',
  SHOP_LOCAL_API_PORT: 5014,
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

describe('Shop App POS and customer route contracts', () => {
  beforeEach(() => {
    rmSync(tempDir, { recursive: true, force: true });
    mkdirSync(tempDir, { recursive: true });
    mkdirSync(backupPath, { recursive: true });
    mkdirSync(exportPath, { recursive: true });
  });

  afterEach(() => {
    rmSync(tempDir, { recursive: true, force: true });
  });

  it('returns POS catalog aggregates with product, stock, barcode, and usage metadata', async () => {
    const app = await createShopApplication(testEnvironment);
    const server = await createLocalServer(app);

    const category = app.services.products.createCategory({ name: 'Beverages', slug: 'beverages' });
    const brand = app.services.products.createBrand({ name: 'MarketLens Brand' });
    const unit = app.services.products.createUnit({ code: 'PCS', name: 'Pieces', isBase: true });
    const product = app.services.products.createProduct({
      sku: 'POS-001',
      name: 'Soda Bottle',
      categoryUuid: category.uuid,
      brandUuid: brand.uuid,
      baseUnitUuid: unit.uuid,
      defaultSalesUnitUuid: unit.uuid,
      active: true,
      retailPrice: '120',
      wholesalePrice: '100',
      minSellingPrice: '90',
    });

    app.services.products.createProductUnit({
      productUuid: product.uuid,
      unitUuid: unit.uuid,
      toBaseMultiplier: '1',
    });
    app.services.products.createBarcode(product.uuid, '1234567890123');

    const warehouse = app.services.inventory.createWarehouse({ code: 'MAIN', name: 'Main Warehouse' });
    app.services.inventory.createStock({
      productUuid: product.uuid,
      warehouseUuid: warehouse.uuid,
      unitUuid: unit.uuid,
      quantity: '15',
    });

    await app.services.sales.createPaymentMethod({ code: 'CASH', name: 'Cash', type: 'cash' });

    const terminal = await app.services.cashier.createTerminal({ code: 'POS-1', name: 'Front Counter' });
    const cashAccount = await app.services.cashier.createCashAccount({ code: 'TILL-1', name: 'Front Till' });
    const shift = await app.services.cashier.openShift({
      terminalUuid: terminal.uuid,
      cashAccountUuid: cashAccount.uuid,
      cashierUserUuid: randomUUID(),
      openingCash: '100',
    });

    const now = new Date().toISOString();
    const saleUuid = randomUUID();
    app.database.sqlite
      .prepare('INSERT INTO sales (uuid, sale_number, idempotency_key, shift_uuid, warehouse_uuid, status, subtotal, discount_amount, total_amount, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
      .run(saleUuid, 'SALE-POS-1', randomUUID(), shift.uuid, warehouse.uuid, 'completed', '120', '0', '120', now);
    app.database.sqlite
      .prepare('INSERT INTO sale_items (uuid, sale_uuid, product_uuid, unit_uuid, quantity, unit_price, line_total, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
      .run(randomUUID(), saleUuid, product.uuid, unit.uuid, '1', '120', '120', now);

    const response = await server.server.inject({
      method: 'GET',
      url: `/api/v1/pos/catalog?warehouseUuid=${warehouse.uuid}&limit=25`,
    });

    expect(response.statusCode).toBe(200);

    const payload = response.json() as {
      success: boolean;
      data: {
        warehouses: Array<{ uuid: string }>;
        categories: Array<{ uuid: string }>;
        brands: Array<{ uuid: string }>;
        paymentMethods: Array<{ code: string }>;
        products: Array<{
          uuid: string;
          sku: string;
          barcodes: string[];
          stockByUnit: Array<{ unitUuid: string; quantity: string }>;
          units: Array<{ unitUuid: string; toBaseMultiplier: string; isBase: boolean }>;
          soldCount: number;
          lastSoldAt: string | null;
        }>;
      };
    };

    expect(payload.success).toBe(true);
    expect(payload.data.warehouses.some((item) => item.uuid === warehouse.uuid)).toBe(true);
    expect(payload.data.categories.some((item) => item.uuid === category.uuid)).toBe(true);
    expect(payload.data.brands.some((item) => item.uuid === brand.uuid)).toBe(true);
    expect(payload.data.paymentMethods.some((item) => item.code === 'CASH')).toBe(true);

    const productRow = payload.data.products.find((item) => item.uuid === product.uuid);
    expect(productRow).toBeDefined();
    expect(productRow?.sku).toBe('POS-001');
    expect(productRow?.barcodes).toContain('1234567890123');
    expect(productRow?.units.some((item) => item.unitUuid === unit.uuid && item.toBaseMultiplier === '1' && item.isBase)).toBe(true);
    expect(productRow?.stockByUnit).toContainEqual({ unitUuid: unit.uuid, quantity: '15' });
    expect(productRow?.soldCount).toBeGreaterThanOrEqual(1);
    expect(productRow?.lastSoldAt).toBeTruthy();

    await server.stop();
  });

  it('filters customer list by search term for POS customer selection', async () => {
    const app = await createShopApplication(testEnvironment);
    const server = await createLocalServer(app);

    await app.services.customers.createCustomer({
      code: 'CUS-ADA',
      name: 'Ada Retail',
      type: 'retail',
      phone: '08030000000',
      address: '1 Market Road',
    });
    await app.services.customers.createCustomer({
      code: 'CUS-BOB',
      name: 'Bob Wholesale',
      type: 'wholesale',
      phone: '08031111111',
      address: '2 Store Lane',
    });

    const response = await server.server.inject({
      method: 'GET',
      url: '/api/v1/customers?search=ada',
    });

    expect(response.statusCode).toBe(200);

    const payload = response.json() as {
      success: boolean;
      data: Array<{ code: string; name: string }>;
    };

    expect(payload.success).toBe(true);
    expect(payload.data.length).toBe(1);
    expect(payload.data[0]).toMatchObject({ code: 'CUS-ADA', name: 'Ada Retail' });

    await server.stop();
  });

  it('returns receipt details and records reprint audit without creating a new sale', async () => {
    const app = await createShopApplication(testEnvironment);
    const server = await createLocalServer(app);

    const unit = app.services.products.createUnit({ code: 'PCS', name: 'Pieces', isBase: true });
    const product = app.services.products.createProduct({
      sku: 'POS-RCP-1',
      name: 'Receipt Product',
      baseUnitUuid: unit.uuid,
      defaultSalesUnitUuid: unit.uuid,
      active: true,
      retailPrice: '150',
      minSellingPrice: '100',
    });
    app.services.products.createProductUnit({ productUuid: product.uuid, unitUuid: unit.uuid, toBaseMultiplier: '1' });

    const warehouse = app.services.inventory.createWarehouse({ code: 'W-RCP', name: 'Receipt Warehouse' });
    app.services.inventory.createStock({ productUuid: product.uuid, warehouseUuid: warehouse.uuid, unitUuid: unit.uuid, quantity: '8' });

    const customer = await app.services.customers.createCustomer({
      code: 'CUS-RCP',
      name: 'Receipt Customer',
      type: 'retail',
      phone: '08032222222',
      address: '3 Receipt Lane',
    });

    const cashMethod = await app.services.sales.createPaymentMethod({ code: 'CASH-RCP', name: 'Cash Receipt', type: 'cash' });
    const terminal = await app.services.cashier.createTerminal({ code: 'POS-RCP', name: 'Receipt Counter' });
    const cashAccount = await app.services.cashier.createCashAccount({ code: 'TILL-RCP', name: 'Receipt Till' });
    const shift = await app.services.cashier.openShift({ terminalUuid: terminal.uuid, cashAccountUuid: cashAccount.uuid, cashierUserUuid: randomUUID(), openingCash: '200' });

    const sale = await app.services.sales.completeSale({
      idempotencyKey: randomUUID(),
      shiftUuid: shift.uuid,
      warehouseUuid: warehouse.uuid,
      customerUuid: customer.uuid,
      discountAmount: '0',
      receiptFormat: 'thermal',
      items: [{ productUuid: product.uuid, unitUuid: unit.uuid, quantity: '1', unitPrice: '150' }],
      payments: [{ paymentMethodUuid: cashMethod.uuid, amount: '150', amountTendered: '200', reference: 'CASH-200' }],
    });

    const detailsResponse = await server.server.inject({ method: 'GET', url: `/api/v1/receipts/${sale.receipt.uuid}` });
    expect(detailsResponse.statusCode).toBe(200);
    const detailsPayload = detailsResponse.json() as {
      success: boolean;
      data: {
        saleUuid: string;
        receiptNumber: string;
        saleNumber: string;
        customerName: string | null;
        items: Array<{ name: string }>;
        payments: Array<{ paymentMethodType: string; amountTendered: string | null }>;
        totals: { totalAmount: string; changeDue: string };
      };
    };

    expect(detailsPayload.success).toBe(true);
    expect(detailsPayload.data.saleUuid).toBe(sale.uuid);
    expect(detailsPayload.data.saleNumber).toBe(sale.saleNumber);
    expect(detailsPayload.data.customerName).toBe('Receipt Customer');
    expect(detailsPayload.data.items[0]?.name).toBe('Receipt Product');
    expect(detailsPayload.data.payments[0]?.paymentMethodType).toBe('cash');
    expect(detailsPayload.data.payments[0]?.amountTendered).toBe('200');
    expect(detailsPayload.data.totals.totalAmount).toBe('150');
    expect(detailsPayload.data.totals.changeDue).toBe('50');

    const saleCountBefore = (app.database.sqlite.prepare('SELECT COUNT(*) AS count FROM sales').get() as { count: number }).count;
    const reprintResponse = await server.server.inject({
      method: 'POST',
      url: `/api/v1/receipts/${sale.receipt.uuid}/reprints`,
      payload: { reason: 'Customer requested duplicate copy', approvedByUserUuid: randomUUID() },
    });
    expect(reprintResponse.statusCode).toBe(200);

    const saleCountAfter = (app.database.sqlite.prepare('SELECT COUNT(*) AS count FROM sales').get() as { count: number }).count;
    expect(saleCountAfter).toBe(saleCountBefore);

    const reprintCount = (app.database.sqlite.prepare('SELECT COUNT(*) AS count FROM receipt_reprints WHERE receipt_uuid = ?').get(sale.receipt.uuid) as { count: number }).count;
    expect(reprintCount).toBe(1);

    const reprintAuditLog = app.database.sqlite
      .prepare('SELECT action, subject_type AS subjectType, subject_uuid AS subjectUuid, metadata_json AS metadataJson FROM audit_logs WHERE action = ? AND subject_uuid = ? ORDER BY id DESC LIMIT 1')
      .get('receipt.reprinted', sale.receipt.uuid) as { action: string; subjectType: string; subjectUuid: string; metadataJson: string | null } | undefined;
    expect(reprintAuditLog).toBeDefined();
    expect(reprintAuditLog?.action).toBe('receipt.reprinted');
    expect(reprintAuditLog?.subjectType).toBe('receipt');
    expect(reprintAuditLog?.subjectUuid).toBe(sale.receipt.uuid);
    expect(reprintAuditLog?.metadataJson).toContain('Customer requested duplicate copy');

    const updatedReceipt = app.database.sqlite.prepare('SELECT print_count AS printCount FROM receipts WHERE uuid = ?').get(sale.receipt.uuid) as { printCount: number };
    expect(updatedReceipt.printCount).toBe(1);

    await server.stop();
  });

  it('supports sale lookup, controlled returns, and controlled cancellation records', async () => {
    const app = await createShopApplication(testEnvironment);
    const server = await createLocalServer(app);

    const unit = app.services.products.createUnit({ code: 'PCS', name: 'Pieces', isBase: true });
    const product = app.services.products.createProduct({
      sku: 'POS-RET-1',
      name: 'Returnable Item',
      baseUnitUuid: unit.uuid,
      defaultSalesUnitUuid: unit.uuid,
      active: true,
      retailPrice: '200',
      minSellingPrice: '150',
    });
    app.services.products.createProductUnit({ productUuid: product.uuid, unitUuid: unit.uuid, toBaseMultiplier: '1' });

    const warehouse = app.services.inventory.createWarehouse({ code: 'W-RET', name: 'Returns Warehouse' });
    app.services.inventory.createStock({ productUuid: product.uuid, warehouseUuid: warehouse.uuid, unitUuid: unit.uuid, quantity: '20' });

    const customer = await app.services.customers.createCustomer({
      code: 'CUS-RET',
      name: 'Return Customer',
      type: 'retail',
      phone: '08033333333',
      address: '4 Return Close',
    });
    const cashMethod = await app.services.sales.createPaymentMethod({ code: 'CASH-RET', name: 'Cash Return', type: 'cash' });
    const terminal = await app.services.cashier.createTerminal({ code: 'POS-RET', name: 'Returns Desk' });
    const cashAccount = await app.services.cashier.createCashAccount({ code: 'TILL-RET', name: 'Returns Till' });
    const shift = await app.services.cashier.openShift({ terminalUuid: terminal.uuid, cashAccountUuid: cashAccount.uuid, cashierUserUuid: randomUUID(), openingCash: '500' });

    const sale = await app.services.sales.completeSale({
      idempotencyKey: randomUUID(),
      shiftUuid: shift.uuid,
      warehouseUuid: warehouse.uuid,
      customerUuid: customer.uuid,
      discountAmount: '0',
      receiptFormat: 'thermal',
      items: [{ productUuid: product.uuid, unitUuid: unit.uuid, quantity: '2', unitPrice: '200' }],
      payments: [{ paymentMethodUuid: cashMethod.uuid, amount: '400', amountTendered: '500', reference: 'CASH-500' }],
    });

    const lookupResponse = await server.server.inject({
      method: 'GET',
      url: `/api/v1/pos/sales/search?saleNumber=${encodeURIComponent(sale.saleNumber)}&customer=Return%20Customer&product=Returnable`,
    });
    expect(lookupResponse.statusCode).toBe(200);
    const lookupPayload = lookupResponse.json() as { success: boolean; data: Array<{ uuid: string; saleNumber: string; status: string; items: Array<{ uuid: string; name: string }>; cancellation: unknown }> };
    expect(lookupPayload.success).toBe(true);
    expect(lookupPayload.data.length).toBeGreaterThanOrEqual(1);

    const lookupSale = lookupPayload.data.find((row) => row.uuid === sale.uuid);
    expect(lookupSale).toBeDefined();
    expect(lookupSale?.saleNumber).toBe(sale.saleNumber);
    expect(lookupSale?.status).toBe('completed');
    expect(lookupSale?.items[0]?.name).toBe('Returnable Item');

    const saleItemRow = app.database.sqlite.prepare('SELECT uuid FROM sale_items WHERE sale_uuid = ? ORDER BY id ASC LIMIT 1').get(sale.uuid) as { uuid: string };
    const createReturnResponse = await server.server.inject({
      method: 'POST',
      url: '/api/v1/pos/returns',
      payload: {
        saleUuid: sale.uuid,
        returnType: 'partial',
        reason: 'Damaged on delivery',
        items: [{ saleItemUuid: saleItemRow.uuid, quantity: '1' }],
        refundMethod: 'cash',
        approvalRequested: false,
        requestedByUserUuid: randomUUID(),
      },
    });
    expect(createReturnResponse.statusCode).toBe(200);
    const createdReturn = createReturnResponse.json() as { success: boolean; data: { uuid: string; saleUuid: string; status: string; refundAmount: string } };
    expect(createdReturn.success).toBe(true);
    expect(createdReturn.data.saleUuid).toBe(sale.uuid);
    expect(createdReturn.data.status).toBe('refunded');
    expect(createdReturn.data.refundAmount).toBe('200');

    const originalSaleAfterReturn = app.database.sqlite.prepare('SELECT status, total_amount AS totalAmount FROM sales WHERE uuid = ?').get(sale.uuid) as { status: string; totalAmount: string };
    expect(originalSaleAfterReturn.status).toBe('completed');
    expect(originalSaleAfterReturn.totalAmount).toBe('400');

    const invalidReturnResponse = await server.server.inject({
      method: 'POST',
      url: '/api/v1/pos/returns',
      payload: {
        saleUuid: sale.uuid,
        returnType: 'partial',
        reason: 'Excess quantity attempt',
        items: [{ saleItemUuid: saleItemRow.uuid, quantity: '5' }],
        refundMethod: 'cash',
        approvalRequested: false,
        requestedByUserUuid: randomUUID(),
      },
    });
    expect(invalidReturnResponse.statusCode).toBeGreaterThanOrEqual(400);

    const returnDetailsResponse = await server.server.inject({
      method: 'GET',
      url: `/api/v1/pos/returns/${createdReturn.data.uuid}`,
    });
    expect(returnDetailsResponse.statusCode).toBe(200);
    const returnDetailsPayload = returnDetailsResponse.json() as { success: boolean; data: { reason: string; refundMethod: string | null; items: Array<{ name: string; quantity: string }> } };
    expect(returnDetailsPayload.success).toBe(true);
    expect(returnDetailsPayload.data.reason).toBe('Damaged on delivery');
    expect(returnDetailsPayload.data.refundMethod).toBe('cash');
    expect(returnDetailsPayload.data.items[0]?.name).toBe('Returnable Item');
    expect(returnDetailsPayload.data.items[0]?.quantity).toBe('1');

    const secondSale = await app.services.sales.completeSale({
      idempotencyKey: randomUUID(),
      shiftUuid: shift.uuid,
      warehouseUuid: warehouse.uuid,
      customerUuid: customer.uuid,
      discountAmount: '0',
      receiptFormat: 'thermal',
      items: [{ productUuid: product.uuid, unitUuid: unit.uuid, quantity: '1', unitPrice: '200' }],
      payments: [{ paymentMethodUuid: cashMethod.uuid, amount: '200', amountTendered: '200', reference: 'CASH-200' }],
    });

    const cancellationResponse = await server.server.inject({
      method: 'POST',
      url: `/api/v1/pos/sales/${secondSale.uuid}/cancel`,
      payload: {
        reason: 'Duplicate transaction',
        refundMethod: 'cash',
        approvalRequested: true,
        refundState: 'pending',
        requestedByUserUuid: randomUUID(),
      },
    });
    expect(cancellationResponse.statusCode).toBe(200);
    const cancellationPayload = cancellationResponse.json() as { success: boolean; data: { saleUuid: string; reason: string; approvalState: string; refundState: string } };
    expect(cancellationPayload.success).toBe(true);
    expect(cancellationPayload.data.saleUuid).toBe(secondSale.uuid);
    expect(cancellationPayload.data.reason).toBe('Duplicate transaction');
    expect(cancellationPayload.data.approvalState).toBe('pending');
    expect(cancellationPayload.data.refundState).toBe('pending');

    const originalSaleAfterCancellation = app.database.sqlite.prepare('SELECT status, total_amount AS totalAmount FROM sales WHERE uuid = ?').get(secondSale.uuid) as { status: string; totalAmount: string };
    expect(originalSaleAfterCancellation.status).toBe('completed');
    expect(originalSaleAfterCancellation.totalAmount).toBe('200');

    const cancelSaleWithReturnResponse = await server.server.inject({
      method: 'POST',
      url: `/api/v1/pos/sales/${sale.uuid}/cancel`,
      payload: {
        reason: 'Should fail after return',
        refundMethod: 'cash',
        approvalRequested: true,
        refundState: 'pending',
        requestedByUserUuid: randomUUID(),
      },
    });
    expect(cancelSaleWithReturnResponse.statusCode).toBeGreaterThanOrEqual(400);

    await server.stop();
  });
});
