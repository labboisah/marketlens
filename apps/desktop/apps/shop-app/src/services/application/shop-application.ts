import { openSqliteDatabase } from '../../database/sqlite';
import { runFoundationalMigrations } from '../../database/migrations';
import { DeviceRepository } from '../../repositories/device-repository';
import { BackupService } from '../../backup/backup-service';
import { createLogger } from '../../logging/logger';
import { SynchronizationWorker } from '../../sync/synchronization-worker';
import { AuthenticationService } from '../domain/authentication-service';
import { DevicePairingService } from '../domain/device-pairing-service';
import { SystemHealthService } from '../domain/system-health-service';
import type { ShopEnvironment } from '../../shared/environment';

export function createShopApplication(environment: ShopEnvironment) {
  const logger = createLogger(environment);
  const database = openSqliteDatabase(environment.SHOP_SQLITE_PATH);

  runFoundationalMigrations(database.sqlite);

  const deviceRepository = new DeviceRepository(database.db);

  return {
    environment,
    logger,
    database,
    services: {
      authentication: new AuthenticationService(),
      devices: new DevicePairingService(deviceRepository),
      health: new SystemHealthService(environment),
      sync: new SynchronizationWorker(environment, logger),
      backup: new BackupService(environment, logger),
    },
  };
}

export type ShopApplication = ReturnType<typeof createShopApplication>;
