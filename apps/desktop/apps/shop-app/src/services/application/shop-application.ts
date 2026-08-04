import { openSqliteDatabase } from '../../database/sqlite';
import { runFoundationalMigrations } from '../../database/migrations';
import { DeviceRepository } from '../../repositories/device-repository';
import { AuditLogRepository } from '../../repositories/audit-log-repository';
import { UserRepository } from '../../repositories/user-repository';
import { InstallationRepository } from '../../repositories/installation-repository';
import { PlatformCredentialsRepository } from '../../repositories/platform-credentials-repository';
import { BackupService } from '../../backup/backup-service';
import { createLogger } from '../../logging/logger';
import { SynchronizationWorker } from '../../sync/synchronization-worker';
import { AuthenticationService, hashPassword } from '../domain/authentication-service';
import { DevicePairingService } from '../domain/device-pairing-service';
import { ActivationService } from '../domain/activation-service';
import { SystemHealthService } from '../domain/system-health-service';
import type { ShopEnvironment } from '../../shared/environment';

export function createShopApplication(environment: ShopEnvironment) {
  const logger = createLogger(environment);
  const database = openSqliteDatabase(environment.SHOP_SQLITE_PATH);

  runFoundationalMigrations(database.sqlite);

  const auditLogRepository = new AuditLogRepository(database.db);
  const deviceRepository = new DeviceRepository(database.db);
  const userRepository = new UserRepository(database.db);
  const installationRepository = new InstallationRepository(database.db);
  const platformCredentialsRepository = new PlatformCredentialsRepository(database.db);

  if (
    environment.SHOP_INITIAL_ADMIN_USERNAME &&
    environment.SHOP_INITIAL_ADMIN_PASSWORD &&
    userRepository.count() === 0
  ) {
    const passwordHash = hashPassword(environment.SHOP_INITIAL_ADMIN_PASSWORD);

    const adminUser = userRepository.create({
      username: environment.SHOP_INITIAL_ADMIN_USERNAME.trim().toLowerCase(),
      displayName: 'Shop Administrator',
      passwordHash,
      status: 'active',
    });

    auditLogRepository.record({
      action: 'user.setup',
      subjectType: 'user',
      subjectUuid: adminUser.uuid,
      metadata: {
        username: adminUser.username,
      },
    });
  }

  return {
    environment,
    logger,
    database,
    services: {
      activation: new ActivationService(installationRepository, platformCredentialsRepository, auditLogRepository),
      authentication: new AuthenticationService(userRepository, deviceRepository, auditLogRepository),
      devices: new DevicePairingService(deviceRepository, auditLogRepository, environment.SHOP_DEVICE_PAIRING_CODE),
      health: new SystemHealthService(environment),
      sync: new SynchronizationWorker(environment, logger),
      backup: new BackupService(environment, logger),
    },
  };
}

export type ShopApplication = ReturnType<typeof createShopApplication>;
