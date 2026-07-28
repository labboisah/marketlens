import { describe, expect, it } from 'vitest';
import { hasPermission, permissions } from '../src';

describe('permissions', () => {
  it('checks granted permissions', () => {
    expect(hasPermission([permissions.viewDashboard], permissions.viewDashboard)).toBe(true);
  });
});
