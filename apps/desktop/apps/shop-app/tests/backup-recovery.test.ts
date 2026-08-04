import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { mkdirSync, rmSync, unlinkSync } from 'node:fs';
import { join } from 'node:path';
import { tmpdir } from 'node:os';
import { createShopApplication } from '../src/services/application/shop-application';
import type { ShopEnvironment } from '../src/shared/environment';

const tempDir = join(tmpdir(), 'marketlens-shop-app-backup-tests');
const environment: ShopEnvironment = {
  SHOP_APP_ENV: 'test', SHOP_APP_LOG_LEVEL: 'debug', SHOP_LOCAL_API_HOST: '127.0.0.1', SHOP_LOCAL_API_PORT: 5012, SHOP_LOCAL_API_PUBLIC_HOST: '', SHOP_LOCAL_API_ALLOWED_ORIGINS: 'http://127.0.0.1:4091', SHOP_SOCKET_IO_PATH: '/socket.io', SHOP_DEVICE_NAME: 'Backup Test Shop', SHOP_DEVICE_PAIRING_CODE: 'test-pairing-code', SHOP_INITIAL_ADMIN_USERNAME: 'admin', SHOP_INITIAL_ADMIN_PASSWORD: 'test-password', SHOP_BRANCH_UUID: '00000000-0000-4000-8000-000000000001', SHOP_TENANT_UUID: '00000000-0000-4000-8000-000000000001', SHOP_SQLITE_PATH: join(tempDir, 'shop.sqlite'), SHOP_TEST_SQLITE_PATH: join(tempDir, 'shop.sqlite'), SHOP_AUTO_START_ENABLED: false, SHOP_BACKUP_RETENTION_DAYS: 14, SHOP_JWT_SECRET: 'test-secret', SHOP_JWT_ACCESS_EXPIRES_SECONDS: 60, SHOP_JWT_REFRESH_EXPIRES_DAYS: 7, PLATFORM_SYNC_ENABLED: false, PLATFORM_SYNC_BASE_URL: 'http://localhost:8000/api', PLATFORM_SYNC_DEVICE_TOKEN: '', PLATFORM_SYNC_HMAC_SECRET: 'test-sync-secret-that-is-long-enough', PLATFORM_SYNC_PROTOCOL_VERSION: '2026-08-04.v1', PLATFORM_SYNC_INTERVAL_SECONDS: 60, LOCAL_BACKUP_PATH: join(tempDir, 'backups'), LOCAL_EXPORT_PATH: join(tempDir, 'exports'), BACKUP_SCHEDULE_ENABLED: false, BACKUP_SCHEDULE_INTERVAL_HOURS: 24, CLOUD_BACKUP_UPLOAD_URL: '', CLOUD_BACKUP_ENCRYPTION_KEY: '',
};

describe('Shop App backup and recovery', () => {
  beforeEach(() => { rmSync(tempDir, { recursive: true, force: true }); mkdirSync(environment.LOCAL_BACKUP_PATH, { recursive: true }); mkdirSync(environment.LOCAL_EXPORT_PATH, { recursive: true }); });
  afterEach(() => rmSync(tempDir, { recursive: true, force: true }));

  it('creates a verified backup and records integrity-safe maintenance without deleting transactions', async () => {
    const app = await createShopApplication(environment);
    const backup = await app.services.backup.createBackup('manual');
    expect(backup.verificationStatus).toBe('verified');
    expect(app.services.backup.verifyBackup(backup.uuid).verified).toBe(true);
    expect(app.services.backup.integrityCheck()).toBe('ok');
    const maintenance = app.services.backup.runMaintenance();
    expect(maintenance.integrity).toBe('ok');
    expect(app.database.sqlite.prepare('SELECT COUNT(*) AS count FROM backups').get()).toEqual({ count: 1 });
  });

  it('rejects a restore candidate whose checksum no longer matches', async () => {
    const app = await createShopApplication(environment);
    const backup = await app.services.backup.createBackup('manual');
    unlinkSync(backup.filePath);
    expect(() => app.services.backup.verifyBackup(backup.uuid)).not.toThrow();
    expect(app.services.backup.verifyBackup(backup.uuid).verified).toBe(false);
  });
});