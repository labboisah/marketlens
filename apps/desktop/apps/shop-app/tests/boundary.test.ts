import { describe, expect, it } from 'vitest';
import { shopAppBoundary, shopModules } from '../src';

describe('Shop App boundary', () => {
  it('owns the local database and operational write API', () => {
    expect(shopAppBoundary.ownsLocalDatabase).toBe(true);
    expect(shopAppBoundary.acceptsOperationalWrites).toBe(true);
    expect(shopAppBoundary.rendererHasUnrestrictedNodeAccess).toBe(false);
    expect(shopAppBoundary.operatesWithoutInternet).toBe(true);
  });

  it('scaffolds all requested foundational modules', () => {
    expect(shopModules.map((module) => module.name)).toEqual([
      'Shop Configuration',
      'Branches',
      'Warehouses',
      'Users',
      'Roles',
      'Permissions',
      'Devices',
      'Authentication',
      'Products',
      'Units',
      'Inventory',
      'Sales',
      'Purchases',
      'Customers',
      'Suppliers',
      'Expenses',
      'Cashier Shifts',
      'Audit Logs',
      'Synchronization',
      'Backup',
      'System Health',
    ]);
  });
});
