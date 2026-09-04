# MarketLens MVP Scope

The first MarketLens MVP proves the core product promise: reliable offline shop
operations with synchronized owner visibility through the cloud Platform.

The MVP must preserve the four application architecture:

  Platform manages cloud administration, sync APIs, reporting summaries, and
  Monitoring App access.
  Business Server owns local business operations and SQLite persistence.
  Business App provides role based workstations over the local network.
  Monitoring App reads synchronized Platform data only.

## MVP Platform

  Businesses.
  Branches.
  Plans.
  Subscriptions.
  Device activation.
  Synchronization API.
  Monitoring authentication.
  Dashboard summaries.
  Application version management.

## MVP Business Server

  Business configuration.
  Users.
  Roles and permissions.
  Products.
  Categories.
  Units.
  Product prices.
  Customers.
  Suppliers.
  Purchases.
  Inventory movements.
  Sales.
  Mixed payments.
  Expenses.
  Customer debts.
  Cashier shifts.
  Local reports.
  Audit logs.
  Device authorization.
  Synchronization.
  Backup and recovery.

## MVP Business App

  Device pairing.
  Login.
  Role aware navigation.
  Cashier POS.
  Receipt printing.
  Product search.
  Customer selection.
  Mixed payment.
  Shift opening and closing.
  Storekeeper stock screens.
  Manager approvals.
  Basic reports.

## MVP Monitoring App

  Login.
  Business and branch selection.
  Daily sales.
  Payment breakdown.
  Expenses.
  Stock alerts.
  Customer debt.
  Cashier performance.
  Last synchronization status.
  Push notifications.

## MVP Quality Bar

  Businesses must continue selling without internet.
  Business Apps must never directly open SQLite.
  Monitoring App must never connect directly to Business Servers.
  Completed sales must not be deleted.
  Stock changes must use inventory movements.
  Customer debt must be ledger based.
  Synchronization must be idempotent and recoverable.
  Tenant and branch isolation must be tested.
  Critical business rules must have automated tests.
