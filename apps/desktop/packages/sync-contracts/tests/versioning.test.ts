import { describe, expect, it } from 'vitest';
import { syncContractVersion } from '../src';

describe('sync contract versioning', () => {
  it('starts with v1 contracts', () => {
    expect(syncContractVersion).toBe('v1');
  });
});
