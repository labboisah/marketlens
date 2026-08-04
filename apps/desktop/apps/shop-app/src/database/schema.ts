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

export const terminals = sqliteTable('terminals', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  code: text('code').notNull().unique(),
  name: text('name').notNull(),
  status: text('status').notNull(),
  createdAt: text('created_at').notNull(),
  updatedAt: text('updated_at').notNull(),
});

export const cashAccounts = sqliteTable('cash_accounts', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  code: text('code').notNull().unique(),
  name: text('name').notNull(),
  currentBalance: text('current_balance').notNull(),
  active: text('active').notNull(),
  createdAt: text('created_at').notNull(),
  updatedAt: text('updated_at').notNull(),
});

export const bankAccounts = sqliteTable('bank_accounts', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  code: text('code').notNull().unique(),
  name: text('name').notNull(),
  bankName: text('bank_name'),
  accountNumberMasked: text('account_number_masked'),
  currentBalance: text('current_balance').notNull(),
  active: text('active').notNull(),
  createdAt: text('created_at').notNull(),
  updatedAt: text('updated_at').notNull(),
});

export const cashierShifts = sqliteTable('cashier_shifts', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  terminalUuid: text('terminal_uuid').notNull(),
  cashAccountUuid: text('cash_account_uuid').notNull(),
  cashierUserUuid: text('cashier_user_uuid').notNull(),
  status: text('status').notNull(),
  openingCash: text('opening_cash').notNull(),
  expectedCash: text('expected_cash'),
  actualCash: text('actual_cash'),
  variance: text('variance'),
  openedAt: text('opened_at').notNull(),
  closedAt: text('closed_at'),
  createdAt: text('created_at').notNull(),
  updatedAt: text('updated_at').notNull(),
});

export const cashierShiftPayments = sqliteTable('cashier_shift_payments', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  shiftUuid: text('shift_uuid').notNull(),
  type: text('type').notNull(),
  amount: text('amount').notNull(),
  reference: text('reference'),
  createdAt: text('created_at').notNull(),
});

export const cashierShiftAdjustments = sqliteTable('cashier_shift_adjustments', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  shiftUuid: text('shift_uuid').notNull(),
  amount: text('amount').notNull(),
  reason: text('reason').notNull(),
  createdByUserUuid: text('created_by_user_uuid').notNull(),
  createdAt: text('created_at').notNull(),
});

export const cashierSettlements = sqliteTable('cashier_settlements', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  shiftUuid: text('shift_uuid').notNull().unique(),
  expectedCash: text('expected_cash').notNull(),
  actualCash: text('actual_cash').notNull(),
  variance: text('variance').notNull(),
  status: text('status').notNull(),
  verifiedByUserUuid: text('verified_by_user_uuid'),
  verifiedAt: text('verified_at'),
  createdAt: text('created_at').notNull(),
});

export const expenseCategories = sqliteTable('expense_categories', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  name: text('name').notNull().unique(),
  requiresApproval: text('requires_approval').notNull(),
  active: text('active').notNull(),
  createdAt: text('created_at').notNull(),
  updatedAt: text('updated_at').notNull(),
});

export const expenses = sqliteTable('expenses', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  categoryUuid: text('category_uuid').notNull(),
  cashAccountUuid: text('cash_account_uuid'),
  bankAccountUuid: text('bank_account_uuid'),
  shiftUuid: text('shift_uuid'),
  amount: text('amount').notNull(),
  paymentMethod: text('payment_method').notNull(),
  description: text('description').notNull(),
  receiptAttachmentPath: text('receipt_attachment_path'),
  status: text('status').notNull(),
  createdAt: text('created_at').notNull(),
  updatedAt: text('updated_at').notNull(),
});

export const expenseApprovals = sqliteTable('expense_approvals', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  expenseUuid: text('expense_uuid').notNull().unique(),
  approvedByUserUuid: text('approved_by_user_uuid').notNull(),
  status: text('status').notNull(),
  createdAt: text('created_at').notNull(),
});

export const cashTransactions = sqliteTable('cash_transactions', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  cashAccountUuid: text('cash_account_uuid').notNull(),
  transactionType: text('transaction_type').notNull(),
  amount: text('amount').notNull(),
  referenceType: text('reference_type'),
  referenceUuid: text('reference_uuid'),
  metadataJson: text('metadata_json'),
  createdAt: text('created_at').notNull(),
});

export const bankTransactions = sqliteTable('bank_transactions', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  bankAccountUuid: text('bank_account_uuid').notNull(),
  transactionType: text('transaction_type').notNull(),
  amount: text('amount').notNull(),
  referenceType: text('reference_type'),
  referenceUuid: text('reference_uuid'),
  metadataJson: text('metadata_json'),
  createdAt: text('created_at').notNull(),
});

export const fundTransfers = sqliteTable('fund_transfers', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  fromAccountType: text('from_account_type').notNull(),
  fromAccountUuid: text('from_account_uuid').notNull(),
  toAccountType: text('to_account_type').notNull(),
  toAccountUuid: text('to_account_uuid').notNull(),
  amount: text('amount').notNull(),
  reference: text('reference'),
  createdAt: text('created_at').notNull(),
});

export const ownerWithdrawals = sqliteTable('owner_withdrawals', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  type: text('type').notNull(),
  cashAccountUuid: text('cash_account_uuid'),
  amount: text('amount'),
  reason: text('reason').notNull(),
  status: text('status').notNull(),
  createdAt: text('created_at').notNull(),
});

export const ownerWithdrawalItems = sqliteTable('owner_withdrawal_items', {
  id: integer('id').primaryKey({ autoIncrement: true }),
  uuid: text('uuid').notNull().unique(),
  ownerWithdrawalUuid: text('owner_withdrawal_uuid').notNull(),
  productUuid: text('product_uuid'),
  quantity: text('quantity'),
  description: text('description'),
  createdAt: text('created_at').notNull(),
});

export const paymentMethods = sqliteTable('payment_methods', { id: integer('id').primaryKey({ autoIncrement: true }), uuid: text('uuid').notNull().unique(), code: text('code').notNull().unique(), name: text('name').notNull(), type: text('type').notNull(), active: text('active').notNull(), createdAt: text('created_at').notNull(), updatedAt: text('updated_at').notNull() });
export const sales = sqliteTable('sales', { id: integer('id').primaryKey({ autoIncrement: true }), uuid: text('uuid').notNull().unique(), saleNumber: text('sale_number').notNull().unique(), idempotencyKey: text('idempotency_key').notNull().unique(), shiftUuid: text('shift_uuid').notNull(), warehouseUuid: text('warehouse_uuid').notNull(), customerUuid: text('customer_uuid'), status: text('status').notNull(), subtotal: text('subtotal').notNull(), discountAmount: text('discount_amount').notNull(), totalAmount: text('total_amount').notNull(), createdAt: text('created_at').notNull() });
export const saleItems = sqliteTable('sale_items', { id: integer('id').primaryKey({ autoIncrement: true }), uuid: text('uuid').notNull().unique(), saleUuid: text('sale_uuid').notNull(), productUuid: text('product_uuid').notNull(), unitUuid: text('unit_uuid').notNull(), quantity: text('quantity').notNull(), unitPrice: text('unit_price').notNull(), lineTotal: text('line_total').notNull(), createdAt: text('created_at').notNull() });
export const salePayments = sqliteTable('sale_payments', { id: integer('id').primaryKey({ autoIncrement: true }), uuid: text('uuid').notNull().unique(), saleUuid: text('sale_uuid').notNull(), paymentMethodUuid: text('payment_method_uuid').notNull(), amount: text('amount').notNull(), reference: text('reference'), createdAt: text('created_at').notNull() });
export const saleDiscounts = sqliteTable('sale_discounts', { id: integer('id').primaryKey({ autoIncrement: true }), uuid: text('uuid').notNull().unique(), saleUuid: text('sale_uuid').notNull(), amount: text('amount').notNull(), reason: text('reason'), approvedByUserUuid: text('approved_by_user_uuid'), createdAt: text('created_at').notNull() });
export const saleCancellations = sqliteTable('sale_cancellations', { id: integer('id').primaryKey({ autoIncrement: true }), uuid: text('uuid').notNull().unique(), saleUuid: text('sale_uuid').notNull().unique(), reason: text('reason').notNull(), approvedByUserUuid: text('approved_by_user_uuid'), createdAt: text('created_at').notNull() });
export const saleReturns = sqliteTable('sale_returns', { id: integer('id').primaryKey({ autoIncrement: true }), uuid: text('uuid').notNull().unique(), saleUuid: text('sale_uuid').notNull(), status: text('status').notNull(), refundAmount: text('refund_amount').notNull(), reason: text('reason').notNull(), createdAt: text('created_at').notNull() });
export const saleReturnItems = sqliteTable('sale_return_items', { id: integer('id').primaryKey({ autoIncrement: true }), uuid: text('uuid').notNull().unique(), saleReturnUuid: text('sale_return_uuid').notNull(), saleItemUuid: text('sale_item_uuid').notNull(), quantity: text('quantity').notNull(), amount: text('amount').notNull(), createdAt: text('created_at').notNull() });
export const receipts = sqliteTable('receipts', { id: integer('id').primaryKey({ autoIncrement: true }), uuid: text('uuid').notNull().unique(), saleUuid: text('sale_uuid').notNull().unique(), format: text('format').notNull(), receiptNumber: text('receipt_number').notNull().unique(), printCount: integer('print_count').notNull(), createdAt: text('created_at').notNull() });
export const receiptReprints = sqliteTable('receipt_reprints', { id: integer('id').primaryKey({ autoIncrement: true }), uuid: text('uuid').notNull().unique(), receiptUuid: text('receipt_uuid').notNull(), reason: text('reason').notNull(), approvedByUserUuid: text('approved_by_user_uuid'), createdAt: text('created_at').notNull() });

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
