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

export const users = sqliteTable('users', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  username: text('username').notNull().unique(),
  displayName: text('display_name').notNull(),
  passwordHash: text('password_hash').notNull(),
  status: text('status').notNull(),
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

export const appInstallation = sqliteTable('app_installation', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  deviceUuid: text('device_uuid').notNull().unique(),
  deviceName: text('device_name').notNull(),
  operatingSystem: text('operating_system').notNull(),
  appVersion: text('app_version').notNull(),
  deviceFingerprint: text('device_fingerprint').notNull(),
  installationState: text('installation_state').notNull(),
  activationState: text('activation_state'),
  subscriptionState: text('subscription_state'),
  businessUuid: text('business_uuid'),
  branchUuid: text('branch_uuid'),
  businessName: text('business_name'),
  branchName: text('branch_name'),
  planFeaturesJson: text('plan_features_json'),
  enabledModulesJson: text('enabled_modules_json'),
  branchSettingsJson: text('branch_settings_json'),
  syncProtocolVersion: text('sync_protocol_version'),
  createdAt: text('created_at').notNull(),
  updatedAt: text('updated_at').notNull(),
  activatedAt: text('activated_at'),
});

export const businessProfile = sqliteTable('business_profile', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  businessUuid: text('business_uuid').notNull(),
  businessName: text('business_name').notNull(),
  metadataJson: text('metadata_json'),
  createdAt: text('created_at').notNull(),
  updatedAt: text('updated_at').notNull(),
});

export const branchProfile = sqliteTable('branch_profile', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  branchUuid: text('branch_uuid').notNull(),
  branchName: text('branch_name').notNull(),
  metadataJson: text('metadata_json'),
  createdAt: text('created_at').notNull(),
  updatedAt: text('updated_at').notNull(),
});

export const platformConnection = sqliteTable('platform_connection', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  platformBaseUrl: text('platform_base_url').notNull(),
  status: text('status').notNull(),
  responseCode: integer('response_code'),
  detailsJson: text('details_json'),
  checkedAt: text('checked_at').notNull(),
  createdAt: text('created_at').notNull(),
  updatedAt: text('updated_at').notNull(),
});

export const platformCredentials = sqliteTable('platform_credentials', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  installationUuid: text('installation_uuid').notNull(),
  encryptedCredentials: text('encrypted_credentials').notNull(),
  createdAt: text('created_at').notNull(),
  updatedAt: text('updated_at').notNull(),
});

export const subscriptionSnapshot = sqliteTable('subscription_snapshot', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  businessUuid: text('business_uuid').notNull(),
  branchUuid: text('branch_uuid').notNull(),
  subscriptionState: text('subscription_state').notNull(),
  planFeaturesJson: text('plan_features_json').notNull(),
  createdAt: text('created_at').notNull(),
  updatedAt: text('updated_at').notNull(),
});

export const moduleSettings = sqliteTable('module_settings', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  moduleKey: text('module_key').notNull(),
  planAllowed: text('plan_allowed').notNull(),
  businessEnabled: text('business_enabled').notNull(),
  branchEffectiveStatus: text('branch_effective_status').notNull(),
  lastReceivedAt: text('last_received_at').notNull(),
  effectiveValueJson: text('effective_value_json'),
  createdAt: text('created_at').notNull(),
  updatedAt: text('updated_at').notNull(),
});

export const applicationSettings = sqliteTable('application_settings', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  key: text('key').notNull().unique(),
  value: text('value').notNull(),
  createdAt: text('created_at').notNull(),
  updatedAt: text('updated_at').notNull(),
});
