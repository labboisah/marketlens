import { describe, expect, it } from 'vitest';
import { localApiVersion } from '../src';

describe('api contract versioning', () => {
  it('starts with v1 contracts', () => {
    expect(localApiVersion).toBe('v1');
  });
});
