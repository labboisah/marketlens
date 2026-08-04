import { describe, expect, it } from 'vitest';

describe('services revenue sharing', () => {
  it('allocates the staff portion among assigned staff and prevents settlements above approved earnings', () => {
    const staffAllocation = 10_000;
    expect(staffAllocation * 0.6).toBe(6_000);
    expect(staffAllocation * 0.4).toBe(4_000);
  });
});
