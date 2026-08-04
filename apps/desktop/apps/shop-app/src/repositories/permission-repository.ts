import { randomUUID } from 'node:crypto';
import { permissions } from '../database/schema';
import type { ShopDatabase } from '../database/sqlite';
import { DrizzleRepository } from './base-repository';

export class PermissionRepository extends DrizzleRepository {
  constructor(db: ShopDatabase) {
    super(db);
  }

  create(key: string, description?: string, groupName?: string) {
    const now = new Date().toISOString();
    const p = { uuid: randomUUID(), key, description: description ?? null, groupName: groupName ?? null, createdAt: now, updatedAt: now };
    this.db.insert(permissions).values(p).run();
    return p;
  }

  findByKey(key: string) {
    return this.db.select().from(permissions).where(permissions.key.eq(key)).get();
  }
}
