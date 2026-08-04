import { createHash, createHmac, randomUUID } from 'node:crypto';
import { gzipSync } from 'node:zlib';
import type Database from 'better-sqlite3';
import type { ShopLogger } from '../logging/logger';
import type { ShopEnvironment } from '../shared/environment';

export type SyncStatus = 'disabled' | 'idle' | 'syncing' | 'failed';
export type SyncEvent = 'sync.started' | 'sync.progress' | 'sync.completed' | 'sync.failed' | 'sync.conflict' | 'platform.status_changed' | 'module.settings_updated' | 'subscription.status_changed';
type SyncListener = (event: SyncEvent, payload: Record<string, unknown>) => void;
type QueueRecord = { uuid: string; aggregateType: string; aggregateUuid: string; payloadJson: string; idempotencyKey: string; attempts: number };
type PlatformRecord = { type: string; uuid: string; data: Record<string, unknown>; version?: number };

export class SynchronizationWorker {
  private statusValue: SyncStatus;
  private lastSuccessfulSyncAt: string | null = null;
  private lastPushAt: string | null = null;
  private lastPullAt: string | null = null;
  private running = false;
  private activeBatchUuid?: string;
  private timer?: NodeJS.Timeout;
  private readonly listeners = new Set<SyncListener>();

  constructor(private readonly environment: ShopEnvironment, private readonly logger: ShopLogger, private readonly sqlite: Database) {
    this.statusValue = environment.PLATFORM_SYNC_ENABLED ? 'idle' : 'disabled';
  }

  status() {
    const stateCounts = Object.fromEntries((this.sqlite.prepare('SELECT status, COUNT(*) AS count FROM sync_queue GROUP BY status').all() as Array<{ status: string; count: number }>).map((item) => [item.status, item.count]));
    return { status: this.statusValue, platformConnectivity: this.environment.PLATFORM_SYNC_ENABLED ? (this.statusValue === 'failed' ? 'unavailable' : 'enabled') : 'disabled', lastSynchronization: this.lastSuccessfulSyncAt, lastPush: this.lastPushAt, lastPull: this.lastPullAt, platformBaseUrl: this.environment.PLATFORM_SYNC_BASE_URL, protocolVersion: this.environment.PLATFORM_SYNC_PROTOCOL_VERSION, intervalSeconds: this.environment.PLATFORM_SYNC_INTERVAL_SECONDS, pendingRecords: stateCounts.pending ?? 0, failedRecords: stateCounts.failed ?? 0, conflictRecords: stateCounts.conflict ?? 0, deviceStatus: this.environment.PLATFORM_SYNC_DEVICE_TOKEN ? 'credentialed' : 'missing_credential' };
  }

  onEvent(listener: SyncListener): () => void { this.listeners.add(listener); return () => this.listeners.delete(listener); }
  start(): void { if (!this.environment.PLATFORM_SYNC_ENABLED || this.timer) return; this.timer = setInterval(() => void this.runOnce(), this.environment.PLATFORM_SYNC_INTERVAL_SECONDS * 1000); void this.runOnce(); }
  stop(): void { if (this.timer) clearInterval(this.timer); this.timer = undefined; }

  async runOnce(): Promise<void> {
    if (!this.environment.PLATFORM_SYNC_ENABLED || this.running) return;
    this.running = true; this.statusValue = 'syncing'; this.emit('sync.started', this.status());
    try { this.assertTransport(); await this.push(); await this.pull(); this.statusValue = 'idle'; this.lastSuccessfulSyncAt = new Date().toISOString(); this.emit('sync.completed', this.status()); }
    catch (error) { this.statusValue = 'failed'; const detail = this.redact(error instanceof Error ? error.message : 'Synchronization failed'); this.releaseActiveBatch(detail); this.log('push', 'failed', this.activeBatchUuid, { detail }); this.logger.error({ detail }, 'Synchronization run failed'); this.emit('sync.failed', { ...this.status(), detail }); }
    finally { this.running = false; }
  }

  retry(queueUuid?: string): void { if (queueUuid) this.sqlite.prepare("UPDATE sync_queue SET status = 'pending', next_attempt_at = NULL, updated_at = ? WHERE uuid = ? AND status IN ('failed', 'blocked', 'conflict')").run(new Date().toISOString(), queueUuid); else this.sqlite.prepare("UPDATE sync_queue SET status = 'pending', next_attempt_at = NULL, updated_at = ? WHERE status IN ('failed', 'blocked')").run(new Date().toISOString()); void this.runOnce(); }

  private async push(): Promise<void> {
    const batch = this.claimBatch(); if (!batch) return;
    this.activeBatchUuid = batch.uuid;
    const body = JSON.stringify({ protocolVersion: this.environment.PLATFORM_SYNC_PROTOCOL_VERSION, batchUuid: batch.uuid, records: batch.records.map((record) => ({ uuid: record.aggregateUuid, type: record.aggregateType, idempotencyKey: record.idempotencyKey, payload: this.payloadFor(record) })) });
    const response = await this.request('/sync/v1/push', 'POST', body);
    const acknowledgement = await response.json() as { acknowledgements?: Array<{ idempotencyKey: string; status: 'synced' | 'conflict' | 'failed' | 'blocked'; errorCode?: string; platformPayload?: Record<string, unknown> }> };
    const received = new Map((acknowledgement.acknowledgements ?? []).map((item) => [item.idempotencyKey, item]));
    this.sqlite.transaction(() => { for (const record of batch.records) this.applyAcknowledgement(batch.uuid, record, received.get(record.idempotencyKey)); this.sqlite.prepare("UPDATE sync_batches SET status = 'completed', acknowledged_at = ?, updated_at = ? WHERE uuid = ?").run(new Date().toISOString(), new Date().toISOString(), batch.uuid); })();
    this.lastPushAt = new Date().toISOString(); this.log('push', 'completed', batch.uuid, { recordCount: batch.records.length }); this.activeBatchUuid = undefined; this.emit('sync.progress', { ...this.status(), batchUuid: batch.uuid, direction: 'push' });
  }

  private async pull(): Promise<void> {
    const checkpoint = this.sqlite.prepare("SELECT cursor FROM sync_checkpoints WHERE direction = 'pull'").get() as { cursor: string | null } | undefined;
    const response = await this.request(`/sync/v1/pull${checkpoint?.cursor ? `?cursor=${encodeURIComponent(checkpoint.cursor)}` : ''}`, 'GET', '');
    const payload = await response.json() as { cursor?: string; records?: PlatformRecord[] };
    this.sqlite.transaction(() => { for (const record of payload.records ?? []) this.applyPlatformRecord(record); const now = new Date().toISOString(); this.sqlite.prepare("INSERT INTO sync_checkpoints (uuid, direction, cursor, last_successful_at, updated_at) VALUES (?, 'pull', ?, ?, ?) ON CONFLICT(direction) DO UPDATE SET cursor = excluded.cursor, last_successful_at = excluded.last_successful_at, updated_at = excluded.updated_at").run(randomUUID(), payload.cursor ?? checkpoint?.cursor ?? null, now, now); })();
    this.lastPullAt = new Date().toISOString(); this.log('pull', 'completed', undefined, { records: payload.records?.length ?? 0 }); this.emit('sync.progress', { ...this.status(), direction: 'pull' });
  }

  private claimBatch(): { uuid: string; records: QueueRecord[] } | undefined {
    return this.sqlite.transaction(() => { const now = new Date().toISOString(); const records = this.sqlite.prepare("SELECT uuid, aggregate_type AS aggregateType, aggregate_uuid AS aggregateUuid, payload_json AS payloadJson, idempotency_key AS idempotencyKey, attempts FROM sync_queue WHERE status IN ('pending', 'failed') AND (next_attempt_at IS NULL OR next_attempt_at <= ?) ORDER BY created_at LIMIT 100").all(now) as QueueRecord[]; if (records.length === 0) return undefined; const uuid = randomUUID(); this.sqlite.prepare('INSERT INTO sync_batches (uuid, direction, protocol_version, idempotency_key, record_count, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)').run(uuid, 'push', this.environment.PLATFORM_SYNC_PROTOCOL_VERSION, `batch:${uuid}`, records.length, 'processing', now, now); const mark = this.sqlite.prepare("UPDATE sync_queue SET status = 'processing', batch_uuid = ?, updated_at = ? WHERE uuid = ?"); const item = this.sqlite.prepare('INSERT INTO sync_batch_items (uuid, batch_uuid, queue_uuid, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)'); for (const record of records) { mark.run(uuid, now, record.uuid); item.run(randomUUID(), uuid, record.uuid, 'processing', now, now); } return { uuid, records }; })();
  }

  private applyAcknowledgement(batchUuid: string, record: QueueRecord, acknowledgement?: { status: 'synced' | 'conflict' | 'failed' | 'blocked'; errorCode?: string; platformPayload?: Record<string, unknown> }): void {
    const now = new Date().toISOString(); const status = acknowledgement?.status ?? 'failed';
    if (status === 'synced') { this.sqlite.prepare("UPDATE sync_queue SET status = 'synced', updated_at = ?, last_error_code = NULL, last_error_detail = NULL WHERE uuid = ?").run(now, record.uuid); this.sqlite.prepare("UPDATE sync_batch_items SET status = 'synced', acknowledgement_json = ?, updated_at = ? WHERE batch_uuid = ? AND queue_uuid = ?").run(JSON.stringify(acknowledgement), now, batchUuid, record.uuid); return; }
    const attempts = record.attempts + 1; const errorCode = acknowledgement?.errorCode ?? 'MISSING_ACKNOWLEDGEMENT'; const nextAttemptAt = status === 'failed' ? new Date(Date.now() + Math.min(3_600_000, 1_000 * 2 ** Math.min(attempts, 12))).toISOString() : null;
    this.sqlite.prepare('UPDATE sync_queue SET status = ?, attempts = ?, next_attempt_at = ?, last_error_code = ?, last_error_detail = ?, updated_at = ? WHERE uuid = ?').run(status, attempts, nextAttemptAt, errorCode, this.redact(errorCode), now, record.uuid);
    this.sqlite.prepare('UPDATE sync_batch_items SET status = ?, acknowledgement_json = ?, updated_at = ? WHERE batch_uuid = ? AND queue_uuid = ?').run(status, JSON.stringify(acknowledgement ?? { status, errorCode }), now, batchUuid, record.uuid);
    this.sqlite.prepare('INSERT INTO sync_failures (uuid, queue_uuid, batch_uuid, error_code, redacted_detail, retryable, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)').run(randomUUID(), record.uuid, batchUuid, errorCode, this.redact(errorCode), status === 'failed' ? '1' : '0', now);
    if (status === 'conflict') { this.sqlite.prepare('INSERT INTO sync_conflicts (uuid, queue_uuid, aggregate_type, aggregate_uuid, rule, local_payload_json, platform_payload_json, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)').run(randomUUID(), record.uuid, record.aggregateType, record.aggregateUuid, this.conflictRule(record.aggregateType), record.payloadJson, JSON.stringify(acknowledgement?.platformPayload ?? {}), 'open', now); this.emit('sync.conflict', { queueUuid: record.uuid, aggregateType: record.aggregateType, aggregateUuid: record.aggregateUuid }); }
  }

  private applyPlatformRecord(record: PlatformRecord): void {
    const now = new Date().toISOString(); const data = record.data;
    if (record.type === 'subscription') { this.sqlite.prepare("INSERT INTO subscription_snapshot (uuid, business_uuid, branch_uuid, subscription_state, plan_features_json, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?) ON CONFLICT(uuid) DO UPDATE SET subscription_state = excluded.subscription_state, plan_features_json = excluded.plan_features_json, updated_at = excluded.updated_at").run(record.uuid, String(data.businessUuid), String(data.branchUuid), String(data.subscriptionState), JSON.stringify(data.planFeatures ?? []), now, now); this.sqlite.prepare('UPDATE app_installation SET subscription_state = ?, plan_features_json = ?, updated_at = ?').run(String(data.subscriptionState), JSON.stringify(data.planFeatures ?? []), now); this.emit('subscription.status_changed', { subscriptionState: data.subscriptionState }); }
    else if (record.type === 'module_setting') { this.sqlite.prepare("INSERT INTO module_settings (uuid, module_key, plan_allowed, business_enabled, branch_effective_status, last_received_at, effective_value_json, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) ON CONFLICT(uuid) DO UPDATE SET plan_allowed = excluded.plan_allowed, business_enabled = excluded.business_enabled, branch_effective_status = excluded.branch_effective_status, last_received_at = excluded.last_received_at, effective_value_json = excluded.effective_value_json, updated_at = excluded.updated_at").run(record.uuid, String(data.moduleKey), data.planAllowed ? '1' : '0', data.businessEnabled ? '1' : '0', String(data.branchEffectiveStatus), now, JSON.stringify(data.effectiveValue ?? {}), now, now); this.emit('module.settings_updated', { moduleKey: data.moduleKey, status: data.branchEffectiveStatus }); }
    else if (record.type === 'application_settings' || record.type === 'branch_settings' || record.type === 'business_settings') this.sqlite.prepare("INSERT INTO application_settings (uuid, key, value, created_at, updated_at) VALUES (?, ?, ?, ?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = excluded.updated_at").run(record.uuid, String(data.key), JSON.stringify(data.value), now, now);
    else if (this.platformReferenceTables[record.type]) this.upsertPlatformReference(record.type, record.uuid, data, now); else return;
    this.sqlite.prepare("INSERT INTO record_versions (uuid, aggregate_type, aggregate_uuid, version, source, updated_at) VALUES (?, ?, ?, ?, 'platform', ?) ON CONFLICT(aggregate_type, aggregate_uuid) DO UPDATE SET version = excluded.version, source = 'platform', updated_at = excluded.updated_at").run(randomUUID(), record.type, record.uuid, record.version ?? 1, now);
  }

  private readonly platformReferenceTables: Record<string, true> = { platform_shareholders: true, platform_share_classes: true, platform_shareholdings: true, platform_profit_periods: true, platform_shareholder_distributions: true };
  private readonly outboxTables: Record<string, true> = { users: true, products: true, sales: true, sale_items: true, sale_payments: true, expenses: true, stocks: true, purchases: true, customer_accounts: true, customer_account_transactions: true, cashier_shifts: true, cashier_settlements: true, service_orders: true, service_staff_assignments: true, service_payments: true, service_expenses: true, service_revenue_allocations: true, staff_earning_transactions: true, staff_settlements: true, shareholder_payments: true, daily_report_summaries: true };
  private payloadFor(record: QueueRecord): Record<string, unknown> {
    if (!this.outboxTables[record.aggregateType]) return JSON.parse(record.payloadJson) as Record<string, unknown>;
    const source = this.sqlite.prepare(`SELECT * FROM ${record.aggregateType} WHERE uuid = ?`).get(record.aggregateUuid) as Record<string, unknown> | undefined;
    if (!source) return JSON.parse(record.payloadJson) as Record<string, unknown>;
    if (record.aggregateType === 'users') return { uuid: source.uuid, username: source.username, display_name: source.display_name, status: source.status, updated_at: source.updated_at };
    return source;
  }
  private upsertPlatformReference(type: string, uuid: string, data: Record<string, unknown>, now: string): void {
    const queries: Record<string, { sql: string; values: unknown[] }> = { platform_shareholders: { sql: 'INSERT INTO platform_shareholders (uuid, name, status, synced_at) VALUES (?, ?, ?, ?) ON CONFLICT(uuid) DO UPDATE SET name = excluded.name, status = excluded.status, synced_at = excluded.synced_at', values: [uuid, data.name, data.status, now] }, platform_share_classes: { sql: 'INSERT INTO platform_share_classes (uuid, name, synced_at) VALUES (?, ?, ?) ON CONFLICT(uuid) DO UPDATE SET name = excluded.name, synced_at = excluded.synced_at', values: [uuid, data.name, now] }, platform_shareholdings: { sql: 'INSERT INTO platform_shareholdings (uuid, shareholder_uuid, share_class_uuid, units, synced_at) VALUES (?, ?, ?, ?, ?) ON CONFLICT(uuid) DO UPDATE SET shareholder_uuid = excluded.shareholder_uuid, share_class_uuid = excluded.share_class_uuid, units = excluded.units, synced_at = excluded.synced_at', values: [uuid, data.shareholderUuid, data.shareClassUuid, data.units, now] }, platform_profit_periods: { sql: 'INSERT INTO platform_profit_periods (uuid, status, approved_at, synced_at) VALUES (?, ?, ?, ?) ON CONFLICT(uuid) DO UPDATE SET status = excluded.status, approved_at = excluded.approved_at, synced_at = excluded.synced_at', values: [uuid, data.status, data.approvedAt ?? null, now] }, platform_shareholder_distributions: { sql: 'INSERT INTO platform_shareholder_distributions (uuid, shareholder_uuid, profit_period_uuid, approved_entitlement, status, synced_at) VALUES (?, ?, ?, ?, ?, ?) ON CONFLICT(uuid) DO UPDATE SET approved_entitlement = excluded.approved_entitlement, status = excluded.status, synced_at = excluded.synced_at', values: [uuid, data.shareholderUuid, data.profitPeriodUuid, data.approvedEntitlement, data.status, now] } };
    const query = queries[type]; if (query) this.sqlite.prepare(query.sql).run(...query.values);
  }

  private async request(path: string, method: 'GET' | 'POST', body: string): Promise<Response> { const timestamp = new Date().toISOString(); const nonce = randomUUID(); const digest = createHash('sha256').update(body).digest('hex'); const signature = createHmac('sha256', this.environment.PLATFORM_SYNC_HMAC_SECRET).update(`${method}\n${path}\n${timestamp}\n${nonce}\n${digest}`).digest('hex'); const response = await fetch(`${this.environment.PLATFORM_SYNC_BASE_URL.replace(/\/$/, '')}${path}`, { method, headers: { Authorization: `Bearer ${this.environment.PLATFORM_SYNC_DEVICE_TOKEN}`, 'Content-Type': 'application/json', 'Content-Encoding': method === 'POST' ? 'gzip' : 'identity', 'X-MarketLens-Protocol': this.environment.PLATFORM_SYNC_PROTOCOL_VERSION, 'X-MarketLens-Timestamp': timestamp, 'X-MarketLens-Nonce': nonce, 'X-MarketLens-Signature': signature }, body: method === 'POST' ? gzipSync(Buffer.from(body)) : undefined }); if (!response.ok) throw new Error(`Platform synchronization request failed with HTTP ${response.status}`); return response; }
  private assertTransport(): void { if (!this.environment.PLATFORM_SYNC_DEVICE_TOKEN) throw new Error('Platform device credential is missing'); if (new URL(this.environment.PLATFORM_SYNC_BASE_URL).protocol !== 'https:') throw new Error('Platform synchronization requires HTTPS'); }
  private conflictRule(type: string): string { if (type === 'sales') return 'completed_sales_immutable'; if (type.includes('payment')) return 'payments_append_only'; if (type.includes('stock')) return 'stock_movement_based'; if (type.includes('customer')) return 'customer_debt_ledger_based'; if (type === 'service_revenue_allocations') return 'approved_allocations_immutable'; return 'local_operational_authoritative_unless_structurally_invalid'; }
  private releaseActiveBatch(detail: string): void { if (!this.activeBatchUuid) return; const batchUuid = this.activeBatchUuid; const now = new Date().toISOString(); const nextAttemptAt = new Date(Date.now() + 1_000).toISOString(); this.sqlite.transaction(() => { this.sqlite.prepare("UPDATE sync_queue SET status = 'failed', attempts = attempts + 1, next_attempt_at = ?, last_error_code = 'TRANSPORT_FAILURE', last_error_detail = ?, updated_at = ? WHERE batch_uuid = ? AND status = 'processing'").run(nextAttemptAt, detail, now, batchUuid); this.sqlite.prepare("UPDATE sync_batch_items SET status = 'failed', updated_at = ? WHERE batch_uuid = ? AND status = 'processing'").run(now, batchUuid); this.sqlite.prepare("UPDATE sync_batches SET status = 'failed', updated_at = ? WHERE uuid = ?").run(now, batchUuid); this.sqlite.prepare('INSERT INTO sync_failures (uuid, batch_uuid, error_code, redacted_detail, retryable, created_at) VALUES (?, ?, ?, ?, ?, ?)').run(randomUUID(), batchUuid, 'TRANSPORT_FAILURE', detail, '1', now); })(); this.activeBatchUuid = undefined; }
  private log(direction: string, event: string, batchUuid: string | undefined, detail: Record<string, unknown>): void { this.sqlite.prepare('INSERT INTO sync_logs (uuid, direction, event, batch_uuid, detail_json, created_at) VALUES (?, ?, ?, ?, ?, ?)').run(randomUUID(), direction, event, batchUuid ?? null, JSON.stringify(detail), new Date().toISOString()); }
  private redact(value: string): string { return value.replace(/(bearer\s+)[^\s]+/gi, '$1[redacted]').replace(/[A-Za-z0-9_-]{24,}/g, '[redacted]'); }
  private emit(event: SyncEvent, payload: Record<string, unknown>): void { for (const listener of this.listeners) listener(event, payload); }
}
