import { describe, expect, it } from 'vitest';
import type { BranchScoped } from '../src';

describe('shared types', () => {
  it('supports branch-scoped records', () => {
    const record = { businessId: 'business', branchId: 'branch' } satisfies BranchScoped;

    expect(record.businessId).toBe('business');
  });
});
