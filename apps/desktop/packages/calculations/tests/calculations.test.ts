import { describe, expect, it } from 'vitest';
import { addMinorUnits } from '../src';

describe('calculations', () => {
  it('adds integer minor money units', () => {
    expect(addMinorUnits([100, 250])).toBe(350);
  });
});
