import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { randomUUID } from 'node:crypto';
import { mkdirSync, rmSync } from 'node:fs';
import { join } from 'node:path';
import { tmpdir } from 'node:os';
import { createShopApplication } from '../src/services/application/shop-application';
import type { ShopEnvironment } from '../src/shared/environment';

const tempDir = join(tmpdir(), 'marketlens-shop-app-cashier-shift-tests');
const sqlitePath = join(tempDir, 'shop-test.sqlite');

const testEnvironment: ShopEnvironment = {
  SHOP_APP_ENV: 'test',
  SHOP_APP_LOG_LEVEL: 'debug',
  SHOP_LOCAL_API_HOST: '127.0.0.1',
  SHOP_LOCAL_API_PORT: 5004,
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

describe('Shop App cashier shifts and cash controls', () => {
  beforeEach(() => {
    rmSync(tempDir, { recursive: true, force: true });
    mkdirSync(testEnvironment.LOCAL_BACKUP_PATH, { recursive: true });
    mkdirSync(testEnvironment.LOCAL_EXPORT_PATH, { recursive: true });
  });

  afterEach(() => {
    rmSync(tempDir, { recursive: true, force: true });
  });

  it('reconciles opening cash, sales, refunds, and approved cash expenses at settlement', async () => {
    const app = await createShopApplication(testEnvironment);
    const terminal = await app.services.cashier.createTerminal({ code: 'POS-1', name: 'Front Counter' });
    const cashAccount = await app.services.cashier.createCashAccount({ code: 'TILL-1', name: 'Front Till' });
    const category = await app.services.cashier.createExpenseCategory({ name: 'Transport', requiresApproval: true });
    const shift = await app.services.cashier.openShift({ terminalUuid: terminal.uuid, cashAccountUuid: cashAccount.uuid, cashierUserUuid: '00000000-0000-0000-0000-000000000004', openingCash: '100' });

    await app.services.cashier.recordShiftPayment(shift.uuid, { type: 'cash_sale', amount: '500', reference: 'SALE-001' });
    await app.services.cashier.recordShiftPayment(shift.uuid, { type: 'cash_refund', amount: '50', reference: 'REFUND-001' });
    const expense = await app.services.cashier.recordExpense({ categoryUuid: category.uuid, cashAccountUuid: cashAccount.uuid, shiftUuid: shift.uuid, amount: '75', paymentMethod: 'cash', description: 'Delivery transport' });
    await app.services.cashier.approveExpense(expense.uuid, '00000000-0000-0000-0000-000000000003');

    const closed = await app.services.cashier.closeShift(shift.uuid, { actualCash: '460' });
    const settlement = await app.services.cashier.verifySettlement(closed.settlementUuid, '00000000-0000-0000-0000-000000000003');

    expect(closed.expectedCash).toBe('475');
    expect(closed.variance).toBe('-15');
    expect(settlement.status).toBe('verified');
    expect(app.database.sqlite.prepare("select count(*) as count from cash_transactions where transaction_type = 'expense'").get().count).toBe(1);
  });

  it('opens and closes a cashier shift with closing details persisted', async () => {
    const app = await createShopApplication(testEnvironment);
    const terminal = await app.services.cashier.createTerminal({ code: 'POS-2', name: 'Service Counter' });
    const cashAccount = await app.services.cashier.createCashAccount({ code: 'TILL-2', name: 'Service Till' });

    const shift = await app.services.cashier.openShift({
      terminalUuid: terminal.uuid,
      cashAccountUuid: cashAccount.uuid,
      cashierUserUuid: '00000000-0000-0000-0000-000000000005',
      openingCash: '250',
      notes: 'Morning shift',
    });

    await app.services.cashier.recordShiftPayment(shift.uuid, { type: 'cash_sale', amount: '120', reference: 'SALE-002' });

    const closed = await app.services.cashier.closeShift(shift.uuid, {
      actualCash: '370',
      notes: 'End of day close',
      cashDenominations: [{ denomination: '100', count: 3 }, { denomination: '50', count: 1 }],
      supportingExplanation: 'No discrepancy found',
    });

    expect(closed.status).toBe('closed');
    expect(closed.expectedCash).toBe('370');
    expect(closed.actualCash).toBe('370');
    expect(closed.variance).toBe('0');
    expect(closed.cashDenominations).toEqual([{ denomination: '100', count: 3 }, { denomination: '50', count: 1 }]);

    const settlement = app.database.sqlite.prepare('select * from cashier_settlements where uuid = ?').get(closed.settlementUuid) as { notes: string | null; cash_denominations_json: string | null; supporting_explanation: string | null; status: string };
    expect(settlement.status).toBe('pending_verification');
    expect(settlement.notes).toBe('End of day close');
    expect(settlement.cash_denominations_json).toBe(JSON.stringify([{ denomination: '100', count: 3 }, { denomination: '50', count: 1 }]));
    expect(settlement.supporting_explanation).toBe('No discrepancy found');
  });

  it('rejects duplicate and interrupted shift openings', async () => {
    const app = await createShopApplication(testEnvironment);
    const terminal = await app.services.cashier.createTerminal({ code: 'POS-3', name: 'Main Counter' });
    const cashAccount = await app.services.cashier.createCashAccount({ code: 'TILL-3', name: 'Main Till' });
    const cashierUserUuid = '00000000-0000-0000-0000-000000000006';

    await app.services.cashier.openShift({
      terminalUuid: terminal.uuid,
      cashAccountUuid: cashAccount.uuid,
      cashierUserUuid,
      openingCash: '75',
    });

    await expect(app.services.cashier.openShift({
      terminalUuid: terminal.uuid,
      cashAccountUuid: cashAccount.uuid,
      cashierUserUuid,
      openingCash: '100',
    })).rejects.toThrow('Another active shift already conflicts with this workstation');

    const interruptedApp = await createShopApplication(testEnvironment);
    const interruptedTerminal = await interruptedApp.services.cashier.createTerminal({ code: 'POS-4', name: 'Interrupted Counter' });
    const interruptedCashAccount = await interruptedApp.services.cashier.createCashAccount({ code: 'TILL-4', name: 'Interrupted Till' });
    const interruptedShiftUuid = randomUUID();
    const now = new Date().toISOString();

    interruptedApp.database.sqlite.prepare('INSERT INTO cashier_shifts (uuid, terminal_uuid, cash_account_uuid, cashier_user_uuid, status, opening_cash, notes, opened_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
      .run(interruptedShiftUuid, interruptedTerminal.uuid, interruptedCashAccount.uuid, '00000000-0000-0000-0000-000000000007', 'open', '60', 'Interrupted before close', now, now, now);

    await expect(interruptedApp.services.cashier.openShift({
      terminalUuid: interruptedTerminal.uuid,
      cashAccountUuid: interruptedCashAccount.uuid,
      cashierUserUuid: '00000000-0000-0000-0000-000000000007',
      openingCash: '60',
    })).rejects.toThrow('Another active shift already conflicts with this workstation');
  });

  it('cancels expenses through a reversal and keeps owner withdrawals separate', async () => {
    const app = await createShopApplication(testEnvironment);
    const cashAccount = await app.services.cashier.createCashAccount({ code: 'TILL-2', name: 'Back Till' });
    const category = await app.services.cashier.createExpenseCategory({ name: 'Utilities', requiresApproval: false });
    const expense = await app.services.cashier.recordExpense({ categoryUuid: category.uuid, cashAccountUuid: cashAccount.uuid, amount: '40', paymentMethod: 'cash', description: 'Electricity' });

    await app.services.cashier.cancelExpense(expense.uuid, 'Duplicate receipt');
    const withdrawal = await app.services.cashier.recordOwnerWithdrawal({ type: 'cash', cashAccountUuid: cashAccount.uuid, amount: '20', reason: 'Owner draw' });

    expect(app.database.sqlite.prepare("select count(*) as count from cash_transactions where transaction_type = 'expense_reversal'").get().count).toBe(1);
    expect(withdrawal.type).toBe('cash');
    expect(app.database.sqlite.prepare('select count(*) as count from expenses').get().count).toBe(1);
  });
});
