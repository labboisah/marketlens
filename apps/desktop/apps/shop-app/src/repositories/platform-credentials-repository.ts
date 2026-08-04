import { randomUUID } from 'node:crypto';
import { platformCredentials } from '../database/schema';
import type { ShopDatabase } from '../database/sqlite';
import { DrizzleRepository } from './base-repository';

export class PlatformCredentialsRepository extends DrizzleRepository {
  constructor(db: ShopDatabase) {
    super(db);
  }

  storeCredentials(installationUuid: string, encryptedCredentials: string): string {
    const uuid = randomUUID();
    const now = new Date().toISOString();

    this.db.insert(platformCredentials).values({
      uuid,
      installationUuid,
      encryptedCredentials,
      createdAt: now,
      updatedAt: now,
    }).run();

    return uuid;
  }
}
