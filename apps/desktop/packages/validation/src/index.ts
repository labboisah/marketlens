import { z } from 'zod';

export const uuidSchema = z.string().uuid();

export const branchScopedSchema = z.object({
  businessId: uuidSchema,
  branchId: uuidSchema,
});
