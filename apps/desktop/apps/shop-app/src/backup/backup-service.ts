import { copyFileSync, existsSync, mkdirSync, readFileSync, statSync } from 'node:fs';
import { basename, join, resolve } from 'node:path';
import { createCipheriv, createHash, randomBytes, randomUUID } from 'node:crypto';
import type Database from 'better-sqlite3';
import type { ShopLogger } from '../logging/logger';
import type { ShopEnvironment } from '../shared/environment';
import type { AuditLogRepository } from '../repositories/audit-log-repository';

export type BackupType = 'automatic' | 'manual' | 'pre_migration' | 'pre_update' | 'external_drive' | 'pre_restore_safety';

export class BackupService {
  constructor(private readonly environment: ShopEnvironment, private readonly logger: ShopLogger, private readonly sqlite: Database, private readonly auditLogs: AuditLogRepository) {}

  async createBackup(type: BackupType = 'manual', destinationPath = this.environment.LOCAL_BACKUP_PATH) {
    mkdirSync(destinationPath, { recursive: true });
    this.sqlite.pragma('wal_checkpoint(TRUNCATE)');
    const uuid = randomUUID(); const now = new Date().toISOString();
    const filePath = join(resolve(destinationPath), `${type}-${now.replace(/[:.]/g, '-')}-${uuid}.sqlite`);
    copyFileSync(resolve(this.environment.SHOP_SQLITE_PATH), filePath);
    const checksum = this.checksum(filePath); const fileSize = statSync(filePath).size;
    const backup = { uuid, type, filePath, fileSize, checksum, encryptionStatus: 'none', applicationVersion: this.environment.SHOP_APP_VERSION, databaseSchemaVersion: this.schemaVersion(), verificationStatus: 'verified', createdAt: now };
    this.sqlite.prepare('INSERT INTO backups (uuid, type, file_path, file_size, checksum, encryption_status, application_version, database_schema_version, verification_status, created_at, verified_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)').run(uuid, type, filePath, fileSize, checksum, 'none', this.environment.SHOP_APP_VERSION, this.schemaVersion(), 'verified', now, now);
    if (this.environment.CLOUD_BACKUP_UPLOAD_URL && this.environment.CLOUD_BACKUP_ENCRYPTION_KEY) {
      await this.uploadEncrypted(filePath, checksum);
      this.sqlite.prepare("UPDATE backups SET encryption_status = 'cloud_aes_256_gcm' WHERE uuid = ?").run(uuid);
      backup.encryptionStatus = 'cloud_aes_256_gcm';
    }
    this.auditLogs.record({ action: 'backup.created', subjectType: 'backup', subjectUuid: uuid, metadata: { type, fileSize } });
    return backup;
  }

  verifyBackup(backupUuid: string) {
    const backup = this.sqlite.prepare('SELECT uuid, file_path AS filePath, checksum FROM backups WHERE uuid = ?').get(backupUuid) as { uuid: string; filePath: string; checksum: string } | undefined;
    if (!backup) throw new Error('Backup was not found');
    const verified = existsSync(backup.filePath) && this.checksum(backup.filePath) === backup.checksum;
    const now = new Date().toISOString();
    this.sqlite.prepare('UPDATE backups SET verification_status = ?, verified_at = ? WHERE uuid = ?').run(verified ? 'verified' : 'failed', now, backupUuid);
    this.maintenanceLog('backup_verification', verified ? 'completed' : 'failed', { backupUuid });
    return { ...backup, verified };
  }

  async prepareRestore(backupUuid: string, requestedByUserUuid: string) {
    const backup = this.verifyBackup(backupUuid);
    if (!backup.verified) throw new Error('Backup checksum verification failed');
    const metadata = this.sqlite.prepare('SELECT database_schema_version AS schemaVersion FROM backups WHERE uuid = ?').get(backupUuid) as { schemaVersion: number };
    if (metadata.schemaVersion > this.schemaVersion()) throw new Error('Backup schema is newer than this Shop App version');
    const safetyBackup = await this.createBackup('pre_restore_safety');
    const logUuid = randomUUID(); const now = new Date().toISOString();
    this.sqlite.prepare('INSERT INTO backup_restore_logs (uuid, backup_uuid, requested_by_user_uuid, status, safety_backup_uuid, created_at) VALUES (?, ?, ?, ?, ?, ?)').run(logUuid, backupUuid, requestedByUserUuid, 'prepared', safetyBackup.uuid, now);
    this.auditLogs.record({ actorUserUuid: requestedByUserUuid, action: 'backup.restore_prepared', subjectType: 'backup', subjectUuid: backupUuid, metadata: { safetyBackupUuid: safetyBackup.uuid } });
    return { restoreLogUuid: logUuid, backupPath: backup.filePath, safetyBackup };
  }

  completeRestore(restoreLogUuid: string, status: 'completed' | 'failed', detail?: string) { this.sqlite.prepare('UPDATE backup_restore_logs SET status = ?, detail = ?, completed_at = ? WHERE uuid = ?').run(status, detail ?? null, new Date().toISOString(), restoreLogUuid); }

  runMaintenance() {
    const integrity = this.integrityCheck();
    this.sqlite.pragma('wal_checkpoint(TRUNCATE)'); this.maintenanceLog('wal_checkpoint', 'completed', {});
    this.sqlite.exec('VACUUM'); this.sqlite.exec('ANALYZE'); this.maintenanceLog('database_optimization', 'completed', {});
    const expiredSessions = this.sqlite.prepare('DELETE FROM user_sessions WHERE expires_at < ? OR revoked_at IS NOT NULL').run(new Date().toISOString()).changes;
    const syncLogs = this.sqlite.prepare("DELETE FROM sync_logs WHERE created_at < datetime('now', '-90 days')").run().changes;
    const reportCache = this.sqlite.prepare("DELETE FROM report_jobs WHERE status IN ('completed', 'failed') AND completed_at < datetime('now', '-30 days')").run().changes;
    this.maintenanceLog('cleanup', 'completed', { expiredSessions, syncLogs, reportCache });
    return { integrity, expiredSessions, syncLogs, reportCache };
  }

  integrityCheck() { const result = this.sqlite.pragma('integrity_check', { simple: true }) as string; const now = new Date().toISOString(); this.sqlite.prepare('INSERT INTO database_integrity_checks (uuid, result, detail, checked_at) VALUES (?, ?, ?, ?)').run(randomUUID(), result === 'ok' ? 'ok' : 'failed', result, now); this.maintenanceLog('integrity_check', result === 'ok' ? 'completed' : 'failed', { result }); return result; }
  startScheduledBackups() { if (!this.environment.BACKUP_SCHEDULE_ENABLED) return; const interval = this.environment.BACKUP_SCHEDULE_INTERVAL_HOURS * 3_600_000; const timer = setInterval(() => void this.createBackup('automatic').catch((error) => this.logger.error({ error }, 'Scheduled backup failed')), interval); timer.unref(); }

  private schemaVersion() { return (this.sqlite.pragma('user_version', { simple: true }) as number) ?? 0; }
  private checksum(filePath: string) { return createHash('sha256').update(readFileSync(filePath)).digest('hex'); }
  private maintenanceLog(operation: string, status: string, detail: Record<string, unknown>) { this.sqlite.prepare('INSERT INTO maintenance_logs (uuid, operation, status, detail_json, created_at) VALUES (?, ?, ?, ?, ?)').run(randomUUID(), operation, status, JSON.stringify(detail), new Date().toISOString()); }
  private async uploadEncrypted(filePath: string, checksum: string) { const key = createHash('sha256').update(this.environment.CLOUD_BACKUP_ENCRYPTION_KEY).digest(); const iv = randomBytes(12); const cipher = createCipheriv('aes-256-gcm', key, iv); const encrypted = Buffer.concat([cipher.update(readFileSync(filePath)), cipher.final()]); const response = await fetch(this.environment.CLOUD_BACKUP_UPLOAD_URL, { method: 'POST', headers: { Authorization: `Bearer ${this.environment.PLATFORM_SYNC_DEVICE_TOKEN}`, 'Content-Type': 'application/octet-stream', 'X-Backup-Name': basename(filePath), 'X-Backup-Checksum': checksum, 'X-Backup-IV': iv.toString('base64'), 'X-Backup-Tag': cipher.getAuthTag().toString('base64') }, body: encrypted }); if (!response.ok) throw new Error(`Encrypted cloud backup upload failed with HTTP ${response.status}`); }
}
