import { randomUUID } from 'node:crypto';
import { users } from '../database/schema';
import type { ShopDatabase } from '../database/sqlite';
import { DrizzleRepository } from './base-repository';

export type CreateUserInput = {
  username: string;
  staffCode?: string;
  displayName: string;
  passwordHash: string;
  status?: 'active' | 'inactive' | 'suspended' | 'disabled';
  forcePasswordChange?: boolean;
};

export type UserRecord = {
  uuid: string;
  username: string;
  staffCode: string | null;
  displayName: string;
  passwordHash: string;
  status: string;
  forcePasswordChange: string;
  createdAt: string;
  updatedAt: string;
};

export class UserRepository extends DrizzleRepository {
  constructor(db: ShopDatabase) {
    super(db);
  }

  create(input: CreateUserInput): UserRecord {
    const now = new Date().toISOString();
    const user = {
      uuid: randomUUID(),
      username: input.username,
      staffCode: input.staffCode ?? null,
      displayName: input.displayName,
      passwordHash: input.passwordHash,
      status: input.status ?? 'active',
      forcePasswordChange: input.forcePasswordChange ? '1' : '0',
      createdAt: now,
      updatedAt: now,
    };

    this.db.insert(users).values(user).run();
    return user;
  }

  findByUsername(username: string): UserRecord | null {
    return this.db.select().from(users).where(users.username.eq(username)).get();
  }

  findByIdentity(identity: string): UserRecord | null {
    return this.db.$client
      .prepare(
        `SELECT
          uuid,
          username,
          staff_code AS staffCode,
          display_name AS displayName,
          password_hash AS passwordHash,
          status,
          force_password_change AS forcePasswordChange,
          created_at AS createdAt,
          updated_at AS updatedAt
        FROM users
        WHERE lower(username) = ? OR lower(coalesce(staff_code, '')) = ?
        LIMIT 1`,
      )
      .get(identity, identity) as UserRecord | undefined ?? null;
  }

  findByUuid(uuid: string): UserRecord | null {
    return this.db.select().from(users).where(users.uuid.eq(uuid)).get();
  }

  count(): number {
    const allUsers = this.db.select().from(users).all();
    return allUsers.length;
  }

  updatePassword(userUuid: string, passwordHash: string) {
    const updatedAt = new Date().toISOString();
    this.db.update(users).set({ passwordHash, forcePasswordChange: '0', updatedAt }).where(users.uuid.eq(userUuid)).run();
  }

  setForcePasswordChange(userUuid: string, required: boolean) {
    const updatedAt = new Date().toISOString();
    this.db.update(users).set({ forcePasswordChange: required ? '1' : '0', updatedAt }).where(users.uuid.eq(userUuid)).run();
  }
}
