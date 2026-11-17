# Sidebar monitoring and synchronization

The Platform sidebar uses uppercase collapsible group headings. The selected page's group opens automatically. Remote Monitoring links to branch monitoring; Production links select explicit record kinds, and Services links target catalogue, orders, and revenue sections.

| Group | Screens and data source |
| --- | --- |
| Downloads | Platform-published desktop installers; no operational synchronization. |
| Business | Overview and Remote Monitoring use tenant/branch-scoped dashboard summaries derived from received sales, payments, stock, expenses and balances. |
| Sales | Sales/payments: sale and sale_payment. Inventory: stock_summary and stock_movement. Expenses: expense. Customers: customer_debt_summary. Purchase & Supplier: purchase, purchase_item, purchase_payment and supplier_balance_summary. Cashier & Staff: safe operational_user_profile, cashier_shift and cashier_settlement records. |
| Monitoring | Branch Performance compares derived Platform branch summaries. |
| Control | Branch/device administration, backup workflows, sync receipts and alerts remain Platform functions. Synchronization shows received batches, failures and conflicts. |
| Insights | Reports read replicated records and derived summaries. Shareholders reads shareholder, share_class and share_ownership_transaction snapshots. |
| Production | Machinery: machine/staff/rule. Items: material/recipe. Production: batch/loss. Store: completed batch/transfer. Expenses: expense. Revenue: sale/return/allocation/settlement. All use versioned production_record snapshots and operational/finance permission checks. |
| Services | Catalogue: service/service_price. Service work: service_order/service_order_item/service_payment. Revenue and shares: service_revenue_allocation. |
| Account | Monitoring users, subscriptions, settings and support are Platform-owned. Business Server refreshes cached subscription and enabled modules through device status. Operational-user authentication stays local. |

## Changes verified on 2026-09-14

The Business Server project is currently at C:/laragon/www/marketlens-business-server.

The worker reconciles existing supported source tables, including changed service records, and queues immutable payload snapshots with increasing versions and unique idempotency keys. An unchanged projected payload does not create another queue entry. Existing CSV/production envelopes and wire entity names remain supported.

Additional projections connect stock balances and movements, expenses, customer/supplier balances and purchase lines to the typed models consumed by Platform screens. Both sides retain the business event date. Repeated batches update the same daily dashboard summary using date-aware lookup.

The sidebar indicator displays the selected branch's last recorded successful synchronization, with no-sync and delayed states. This is received-data freshness, not a live connectivity probe.

## Verification

The fixture at tests/Fixtures/business-server-sync.json contains synthetic payloads emitted by the actual Business Server worker test, without credentials or real business data. The Platform intake test sends them through signed requests and checks receipts, acknowledgements, checkpoints, typed projections and updated service data.

To regenerate it, set MARKETLENS_SYNC_FIXTURE_PATH to the absolute fixture path and run:
    node scripts/test-electron.cjs tests/synchronization.test.ts

Run the Platform SynchronizationIntakeApiTest, AuthenticatedPlatformShellTest and BusinessOverviewDashboardTest using php artisan test.

The worker test checks unchanged-run deduplication, increasing versions and retry payload stability after a simulated network interruption. Existing activation tests verify Platform-to-server subscription refresh and preservation of cached settings when offline.

These are isolated automated checks, not a test against an activated live installation. Unsupported legacy outbox entity types remain retained locally; this change does not invent new wire contracts for them. The checkpoint endpoint acknowledges progress rather than pulling operational business data back from Platform.

Typed projections ignore older accepted versions when a newer version already exists in the same business, branch and entity scope. Replaying an old receipt remains idempotent and cannot revert the displayed snapshot. The cross-end fixture test replays its first batch after the updated batch to check this.
