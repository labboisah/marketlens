import { randomUUID } from 'node:crypto';
import { roles, rolePermissions, userRoles } from '../database/schema';
import type { ShopDatabase } from '../database/sqlite';
import { DrizzleRepository } from './base-repository';

export class RoleRepository extends DrizzleRepository {
  constructor(db: ShopDatabase) {
    super(db);
  }

  create(name: string, description?: string) {
    const now = new Date().toISOString();
    const r = { uuid: randomUUID(), name, description: description ?? null, createdAt: now, updatedAt: now };
    this.db.insert(roles).values(r).run();
    return r;
  }

  findByName(name: string) {
    return this.db.select().from(roles).where(roles.name.eq(name)).get();
  }

  assignPermission(roleUuid: string, permissionUuid: string) {
    const now = new Date().toISOString();
    this.db.insert(rolePermissions).values({ uuid: randomUUID(), roleUuid, permissionUuid, createdAt: now }).run();
  }

  assignRoleToUser(userUuid: string, roleUuid: string) {
    const now = new Date().toISOString();
    this.db.insert(userRoles).values({ uuid: randomUUID(), userUuid, roleUuid, createdAt: now }).run();
  }
}
