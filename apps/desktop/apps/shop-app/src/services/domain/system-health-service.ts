import { statfsSync } from 'node:fs';
import type Database from 'better-sqlite3';
import type { ShopEnvironment } from '../../shared/environment';
import type { SynchronizationWorker } from '../../sync/synchronization-worker';

export class SystemHealthService {
  constructor(private readonly environment: ShopEnvironment, private readonly sqlite: Database, private readonly sync: SynchronizationWorker) {}

  status() {
    const sqliteVersion = (this.sqlite.prepare('SELECT sqlite_version() AS version').get() as { version: string }).version;
    const integrity = this.sqlite.pragma('quick_check', { simple: true }) as string;
    const disk = statfsSync(this.environment.SHOP_SQLITE_PATH);
    const lastTransaction = this.sqlite.prepare("SELECT created_at AS createdAt FROM audit_logs WHERE action NOT LIKE 'user.login%' ORDER BY id DESC LIMIT 1").get() as { createdAt: string } | undefined;
    const connectedClientApps = (this.sqlite.prepare("SELECT COUNT(*) AS count FROM devices WHERE kind = 'client_app' AND status IN ('authorized', 'paired')").get() as { count: number }).count;
    const backup = this.sqlite.prepare('SELECT created_at AS createdAt, verification_status AS verificationStatus FROM backups ORDER BY id DESC LIMIT 1').get() as { createdAt: string; verificationStatus: string } | undefined;
    return {
      status: integrity === 'ok' ? 'ok' : 'degraded',
      service: 'marketlens-shop-app',
      mode: this.environment.SHOP_APP_ENV,
      activationStatus: this.environment.SHOP_ACTIVATION_STATUS,
      timestamp: new Date().toISOString(),
      sqlite: { version: sqliteVersion, integrity },
      fastify: { status: 'managed_by_local_server' },
      socketIo: { status: 'managed_by_local_server', connectedClientApps },
      platform: this.sync.status(),
      backup: backup ?? { verificationStatus: 'no_backup' },
      disk: { availableBytes: Number(disk.bavail) * Number(disk.bsize) },
      applicationVersion: this.environment.SHOP_APP_VERSION,
      databaseMigrationVersion: this.sqlite.pragma('user_version', { simple: true }),
      lastSuccessfulTransaction: lastTransaction?.createdAt ?? null,
    };
  }
}
