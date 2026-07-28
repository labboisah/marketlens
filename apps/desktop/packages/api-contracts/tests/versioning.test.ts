import { describe, expect, it } from 'vitest';
import {
  apiBasePath,
  apiErrorResponseSchema,
  apiSuccessResponseSchema,
  localApiVersion,
  moneyMinorSchema,
  quantitySchema,
} from '../src';
import { z } from 'zod';

describe('api contract versioning', () => {
  it('starts with v1 contracts', () => {
    expect(localApiVersion).toBe('v1');
    expect(apiBasePath).toBe('/api/v1');
  });

  it('validates standard response envelopes', () => {
    const successSchema = apiSuccessResponseSchema(z.object({ ok: z.boolean() }));
    expect(successSchema.parse({ success: true, message: 'Operation completed', data: { ok: true }, meta: {} }).data.ok).toBe(true);

    expect(
      apiErrorResponseSchema.parse({
        success: false,
        message: 'Validation failed',
        code: 'VALIDATION_ERROR',
        errors: {},
        trace_id: '00000000-0000-4000-8000-000000000001',
      }).code,
    ).toBe('VALIDATION_ERROR');
  });

  it('uses precise money and quantity formats', () => {
    expect(moneyMinorSchema.parse({ amount_minor: 1000, currency: 'NGN' }).amount_minor).toBe(1000);
    expect(quantitySchema.parse({ quantity: '12.500000', unit_uuid: '00000000-0000-4000-8000-000000000001' }).quantity).toBe('12.500000');
  });
});
