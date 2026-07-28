import { describe, expect, it } from 'vitest';
import { uuidSchema } from '../src';

describe('validation schemas', () => {
  it('validates UUIDs', () => {
    expect(uuidSchema.safeParse('00000000-0000-4000-8000-000000000001').success).toBe(true);
  });
});
