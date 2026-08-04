import { openSqliteDatabase } from '../../database/sqlite';
import { runFoundationalMigrations } from '../../database/migrations';
import { DeviceRepository } from '../../repositories/device-repository';
import { AuditLogRepository } from '../../repositories/audit-log-repository';
import { UserRepository } from '../../repositories/user-repository';
import { InstallationRepository } from '../../repositories/installation-repository';
import { PlatformCredentialsRepository } from '../../repositories/platform-credentials-repository';
import { SessionRepository } from '../../repositories/session-repository';
import { LoginLogRepository } from '../../repositories/login-log-repository';
import { RoleRepository } from '../../repositories/role-repository';
import { PermissionRepository } from '../../repositories/permission-repository';
import { ClientDeviceRepository } from '../../repositories/client-device-repository';
import { ProductRepository } from '../../repositories/product-repository';
import { InventoryRepository } from '../../repositories/inventory-repository';
import { BackupService } from '../../backup/backup-service';
import { createLogger } from '../../logging/logger';
import { SynchronizationWorker } from '../../sync/synchronization-worker';
import { AuthenticationService, hashPassword } from '../domain/authentication-service';
import { DevicePairingService } from '../domain/device-pairing-service';
import { ActivationService } from '../domain/activation-service';
import { SystemHealthService } from '../domain/system-health-service';
import { SupplierService } from '../domain/supplier-service';
import { PurchaseService } from '../domain/purchase-service';
import { CustomerService } from '../domain/customer-service';
import { CashierService } from '../domain/cashier-service';
import { SalesService } from '../domain/sales-service';
import { ServiceRevenueService } from '../domain/service-revenue-service';
import { ShareholderOperationsService } from '../domain/shareholder-operations-service';
import { LocalReportingService } from '../domain/local-reporting-service';
import type { ShopEnvironment } from '../../shared/environment';
import { SyncQueueRepository } from '../../repositories/sync-queue-repository';

export async function createShopApplication(environment: ShopEnvironment) {
  const logger = createLogger(environment);
  const database = openSqliteDatabase(environment.SHOP_SQLITE_PATH);

  runFoundationalMigrations(database.sqlite);

  const auditLogRepository = new AuditLogRepository(database.db);
  const deviceRepository = new DeviceRepository(database.db);
  const clientDeviceRepository = new ClientDeviceRepository(database.db);
  const userRepository = new UserRepository(database.db);
  const sessionRepository = new SessionRepository(database.db);
  const loginLogRepository = new LoginLogRepository(database.db);
  const roleRepository = new RoleRepository(database.db);
  const permissionRepository = new PermissionRepository(database.db);
  const installationRepository = new InstallationRepository(database.db);
  const platformCredentialsRepository = new PlatformCredentialsRepository(database.db);
  const productRepository = new ProductRepository(database.db);
  const inventoryRepository = new InventoryRepository(database.db);
  const syncQueueRepository = new SyncQueueRepository(database.db);
  const supplierService = new SupplierService(database.db, auditLogRepository, syncQueueRepository, environment);

  if (
    environment.SHOP_INITIAL_ADMIN_USERNAME &&
    environment.SHOP_INITIAL_ADMIN_PASSWORD &&
    userRepository.count() === 0
  ) {
    const passwordHash = await hashPassword(environment.SHOP_INITIAL_ADMIN_PASSWORD);

    const adminUser = userRepository.create({
      username: environment.SHOP_INITIAL_ADMIN_USERNAME.trim().toLowerCase(),
      displayName: 'Shop Administrator',
      passwordHash,
      status: 'active',
    });

    database.sqlite.prepare('INSERT OR IGNORE INTO user_roles (uuid, user_uuid, role_uuid, created_at) VALUES (?, ?, ?, ?)')
      .run(crypto.randomUUID(), adminUser.uuid, '00000000-0000-0000-0000-000000000002', new Date().toISOString());

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
      authentication: new AuthenticationService(userRepository, deviceRepository, sessionRepository, auditLogRepository, environment),
      users: userRepository,
      devices: new DevicePairingService(deviceRepository, auditLogRepository, environment.SHOP_DEVICE_PAIRING_CODE),
      clientDevices: clientDeviceRepository,
      products: productRepository,
      inventory: inventoryRepository,
      suppliers: supplierService,
      purchases: new PurchaseService(database.db, auditLogRepository, syncQueueRepository, environment, productRepository, inventoryRepository, supplierService),
      customers: new CustomerService(database.sqlite, auditLogRepository, syncQueueRepository),
      cashier: new CashierService(database.sqlite, auditLogRepository, syncQueueRepository),
      sales: new SalesService(database.sqlite, auditLogRepository, syncQueueRepository),
      serviceRevenue: new ServiceRevenueService(database.sqlite, auditLogRepository, syncQueueRepository),
      shareholderOperations: new ShareholderOperationsService(database.sqlite, auditLogRepository, syncQueueRepository),
      reporting: new LocalReportingService(database.sqlite, environment.SHOP_BRANCH_UUID, environment.LOCAL_EXPORT_PATH),
      roles: roleRepository,
      permissions: permissionRepository,
      sessions: sessionRepository,
      loginLogs: loginLogRepository,
      health: new SystemHealthService(environment),
      sync: new SynchronizationWorker(environment, logger, database.sqlite),
      backup: new BackupService(environment, logger, database.sqlite, auditLogRepository),
    },
  };
}

export type ShopApplication = Awaited<ReturnType<typeof createShopApplication>>;
