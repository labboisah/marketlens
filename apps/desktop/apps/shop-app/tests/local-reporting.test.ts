import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { existsSync, mkdirSync, rmSync } from 'node:fs';
import { join } from 'node:path';
import { tmpdir } from 'node:os';
import { randomUUID } from 'node:crypto';
import { createShopApplication } from '../src/services/application/shop-application';
import type { ShopEnvironment } from '../src/shared/environment';

const tempDir = join(tmpdir(), 'marketlens-shop-app-reporting-tests');
const sqlitePath = join(tempDir, 'shop-test.sqlite');
const exportPath = join(tempDir, 'exports');
const branchUuid = '00000000-0000-4000-8000-000000000001';
const reportDate = '2026-08-04';

const testEnvironment: ShopEnvironment = {
  SHOP_APP_ENV: 'test', SHOP_APP_LOG_LEVEL: 'debug', SHOP_LOCAL_API_HOST: '127.0.0.1', SHOP_LOCAL_API_PORT: 5010,
  SHOP_LOCAL_API_PUBLIC_HOST: '', SHOP_LOCAL_API_ALLOWED_ORIGINS: 'http://127.0.0.1:4091', SHOP_SOCKET_IO_PATH: '/socket.io',
  SHOP_DEVICE_NAME: 'MarketLens Test Shop', SHOP_DEVICE_PAIRING_CODE: 'test-pairing-code', SHOP_INITIAL_ADMIN_USERNAME: 'admin', SHOP_INITIAL_ADMIN_PASSWORD: 'test-password',
  SHOP_BRANCH_UUID: branchUuid, SHOP_TENANT_UUID: branchUuid, SHOP_SQLITE_PATH: sqlitePath, SHOP_TEST_SQLITE_PATH: sqlitePath,
  SHOP_AUTO_START_ENABLED: false, SHOP_BACKUP_RETENTION_DAYS: 14, PLATFORM_SYNC_ENABLED: false, PLATFORM_SYNC_BASE_URL: 'http://localhost:8000/api', PLATFORM_SYNC_DEVICE_TOKEN: '', PLATFORM_SYNC_INTERVAL_SECONDS: 60,
  LOCAL_BACKUP_PATH: join(tempDir, 'backups'), LOCAL_EXPORT_PATH: exportPath,
};

describe('Shop App local reporting', () => {
  beforeEach(() => { rmSync(tempDir, { recursive: true, force: true }); mkdirSync(exportPath, { recursive: true }); mkdirSync(testEnvironment.LOCAL_BACKUP_PATH, { recursive: true }); });
  afterEach(() => rmSync(tempDir, { recursive: true, force: true }));

  it('derives dashboard totals and exports a branch-local daily sales report', async () => {
    const app = await createShopApplication(testEnvironment);
    const db = app.database.sqlite;
    const now = `${reportDate}T12:00:00.000Z`;
    const unitUuid = randomUUID(); const productUuid = randomUUID(); const warehouseUuid = randomUUID(); const stockUuid = randomUUID();
    const terminalUuid = randomUUID(); const cashAccountUuid = randomUUID(); const shiftUuid = randomUUID(); const saleUuid = randomUUID(); const paymentMethodUuid = randomUUID();
    const supplierUuid = randomUUID(); const customerUuid = randomUUID(); const expenseCategoryUuid = randomUUID();

    db.prepare('INSERT INTO units (uuid, code, name, is_base, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)').run(unitUuid, 'PCS', 'Pieces', '1', now, now);
    db.prepare('INSERT INTO products (uuid, sku, name, base_unit_uuid, is_service, is_non_stock, active, reorder_level, cost_price, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)').run(productUuid, 'REPORT-1', 'Report Product', unitUuid, '0', '0', '1', '5', '40', now, now);
    db.prepare('INSERT INTO warehouses (uuid, code, name, created_at, updated_at) VALUES (?, ?, ?, ?, ?)').run(warehouseUuid, 'MAIN', 'Main', now, now);
    db.prepare('INSERT INTO stocks (uuid, product_uuid, warehouse_uuid, unit_uuid, quantity, last_updated_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)').run(stockUuid, productUuid, warehouseUuid, unitUuid, '5', now, now, now);
    db.prepare('INSERT INTO terminals (uuid, code, name, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)').run(terminalUuid, 'POS-1', 'POS 1', 'active', now, now);
    db.prepare('INSERT INTO cash_accounts (uuid, code, name, current_balance, active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)').run(cashAccountUuid, 'TILL', 'Till', '0', '1', now, now);
    db.prepare('INSERT INTO cashier_shifts (uuid, terminal_uuid, cash_account_uuid, cashier_user_uuid, status, opening_cash, opened_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)').run(shiftUuid, terminalUuid, cashAccountUuid, randomUUID(), 'open', '0', now, now, now);
    db.prepare('INSERT INTO sales (uuid, sale_number, idempotency_key, shift_uuid, warehouse_uuid, status, subtotal, discount_amount, total_amount, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)').run(saleUuid, 'SALE-REPORT-1', randomUUID(), shiftUuid, warehouseUuid, 'completed', '100', '0', '100', now);
    db.prepare('INSERT INTO sale_items (uuid, sale_uuid, product_uuid, unit_uuid, quantity, unit_price, line_total, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)').run(randomUUID(), saleUuid, productUuid, unitUuid, '1', '100', '100', now);
    db.prepare('INSERT INTO payment_methods (uuid, code, name, type, active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)').run(paymentMethodUuid, 'CASH', 'Cash', 'cash', '1', now, now);
    db.prepare('INSERT INTO sale_payments (uuid, sale_uuid, payment_method_uuid, amount, created_at) VALUES (?, ?, ?, ?, ?)').run(randomUUID(), saleUuid, paymentMethodUuid, '100', now);
    db.prepare('INSERT INTO customers (uuid, code, name, type, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)').run(customerUuid, 'CUST-R', 'Report Customer', 'retail', 'active', now, now);
    db.prepare('INSERT INTO customer_accounts (uuid, customer_uuid, current_balance, created_at, updated_at) VALUES (?, ?, ?, ?, ?)').run(randomUUID(), customerUuid, '25', now, now);
    db.prepare('INSERT INTO suppliers (uuid, code, name, preferred, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)').run(supplierUuid, 'SUP-R', 'Report Supplier', '0', 'active', now, now);
    db.prepare('INSERT INTO supplier_accounts (uuid, supplier_uuid, current_balance, created_at, updated_at) VALUES (?, ?, ?, ?, ?)').run(randomUUID(), supplierUuid, '60', now, now);
    db.prepare('INSERT INTO expense_categories (uuid, name, requires_approval, active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)').run(expenseCategoryUuid, 'Utilities', '0', '1', now, now);
    db.prepare('INSERT INTO expenses (uuid, category_uuid, amount, payment_method, description, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)').run(randomUUID(), expenseCategoryUuid, '10', 'cash', 'Power', 'approved', now, now);
    db.prepare('INSERT INTO devices (uuid, name, kind, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)').run(randomUUID(), 'Client', 'client_app', 'authorized', now, now);

    const dashboard = app.services.reporting.dashboard(reportDate);
    expect(dashboard.todaySales).toBe(100);
    expect(dashboard.grossProfit).toBe(60);
    expect(dashboard.estimatedNetProfit).toBe(50);
    expect(dashboard.customerDebt).toBe(25);
    expect(dashboard.supplierBalance).toBe(60);
    expect(dashboard.stockValue).toBe(200);
    expect(dashboard.lowStockCount).toBe(1);
    expect(dashboard.openCashierShifts).toBe(1);
    expect(dashboard.authorizedClientApps).toBe(1);

    const job = app.services.reporting.createReportJob('daily-sales', { from: reportDate, to: reportDate, branchUuid }, 'csv', randomUUID());
    const result = app.services.reporting.processReportJob(job.uuid);
    expect(existsSync(result.path)).toBe(true);
    expect(() => app.services.reporting.report('daily-sales', { from: reportDate, to: reportDate, branchUuid: randomUUID() })).toThrow('configured branch');
  });
});