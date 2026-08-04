import { randomUUID } from 'node:crypto';
import { users } from '../database/schema';
import type { ShopDatabase } from '../database/sqlite';
import { DrizzleRepository } from './base-repository';

export type CreateUserInput = {
  username: string;
  displayName: string;
  passwordHash: string;
  status?: 'active' | 'inactive';
};

export type UserRecord = {
  uuid: string;
  username: string;
  displayName: string;
  passwordHash: string;
  status: string;
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
      displayName: input.displayName,
      passwordHash: input.passwordHash,
      status: input.status ?? 'active',
      createdAt: now,
      updatedAt: now,
    };

    this.db.insert(users).values(user).run();
    return user;
  }

  findByUsername(username: string): UserRecord | null {
    return this.db.select().from(users).where(users.username.eq(username)).get();
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
    this.db.update(users).set({ passwordHash, updatedAt }).where(users.uuid.eq(userUuid)).run();
  }
}
