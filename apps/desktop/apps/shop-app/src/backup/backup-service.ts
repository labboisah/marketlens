import type { ShopLogger } from '../logging/logger';
import type { ShopEnvironment } from '../shared/environment';

export class BackupService {
  constructor(
    private readonly environment: ShopEnvironment,
    private readonly logger: ShopLogger,
  ) {}

  async createBackup(): Promise<{ created: false; reason: string; backupPath: string }> {
    this.logger.info({ backupPath: this.environment.LOCAL_BACKUP_PATH }, 'Backup requested');

    return {
      created: false,
      reason: 'Backup workflow is not implemented yet.',
      backupPath: this.environment.LOCAL_BACKUP_PATH,
    };
  }
}
