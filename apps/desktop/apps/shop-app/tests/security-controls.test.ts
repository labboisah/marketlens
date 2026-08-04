import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { mkdirSync, rmSync } from 'node:fs';
import { join } from 'node:path';
import { tmpdir } from 'node:os';
import { decryptSecret, encryptSecret } from '../src/services/security/secret-encryption';
import { createShopApplication } from '../src/services/application/shop-application';
import type { ShopEnvironment } from '../src/shared/environment';

const tempDir = join(tmpdir(), 'marketlens-shop-app-security-tests');
const environment: ShopEnvironment = {
  SHOP_APP_ENV: 'test', SHOP_APP_LOG_LEVEL: 'silent', SHOP_LOCAL_API_HOST: '127.0.0.1', SHOP_LOCAL_API_PORT: 5013, SHOP_LOCAL_API_PUBLIC_HOST: '', SHOP_LOCAL_API_ALLOWED_ORIGINS: 'http://127.0.0.1:4091', SHOP_SOCKET_IO_PATH: '/socket.io', SHOP_DEVICE_NAME: 'Security Test Shop', SHOP_DEVICE_PAIRING_CODE: 'test-pairing-code', SHOP_INITIAL_ADMIN_USERNAME: 'admin', SHOP_INITIAL_ADMIN_PASSWORD: 'test-password', SHOP_ACTIVATION_STATUS: 'active', SHOP_APP_VERSION: 'test', SHOP_BRANCH_UUID: '00000000-0000-4000-8000-000000000001', SHOP_TENANT_UUID: '00000000-0000-4000-8000-000000000001', SHOP_SQLITE_PATH: join(tempDir, 'shop.sqlite'), SHOP_TEST_SQLITE_PATH: join(tempDir, 'shop.sqlite'), SHOP_AUTO_START_ENABLED: false, SHOP_BACKUP_RETENTION_DAYS: 14, SHOP_JWT_SECRET: 'security-test-secret', SHOP_JWT_ACCESS_EXPIRES_SECONDS: 60, SHOP_JWT_REFRESH_EXPIRES_DAYS: 7, PLATFORM_SYNC_ENABLED: false, PLATFORM_SYNC_BASE_URL: 'http://localhost:8000/api', PLATFORM_SYNC_DEVICE_TOKEN: '', PLATFORM_SYNC_HMAC_SECRET: 'test-sync-secret-that-is-long-enough', PLATFORM_SYNC_PROTOCOL_VERSION: '2026-08-04.v1', PLATFORM_SYNC_INTERVAL_SECONDS: 60, LOCAL_BACKUP_PATH: join(tempDir, 'backups'), LOCAL_EXPORT_PATH: join(tempDir, 'exports'), BACKUP_SCHEDULE_ENABLED: false, BACKUP_SCHEDULE_INTERVAL_HOURS: 24, CLOUD_BACKUP_UPLOAD_URL: '', CLOUD_BACKUP_ENCRYPTION_KEY: '',
};

describe('Shop App security controls', () => {
  beforeEach(() => { rmSync(tempDir, { recursive: true, force: true }); mkdirSync(environment.LOCAL_BACKUP_PATH, { recursive: true }); mkdirSync(environment.LOCAL_EXPORT_PATH, { recursive: true }); });
  afterEach(() => rmSync(tempDir, { recursive: true, force: true }));

  it('encrypts and authenticates platform credential material', () => {
    const encrypted = encryptSecret('platform-device-credential', environment.SHOP_JWT_SECRET);
    expect(encrypted).not.toContain('platform-device-credential');
    expect(decryptSecret(encrypted, environment.SHOP_JWT_SECRET)).toBe('platform-device-credential');
    expect(() => decryptSecret(`${encrypted}tampered`, environment.SHOP_JWT_SECRET)).toThrow();
  });

  it('records failed login attempts without storing passwords', async () => {
    const app = await createShopApplication(environment);
    await app.services.authentication.login({ username: 'admin', password: 'incorrect-password' });
    const audit = app.database.sqlite.prepare("SELECT action, metadata_json AS metadataJson FROM audit_logs WHERE action = 'user.login_failed' ORDER BY id DESC LIMIT 1").get() as { action: string; metadataJson: string };
    expect(audit.action).toBe('user.login_failed');
    expect(audit.metadataJson).not.toContain('incorrect-password');
  });
});