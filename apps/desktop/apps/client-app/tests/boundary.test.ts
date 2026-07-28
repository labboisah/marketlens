import { describe, expect, it } from 'vitest';
import { clientAppBoundary } from '../src';

describe('Client App boundary', () => {
  it('never opens SQLite directly', () => {
    expect(clientAppBoundary.opensSqliteDirectly).toBe(false);
    expect(clientAppBoundary.writesThroughShopApi).toBe(true);
    expect(clientAppBoundary.rendererHasUnrestrictedNodeAccess).toBe(false);
  });
});
