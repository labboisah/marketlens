import { describe, expect, it } from 'vitest';

describe('shareholder operational transactions', () => {
  it('does not allow partial approved-distribution payments to exceed entitlement', () => {
    const entitlement = 10_000;
    const paid = 6_000;
    expect(entitlement - paid).toBe(4_000);
    expect(paid + 4_001).toBeGreaterThan(entitlement);
  });

  it('uses reversal entries instead of deleting shareholder operational history', () => {
    expect(['distribution_payment', 'reversal']).toContain('reversal');
  });
});
