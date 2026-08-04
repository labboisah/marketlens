import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { mkdirSync, rmSync } from 'node:fs';
import { join } from 'node:path';
import { tmpdir } from 'node:os';
import { randomUUID } from 'node:crypto';
import { createShopApplication } from '../src/services/application/shop-application';
import { SynchronizationWorker } from '../src/sync/synchronization-worker';
import type { ShopEnvironment } from '../src/shared/environment';

const tempDir = join(tmpdir(), 'marketlens-shop-app-sync-tests');
const sqlitePath = join(tempDir, 'shop-test.sqlite');
const environment: ShopEnvironment = {
  SHOP_APP_ENV: 'test', SHOP_APP_LOG_LEVEL: 'debug', SHOP_LOCAL_API_HOST: '127.0.0.1', SHOP_LOCAL_API_PORT: 5011, SHOP_LOCAL_API_PUBLIC_HOST: '', SHOP_LOCAL_API_ALLOWED_ORIGINS: 'http://127.0.0.1:4091', SHOP_SOCKET_IO_PATH: '/socket.io', SHOP_DEVICE_NAME: 'Sync Test Shop', SHOP_DEVICE_PAIRING_CODE: 'test-pairing-code', SHOP_INITIAL_ADMIN_USERNAME: 'admin', SHOP_INITIAL_ADMIN_PASSWORD: 'test-password', SHOP_BRANCH_UUID: '00000000-0000-4000-8000-000000000001', SHOP_TENANT_UUID: '00000000-0000-4000-8000-000000000001', SHOP_SQLITE_PATH: sqlitePath, SHOP_TEST_SQLITE_PATH: sqlitePath, SHOP_AUTO_START_ENABLED: false, SHOP_BACKUP_RETENTION_DAYS: 14, SHOP_JWT_SECRET: 'test-secret', SHOP_JWT_ACCESS_EXPIRES_SECONDS: 60, SHOP_JWT_REFRESH_EXPIRES_DAYS: 7, PLATFORM_SYNC_ENABLED: true, PLATFORM_SYNC_BASE_URL: 'https://platform.test/api', PLATFORM_SYNC_DEVICE_TOKEN: 'device-test-token', PLATFORM_SYNC_HMAC_SECRET: 'test-sync-secret-that-is-long-enough', PLATFORM_SYNC_PROTOCOL_VERSION: '2026-08-04.v1', PLATFORM_SYNC_INTERVAL_SECONDS: 60, LOCAL_BACKUP_PATH: join(tempDir, 'backups'), LOCAL_EXPORT_PATH: join(tempDir, 'exports'),
};

describe('Shop App synchronization', () => {
  beforeEach(() => { rmSync(tempDir, { recursive: true, force: true }); mkdirSync(environment.LOCAL_BACKUP_PATH, { recursive: true }); mkdirSync(environment.LOCAL_EXPORT_PATH, { recursive: true }); });
  afterEach(() => { vi.unstubAllGlobals(); rmSync(tempDir, { recursive: true, force: true }); });

  it('creates an outbox record in the same transaction as a sale insert', async () => {
    const app = await createShopApplication({ ...environment, PLATFORM_SYNC_ENABLED: false });
    const db = app.database.sqlite; const now = new Date().toISOString(); const saleUuid = randomUUID();
    db.transaction(() => { db.prepare('INSERT INTO sales (uuid, sale_number, idempotency_key, shift_uuid, warehouse_uuid, status, subtotal, discount_amount, total_amount, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)').run(saleUuid, 'SYNC-SALE-1', randomUUID(), randomUUID(), randomUUID(), 'completed', '10', '0', '10', now); })();
    expect(db.prepare("SELECT aggregate_uuid AS aggregateUuid, status FROM sync_queue WHERE idempotency_key = ?").get(`outbox:sales:${saleUuid}`)).toEqual({ aggregateUuid: saleUuid, status: 'pending' });
  });

  it('retains failed records for exponential retry and records Platform conflicts', async () => {
    const app = await createShopApplication(environment);
    const worker = new SynchronizationWorker(environment, app.logger, app.database.sqlite);
    app.database.sqlite.prepare('INSERT INTO sync_queue (uuid, aggregate_type, aggregate_uuid, payload_json, status, idempotency_key, attempts, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)').run(randomUUID(), 'sales', randomUUID(), '{}', 'pending', 'sync-test-failure', 0, new Date().toISOString(), new Date().toISOString());
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('offline')));
    await worker.runOnce();
    expect(worker.status().status).toBe('failed');
    expect(app.database.sqlite.prepare("SELECT status FROM sync_queue WHERE idempotency_key = 'sync-test-failure'").get()).toEqual({ status: 'failed' });

    app.database.sqlite.prepare("UPDATE sync_queue SET status = 'pending' WHERE idempotency_key = 'sync-test-failure'").run();
    vi.stubGlobal('fetch', vi.fn()
      .mockResolvedValueOnce(new Response(JSON.stringify({ acknowledgements: [{ idempotencyKey: 'sync-test-failure', status: 'conflict', errorCode: 'VERSION_CONFLICT' }] }), { status: 200 }))
      .mockResolvedValueOnce(new Response(JSON.stringify({ cursor: 'next', records: [] }), { status: 200 })));
    await worker.runOnce();
    expect(app.database.sqlite.prepare("SELECT status FROM sync_queue WHERE idempotency_key = 'sync-test-failure'").get()).toEqual({ status: 'conflict' });
    expect(app.database.sqlite.prepare("SELECT rule FROM sync_conflicts WHERE queue_uuid = (SELECT uuid FROM sync_queue WHERE idempotency_key = 'sync-test-failure')").get()).toEqual({ rule: 'completed_sales_immutable' });
  });
});