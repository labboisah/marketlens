import { randomUUID } from 'node:crypto';
import { loginLogs } from '../database/schema';
import type { ShopDatabase } from '../database/sqlite';
import { DrizzleRepository } from './base-repository';

export class LoginLogRepository extends DrizzleRepository {
  constructor(db: ShopDatabase) {
    super(db);
  }

  record(entry: { userUuid?: string | null; username?: string | null; success: boolean; deviceUuid?: string | null; ipAddress?: string | null; reason?: string | null }) {
    const now = new Date().toISOString();
    this.db.insert(loginLogs).values({ uuid: randomUUID(), userUuid: entry.userUuid ?? null, username: entry.username ?? null, success: entry.success ? '1' : '0', deviceUuid: entry.deviceUuid ?? null, ipAddress: entry.ipAddress ?? null, reason: entry.reason ?? null, createdAt: now }).run();
  }
}
