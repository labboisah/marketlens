import { createHash } from 'node:crypto';
import { readFileSync } from 'node:fs';
import type { ShopEnvironment } from '../../shared/environment';
import type { BackupService } from '../../backup/backup-service';

export type UpdatePolicy = { latestVersion: string; minimumSupportedVersion: string; mandatory: boolean; releaseNotes: string; downloadUrl: string; checksum: string; channel: 'stable' | 'beta' };

export class UpdateService {
  constructor(private readonly environment: ShopEnvironment, private readonly backup: BackupService) {}
  async check(): Promise<{ available: boolean; policy?: UpdatePolicy; currentVersion: string }> {
    if (!this.environment.SHOP_UPDATE_MANIFEST_URL) return { available: false, currentVersion: this.environment.SHOP_APP_VERSION };
    const response = await fetch(`${this.environment.SHOP_UPDATE_MANIFEST_URL}?channel=${this.environment.SHOP_UPDATE_CHANNEL}`, { headers: { Accept: 'application/json' } });
    if (!response.ok) throw new Error(`Update policy request failed with HTTP ${response.status}`);
    const policy = await response.json() as UpdatePolicy;
    return { available: this.compare(policy.latestVersion, this.environment.SHOP_APP_VERSION) > 0, policy, currentVersion: this.environment.SHOP_APP_VERSION };
  }
  async prepareInstall(policy: UpdatePolicy, installerPath: string) { if (policy.mandatory && this.compare(this.environment.SHOP_APP_VERSION, policy.minimumSupportedVersion) < 0) throw new Error('This update is mandatory before the Shop App can continue'); const checksum = createHash('sha256').update(readFileSync(installerPath)).digest('hex'); if (checksum !== policy.checksum) throw new Error('Update checksum verification failed'); return this.backup.createBackup('pre_update'); }
  private compare(left: string, right: string) { const values = (value: string) => value.replace(/^v/, '').split('.').map((part) => Number(part) || 0); const [a, b] = [values(left), values(right)]; for (let index = 0; index < Math.max(a.length, b.length); index += 1) { if ((a[index] ?? 0) !== (b[index] ?? 0)) return (a[index] ?? 0) - (b[index] ?? 0); } return 0; }
}