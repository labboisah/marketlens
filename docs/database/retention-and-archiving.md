# Retention and Archiving

Retention policy must preserve legal, audit, operational, and support needs
while keeping local Shop App databases responsive and Platform reporting tables
manageable.

Final retention periods should be approved with product, legal, and operations
stakeholders before production launch.

## Data Classes

| Data Class | Examples | Retention Guidance |
| --- | --- | --- |
| Immutable transactions | Sales, payments, purchases, stock movements, ledger entries | Retain long-term. Archive rather than delete. |
| Master records | Products, customers, suppliers, roles | Soft-delete when appropriate; retain while referenced. |
| Audit logs | User, security, approval, sync, admin changes | Retain long-term; archive old partitions on Platform. |
| Synchronization logs | Batches, failures, conflict logs | Retain detailed logs for support window; summarize older logs. |
| Notifications | Owner alerts, system notifications | Retain active/recent; archive or purge old low-value notifications. |
| Local backups | SQLite backup files | Retain rolling set based on disk policy. |
| Cached Monitoring reports | Read-only summaries | Purge when stale or after configured offline cache window. |

## Shop App Retention

The Shop App should keep complete local operational history needed for branch
continuity. Because it may operate offline, deletion policies must be
conservative.

Guidance:

- Do not delete completed sales, sale items, payments, purchases, stock
  movements, customer ledger transactions, supplier ledger transactions, or cash
  and bank transactions.
- Archive old audit and sync diagnostic logs only after they are acknowledged by
  the Platform and outside the support window.
- Keep local backup retention configurable, for example last 14 to 30 days.
- Do not purge records that have not synchronized successfully.
- Use SQLite maintenance operations carefully and only outside active sales
  periods.

## Platform Retention

The Platform should retain authoritative cloud records and reporting history,
but can use partitioning and archival storage for high-volume replicated tables.

Guidance:

- Keep plans, subscriptions, businesses, owners, branches, devices, application
  versions, and support records according to business/legal requirements.
- Keep replicated operational data long enough to support owner reporting and
  audits.
- Archive older replicated sales, sale items, audit logs, sync logs, and
  notifications by time partition when volume requires it.
- Preserve daily summaries even if detailed replicated rows are archived to
  colder storage.

## Archiving Candidates

Platform archiving candidates:

- `replicated_sales`.
- `replicated_sale_items`.
- `replicated_payments`.
- `replicated_expenses`.
- `audit_logs`.
- `sync_batches`, `sync_receipts`, `sync_failures`.
- Detailed synchronization logs if introduced.
- `notifications`.

Shop App archiving candidates:

- Old sync logs after successful acknowledgement.
- Old security alerts after resolution and sync.
- Old receipt reprint logs after policy window, if legal requirements permit.
- Old local backup files.

## Partitioning and Cold Storage

For Platform PostgreSQL, consider monthly partitions for high-volume append-only
tables once volume justifies the operational complexity. Archive old partitions
to lower-cost storage when they are outside normal query windows.

Daily summary tables should remain queryable for owner dashboards and long-range
reports even when detailed replicated data moves to cold storage.

## Purge Rules

Purge only when:

- The record is not immutable business history.
- Legal, tax, audit, and product retention requirements allow deletion.
- The record has synchronized or been summarized if it affects reporting.
- Deletion is logged where operationally important.

Production purge jobs must be tenant-aware and branch-aware.
