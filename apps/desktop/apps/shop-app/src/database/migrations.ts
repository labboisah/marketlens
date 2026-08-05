import type { Database as BetterSqliteDatabase } from 'better-sqlite3';

export function runFoundationalMigrations(sqlite: BetterSqliteDatabase): void {
  sqlite.exec(`
    CREATE TABLE IF NOT EXISTS shop_configuration (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      key TEXT NOT NULL UNIQUE,
      value TEXT NOT NULL,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS audit_logs (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      actor_user_uuid TEXT,
      action TEXT NOT NULL,
      subject_type TEXT,
      subject_uuid TEXT,
      metadata_json TEXT,
      created_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS devices (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      name TEXT NOT NULL,
      kind TEXT NOT NULL,
      status TEXT NOT NULL,
      paired_at TEXT,
      last_seen_at TEXT,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS users (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      username TEXT NOT NULL UNIQUE,
      staff_code TEXT,
      display_name TEXT NOT NULL,
      password_hash TEXT NOT NULL,
      status TEXT NOT NULL,
      force_password_change TEXT NOT NULL DEFAULT '0',
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS sync_queue (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      aggregate_type TEXT NOT NULL,
      aggregate_uuid TEXT NOT NULL,
      payload_json TEXT NOT NULL,
      status TEXT NOT NULL,
      idempotency_key TEXT NOT NULL UNIQUE,
      attempts INTEGER NOT NULL DEFAULT 0,
      batch_uuid TEXT,
      next_attempt_at TEXT,
      last_error_code TEXT,
      last_error_detail TEXT,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS backups (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      type TEXT NOT NULL,
      file_path TEXT NOT NULL,
      file_size INTEGER NOT NULL,
      checksum TEXT NOT NULL,
      encryption_status TEXT NOT NULL,
      application_version TEXT NOT NULL,
      database_schema_version INTEGER NOT NULL,
      verification_status TEXT NOT NULL,
      created_at TEXT NOT NULL,
      verified_at TEXT
    );

    CREATE TABLE IF NOT EXISTS backup_schedules (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      type TEXT NOT NULL,
      interval_hours INTEGER NOT NULL,
      destination_path TEXT,
      enabled TEXT NOT NULL,
      last_run_at TEXT,
      next_run_at TEXT,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS backup_restore_logs (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      backup_uuid TEXT NOT NULL,
      requested_by_user_uuid TEXT NOT NULL,
      status TEXT NOT NULL,
      safety_backup_uuid TEXT,
      detail TEXT,
      created_at TEXT NOT NULL,
      completed_at TEXT
    );

    CREATE TABLE IF NOT EXISTS maintenance_logs (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      operation TEXT NOT NULL,
      status TEXT NOT NULL,
      detail_json TEXT,
      created_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS database_integrity_checks (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      result TEXT NOT NULL,
      detail TEXT,
      checked_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS sync_batches (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      direction TEXT NOT NULL,
      protocol_version TEXT NOT NULL,
      idempotency_key TEXT NOT NULL UNIQUE,
      record_count INTEGER NOT NULL,
      status TEXT NOT NULL,
      request_hash TEXT,
      acknowledged_at TEXT,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS sync_batch_items (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      batch_uuid TEXT NOT NULL,
      queue_uuid TEXT NOT NULL,
      status TEXT NOT NULL,
      acknowledgement_json TEXT,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL,
      UNIQUE(batch_uuid, queue_uuid)
    );

    CREATE TABLE IF NOT EXISTS sync_logs (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      direction TEXT NOT NULL,
      event TEXT NOT NULL,
      batch_uuid TEXT,
      detail_json TEXT,
      created_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS sync_failures (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      queue_uuid TEXT,
      batch_uuid TEXT,
      error_code TEXT NOT NULL,
      redacted_detail TEXT,
      retryable TEXT NOT NULL,
      created_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS sync_conflicts (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      queue_uuid TEXT,
      aggregate_type TEXT NOT NULL,
      aggregate_uuid TEXT NOT NULL,
      rule TEXT NOT NULL,
      local_payload_json TEXT,
      platform_payload_json TEXT,
      status TEXT NOT NULL,
      created_at TEXT NOT NULL,
      resolved_at TEXT
    );

    CREATE TABLE IF NOT EXISTS sync_checkpoints (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      direction TEXT NOT NULL UNIQUE,
      cursor TEXT,
      last_successful_at TEXT,
      updated_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS record_versions (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      aggregate_type TEXT NOT NULL,
      aggregate_uuid TEXT NOT NULL,
      version INTEGER NOT NULL,
      source TEXT NOT NULL,
      updated_at TEXT NOT NULL,
      UNIQUE(aggregate_type, aggregate_uuid)
    );

    CREATE TABLE IF NOT EXISTS platform_pull_checkpoints (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      resource_type TEXT NOT NULL UNIQUE,
      cursor TEXT,
      last_pulled_at TEXT,
      updated_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS app_installation (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      device_uuid TEXT NOT NULL UNIQUE,
      device_name TEXT NOT NULL,
      operating_system TEXT NOT NULL,
      app_version TEXT NOT NULL,
      device_fingerprint TEXT NOT NULL,
      installation_state TEXT NOT NULL,
      activation_state TEXT,
      subscription_state TEXT,
      business_uuid TEXT,
      branch_uuid TEXT,
      business_name TEXT,
      branch_name TEXT,
      plan_features_json TEXT,
      enabled_modules_json TEXT,
      branch_settings_json TEXT,
      sync_protocol_version TEXT,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL,
      activated_at TEXT
    );

    CREATE TABLE IF NOT EXISTS business_profile (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      business_uuid TEXT NOT NULL,
      business_name TEXT NOT NULL,
      metadata_json TEXT,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS branch_profile (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      branch_uuid TEXT NOT NULL,
      branch_name TEXT NOT NULL,
      metadata_json TEXT,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS platform_connection (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      platform_base_url TEXT NOT NULL,
      status TEXT NOT NULL,
      response_code INTEGER,
      details_json TEXT,
      checked_at TEXT NOT NULL,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS platform_credentials (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      installation_uuid TEXT NOT NULL,
      encrypted_credentials TEXT NOT NULL,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS subscription_snapshot (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      business_uuid TEXT NOT NULL,
      branch_uuid TEXT NOT NULL,
      subscription_state TEXT NOT NULL,
      plan_features_json TEXT NOT NULL,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS module_settings (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      module_key TEXT NOT NULL,
      plan_allowed TEXT NOT NULL,
      business_enabled TEXT NOT NULL,
      branch_effective_status TEXT NOT NULL,
      last_received_at TEXT NOT NULL,
      effective_value_json TEXT,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS application_settings (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      key TEXT NOT NULL UNIQUE,
      value TEXT NOT NULL,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS product_categories (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      name TEXT NOT NULL,
      slug TEXT,
      parent_uuid TEXT,
      description TEXT,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS brands (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      name TEXT NOT NULL,
      description TEXT,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS units (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      code TEXT NOT NULL UNIQUE,
      name TEXT NOT NULL,
      is_base TEXT NOT NULL,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS products (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      sku TEXT NOT NULL UNIQUE,
      name TEXT NOT NULL,
      description TEXT,
      category_uuid TEXT,
      brand_uuid TEXT,
      base_unit_uuid TEXT,
      default_purchase_unit_uuid TEXT,
      default_sales_unit_uuid TEXT,
      is_service TEXT NOT NULL,
      is_non_stock TEXT NOT NULL,
      active TEXT NOT NULL,
      min_selling_price TEXT,
      reorder_level TEXT,
      max_stock TEXT,
      cost_price TEXT,
      retail_price TEXT,
      wholesale_price TEXT,
      images_json TEXT,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS product_units (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      product_uuid TEXT NOT NULL,
      unit_uuid TEXT NOT NULL,
      to_base_multiplier TEXT NOT NULL,
      created_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS product_barcodes (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      product_uuid TEXT NOT NULL,
      barcode TEXT NOT NULL UNIQUE,
      created_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS product_prices (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      product_uuid TEXT NOT NULL,
      price_type TEXT NOT NULL,
      amount TEXT NOT NULL,
      currency TEXT,
      effective_from TEXT,
      created_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS product_price_histories (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      product_uuid TEXT NOT NULL,
      old_amount TEXT,
      new_amount TEXT,
      changed_at TEXT NOT NULL,
      changed_by TEXT
    );

    CREATE TABLE IF NOT EXISTS product_branch_settings (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      product_uuid TEXT NOT NULL,
      branch_uuid TEXT NOT NULL,
      branch_price_json TEXT,
      branch_stock_settings_json TEXT,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS product_batches (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      product_uuid TEXT NOT NULL,
      batch_number TEXT NOT NULL,
      expiry_at TEXT,
      quantity TEXT NOT NULL,
      created_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS product_serial_numbers (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      product_uuid TEXT NOT NULL,
      serial TEXT NOT NULL UNIQUE,
      batch_uuid TEXT,
      sold TEXT NOT NULL,
      created_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS suppliers (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      code TEXT NOT NULL UNIQUE,
      name TEXT NOT NULL,
      contact_name TEXT,
      contact_phone TEXT,
      contact_email TEXT,
      address TEXT,
      preferred TEXT NOT NULL,
      tax_number TEXT,
      payment_terms TEXT,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS supplier_accounts (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      supplier_uuid TEXT NOT NULL UNIQUE,
      current_balance TEXT NOT NULL,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS purchases (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      supplier_uuid TEXT NOT NULL,
      invoice_number TEXT,
      invoice_date TEXT,
      status TEXT NOT NULL,
      currency TEXT NOT NULL,
      transport_cost TEXT NOT NULL,
      loading_cost TEXT NOT NULL,
      other_landed_cost TEXT NOT NULL,
      tax TEXT NOT NULL,
      discount TEXT NOT NULL,
      notes TEXT,
      total_amount TEXT NOT NULL,
      amount_paid TEXT NOT NULL,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS purchase_items (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      purchase_uuid TEXT NOT NULL,
      product_uuid TEXT NOT NULL,
      purchase_unit_uuid TEXT NOT NULL,
      quantity TEXT NOT NULL,
      unit_cost TEXT NOT NULL,
      batch_number TEXT,
      expiry_date TEXT,
      serial_numbers_json TEXT,
      base_unit_multiplier TEXT NOT NULL,
      received_quantity TEXT NOT NULL,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS purchase_payments (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      purchase_uuid TEXT NOT NULL,
      supplier_uuid TEXT NOT NULL,
      amount TEXT NOT NULL,
      reference TEXT,
      payment_method TEXT,
      created_at TEXT NOT NULL
    );

    CREATE INDEX IF NOT EXISTS idx_purchase_payments_purchase_date
      ON purchase_payments (purchase_uuid, created_at);

    CREATE TABLE IF NOT EXISTS purchase_returns (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      purchase_uuid TEXT NOT NULL,
      supplier_uuid TEXT NOT NULL,
      warehouse_uuid TEXT NOT NULL,
      product_uuid TEXT NOT NULL,
      unit_uuid TEXT NOT NULL,
      quantity TEXT NOT NULL,
      unit_cost TEXT NOT NULL,
      amount TEXT NOT NULL,
      reason TEXT,
      note TEXT,
      created_at TEXT NOT NULL
    );

    CREATE INDEX IF NOT EXISTS idx_purchase_returns_purchase_date
      ON purchase_returns (purchase_uuid, created_at);

    CREATE TABLE IF NOT EXISTS customers (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      code TEXT NOT NULL UNIQUE,
      name TEXT NOT NULL,
      type TEXT NOT NULL,
      phone TEXT,
      address TEXT,
      status TEXT NOT NULL,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS customer_contacts (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      customer_uuid TEXT NOT NULL,
      name TEXT NOT NULL,
      phone TEXT,
      email TEXT,
      role TEXT,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS customer_guarantors (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      customer_uuid TEXT NOT NULL,
      name TEXT NOT NULL,
      phone TEXT,
      address TEXT,
      relationship TEXT,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS customer_credit_limits (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      customer_uuid TEXT NOT NULL UNIQUE,
      credit_limit TEXT NOT NULL,
      additional_credit_allowed TEXT NOT NULL,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS customer_accounts (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      customer_uuid TEXT NOT NULL UNIQUE,
      current_balance TEXT NOT NULL,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS customer_account_transactions (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      customer_uuid TEXT NOT NULL,
      transaction_type TEXT NOT NULL,
      amount TEXT NOT NULL,
      due_date TEXT,
      reference TEXT,
      reversal_of_uuid TEXT,
      metadata_json TEXT,
      created_at TEXT NOT NULL
    );
    CREATE INDEX IF NOT EXISTS idx_customer_account_transactions_customer_date
      ON customer_account_transactions (customer_uuid, created_at);

    CREATE TABLE IF NOT EXISTS customer_payments (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      customer_uuid TEXT NOT NULL,
      amount TEXT NOT NULL,
      reference TEXT,
      payment_method TEXT,
      created_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS customer_payment_allocations (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      payment_uuid TEXT NOT NULL,
      account_transaction_uuid TEXT NOT NULL,
      amount TEXT NOT NULL,
      created_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS credit_approvals (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      customer_uuid TEXT NOT NULL,
      requested_amount TEXT NOT NULL,
      approved_amount TEXT NOT NULL,
      approved_by_user_uuid TEXT NOT NULL,
      reason TEXT NOT NULL,
      status TEXT NOT NULL,
      created_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS debt_reminders (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      customer_uuid TEXT NOT NULL,
      account_transaction_uuid TEXT,
      reminder_at TEXT NOT NULL,
      channel TEXT NOT NULL,
      status TEXT NOT NULL,
      created_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS debt_write_offs (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      customer_uuid TEXT NOT NULL,
      account_transaction_uuid TEXT NOT NULL,
      amount TEXT NOT NULL,
      approved_by_user_uuid TEXT NOT NULL,
      reason TEXT NOT NULL,
      created_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS terminals (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      code TEXT NOT NULL UNIQUE,
      name TEXT NOT NULL,
      status TEXT NOT NULL,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS cash_accounts (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      code TEXT NOT NULL UNIQUE,
      name TEXT NOT NULL,
      current_balance TEXT NOT NULL,
      active TEXT NOT NULL,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS bank_accounts (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      code TEXT NOT NULL UNIQUE,
      name TEXT NOT NULL,
      bank_name TEXT,
      account_number_masked TEXT,
      current_balance TEXT NOT NULL,
      active TEXT NOT NULL,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS cashier_shifts (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      terminal_uuid TEXT NOT NULL,
      cash_account_uuid TEXT NOT NULL,
      cashier_user_uuid TEXT NOT NULL,
      status TEXT NOT NULL,
      opening_cash TEXT NOT NULL,
      notes TEXT,
      expected_cash TEXT,
      actual_cash TEXT,
      variance TEXT,
      opened_at TEXT NOT NULL,
      closed_at TEXT,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS cashier_shift_payments (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      shift_uuid TEXT NOT NULL,
      type TEXT NOT NULL,
      amount TEXT NOT NULL,
      reference TEXT,
      created_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS cashier_shift_adjustments (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      shift_uuid TEXT NOT NULL,
      amount TEXT NOT NULL,
      reason TEXT NOT NULL,
      created_by_user_uuid TEXT NOT NULL,
      created_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS cashier_settlements (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      shift_uuid TEXT NOT NULL UNIQUE,
      expected_cash TEXT NOT NULL,
      actual_cash TEXT NOT NULL,
      variance TEXT NOT NULL,
      status TEXT NOT NULL,
      notes TEXT,
      cash_denominations_json TEXT,
      supporting_explanation TEXT,
      verified_by_user_uuid TEXT,
      verified_at TEXT,
      created_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS expense_categories (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      name TEXT NOT NULL UNIQUE,
      requires_approval TEXT NOT NULL,
      active TEXT NOT NULL,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS expenses (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      category_uuid TEXT NOT NULL,
      approval_requested TEXT,
      created_by_user_uuid TEXT,
      created_by_name TEXT,
      approved_by_user_uuid TEXT,
      reversed_by_user_uuid TEXT,
      reversed_at TEXT,
      cash_account_uuid TEXT,
      bank_account_uuid TEXT,
      shift_uuid TEXT,
      amount TEXT NOT NULL,
      payment_method TEXT NOT NULL,
      description TEXT NOT NULL,
      receipt_attachment_path TEXT,
      status TEXT NOT NULL,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS expense_reversal_requests (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      expense_uuid TEXT NOT NULL,
      requested_by_user_uuid TEXT,
      approved_by_user_uuid TEXT,
      rejected_by_user_uuid TEXT,
      reason TEXT NOT NULL,
      rejection_reason TEXT,
      status TEXT NOT NULL,
      created_at TEXT NOT NULL,
      processed_at TEXT
    );

    CREATE INDEX IF NOT EXISTS idx_expense_reversal_requests_expense
      ON expense_reversal_requests (expense_uuid, created_at);

    CREATE TABLE IF NOT EXISTS expense_approvals (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      expense_uuid TEXT NOT NULL UNIQUE,
      approved_by_user_uuid TEXT NOT NULL,
      status TEXT NOT NULL,
      created_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS cash_transactions (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      cash_account_uuid TEXT NOT NULL,
      transaction_type TEXT NOT NULL,
      amount TEXT NOT NULL,
      reference_type TEXT,
      reference_uuid TEXT,
      metadata_json TEXT,
      created_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS bank_transactions (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      bank_account_uuid TEXT NOT NULL,
      transaction_type TEXT NOT NULL,
      amount TEXT NOT NULL,
      reference_type TEXT,
      reference_uuid TEXT,
      metadata_json TEXT,
      created_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS fund_transfers (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      from_account_type TEXT NOT NULL,
      from_account_uuid TEXT NOT NULL,
      to_account_type TEXT NOT NULL,
      to_account_uuid TEXT NOT NULL,
      amount TEXT NOT NULL,
      reference TEXT,
      created_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS owner_withdrawals (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      type TEXT NOT NULL,
      cash_account_uuid TEXT,
      amount TEXT,
      reason TEXT NOT NULL,
      status TEXT NOT NULL,
      created_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS owner_withdrawal_items (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      owner_withdrawal_uuid TEXT NOT NULL,
      product_uuid TEXT,
      quantity TEXT,
      description TEXT,
      created_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS payment_methods (id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT NOT NULL UNIQUE, code TEXT NOT NULL UNIQUE, name TEXT NOT NULL, type TEXT NOT NULL, active TEXT NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS sales (id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT NOT NULL UNIQUE, sale_number TEXT NOT NULL UNIQUE, idempotency_key TEXT NOT NULL UNIQUE, shift_uuid TEXT NOT NULL, warehouse_uuid TEXT NOT NULL, customer_uuid TEXT, status TEXT NOT NULL, subtotal TEXT NOT NULL, discount_amount TEXT NOT NULL, total_amount TEXT NOT NULL, created_at TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS sale_items (id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT NOT NULL UNIQUE, sale_uuid TEXT NOT NULL, product_uuid TEXT NOT NULL, unit_uuid TEXT NOT NULL, quantity TEXT NOT NULL, unit_price TEXT NOT NULL, line_total TEXT NOT NULL, created_at TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS sale_payments (id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT NOT NULL UNIQUE, sale_uuid TEXT NOT NULL, payment_method_uuid TEXT NOT NULL, amount TEXT NOT NULL, amount_tendered TEXT, reference TEXT, transfer_verified TEXT, credit_amount TEXT, customer_credit_selection TEXT, created_at TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS sale_discounts (id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT NOT NULL UNIQUE, sale_uuid TEXT NOT NULL, amount TEXT NOT NULL, reason TEXT, approved_by_user_uuid TEXT, created_at TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS sale_cancellations (id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT NOT NULL UNIQUE, sale_uuid TEXT NOT NULL UNIQUE, reason TEXT NOT NULL, approved_by_user_uuid TEXT, approval_state TEXT, refund_state TEXT, refund_method TEXT, created_at TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS sale_returns (id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT NOT NULL UNIQUE, sale_uuid TEXT NOT NULL, status TEXT NOT NULL, refund_amount TEXT NOT NULL, reason TEXT NOT NULL, refund_method TEXT, approval_requested TEXT, approved_by_user_uuid TEXT, created_at TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS sale_return_items (id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT NOT NULL UNIQUE, sale_return_uuid TEXT NOT NULL, sale_item_uuid TEXT NOT NULL, quantity TEXT NOT NULL, amount TEXT NOT NULL, created_at TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS receipts (id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT NOT NULL UNIQUE, sale_uuid TEXT NOT NULL UNIQUE, format TEXT NOT NULL, receipt_number TEXT NOT NULL UNIQUE, print_count INTEGER NOT NULL, created_at TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS receipt_reprints (id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT NOT NULL UNIQUE, receipt_uuid TEXT NOT NULL, reason TEXT NOT NULL, approved_by_user_uuid TEXT, created_at TEXT NOT NULL);

    CREATE TABLE IF NOT EXISTS service_categories (id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT NOT NULL UNIQUE, name TEXT NOT NULL UNIQUE, active TEXT NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS services (id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT NOT NULL UNIQUE, category_uuid TEXT NOT NULL, code TEXT NOT NULL UNIQUE, name TEXT NOT NULL, active TEXT NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS service_branch_settings (id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT NOT NULL UNIQUE, service_uuid TEXT NOT NULL, branch_uuid TEXT NOT NULL, enabled TEXT NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS service_prices (id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT NOT NULL UNIQUE, service_uuid TEXT NOT NULL, branch_uuid TEXT, amount TEXT NOT NULL, active TEXT NOT NULL, created_at TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS service_price_histories (id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT NOT NULL UNIQUE, service_price_uuid TEXT NOT NULL, old_amount TEXT, new_amount TEXT NOT NULL, created_at TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS service_revenue_share_rules (id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT NOT NULL UNIQUE, service_uuid TEXT, branch_uuid TEXT, basis TEXT NOT NULL, fixed_amount TEXT, requires_approval TEXT NOT NULL, active TEXT NOT NULL, created_at TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS service_revenue_share_rule_components (id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT NOT NULL UNIQUE, rule_uuid TEXT NOT NULL, type TEXT NOT NULL, percentage TEXT NOT NULL, created_at TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS service_orders (id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT NOT NULL UNIQUE, order_number TEXT NOT NULL UNIQUE, customer_uuid TEXT, branch_uuid TEXT NOT NULL, status TEXT NOT NULL, discount_amount TEXT NOT NULL, payment_confirmed_at TEXT, allocation_requested_at TEXT, approved_by_user_uuid TEXT, cancelled_at TEXT, refunded_at TEXT, created_at TEXT NOT NULL, completed_at TEXT);
    CREATE TABLE IF NOT EXISTS service_order_items (id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT NOT NULL UNIQUE, order_uuid TEXT NOT NULL, service_uuid TEXT NOT NULL, quantity TEXT NOT NULL, unit_price TEXT NOT NULL, line_total TEXT NOT NULL, created_at TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS service_staff_assignments (id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT NOT NULL UNIQUE, order_item_uuid TEXT NOT NULL, staff_user_uuid TEXT NOT NULL, contribution_percentage TEXT NOT NULL, assignment_role TEXT, started_at TEXT, completed_at TEXT, notes TEXT, created_at TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS service_payments (id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT NOT NULL UNIQUE, order_uuid TEXT NOT NULL, amount TEXT NOT NULL, payment_method TEXT NOT NULL, reference TEXT, created_at TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS service_expenses (id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT NOT NULL UNIQUE, order_uuid TEXT NOT NULL, category TEXT, amount TEXT NOT NULL, description TEXT NOT NULL, paid_by TEXT, payment_method TEXT, receipt_attachment_path TEXT, approval_status TEXT NOT NULL DEFAULT 'pending', expense_scope TEXT NOT NULL DEFAULT 'direct_service', created_at TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS service_revenue_allocations (id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT NOT NULL UNIQUE, order_uuid TEXT NOT NULL, rule_uuid TEXT NOT NULL, component_type TEXT NOT NULL, staff_user_uuid TEXT, amount TEXT NOT NULL, status TEXT NOT NULL, reversal_of_uuid TEXT, created_at TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS staff_earning_accounts (id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT NOT NULL UNIQUE, staff_user_uuid TEXT NOT NULL UNIQUE, current_balance TEXT NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS staff_earning_transactions (id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT NOT NULL UNIQUE, staff_user_uuid TEXT NOT NULL, type TEXT NOT NULL, amount TEXT NOT NULL, reference_uuid TEXT, reversal_of_uuid TEXT, created_at TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS staff_settlements (id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT NOT NULL UNIQUE, staff_user_uuid TEXT NOT NULL, amount TEXT NOT NULL, status TEXT NOT NULL, created_at TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS staff_settlement_items (id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT NOT NULL UNIQUE, settlement_uuid TEXT NOT NULL, earning_transaction_uuid TEXT NOT NULL, amount TEXT NOT NULL, created_at TEXT NOT NULL);

    CREATE TABLE IF NOT EXISTS platform_shareholders (id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT NOT NULL UNIQUE, name TEXT NOT NULL, status TEXT NOT NULL, synced_at TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS platform_share_classes (id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT NOT NULL UNIQUE, name TEXT NOT NULL, synced_at TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS platform_shareholdings (id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT NOT NULL UNIQUE, shareholder_uuid TEXT NOT NULL, share_class_uuid TEXT NOT NULL, units TEXT NOT NULL, synced_at TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS platform_profit_periods (id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT NOT NULL UNIQUE, status TEXT NOT NULL, approved_at TEXT, synced_at TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS platform_shareholder_distributions (id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT NOT NULL UNIQUE, shareholder_uuid TEXT NOT NULL, profit_period_uuid TEXT NOT NULL, approved_entitlement TEXT NOT NULL, status TEXT NOT NULL, synced_at TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS shareholder_payments (id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT NOT NULL UNIQUE, platform_distribution_uuid TEXT NOT NULL, shareholder_uuid TEXT NOT NULL, amount TEXT NOT NULL, payment_method TEXT NOT NULL, reference TEXT, created_at TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS shareholder_capital_contributions (id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT NOT NULL UNIQUE, shareholder_uuid TEXT NOT NULL, amount TEXT NOT NULL, reference TEXT, created_at TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS shareholder_loans (id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT NOT NULL UNIQUE, shareholder_uuid TEXT NOT NULL, principal_amount TEXT NOT NULL, outstanding_balance TEXT NOT NULL, reference TEXT, created_at TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS shareholder_loan_payments (id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT NOT NULL UNIQUE, loan_uuid TEXT NOT NULL, amount TEXT NOT NULL, reference TEXT, created_at TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS shareholder_account_transactions (id INTEGER PRIMARY KEY AUTOINCREMENT, uuid TEXT NOT NULL UNIQUE, shareholder_uuid TEXT NOT NULL, transaction_type TEXT NOT NULL, amount TEXT NOT NULL, reference_uuid TEXT, reversal_of_uuid TEXT, metadata_json TEXT, created_at TEXT NOT NULL);

    CREATE TABLE IF NOT EXISTS warehouses (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      code TEXT NOT NULL UNIQUE,
      name TEXT NOT NULL,
      address TEXT,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS stocks (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      product_uuid TEXT NOT NULL,
      warehouse_uuid TEXT NOT NULL,
      unit_uuid TEXT NOT NULL,
      quantity TEXT NOT NULL,
      reserved_quantity TEXT,
      last_updated_at TEXT NOT NULL,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS stock_movements (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      product_uuid TEXT NOT NULL,
      from_warehouse_uuid TEXT,
      to_warehouse_uuid TEXT,
      quantity_base TEXT NOT NULL,
      unit_uuid TEXT NOT NULL,
      movement_type TEXT NOT NULL,
      reference TEXT,
      metadata_json TEXT,
      created_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS stock_count_sessions (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      warehouse_uuid TEXT NOT NULL,
      status TEXT NOT NULL,
      approval_state TEXT NOT NULL,
      notes TEXT,
      created_by_user_uuid TEXT,
      approved_by_user_uuid TEXT,
      approved_at TEXT,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS stock_count_items (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      session_uuid TEXT NOT NULL,
      product_uuid TEXT NOT NULL,
      unit_uuid TEXT NOT NULL,
      barcode TEXT,
      system_quantity TEXT NOT NULL,
      counted_quantity TEXT NOT NULL,
      variance_quantity TEXT NOT NULL,
      note TEXT,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS stock_adjustments (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      warehouse_uuid TEXT NOT NULL,
      product_uuid TEXT NOT NULL,
      unit_uuid TEXT NOT NULL,
      adjustment_type TEXT NOT NULL,
      quantity TEXT NOT NULL,
      reason TEXT NOT NULL,
      supporting_note TEXT,
      approval_requested TEXT NOT NULL,
      approval_state TEXT NOT NULL,
      created_by_user_uuid TEXT,
      approved_by_user_uuid TEXT,
      approved_at TEXT,
      created_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS stock_transfers (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      from_warehouse_uuid TEXT NOT NULL,
      to_warehouse_uuid TEXT NOT NULL,
      status TEXT NOT NULL,
      approval_requested TEXT NOT NULL,
      approval_state TEXT NOT NULL,
      reason TEXT,
      requested_by_user_uuid TEXT,
      approved_by_user_uuid TEXT,
      approved_at TEXT,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS stock_transfer_items (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      transfer_uuid TEXT NOT NULL,
      product_uuid TEXT NOT NULL,
      unit_uuid TEXT NOT NULL,
      quantity TEXT NOT NULL,
      note TEXT,
      created_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS roles (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      name TEXT NOT NULL UNIQUE,
      description TEXT,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS permissions (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      key TEXT NOT NULL UNIQUE,
      description TEXT,
      group_name TEXT,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS role_permissions (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      role_uuid TEXT NOT NULL,
      permission_uuid TEXT NOT NULL,
      created_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS user_roles (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      user_uuid TEXT NOT NULL,
      role_uuid TEXT NOT NULL,
      created_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS user_branches (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      user_uuid TEXT NOT NULL,
      branch_uuid TEXT NOT NULL,
      created_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS user_sessions (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      user_uuid TEXT NOT NULL,
      device_uuid TEXT,
      access_token_hash TEXT NOT NULL,
      refresh_token_hash TEXT NOT NULL,
      expires_at TEXT NOT NULL,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL,
      revoked_at TEXT
    );

    CREATE TABLE IF NOT EXISTS login_logs (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      user_uuid TEXT,
      username TEXT,
      success TEXT NOT NULL,
      device_uuid TEXT,
      ip_address TEXT,
      reason TEXT,
      created_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS client_devices (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      name TEXT NOT NULL,
      kind TEXT NOT NULL,
      status TEXT NOT NULL,
      pairing_code_uuid TEXT,
      paired_at TEXT,
      last_seen_at TEXT,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS device_pairing_codes (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      code TEXT NOT NULL UNIQUE,
      device_uuid TEXT,
      status TEXT NOT NULL,
      expires_at TEXT NOT NULL,
      created_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS device_connection_logs (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      device_uuid TEXT NOT NULL,
      connection_type TEXT,
      success TEXT NOT NULL,
      details_json TEXT,
      created_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS password_reset_requests (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      user_uuid TEXT NOT NULL,
      token_hash TEXT NOT NULL,
      expires_at TEXT NOT NULL,
      used_at TEXT,
      created_at TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS daily_report_summaries (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      branch_uuid TEXT NOT NULL,
      report_date TEXT NOT NULL,
      sales_total TEXT NOT NULL,
      expenses_total TEXT NOT NULL,
      gross_profit TEXT NOT NULL,
      net_profit TEXT NOT NULL,
      payment_breakdown_json TEXT NOT NULL,
      generated_at TEXT NOT NULL,
      UNIQUE(branch_uuid, report_date)
    );

    CREATE TABLE IF NOT EXISTS report_jobs (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uuid TEXT NOT NULL UNIQUE,
      report_type TEXT NOT NULL,
      requested_by_user_uuid TEXT NOT NULL,
      filters_json TEXT NOT NULL,
      format TEXT NOT NULL,
      status TEXT NOT NULL,
      result_path TEXT,
      error_message TEXT,
      created_at TEXT NOT NULL,
      completed_at TEXT
    );

    -- Seed default roles and a minimal set of permissions (idempotent)
    INSERT OR IGNORE INTO roles (uuid, name, description, created_at, updated_at)
    VALUES
      ('00000000-0000-0000-0000-000000000001','Owner','Owner role',datetime('now'),datetime('now')),
      ('00000000-0000-0000-0000-000000000002','Administrator','Administrator role',datetime('now'),datetime('now')),
      ('00000000-0000-0000-0000-000000000003','Manager','Manager role',datetime('now'),datetime('now')),
      ('00000000-0000-0000-0000-000000000004','Cashier','Cashier role',datetime('now'),datetime('now')),
      ('00000000-0000-0000-0000-000000000005','Storekeeper','Storekeeper role',datetime('now'),datetime('now')),
      ('00000000-0000-0000-0000-000000000006','Accountant','Accountant role',datetime('now'),datetime('now')),
      ('00000000-0000-0000-0000-000000000007','Service Staff','Service Staff role',datetime('now'),datetime('now')),
      ('00000000-0000-0000-0000-000000000008','Supervisor','Supervisor role',datetime('now'),datetime('now')),
      ('00000000-0000-0000-0000-000000000009','Read-Only Auditor','Read-Only Auditor role',datetime('now'),datetime('now'));

    INSERT OR IGNORE INTO permissions (uuid, key, description, group_name, created_at, updated_at)
    VALUES
      ('perm-admin-manage-users','admin.manage_users','Manage users','Administration',datetime('now'),datetime('now')),
      ('perm-admin-manage-roles','admin.manage_roles','Manage roles','Administration',datetime('now'),datetime('now')),
      ('perm-admin-manage-permissions','admin.manage_permissions','Manage permissions','Administration',datetime('now'),datetime('now')),
      ('perm-admin-authorize-devices','admin.authorize_devices','Authorize Client App devices','Administration',datetime('now'),datetime('now')),
      ('perm-admin-manage-config','admin.manage_config','Manage local configuration','Administration',datetime('now'),datetime('now')),
      ('perm-admin-view-audit','admin.view_audit','View audit logs','Administration',datetime('now'),datetime('now')),
      ('perm-admin-manage-backups','admin.manage_backups','Manage backups','Administration',datetime('now'),datetime('now')),
      ('perm-sales-create-sale','sales.create_sale','Create sale','Sales',datetime('now'),datetime('now')),
      ('perm-sales-apply-discount','sales.apply_discount','Apply discount','Sales',datetime('now'),datetime('now')),
      ('perm-sales-approve-discount','sales.approve_discount','Approve discount','Sales',datetime('now'),datetime('now')),
      ('perm-sales-cancel-sale','sales.cancel_sale','Cancel sale','Sales',datetime('now'),datetime('now')),
      ('perm-sales-approve-cancel','sales.approve_cancel','Approve cancellation','Sales',datetime('now'),datetime('now')),
      ('perm-sales-process-return','sales.process_return','Process return','Sales',datetime('now'),datetime('now')),
      ('perm-sales-approve-return','sales.approve_return','Approve return','Sales',datetime('now'),datetime('now')),
      ('perm-sales-view-cost','sales.view_cost','View cost price','Sales',datetime('now'),datetime('now')),
      ('perm-sales-view-reports','sales.view_reports','View sales reports','Sales',datetime('now'),datetime('now')),
      ('perm-dashboard-view','dashboard.view','View local administrative dashboard','Reporting',datetime('now'),datetime('now')),
      ('perm-reports-view','reports.view','View local operational reports','Reporting',datetime('now'),datetime('now')),
      ('perm-reports-export','reports.export','Export local operational reports','Reporting',datetime('now'),datetime('now')),
      ('perm-reports-manage-jobs','reports.manage_jobs','Process local report jobs','Reporting',datetime('now'),datetime('now')),
      ('perm-staff-performance-view','staff.performance.view','View staff performance','Reporting',datetime('now'),datetime('now')),
      ('perm-inv-manage-products','inventory.manage_products','Manage products','Inventory',datetime('now'),datetime('now')),
      ('perm-inv-manage-prices','inventory.manage_prices','Manage prices','Inventory',datetime('now'),datetime('now')),
      ('perm-inv-record-purchases','inventory.record_purchases','Record purchases','Inventory',datetime('now'),datetime('now')),
      ('perm-inv-receive-stock','inventory.receive_stock','Receive stock','Inventory',datetime('now'),datetime('now')),
      ('perm-inv-adjust-stock','inventory.adjust_stock','Adjust stock','Inventory',datetime('now'),datetime('now')),
      ('perm-inv-approve-adjustment','inventory.approve_adjustment','Approve stock adjustment','Inventory',datetime('now'),datetime('now')),
      ('perm-inv-conduct-count','inventory.conduct_count','Conduct stock count','Inventory',datetime('now'),datetime('now')),
      ('perm-inv-transfer-stock','inventory.transfer_stock','Transfer stock','Inventory',datetime('now'),datetime('now')),
      ('perm-inv-record-damage','inventory.record_damage','Record damage or expiry','Inventory',datetime('now'),datetime('now')),
      ('perm-fin-record-expense','finance.record_expense','Record expense','Finance',datetime('now'),datetime('now')),
      ('perm-fin-approve-expense','finance.approve_expense','Approve expense','Finance',datetime('now'),datetime('now')),
      ('perm-fin-view-profit','finance.view_profit','View profit','Finance',datetime('now'),datetime('now')),
      ('perm-fin-manage-debt','finance.manage_customer_debt','Manage customer debt','Finance',datetime('now'),datetime('now')),
      ('perm-fin-manage-cash','finance.manage_cash_accounts','Manage cash and bank accounts','Finance',datetime('now'),datetime('now')),
      ('perm-fin-open-shift','finance.open_cashier_shift','Open cashier shifts','Finance',datetime('now'),datetime('now')),
      ('perm-fin-close-shift','finance.close_cashier_shift','Close cashier shifts','Finance',datetime('now'),datetime('now')),
      ('perm-fin-verify-settlement','finance.verify_cashier_settlement','Verify cashier settlements','Finance',datetime('now'),datetime('now')),
      ('perm-fin-record-expense-payment','finance.record_cash_expense','Record cash or bank expense','Finance',datetime('now'),datetime('now')),
      ('perm-fin-approve-expense-payment','finance.approve_cash_expense','Approve cash or bank expense','Finance',datetime('now'),datetime('now')),
      ('perm-fin-transfer-funds','finance.transfer_funds','Transfer funds between accounts','Finance',datetime('now'),datetime('now')),
      ('perm-fin-record-owner-withdrawal','finance.record_owner_withdrawal','Record owner withdrawal','Finance',datetime('now'),datetime('now')),
      ('perm-customer-manage','customers.manage','Manage customer profiles','Customers',datetime('now'),datetime('now')),
      ('perm-customer-record-payment','customers.record_payment','Record customer payments','Customers',datetime('now'),datetime('now')),
      ('perm-customer-approve-credit','customers.approve_credit','Approve customer credit','Customers',datetime('now'),datetime('now')),
      ('perm-customer-write-off','customers.write_off_debt','Write off customer debt','Customers',datetime('now'),datetime('now')),
      ('perm-fin-manage-supplier','finance.manage_supplier_balances','Manage supplier balances','Finance',datetime('now'),datetime('now')),
      ('perm-fin-settle-shifts','finance.settle_cashier_shifts','Settle cashier shifts','Finance',datetime('now'),datetime('now')),
      ('perm-svc-manage-services','services.manage_services','Manage services','Services',datetime('now'),datetime('now')),
      ('perm-svc-create-order','services.create_service_order','Create service order','Services',datetime('now'),datetime('now')),
      ('perm-svc-assign-staff','services.assign_service_staff','Assign service staff','Services',datetime('now'),datetime('now')),
      ('perm-svc-complete-service','services.complete_service','Complete service','Services',datetime('now'),datetime('now')),
      ('perm-svc-record-expense','services.record_service_expense','Record service expense','Services',datetime('now'),datetime('now')),
      ('perm-svc-generate-allocation','services.generate_revenue_allocation','Generate revenue allocation','Services',datetime('now'),datetime('now')),
      ('perm-svc-approve-allocation','services.approve_allocation','Approve allocation','Services',datetime('now'),datetime('now')),
      ('perm-svc-view-staff-earnings','services.view_staff_earnings','View staff earnings','Services',datetime('now'),datetime('now')),
      ('perm-svc-settle-staff-earnings','services.settle_staff_earnings','Settle staff earnings','Services',datetime('now'),datetime('now')),
      ('perm-sh-view-shareholders','shareholders.view_info','View shareholder information','Shareholders',datetime('now'),datetime('now')),
      ('perm-sh-record-approved-payment','shareholders.record_approved_payment','Record approved shareholder payment','Shareholders',datetime('now'),datetime('now')),
      ('perm-sh-record-capital','shareholders.record_capital_contribution','Record capital contribution','Shareholders',datetime('now'),datetime('now')),
      ('perm-sh-record-loan','shareholders.record_loan','Record shareholder loan','Shareholders',datetime('now'),datetime('now')),
      ('perm-sh-view-statements','shareholders.view_statements','View shareholder statements','Shareholders',datetime('now'),datetime('now'));

    INSERT OR IGNORE INTO role_permissions (uuid, role_uuid, permission_uuid, created_at)
    VALUES
      ('role-perm-owner-dashboard','00000000-0000-0000-0000-000000000001','perm-dashboard-view',datetime('now')),
      ('role-perm-owner-reports','00000000-0000-0000-0000-000000000001','perm-reports-view',datetime('now')),
      ('role-perm-owner-export','00000000-0000-0000-0000-000000000001','perm-reports-export',datetime('now')),
      ('role-perm-owner-jobs','00000000-0000-0000-0000-000000000001','perm-reports-manage-jobs',datetime('now')),
      ('role-perm-owner-staff','00000000-0000-0000-0000-000000000001','perm-staff-performance-view',datetime('now')),
      ('role-perm-admin-dashboard','00000000-0000-0000-0000-000000000002','perm-dashboard-view',datetime('now')),
      ('role-perm-admin-reports','00000000-0000-0000-0000-000000000002','perm-reports-view',datetime('now')),
      ('role-perm-admin-export','00000000-0000-0000-0000-000000000002','perm-reports-export',datetime('now')),
      ('role-perm-admin-jobs','00000000-0000-0000-0000-000000000002','perm-reports-manage-jobs',datetime('now')),
      ('role-perm-admin-staff','00000000-0000-0000-0000-000000000002','perm-staff-performance-view',datetime('now'));

  `);

  ensureColumn(sqlite, 'sync_queue', 'batch_uuid', 'TEXT');
  ensureColumn(sqlite, 'sync_queue', 'next_attempt_at', 'TEXT');
  ensureColumn(sqlite, 'sync_queue', 'last_error_code', 'TEXT');
  ensureColumn(sqlite, 'sync_queue', 'last_error_detail', 'TEXT');
  ensureColumn(sqlite, 'users', 'staff_code', 'TEXT');
  ensureColumn(sqlite, 'users', 'force_password_change', "TEXT NOT NULL DEFAULT '0'");
  ensureColumn(sqlite, 'expenses', 'approval_requested', 'TEXT');
  ensureColumn(sqlite, 'expenses', 'created_by_user_uuid', 'TEXT');
  ensureColumn(sqlite, 'expenses', 'created_by_name', 'TEXT');
  ensureColumn(sqlite, 'expenses', 'approved_by_user_uuid', 'TEXT');
  ensureColumn(sqlite, 'expenses', 'reversed_by_user_uuid', 'TEXT');
  ensureColumn(sqlite, 'expenses', 'reversed_at', 'TEXT');
  ensureColumn(sqlite, 'service_orders', 'payment_confirmed_at', 'TEXT');
  ensureColumn(sqlite, 'service_orders', 'allocation_requested_at', 'TEXT');
  ensureColumn(sqlite, 'service_orders', 'approved_by_user_uuid', 'TEXT');
  ensureColumn(sqlite, 'service_orders', 'cancelled_at', 'TEXT');
  ensureColumn(sqlite, 'service_orders', 'refunded_at', 'TEXT');
  ensureColumn(sqlite, 'service_staff_assignments', 'assignment_role', 'TEXT');
  ensureColumn(sqlite, 'service_staff_assignments', 'started_at', 'TEXT');
  ensureColumn(sqlite, 'service_staff_assignments', 'completed_at', 'TEXT');
  ensureColumn(sqlite, 'service_staff_assignments', 'notes', 'TEXT');
  ensureColumn(sqlite, 'service_expenses', 'category', 'TEXT');
  ensureColumn(sqlite, 'service_expenses', 'paid_by', 'TEXT');
  ensureColumn(sqlite, 'service_expenses', 'payment_method', 'TEXT');
  ensureColumn(sqlite, 'service_expenses', 'receipt_attachment_path', 'TEXT');
  ensureColumn(sqlite, 'service_expenses', 'approval_status', "TEXT NOT NULL DEFAULT 'pending'");
  ensureColumn(sqlite, 'service_expenses', 'expense_scope', "TEXT NOT NULL DEFAULT 'direct_service'");

  const outboxTables = [
    'users', 'products', 'sales', 'sale_items', 'sale_payments', 'expenses', 'stocks', 'purchases',
    'customer_accounts', 'customer_account_transactions', 'cashier_shifts', 'cashier_settlements',
    'service_orders', 'service_staff_assignments', 'service_payments', 'service_expenses',
    'service_revenue_allocations', 'staff_earning_transactions', 'staff_settlements',
    'shareholder_payments', 'daily_report_summaries',
  ];
  for (const table of outboxTables) {
    sqlite.exec(`
      CREATE TRIGGER IF NOT EXISTS sync_outbox_${table}_insert
      AFTER INSERT ON ${table}
      BEGIN
        INSERT OR IGNORE INTO sync_queue (
          uuid, aggregate_type, aggregate_uuid, payload_json, status, idempotency_key, attempts, created_at, updated_at
        ) VALUES (
          lower(hex(randomblob(4))) || '-' || lower(hex(randomblob(2))) || '-4' || substr(lower(hex(randomblob(2))), 2) || '-a' || substr(lower(hex(randomblob(2))), 2) || '-' || lower(hex(randomblob(6))),
          '${table}', NEW.uuid, json_object('recordUuid', NEW.uuid, 'aggregateType', '${table}'), 'pending',
          'outbox:${table}:' || NEW.uuid, 0, datetime('now'), datetime('now')
        );
      END;
    `);
  }
}

function ensureColumn(sqlite: BetterSqliteDatabase, table: string, column: string, definition: string): void {
  const columns = sqlite.prepare(`PRAGMA table_info(${table})`).all() as Array<{ name: string }>;
  if (!columns.some((item) => item.name === column)) sqlite.exec(`ALTER TABLE ${table} ADD COLUMN ${column} ${definition}`);
}
