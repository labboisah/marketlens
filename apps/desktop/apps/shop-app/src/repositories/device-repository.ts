import { randomUUID } from 'node:crypto';
import { devices } from '../database/schema';
import type { ShopDatabase } from '../database/sqlite';
import { DrizzleRepository } from './base-repository';

export type RegisterDeviceInput = {
  name: string;
  kind: 'client_app' | 'shop_app';
};

export class DeviceRepository extends DrizzleRepository {
  constructor(db: ShopDatabase) {
    super(db);
  }

  async registerPlaceholderDevice(input: RegisterDeviceInput) {
    const now = new Date().toISOString();
    const device = {
      uuid: randomUUID(),
      name: input.name,
      kind: input.kind,
      status: 'pending',
      pairedAt: null,
      lastSeenAt: null,
      createdAt: now,
      updatedAt: now,
    };

    this.db.insert(devices).values(device).run();

    return device;
  }

  async findByUuid(deviceUuid: string) {
    return this.db.select().from(devices).where(devices.uuid.eq(deviceUuid)).get();
  }

  async approveDevice(deviceUuid: string) {
    const now = new Date().toISOString();

    this.db
      .update(devices)
      .set({ status: 'paired', pairedAt: now, updatedAt: now })
      .where(devices.uuid.eq(deviceUuid))
      .run();
  }
}
