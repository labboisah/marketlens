import { describe, expect, it } from 'vitest';
import { syncContractVersion, syncEnvelopeSchema, syncProtocolVersion, syncResponseSchema } from '../src';

describe('sync contract versioning', () => {
  it('starts with v1 contracts', () => {
    expect(syncContractVersion).toBe('v1');
    expect(syncProtocolVersion).toBe('1.0');
  });

  it('validates the canonical sync envelope and response', () => {
    const recordUuid = '00000000-0000-4000-8000-000000000001';
    const batchUuid = '00000000-0000-4000-8000-000000000002';
    const businessUuid = '00000000-0000-4000-8000-000000000003';
    const branchUuid = '00000000-0000-4000-8000-000000000004';
    const deviceUuid = '00000000-0000-4000-8000-000000000005';
    const now = '2026-07-28T12:00:00Z';

    expect(
      syncEnvelopeSchema.parse({
        protocol_version: '1.0',
        batch_uuid: batchUuid,
        business_uuid: businessUuid,
        branch_uuid: branchUuid,
        device_uuid: deviceUuid,
        created_at: now,
        records: [
          {
            entity: 'sale',
            record_uuid: recordUuid,
            operation: 'create',
            version: 1,
            idempotency_key: 'sale-create-1',
            occurred_at: now,
            payload: {},
          },
        ],
      }).records[0].entity,
    ).toBe('sale');

    expect(
      syncResponseSchema.parse({
        batch_uuid: batchUuid,
        status: 'partial',
        accepted: [],
        rejected: [],
        conflicts: [],
        server_time: now,
      }).status,
    ).toBe('partial');
  });
});
