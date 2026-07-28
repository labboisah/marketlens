# Migration Strategy

This document defines migration ordering and safety rules. It does not create
production migrations.

## Principles

- Migrations must preserve offline operation for Shop Apps.
- Synchronized identity must use UUIDs from the first migration.
- Local numeric IDs may support local joins, but must not be exposed as cloud
  identities.
- Add foreign keys, unique constraints, and check constraints with the initial
  table when possible.
- Keep immutable transaction tables append-only after completion.
- Use status transitions instead of destructive updates for completed
  transactions.
- Write audit logs for important schema-impacting state changes where
  application behavior changes.

## Shop App Migration Order

1. Local configuration and branch identity.
2. Users, roles, permissions, sessions, devices, and login logs.
3. Product catalog foundations.
4. Customers, suppliers, cash accounts, and bank accounts.
5. Inventory foundations and stock movement documents.
6. Sales, sale items, payments, returns, cancellations, receipts.
7. Purchases, purchase items, payments, returns.
8. Expense and financial ledger tables.
9. Terminals, cashier shifts, and settlements.
10. Approval workflows.
11. Audit logs and security alerts.
12. Synchronization queue, batches, logs, conflicts, checkpoints.
13. Backup metadata and restore logs.

## Platform Migration Order

1. Plan catalog and plan features.
2. Businesses, business owners, and branches.
3. Subscriptions and subscription payments.
4. Devices and device activations.
5. Monitoring users and personal access tokens.
6. Synchronization batches, receipts, and failures.
7. Replicated users, sales, sale items, payments, purchases, expenses, stock
   summaries, customer debt summaries, cashier shift summaries, and alerts.
8. Daily sales summaries and daily branch summaries.
9. Notifications, support tickets, application versions.
10. Platform audit logs.

## Backward-Compatible Changes

Prefer expand-and-contract migrations:

1. Add nullable column or new table.
2. Deploy application code that writes both old and new shape if needed.
3. Backfill in batches.
4. Validate counts and constraints.
5. Make column non-null or remove old column in a later release.

## Synchronization Compatibility

Synchronization migrations must account for old Shop Apps that may not upgrade
immediately.

Rules:

- Version API and synchronization payload contracts.
- Keep parsers tolerant of missing optional fields during rollout.
- Never silently reinterpret an existing field.
- Record source app version and schema version in sync batches.
- Make idempotent replays safe before deploying new synchronization behavior.

## Rollback Guidance

Rollback should be designed before a migration ships.

- Additive migrations are easiest to roll back in code.
- Destructive migrations require backups and explicit approval.
- Shop App migrations should create a local backup before high-risk schema
  changes.
- Platform migrations should use database backups, migration dry runs, and
  staging restores.

## Data Backfills

Backfills should:

- Run in small batches.
- Be resumable.
- Record progress.
- Avoid locking high-traffic tables for long periods.
- Validate row counts and aggregates.
- Be safe to rerun.

## Migration Testing

Test migrations against:

- Empty databases.
- Current development data.
- Realistic large datasets.
- Old sync payloads.
- Interrupted migration recovery.
- Downgrade or rollback behavior where supported.
