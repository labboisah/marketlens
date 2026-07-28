# Data Ownership

MarketLens separates operational authority from cloud reporting authority. This
keeps shops operational offline while still allowing secure remote visibility.

## Shop App Authoritative Data

The Shop App is authoritative for branch operations. It owns the local SQLite
database and validates all operational writes from Client Apps.

Authoritative Shop App data:

- Operational users.
- Roles and permissions.
- Products used locally.
- Sales.
- Sale items.
- Payments.
- Purchases.
- Inventory movements.
- Expenses.
- Customer debts.
- Supplier accounts.
- Cashier shifts.
- Audit logs.
- Synchronization queue.

Rules:

- Client Apps must submit writes through the Shop App API.
- Stock quantities must be derived from stock movements.
- Customer debts and supplier balances must be derived from account ledger
  transactions.
- Completed sales, payments, purchases, stock movements, and ledger
  transactions are immutable business history.
- Corrections use returns, cancellations, reversals, adjustments, or new ledger
  entries.

## Platform Authoritative Data

The Platform is authoritative for cloud administration and monitoring access.
It owns multi-tenant PostgreSQL data.

Authoritative Platform data:

- Plans.
- Subscriptions.
- Businesses.
- Business owners.
- Device activations.
- Monitoring users.
- Application versions.
- Cloud synchronization acknowledgements.
- Consolidated reports.
- Notifications.
- Support records.

Rules:

- The Platform manages tenant and branch authorization for cloud APIs.
- The Platform is not the authentication authority for Shop App operational
  users.
- Platform device activation authorizes Shop Apps to synchronize, but does not
  replace local Shop App authorization for daily operations.

## Platform Replicated Data

The Platform stores replicated and summarized operational data received from
Shop Apps. This data supports Monitoring App dashboards, owner reports, support
diagnostics, and consolidated business visibility.

Replicated Platform data:

- Limited shop-user profile.
- Sales.
- Payments.
- Stock summaries.
- Purchases.
- Expenses.
- Customer debt summaries.
- Cashier shift summaries.
- Alerts.
- Branch performance summaries.

Rules:

- Replicated records must preserve source UUIDs, `business_uuid`, `branch_uuid`,
  source device identity, source record version, and synchronization batch
  identity.
- Replicated data should be query-optimized for reporting and monitoring.
- Replicated operational rows do not make the Platform authoritative for daily
  branch operations.

## Monitoring App Data

The Monitoring App connects only to the Platform. It may cache report summaries
for offline viewing.

Rules:

- Cached monitoring records are read-only.
- Cached monitoring data must show last successful synchronization time.
- Stale data must be clearly identified.
- Authentication tokens must use secure storage.
