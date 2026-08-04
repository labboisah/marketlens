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

export const productCategories = sqliteTable('product_categories', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  name: text('name').notNull(),
  slug: text('slug'),
  parentUuid: text('parent_uuid'),
  description: text('description'),
  createdAt: text('created_at').notNull(),
  updatedAt: text('updated_at').notNull(),
});

export const brands = sqliteTable('brands', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  name: text('name').notNull(),
  description: text('description'),
  createdAt: text('created_at').notNull(),
  updatedAt: text('updated_at').notNull(),
});

export const units = sqliteTable('units', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  code: text('code').notNull().unique(),
  name: text('name').notNull(),
  isBase: text('is_base').notNull(),
  createdAt: text('created_at').notNull(),
  updatedAt: text('updated_at').notNull(),
});

export const products = sqliteTable('products', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  sku: text('sku').notNull().unique(),
  name: text('name').notNull(),
  description: text('description'),
  categoryUuid: text('category_uuid'),
  brandUuid: text('brand_uuid'),
  baseUnitUuid: text('base_unit_uuid'),
  defaultPurchaseUnitUuid: text('default_purchase_unit_uuid'),
  defaultSalesUnitUuid: text('default_sales_unit_uuid'),
  isService: text('is_service').notNull(),
  isNonStock: text('is_non_stock').notNull(),
  active: text('active').notNull(),
  minSellingPrice: text('min_selling_price'),
  reorderLevel: text('reorder_level'),
  maxStock: text('max_stock'),
  costPrice: text('cost_price'),
  retailPrice: text('retail_price'),
  wholesalePrice: text('wholesale_price'),
  imagesJson: text('images_json'),
  createdAt: text('created_at').notNull(),
  updatedAt: text('updated_at').notNull(),
});

export const productUnits = sqliteTable('product_units', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  productUuid: text('product_uuid').notNull(),
  unitUuid: text('unit_uuid').notNull(),
  toBaseMultiplier: text('to_base_multiplier').notNull(),
  createdAt: text('created_at').notNull(),
});

export const productBarcodes = sqliteTable('product_barcodes', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  productUuid: text('product_uuid').notNull(),
  barcode: text('barcode').notNull().unique(),
  createdAt: text('created_at').notNull(),
});

export const productPrices = sqliteTable('product_prices', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  productUuid: text('product_uuid').notNull(),
  priceType: text('price_type').notNull(),
  amount: text('amount').notNull(),
  currency: text('currency'),
  effectiveFrom: text('effective_from'),
  createdAt: text('created_at').notNull(),
});

export const productPriceHistories = sqliteTable('product_price_histories', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  productUuid: text('product_uuid').notNull(),
  oldAmount: text('old_amount'),
  newAmount: text('new_amount'),
  changedAt: text('changed_at').notNull(),
  changedBy: text('changed_by'),
});

export const productBranchSettings = sqliteTable('product_branch_settings', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  productUuid: text('product_uuid').notNull(),
  branchUuid: text('branch_uuid').notNull(),
  branchPriceJson: text('branch_price_json'),
  branchStockSettingsJson: text('branch_stock_settings_json'),
  createdAt: text('created_at').notNull(),
  updatedAt: text('updated_at').notNull(),
});

export const productBatches = sqliteTable('product_batches', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  productUuid: text('product_uuid').notNull(),
  batchNumber: text('batch_number').notNull(),
  expiryAt: text('expiry_at'),
  quantity: text('quantity').notNull(),
  createdAt: text('created_at').notNull(),
});

export const productSerialNumbers = sqliteTable('product_serial_numbers', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  productUuid: text('product_uuid').notNull(),
  serial: text('serial').notNull().unique(),
  batchUuid: text('batch_uuid'),
  sold: text('sold').notNull(),
  createdAt: text('created_at').notNull(),
});

export const suppliers = sqliteTable('suppliers', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  code: text('code').notNull().unique(),
  name: text('name').notNull(),
  contactName: text('contact_name'),
  contactPhone: text('contact_phone'),
  contactEmail: text('contact_email'),
  address: text('address'),
  preferred: text('preferred').notNull(),
  taxNumber: text('tax_number'),
  paymentTerms: text('payment_terms'),
  createdAt: text('created_at').notNull(),
  updatedAt: text('updated_at').notNull(),
});

export const supplierAccounts = sqliteTable('supplier_accounts', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  supplierUuid: text('supplier_uuid').notNull().unique(),
  currentBalance: text('current_balance').notNull(),
  createdAt: text('created_at').notNull(),
  updatedAt: text('updated_at').notNull(),
});

export const purchases = sqliteTable('purchases', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  supplierUuid: text('supplier_uuid').notNull(),
  invoiceNumber: text('invoice_number'),
  invoiceDate: text('invoice_date'),
  status: text('status').notNull(),
  currency: text('currency').notNull(),
  transportCost: text('transport_cost').notNull(),
  loadingCost: text('loading_cost').notNull(),
  otherLandedCost: text('other_landed_cost').notNull(),
  tax: text('tax').notNull(),
  discount: text('discount').notNull(),
  notes: text('notes'),
  totalAmount: text('total_amount').notNull(),
  amountPaid: text('amount_paid').notNull(),
  createdAt: text('created_at').notNull(),
  updatedAt: text('updated_at').notNull(),
});

export const purchaseItems = sqliteTable('purchase_items', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  purchaseUuid: text('purchase_uuid').notNull(),
  productUuid: text('product_uuid').notNull(),
  purchaseUnitUuid: text('purchase_unit_uuid').notNull(),
  quantity: text('quantity').notNull(),
  unitCost: text('unit_cost').notNull(),
  batchNumber: text('batch_number'),
  expiryDate: text('expiry_date'),
  serialNumbersJson: text('serial_numbers_json'),
  baseUnitMultiplier: text('base_unit_multiplier').notNull(),
  receivedQuantity: text('received_quantity').notNull(),
  createdAt: text('created_at').notNull(),
  updatedAt: text('updated_at').notNull(),
});

export const customers = sqliteTable('customers', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  code: text('code').notNull().unique(),
  name: text('name').notNull(),
  type: text('type').notNull(),
  phone: text('phone'),
  address: text('address'),
  status: text('status').notNull(),
  createdAt: text('created_at').notNull(),
  updatedAt: text('updated_at').notNull(),
});

export const customerContacts = sqliteTable('customer_contacts', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  customerUuid: text('customer_uuid').notNull(),
  name: text('name').notNull(),
  phone: text('phone'),
  email: text('email'),
  role: text('role'),
  createdAt: text('created_at').notNull(),
  updatedAt: text('updated_at').notNull(),
});

export const customerGuarantors = sqliteTable('customer_guarantors', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  customerUuid: text('customer_uuid').notNull(),
  name: text('name').notNull(),
  phone: text('phone'),
  address: text('address'),
  relationship: text('relationship'),
  createdAt: text('created_at').notNull(),
  updatedAt: text('updated_at').notNull(),
});

export const customerCreditLimits = sqliteTable('customer_credit_limits', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  customerUuid: text('customer_uuid').notNull().unique(),
  creditLimit: text('credit_limit').notNull(),
  additionalCreditAllowed: text('additional_credit_allowed').notNull(),
  createdAt: text('created_at').notNull(),
  updatedAt: text('updated_at').notNull(),
});

export const customerAccounts = sqliteTable('customer_accounts', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  customerUuid: text('customer_uuid').notNull().unique(),
  currentBalance: text('current_balance').notNull(),
  createdAt: text('created_at').notNull(),
  updatedAt: text('updated_at').notNull(),
});

export const customerAccountTransactions = sqliteTable('customer_account_transactions', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  customerUuid: text('customer_uuid').notNull(),
  transactionType: text('transaction_type').notNull(),
  amount: text('amount').notNull(),
  dueDate: text('due_date'),
  reference: text('reference'),
  reversalOfUuid: text('reversal_of_uuid'),
  metadataJson: text('metadata_json'),
  createdAt: text('created_at').notNull(),
});

export const customerPayments = sqliteTable('customer_payments', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  customerUuid: text('customer_uuid').notNull(),
  amount: text('amount').notNull(),
  reference: text('reference'),
  paymentMethod: text('payment_method'),
  createdAt: text('created_at').notNull(),
});

export const customerPaymentAllocations = sqliteTable('customer_payment_allocations', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  paymentUuid: text('payment_uuid').notNull(),
  accountTransactionUuid: text('account_transaction_uuid').notNull(),
  amount: text('amount').notNull(),
  createdAt: text('created_at').notNull(),
});

export const creditApprovals = sqliteTable('credit_approvals', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  customerUuid: text('customer_uuid').notNull(),
  requestedAmount: text('requested_amount').notNull(),
  approvedAmount: text('approved_amount').notNull(),
  approvedByUserUuid: text('approved_by_user_uuid').notNull(),
  reason: text('reason').notNull(),
  status: text('status').notNull(),
  createdAt: text('created_at').notNull(),
});

export const debtReminders = sqliteTable('debt_reminders', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  customerUuid: text('customer_uuid').notNull(),
  accountTransactionUuid: text('account_transaction_uuid'),
  reminderAt: text('reminder_at').notNull(),
  channel: text('channel').notNull(),
  status: text('status').notNull(),
  createdAt: text('created_at').notNull(),
});

export const debtWriteOffs = sqliteTable('debt_write_offs', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  customerUuid: text('customer_uuid').notNull(),
  accountTransactionUuid: text('account_transaction_uuid').notNull(),
  amount: text('amount').notNull(),
  approvedByUserUuid: text('approved_by_user_uuid').notNull(),
  reason: text('reason').notNull(),
  createdAt: text('created_at').notNull(),
});

export const warehouses = sqliteTable('warehouses', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  code: text('code').notNull().unique(),
  name: text('name').notNull(),
  address: text('address'),
  createdAt: text('created_at').notNull(),
  updatedAt: text('updated_at').notNull(),
});

export const stocks = sqliteTable('stocks', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  productUuid: text('product_uuid').notNull(),
  warehouseUuid: text('warehouse_uuid').notNull(),
  unitUuid: text('unit_uuid').notNull(),
  quantity: text('quantity').notNull(),
  reservedQuantity: text('reserved_quantity'),
  lastUpdatedAt: text('last_updated_at').notNull(),
  createdAt: text('created_at').notNull(),
  updatedAt: text('updated_at').notNull(),
});

export const stockMovements = sqliteTable('stock_movements', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  productUuid: text('product_uuid').notNull(),
  fromWarehouseUuid: text('from_warehouse_uuid'),
  toWarehouseUuid: text('to_warehouse_uuid'),
  quantityBase: text('quantity_base').notNull(),
  unitUuid: text('unit_uuid').notNull(),
  movementType: text('movement_type').notNull(),
  reference: text('reference'),
  metadataJson: text('metadata_json'),
  createdAt: text('created_at').notNull(),
});

export const roles = sqliteTable('roles', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  name: text('name').notNull().unique(),
  description: text('description'),
  createdAt: text('created_at').notNull(),
  updatedAt: text('updated_at').notNull(),
});

export const permissions = sqliteTable('permissions', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  key: text('key').notNull().unique(),
  description: text('description'),
  groupName: text('group_name'),
  createdAt: text('created_at').notNull(),
  updatedAt: text('updated_at').notNull(),
});

export const rolePermissions = sqliteTable('role_permissions', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  roleUuid: text('role_uuid').notNull(),
  permissionUuid: text('permission_uuid').notNull(),
  createdAt: text('created_at').notNull(),
});

export const userRoles = sqliteTable('user_roles', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  userUuid: text('user_uuid').notNull(),
  roleUuid: text('role_uuid').notNull(),
  createdAt: text('created_at').notNull(),
});

export const userBranches = sqliteTable('user_branches', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  userUuid: text('user_uuid').notNull(),
  branchUuid: text('branch_uuid').notNull(),
  createdAt: text('created_at').notNull(),
});

export const userSessions = sqliteTable('user_sessions', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  userUuid: text('user_uuid').notNull(),
  deviceUuid: text('device_uuid'),
  accessTokenHash: text('access_token_hash').notNull(),
  refreshTokenHash: text('refresh_token_hash').notNull(),
  expiresAt: text('expires_at').notNull(),
  createdAt: text('created_at').notNull(),
  updatedAt: text('updated_at').notNull(),
  revokedAt: text('revoked_at'),
});

export const loginLogs = sqliteTable('login_logs', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  userUuid: text('user_uuid'),
  username: text('username'),
  success: text('success').notNull(),
  deviceUuid: text('device_uuid'),
  ipAddress: text('ip_address'),
  reason: text('reason'),
  createdAt: text('created_at').notNull(),
});

export const clientDevices = sqliteTable('client_devices', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  name: text('name').notNull(),
  kind: text('kind').notNull(),
  status: text('status').notNull(),
  pairingCodeUuid: text('pairing_code_uuid'),
  pairedAt: text('paired_at'),
  lastSeenAt: text('last_seen_at'),
  createdAt: text('created_at').notNull(),
  updatedAt: text('updated_at').notNull(),
});

export const devicePairingCodes = sqliteTable('device_pairing_codes', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  code: text('code').notNull().unique(),
  deviceUuid: text('device_uuid'),
  status: text('status').notNull(),
  expiresAt: text('expires_at').notNull(),
  createdAt: text('created_at').notNull(),
});

export const deviceConnectionLogs = sqliteTable('device_connection_logs', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  deviceUuid: text('device_uuid').notNull(),
  connectionType: text('connection_type'),
  success: text('success').notNull(),
  detailsJson: text('details_json'),
  createdAt: text('created_at').notNull(),
});

export const passwordResetRequests = sqliteTable('password_reset_requests', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  userUuid: text('user_uuid').notNull(),
  tokenHash: text('token_hash').notNull(),
  expiresAt: text('expires_at').notNull(),
  usedAt: text('used_at'),
  createdAt: text('created_at').notNull(),
});
