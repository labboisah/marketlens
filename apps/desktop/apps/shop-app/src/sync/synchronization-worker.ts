import type { ShopLogger } from '../logging/logger';
import type { ShopEnvironment } from '../shared/environment';

export type SyncStatus = 'disabled' | 'idle' | 'syncing' | 'failed';

export class SynchronizationWorker {
  private statusValue: SyncStatus;

  constructor(
    private readonly environment: ShopEnvironment,
    private readonly logger: ShopLogger,
  ) {
    this.statusValue = environment.PLATFORM_SYNC_ENABLED ? 'idle' : 'disabled';
  }

  status() {
    return {
      status: this.statusValue,
      platformBaseUrl: this.environment.PLATFORM_SYNC_BASE_URL,
      intervalSeconds: this.environment.PLATFORM_SYNC_INTERVAL_SECONDS,
    };
  }

  start(): void {
    this.logger.info(this.status(), 'Synchronization worker scaffold initialized');
  }
}
