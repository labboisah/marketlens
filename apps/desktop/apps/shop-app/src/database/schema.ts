import { integer, sqliteTable, text } from 'drizzle-orm/sqlite-core';

export const shopConfiguration = sqliteTable('shop_configuration', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  key: text('key').notNull().unique(),
  value: text('value').notNull(),
  createdAt: text('created_at').notNull(),
  updatedAt: text('updated_at').notNull(),
});

export const auditLogs = sqliteTable('audit_logs', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  actorUserUuid: text('actor_user_uuid'),
  action: text('action').notNull(),
  subjectType: text('subject_type'),
  subjectUuid: text('subject_uuid'),
  metadataJson: text('metadata_json'),
  createdAt: text('created_at').notNull(),
});

export const devices = sqliteTable('devices', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  name: text('name').notNull(),
  kind: text('kind').notNull(),
  status: text('status').notNull(),
  pairedAt: text('paired_at'),
  lastSeenAt: text('last_seen_at'),
  createdAt: text('created_at').notNull(),
  updatedAt: text('updated_at').notNull(),
});

export const syncQueue = sqliteTable('sync_queue', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  aggregateType: text('aggregate_type').notNull(),
  aggregateUuid: text('aggregate_uuid').notNull(),
  payloadJson: text('payload_json').notNull(),
  status: text('status').notNull(),
  idempotencyKey: text('idempotency_key').notNull().unique(),
  attempts: integer('attempts').notNull().default(0),
  createdAt: text('created_at').notNull(),
  updatedAt: text('updated_at').notNull(),
});
