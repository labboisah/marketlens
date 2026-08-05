import { randomUUID } from 'node:crypto';
import argon2 from 'argon2';
import { userSessions } from '../database/schema';
import type { ShopDatabase } from '../database/sqlite';
import { DrizzleRepository } from './base-repository';

export class SessionRepository extends DrizzleRepository {
  constructor(db: ShopDatabase) {
    super(db);
  }

  create(userUuid: string, deviceUuid: string | null, accessTokenHash: string, refreshTokenHash: string, expiresAt: string) {
    const now = new Date().toISOString();
    const s = { uuid: randomUUID(), userUuid, deviceUuid, accessTokenHash, refreshTokenHash, expiresAt, createdAt: now, updatedAt: now, revokedAt: null };
    this.db.insert(userSessions).values(s).run();
    return s;
  }

  revoke(sessionUuid: string) {
    const now = new Date().toISOString();
    this.db.update(userSessions).set({ revokedAt: now, updatedAt: now }).where(userSessions.uuid.eq(sessionUuid)).run();
  }

  revokeByUser(userUuid: string) {
    const now = new Date().toISOString();
    this.db.update(userSessions).set({ revokedAt: now, updatedAt: now }).where(userSessions.userUuid.eq(userUuid)).run();
  }

  findByUuid(sessionUuid: string) {
    return this.db.select().from(userSessions).where(userSessions.uuid.eq(sessionUuid)).get();
  }

  rotateRefreshToken(sessionUuid: string, refreshTokenHash: string, expiresAt: string) {
    const now = new Date().toISOString();
    this.db
      .update(userSessions)
      .set({ refreshTokenHash, expiresAt, updatedAt: now, revokedAt: null })
      .where(userSessions.uuid.eq(sessionUuid))
      .run();
  }

  async findByRefreshToken(token: string) {
    const all = this.db.select().from(userSessions).all();
    for (const s of all) {
      try {
        const ok = await argon2.verify(s.refreshTokenHash, token);
        if (ok && !s.revokedAt) {
          return s;
        }
      } catch (err) {
        // ignore - verify failed
      }
    }

    return null;
  }
}
