import { randomUUID } from 'node:crypto';
import { syncQueue } from '../database/schema';
import type { ShopDatabase } from '../database/sqlite';

export interface SyncQueueInput {
  aggregateType: string;
  aggregateUuid: string;
  payload: Record<string, unknown>;
  idempotencyKey: string;
}

export class SyncQueueRepository {
  constructor(private readonly db: ShopDatabase) {}

  enqueue(input: SyncQueueInput): string {
    const uuid = randomUUID();
    const now = new Date().toISOString();

    this.db.insert(syncQueue).values({
      uuid,
      aggregateType: input.aggregateType,
      aggregateUuid: input.aggregateUuid,
      payloadJson: JSON.stringify(input.payload),
      status: 'pending',
      idempotencyKey: input.idempotencyKey,
      attempts: 0,
      createdAt: now,
      updatedAt: now,
    }).run();

    return uuid;
  }
}
