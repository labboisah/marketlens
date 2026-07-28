import { describe, expect, it } from 'vitest';
import { shopAppBoundary } from '../src';

describe('Shop App boundary', () => {
  it('owns the local database and operational write API', () => {
    expect(shopAppBoundary.ownsLocalDatabase).toBe(true);
    expect(shopAppBoundary.acceptsOperationalWrites).toBe(true);
    expect(shopAppBoundary.rendererHasUnrestrictedNodeAccess).toBe(false);
  });
});
