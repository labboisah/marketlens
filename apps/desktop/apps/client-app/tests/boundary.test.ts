import { describe, expect, it } from 'vitest';
import { clientAppBoundary, roleShells } from '../src';

describe('Client App boundary', () => {
  it('never opens SQLite directly', () => {
    expect(clientAppBoundary.opensSqliteDirectly).toBe(false);
    expect(clientAppBoundary.ownsBusinessDatabase).toBe(false);
    expect(clientAppBoundary.writesThroughShopApi).toBe(true);
    expect(clientAppBoundary.storesSensitiveSessionWithSafeStorage).toBe(true);
    expect(clientAppBoundary.rendererHasUnrestrictedNodeAccess).toBe(false);
  });

  it('scaffolds the requested role-based application shells', () => {
    expect(roleShells.map((shell) => shell.role)).toEqual(['cashier', 'storekeeper', 'manager', 'accountant']);
    expect(roleShells.flatMap((shell) => shell.items.map((item) => item.label))).toEqual([
      'POS',
      'Cart',
      'Customer selection',
      'Payment dialog',
      'Receipt',
      'Shift opening and closing',
      'Products',
      'Stock',
      'Purchases',
      'Stock counts',
      'Damaged goods',
      'Approvals',
      'Returns',
      'Expenses',
      'Cashier settlement',
      'Reports',
      'Customer debts',
      'Supplier balances',
      'Expenses',
      'Reconciliation',
      'Profit reports',
    ]);
  });
});
