import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { mkdirSync, rmSync } from 'node:fs';
import { join } from 'node:path';
import { tmpdir } from 'node:os';
import { createShopApplication } from '../src/services/application/shop-application';
import type { ShopEnvironment } from '../src/shared/environment';

const tempDir = join(tmpdir(), 'marketlens-shop-app-customer-debt-tests');
const sqlitePath = join(tempDir, 'shop-test.sqlite');

const testEnvironment: ShopEnvironment = {
  SHOP_APP_ENV: 'test',
  SHOP_APP_LOG_LEVEL: 'debug',
  SHOP_LOCAL_API_HOST: '127.0.0.1',
  SHOP_LOCAL_API_PORT: 5003,
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
  LOCAL_BACKUP_PATH: join(tempDir, 'backups'),
  LOCAL_EXPORT_PATH: join(tempDir, 'exports'),
};

describe('Shop App customer debt ledger', () => {
  beforeEach(() => {
    rmSync(tempDir, { recursive: true, force: true });
    mkdirSync(testEnvironment.LOCAL_BACKUP_PATH, { recursive: true });
    mkdirSync(testEnvironment.LOCAL_EXPORT_PATH, { recursive: true });
  });

  afterEach(() => {
    rmSync(tempDir, { recursive: true, force: true });
  });

  it('records every balance change in the immutable customer ledger', async () => {
    const app = await createShopApplication(testEnvironment);
    const customer = await app.services.customers.createCustomer({
      code: 'CUS-001',
      name: 'Ada Retail',
      type: 'retail',
      phone: '08030000000',
      address: '1 Market Road',
      creditLimit: '1000',
      openingBalance: '100',
    });

    const creditSale = await app.services.customers.recordCreditSale(customer.uuid, {
      amount: '300',
      dueDate: '2026-08-10',
      reference: 'SALE-001',
    });

    const payment = await app.services.customers.recordPayment(customer.uuid, {
      amount: '250',
      reference: 'RCPT-001',
      allocations: [{ accountTransactionUuid: creditSale.uuid, amount: '250' }],
    });

    const account = app.services.customers.getAccount(customer.uuid);
    const statement = app.services.customers.getStatement(customer.uuid);

    expect(account?.currentBalance).toBe('150');
    expect(statement.transactions.map((transaction) => transaction.transactionType)).toEqual([
      'opening_balance',
      'credit_sale',
      'payment',
    ]);
    expect(statement.balance).toBe('150');
    expect(payment.amount).toBe('250');
    expect(app.database.sqlite.prepare('select count(*) as count from customer_payment_allocations').get().count).toBe(1);
  });

  it('rejects restricted credit and permits manager-approved excess credit', async () => {
    const app = await createShopApplication(testEnvironment);
    const customer = await app.services.customers.createCustomer({
      code: 'CUS-002',
      name: 'Blocked Customer',
      type: 'corporate',
      creditLimit: '100',
    });

    await expect(app.services.customers.recordCreditSale(customer.uuid, { amount: '150', dueDate: '2026-08-10' }))
      .rejects.toThrow('Credit limit exceeded');

    const approval = await app.services.customers.approveCredit(customer.uuid, {
      requestedAmount: '150',
      approvedByUserUuid: '00000000-0000-0000-0000-000000000003',
      reason: 'Approved by manager',
    });

    const transaction = await app.services.customers.recordCreditSale(customer.uuid, {
      amount: '150',
      dueDate: '2026-08-10',
      creditApprovalUuid: approval.uuid,
    });

    expect(transaction.transactionType).toBe('credit_sale');
  });

  it('writes off debt with a ledger entry and blocks further credit for blocked customers', async () => {
    const app = await createShopApplication(testEnvironment);
    const customer = await app.services.customers.createCustomer({
      code: 'CUS-003',
      name: 'Wholesale Debtor',
      type: 'wholesale',
      creditLimit: '500',
    });
    const sale = await app.services.customers.recordCreditSale(customer.uuid, { amount: '200', dueDate: '2026-08-10' });

    await app.services.customers.writeOffDebt(customer.uuid, {
      accountTransactionUuid: sale.uuid,
      amount: '50',
      approvedByUserUuid: '00000000-0000-0000-0000-000000000003',
      reason: 'Approved irrecoverable balance',
    });
    await app.services.customers.setCustomerStatus(customer.uuid, 'blocked');

    expect(app.services.customers.getAccount(customer.uuid)?.currentBalance).toBe('150');
    expect(app.services.customers.getStatement(customer.uuid).transactions.at(-1)?.transactionType).toBe('write_off');
    await expect(app.services.customers.recordCreditSale(customer.uuid, { amount: '10', dueDate: '2026-08-10' }))
      .rejects.toThrow('Customer is blocked');
  });
});
