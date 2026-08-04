import { randomUUID } from 'node:crypto';
import { appInstallation } from '../database/schema';
import type { ShopDatabase } from '../database/sqlite';
import { DrizzleRepository } from './base-repository';

export type AppInstallationInput = {
  deviceUuid: string;
  deviceName: string;
  operatingSystem: string;
  appVersion: string;
  deviceFingerprint: string;
};

export type AppInstallationRecord = {
  uuid: string;
  deviceUuid: string;
  deviceName: string;
  operatingSystem: string;
  appVersion: string;
  deviceFingerprint: string;
  installationState: string;
  activationState: string | null;
  subscriptionState: string | null;
  businessUuid: string | null;
  branchUuid: string | null;
  businessName: string | null;
  branchName: string | null;
  planFeaturesJson: string | null;
  enabledModulesJson: string | null;
  branchSettingsJson: string | null;
  syncProtocolVersion: string | null;
  createdAt: string;
  updatedAt: string;
  activatedAt: string | null;
};

export class InstallationRepository extends DrizzleRepository {
  constructor(db: ShopDatabase) {
    super(db);
  }

  create(input: AppInstallationInput): AppInstallationRecord {
    const now = new Date().toISOString();
    const record = {
      uuid: randomUUID(),
      deviceUuid: input.deviceUuid,
      deviceName: input.deviceName,
      operatingSystem: input.operatingSystem,
      appVersion: input.appVersion,
      deviceFingerprint: input.deviceFingerprint,
      installationState: 'awaiting_activation',
      activationState: 'awaiting_activation',
      subscriptionState: null,
      businessUuid: null,
      branchUuid: null,
      businessName: null,
      branchName: null,
      planFeaturesJson: null,
      enabledModulesJson: null,
      branchSettingsJson: null,
      syncProtocolVersion: null,
      createdAt: now,
      updatedAt: now,
      activatedAt: null,
    };

    this.db.insert(appInstallation).values(record).run();
    return record;
  }

  findCurrent(): AppInstallationRecord | null {
    return this.db
      .select()
      .from(appInstallation)
      .orderBy(appInstallation.createdAt, 'desc')
      .limit(1)
      .get();
  }

  findByDeviceUuid(deviceUuid: string): AppInstallationRecord | null {
    return this.db.select().from(appInstallation).where(appInstallation.deviceUuid.eq(deviceUuid)).get();
  }

  updateActivation(record: AppInstallationRecord, activation: Partial<AppInstallationRecord>): AppInstallationRecord {
    const updatedAt = new Date().toISOString();
    this.db
      .update(appInstallation)
      .set({ ...activation, updatedAt })
      .where(appInstallation.uuid.eq(record.uuid))
      .run();

    return { ...record, ...activation, updatedAt };
  }
}
