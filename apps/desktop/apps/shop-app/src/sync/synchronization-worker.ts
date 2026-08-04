import type { ShopLogger } from '../logging/logger';
import type { ShopEnvironment } from '../shared/environment';

export type SyncStatus = 'disabled' | 'idle' | 'syncing' | 'failed';

export class SynchronizationWorker {
  private statusValue: SyncStatus;
  private lastSuccessfulSyncAt: string | null = null;

  constructor(
    private readonly environment: ShopEnvironment,
    private readonly logger: ShopLogger,
  ) {
    this.statusValue = environment.PLATFORM_SYNC_ENABLED ? 'idle' : 'disabled';
  }

  status() {
    return {
      status: this.statusValue,
      platformConnectivity: this.environment.PLATFORM_SYNC_ENABLED ? 'enabled' : 'disabled',
      lastSynchronization: this.lastSuccessfulSyncAt,
      platformBaseUrl: this.environment.PLATFORM_SYNC_BASE_URL,
      intervalSeconds: this.environment.PLATFORM_SYNC_INTERVAL_SECONDS,
    };
  }

  start(): void {
    this.logger.info(this.status(), 'Synchronization worker scaffold initialized');
  }
}
