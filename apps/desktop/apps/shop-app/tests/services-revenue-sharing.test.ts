import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { randomUUID } from 'node:crypto';
import { mkdirSync, rmSync } from 'node:fs';
import { join } from 'node:path';
import { tmpdir } from 'node:os';
import { createShopApplication } from '../src/services/application/shop-application';
import type { ShopEnvironment } from '../src/shared/environment';

const tempDir = join(tmpdir(), 'marketlens-shop-app-services-tests');
const sqlitePath = join(tempDir, 'shop-test.sqlite');

const testEnvironment: ShopEnvironment = {
  SHOP_APP_ENV: 'test',
  SHOP_APP_LOG_LEVEL: 'debug',
  SHOP_LOCAL_API_HOST: '127.0.0.1',
  SHOP_LOCAL_API_PORT: 5004,
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
  LOCAL_BACKUP_PATH: join(tempDir, 'backups'),
  LOCAL_EXPORT_PATH: join(tempDir, 'exports'),
};

function enableServicesModule(sqlite: { prepare(sql: string): { run(...params: unknown[]): void } }) {
  const now = new Date().toISOString();
  sqlite.prepare(`
    INSERT INTO module_settings (
      uuid,
      module_key,
      plan_allowed,
      business_enabled,
      branch_effective_status,
      last_received_at,
      effective_value_json,
      created_at,
      updated_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
  `).run(
    randomUUID(),
    'services',
    '1',
    '1',
    'enabled',
    now,
    JSON.stringify({ enabled: true }),
    now,
    now,
  );
}

describe('services revenue sharing', () => {
  beforeEach(() => {
    rmSync(tempDir, { recursive: true, force: true });
    mkdirSync(testEnvironment.LOCAL_BACKUP_PATH, { recursive: true });
    mkdirSync(testEnvironment.LOCAL_EXPORT_PATH, { recursive: true });
  });

  afterEach(() => {
    rmSync(tempDir, { recursive: true, force: true });
  });

  it('rejects settlements above approved outstanding earnings', async () => {
    const app = await createShopApplication(testEnvironment);
    enableServicesModule(app.database.sqlite);

    const staffUserUuid = randomUUID();
    const now = new Date().toISOString();
    app.database.sqlite.prepare(`
      INSERT INTO users (uuid, username, display_name, password_hash, status, force_password_change, created_at, updated_at)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    `).run(staffUserUuid, 'svc-staff-1', 'Service Staff One', 'hash', 'active', '0', now, now);

    app.database.sqlite.prepare(`
      INSERT INTO staff_earning_accounts (uuid, staff_user_uuid, current_balance, created_at, updated_at)
      VALUES (?, ?, ?, ?, ?)
    `).run(randomUUID(), staffUserUuid, '200', now, now);

    await expect(app.services.serviceRevenue.settleStaff(staffUserUuid, '201')).rejects.toThrow(
      'Settlement exceeds approved outstanding earnings',
    );

    const account = app.database.sqlite.prepare('SELECT current_balance AS currentBalance FROM staff_earning_accounts WHERE staff_user_uuid = ?').get(staffUserUuid) as { currentBalance: string };
    expect(account.currentBalance).toBe('200');
  });
});
