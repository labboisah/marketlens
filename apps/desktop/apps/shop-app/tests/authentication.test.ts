import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { mkdirSync, rmSync } from 'node:fs';
import { join } from 'node:path';
import { tmpdir } from 'node:os';
import { createShopApplication } from '../src/services/application/shop-application';
import type { ShopEnvironment } from '../src/shared/environment';

const tempDir = join(tmpdir(), 'marketlens-shop-app-tests');
const sqlitePath = join(tempDir, 'shop-test.sqlite');
const backupPath = join(tempDir, 'backups');
const exportPath = join(tempDir, 'exports');

const testEnvironment: ShopEnvironment = {
  SHOP_APP_ENV: 'test',
  SHOP_APP_LOG_LEVEL: 'debug',
  SHOP_LOCAL_API_HOST: '127.0.0.1',
  SHOP_LOCAL_API_PORT: 5001,
  SHOP_LOCAL_API_PUBLIC_HOST: '',
  SHOP_LOCAL_API_ALLOWED_ORIGINS: 'http://127.0.0.1:4091',
  SHOP_SOCKET_IO_PATH: '/socket.io',
  SHOP_DEVICE_NAME: 'MarketLens Test Shop',
  SHOP_DEVICE_PAIRING_CODE: 'test-pairing-code',
  SHOP_INITIAL_ADMIN_USERNAME: 'admin',
  SHOP_INITIAL_ADMIN_PASSWORD: 'test-password',
  SHOP_BRANCH_UUID: '00000000-0000-4000-8000-000000000001',
  SHOP_TENANT_UUID: '00000000-0000-4000-8000-000000000001',
  SHOP_SQLITE_PATH: sqlitePath,
  SHOP_TEST_SQLITE_PATH: sqlitePath,
  SHOP_AUTO_START_ENABLED: false,
  SHOP_BACKUP_RETENTION_DAYS: 14,
  PLATFORM_SYNC_ENABLED: false,
  PLATFORM_SYNC_BASE_URL: 'http://localhost:8000/api',
  PLATFORM_SYNC_DEVICE_TOKEN: '',
  PLATFORM_SYNC_INTERVAL_SECONDS: 60,
  LOCAL_BACKUP_PATH: backupPath,
  LOCAL_EXPORT_PATH: exportPath,
};

describe('Shop App authentication and pairing', () => {
  beforeEach(() => {
    rmSync(tempDir, { recursive: true, force: true });
    mkdirSync(tempDir, { recursive: true });
    mkdirSync(backupPath, { recursive: true });
    mkdirSync(exportPath, { recursive: true });
  });

  afterEach(() => {
    rmSync(tempDir, { recursive: true, force: true });
  });

  it('creates an initial admin user and allows login without a device', () => {
    const app = await createShopApplication(testEnvironment);

    const result = app.services.authentication.login({
      username: 'admin',
      password: 'test-password',
    });

    expect(result).resolves.toEqual(
      expect.objectContaining({
        authenticated: true,
        userUuid: expect.any(String),
        sessionUuid: expect.any(String),
        permissions: expect.arrayContaining(['pos.access']),
      }),
    );
  });

  it('rejects device pairing with an invalid code and accepts the correct pairing code', async () => {
    const app = await createShopApplication(testEnvironment);

    const invalid = await app.services.devices.pair({
      deviceName: 'Client Terminal',
      pairingCode: 'wrong-code',
    });

    expect(invalid).toEqual({
      paired: false,
      reason: 'The pairing code is invalid.',
    });

    const accepted = await app.services.devices.pair({
      deviceName: 'Client Terminal',
      pairingCode: 'test-pairing-code',
    });

    expect(accepted).toEqual(
      expect.objectContaining({
        paired: true,
        deviceUuid: expect.any(String),
        reason: 'Device paired successfully.',
      }),
    );
  });

  it('allows admin login from an authorized paired device', async () => {
    const app = await createShopApplication(testEnvironment);

    const pairing = await app.services.devices.pair({
      deviceName: 'Client Terminal',
      pairingCode: 'test-pairing-code',
    });

    expect(pairing.paired).toBe(true);
    expect(pairing.deviceUuid).toBeDefined();

    const loginResult = await app.services.authentication.login({
      username: 'admin',
      password: 'test-password',
      deviceUuid: pairing.deviceUuid,
    });

    expect(loginResult.authenticated).toBe(true);
    expect(loginResult.userUuid).toBeDefined();
    expect(loginResult.sessionUuid).toBeDefined();
  });
});
