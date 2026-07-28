import { z } from 'zod';

export const syncContractVersion = 'v1' as const;
export const syncProtocolVersion = '1.0' as const;

export const uuidSchema = z.string().uuid();
export const isoDateTimeSchema = z.string().datetime({ offset: true });

export const syncOperationSchema = z.enum(['create', 'update', 'void', 'return', 'summary_update']);
export const syncEntitySchema = z.enum([
  'limited_user_profile',
  'sale',
  'sale_item',
  'payment',
  'purchase',
  'expense',
  'stock_summary',
  'stock_movement',
  'customer_debt_summary',
  'cashier_shift_summary',
  'alert',
]);

export const syncRecordSchema = z.object({
  entity: syncEntitySchema,
  record_uuid: uuidSchema,
  operation: syncOperationSchema,
  version: z.number().int().positive(),
  idempotency_key: z.string().min(1).max(200),
  occurred_at: isoDateTimeSchema,
  payload: z.record(z.string(), z.unknown()),
});

export const syncEnvelopeSchema = z.object({
  protocol_version: z.literal(syncProtocolVersion),
  batch_uuid: uuidSchema,
  business_uuid: uuidSchema,
  branch_uuid: uuidSchema,
  device_uuid: uuidSchema,
  created_at: isoDateTimeSchema,
  records: z.array(syncRecordSchema).min(1),
});

export const syncAcceptedRecordSchema = z.object({
  record_uuid: uuidSchema,
  entity: syncEntitySchema,
  version: z.number().int().positive(),
  idempotency_key: z.string(),
  acknowledged_at: isoDateTimeSchema,
});

export const syncRejectedRecordSchema = z.object({
  record_uuid: uuidSchema,
  entity: syncEntitySchema,
  idempotency_key: z.string(),
  code: z.string(),
  message: z.string(),
  retryable: z.boolean(),
});

export const syncConflictRecordSchema = z.object({
  record_uuid: uuidSchema,
  entity: syncEntitySchema,
  idempotency_key: z.string(),
  code: z.string(),
  message: z.string(),
  resolution: z.string(),
});

export const syncResponseSchema = z.object({
  batch_uuid: uuidSchema,
  status: z.enum(['accepted', 'partial', 'rejected', 'conflict']),
  accepted: z.array(syncAcceptedRecordSchema),
  rejected: z.array(syncRejectedRecordSchema),
  conflicts: z.array(syncConflictRecordSchema),
  server_time: isoDateTimeSchema,
});

export type SyncOperation = z.infer<typeof syncOperationSchema>;
export type SyncEntity = z.infer<typeof syncEntitySchema>;
export type SyncRecord = z.infer<typeof syncRecordSchema>;
export type SyncEnvelope = z.infer<typeof syncEnvelopeSchema>;
export type SyncAcceptedRecord = z.infer<typeof syncAcceptedRecordSchema>;
export type SyncRejectedRecord = z.infer<typeof syncRejectedRecordSchema>;
export type SyncConflictRecord = z.infer<typeof syncConflictRecordSchema>;
export type SyncResponse = z.infer<typeof syncResponseSchema>;
