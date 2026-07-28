import { randomUUID } from 'node:crypto';
import { auditLogs } from '../database/schema';
import type { ShopDatabase } from '../database/sqlite';

export interface AuditLogInput {
  actorUserUuid?: string;
  action: string;
  subjectType?: string;
  subjectUuid?: string;
  metadata?: Record<string, unknown>;
}

export class AuditLogRepository {
  constructor(private readonly db: ShopDatabase) {}

  record(input: AuditLogInput): string {
    const uuid = randomUUID();

    this.db.insert(auditLogs).values({
      uuid,
      actorUserUuid: input.actorUserUuid ?? null,
      action: input.action,
      subjectType: input.subjectType ?? null,
      subjectUuid: input.subjectUuid ?? null,
      metadataJson: input.metadata ? JSON.stringify(input.metadata) : null,
      createdAt: new Date().toISOString(),
    }).run();

    return uuid;
  }
}
