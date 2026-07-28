# Indexing Strategy

Indexes should support tenant isolation, branch filtering, synchronization
idempotency, local operational speed, and reporting freshness. Add indexes based
on measured query plans, but the following recommendations are expected
starting points.

## Required Index Patterns

| Pattern | Applies To | Purpose |
| --- | --- | --- |
| `business_uuid + branch_uuid + transaction_date` | Sales, payments, purchases, expenses, summaries | Branch/date reporting and dashboard filters. |
| `business_uuid + record_uuid` | Synchronized records | Tenant-safe lookup by stable UUID. |
| `product_uuid + warehouse_uuid` | Stocks, stock movements | Stock lookup and movement history. |
| `status + created_at` | Queues, approvals, notifications, sessions | Process pending work and recent status pages. |
| `device_uuid + sync_status` | Sync queues, sync batches | Device-specific synchronization processing. |
| `customer_uuid + transaction_date` | Customer account transactions, payments, debt summaries | Customer ledger and debt history. |
| `sale_uuid` | Sale items, payments, returns, receipts | Sale drill-down and receipt lookup. |
| `barcode` | Product barcodes | Fast POS scanning. |
| `sku` | Products | Fast product lookup and import de-duplication. |

## Shop App SQLite Indexes

Recommended Shop indexes:

```sql
CREATE UNIQUE INDEX idx_products_business_sku
ON products (business_uuid, sku);

CREATE UNIQUE INDEX idx_product_barcodes_business_barcode
ON product_barcodes (business_uuid, barcode);

CREATE INDEX idx_sales_branch_date
ON sales (business_uuid, branch_uuid, transaction_date);

CREATE INDEX idx_sale_items_sale
ON sale_items (sale_uuid);

CREATE INDEX idx_sale_payments_sale
ON sale_payments (sale_uuid);

CREATE INDEX idx_stock_product_warehouse
ON stocks (product_uuid, warehouse_uuid);

CREATE INDEX idx_stock_movements_product_warehouse_date
ON stock_movements (product_uuid, warehouse_uuid, movement_date);

CREATE INDEX idx_customer_account_transactions_customer_date
ON customer_account_transactions (customer_uuid, transaction_date);

CREATE INDEX idx_sync_queue_device_status
ON sync_queue (device_uuid, sync_status);

CREATE INDEX idx_sync_queue_status_created
ON sync_queue (sync_status, created_at);
```

SQLite notes:

- Keep write-heavy indexes minimal on the Shop App because local writes must
  stay fast during busy sales periods.
- Use covering indexes only where profiling proves a benefit.
- Keep barcode and SKU lookups fast for keyboard/scanner workflows.

## Platform PostgreSQL Indexes

Recommended Platform indexes:

```sql
CREATE UNIQUE INDEX idx_business_record_uuid
ON replicated_sales (business_uuid, source_record_uuid);

CREATE INDEX idx_replicated_sales_branch_date
ON replicated_sales (business_uuid, branch_uuid, transaction_date);

CREATE INDEX idx_replicated_sale_items_sale
ON replicated_sale_items (sale_uuid);

CREATE INDEX idx_replicated_payments_branch_date
ON replicated_payments (business_uuid, branch_uuid, transaction_date);

CREATE INDEX idx_replicated_expenses_branch_date
ON replicated_expenses (business_uuid, branch_uuid, transaction_date);

CREATE INDEX idx_replicated_purchases_branch_date
ON replicated_purchases (business_uuid, branch_uuid, transaction_date);

CREATE INDEX idx_stock_summaries_product_warehouse
ON replicated_stock_summaries (product_uuid, warehouse_uuid);

CREATE INDEX idx_replicated_alerts_status_created
ON replicated_alerts (status, created_at);

CREATE INDEX idx_sync_batches_device_status
ON sync_batches (device_uuid, sync_status);

CREATE INDEX idx_sync_failures_status_created
ON sync_failures (status, created_at);

CREATE INDEX idx_notifications_status_created
ON notifications (status, created_at);
```

PostgreSQL notes:

- Prefer composite indexes that start with `business_uuid` for tenant-filtered
  queries.
- Use partial indexes for hot operational states such as `pending`, `failed`,
  `unread`, or `active`.
- Use BRIN indexes for very large append-only time-series tables when data is
  naturally ordered by time.
- Consider materialized views for expensive reporting queries.

## Unique Constraints

Use unique constraints for:

- `record_uuid` where global uniqueness is acceptable.
- `(business_uuid, record_uuid)` for tenant-scoped UUIDs.
- `(business_uuid, sku)`.
- `(business_uuid, barcode)`.
- Synchronization idempotency keys.
- `(source_device_uuid, source_record_uuid, source_record_version)` on
  replicated synchronized records when appropriate.

## Check Constraints

Use check constraints for:

- Non-negative monetary amounts where negative values are not valid.
- Quantity precision and non-negative quantities where appropriate.
- Status enumerations where the database supports stable enum/check behavior.
- Date ordering, for example `closed_at >= opened_at`.
- Payment allocation values not exceeding payment amount.
