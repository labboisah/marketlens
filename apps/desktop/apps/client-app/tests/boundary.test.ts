import { describe, expect, it } from 'vitest';
import { clientAppBoundary, shellNavigationItems, startingRolePresets } from '../src';

describe('Client App boundary', () => {
  it('never opens SQLite directly', () => {
    expect(clientAppBoundary.opensSqliteDirectly).toBe(false);
    expect(clientAppBoundary.ownsBusinessDatabase).toBe(false);
    expect(clientAppBoundary.writesThroughShopApi).toBe(true);
    expect(clientAppBoundary.storesSensitiveSessionWithSafeStorage).toBe(true);
    expect(clientAppBoundary.rendererHasUnrestrictedNodeAccess).toBe(false);
  });

  it('scaffolds one permission-driven application shell with required starting roles', () => {
    expect(startingRolePresets.map((preset) => preset.value)).toEqual([
      'owner',
      'administrator',
      'manager',
      'cashier',
      'storekeeper',
      'accountant',
      'service_staff',
      'supervisor',
      'auditor',
    ]);
    expect(shellNavigationItems.map((item) => item.group)).toEqual([
      'dashboard',
      'sales',
      'products',
      'inventory',
      'purchases',
      'customers',
      'suppliers',
      'expenses',
      'services',
      'staff_earnings',
      'cashier_shifts',
      'approvals',
      'reports',
      'settings',
      'connection',
    ]);
  });
});
