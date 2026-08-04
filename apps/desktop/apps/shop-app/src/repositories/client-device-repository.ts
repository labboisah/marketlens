import { randomUUID } from 'node:crypto';
import { clientDevices, devicePairingCodes, deviceConnectionLogs } from '../database/schema';
import type { ShopDatabase } from '../database/sqlite';
import { DrizzleRepository } from './base-repository';

export class ClientDeviceRepository extends DrizzleRepository {
  constructor(db: ShopDatabase) {
    super(db);
  }

  createDevice(name: string, kind: string) {
    const now = new Date().toISOString();
    const d = { uuid: randomUUID(), name, kind, status: 'pending', pairingCodeUuid: null, pairedAt: null, lastSeenAt: null, createdAt: now, updatedAt: now };
    this.db.insert(clientDevices).values(d).run();
    return d;
  }

  findByUuid(uuid: string) {
    return this.db.select().from(clientDevices).where(clientDevices.uuid.eq(uuid)).get();
  }

  list() {
    return this.db.select().from(clientDevices).all();
  }

  approve(uuid: string) {
    const now = new Date().toISOString();
    this.db.update(clientDevices).set({ status: 'authorized', pairedAt: now, updatedAt: now }).where(clientDevices.uuid.eq(uuid)).run();
  }

  revoke(uuid: string) {
    const now = new Date().toISOString();
    this.db.update(clientDevices).set({ status: 'revoked', updatedAt: now }).where(clientDevices.uuid.eq(uuid)).run();
  }

  createPairingCode(code: string, deviceUuid?: string, expiresAt?: string) {
    const now = new Date().toISOString();
    const d = { uuid: randomUUID(), code, deviceUuid: deviceUuid ?? null, status: 'active', expiresAt: expiresAt ?? new Date(Date.now() + 5 * 60 * 1000).toISOString(), createdAt: now };
    this.db.insert(devicePairingCodes).values(d).run();
    return d;
  }

  findPairingCode(code: string) {
    return this.db.select().from(devicePairingCodes).where(devicePairingCodes.code.eq(code)).get();
  }

  recordConnectionLog(deviceUuid: string, connectionType: string, success: boolean, details?: string) {
    const now = new Date().toISOString();
    this.db.insert(deviceConnectionLogs).values({ uuid: randomUUID(), deviceUuid, connectionType, success: success ? '1' : '0', detailsJson: details ?? null, createdAt: now }).run();
  }
}
