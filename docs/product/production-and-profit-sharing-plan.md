# Production, stock and profit-sharing extension

Date: 2026-09-13
Status: first implementation in the working tree for Server/App 2.2.0 and Platform. See [operations and RBAC](production-operations.md) for delivered behavior and first-release boundaries. Pilot installation and publication remain release gates.

## Confirmed requirements

Support production materials, production batches, production sales, production expenses, production stores and machines. Produce finished goods into stock before sale. Machine owners and staff receive percentages of profit after production costs. The business owner registers machines and staff and configures their share percentages in the Business Server. Rates are business-owned settings, not hard-coded product rules or values that must be supplied during development.

## Baseline findings before implementation

Reviewed the standalone Business Server at `C:/laragon/www/marketlens-business-server` and the Platform architecture. This is a focused extension assessment, not a full accounting or application audit.

- Products, units, purchases, warehouses, stock movements, sales, expenses and CSV transfers provide foundations to extend.
- `src/services/domain/local-reporting-service.ts`, dashboard: sales cost currently uses quantity multiplied by the product's current `cost_price`. Historical production profit therefore needs a new immutable cost source; changing a catalog price must not rewrite historical profit.
- `src/database/schema.ts`, `saleItems`: existing sale lines do not contain a historical cost field. Add explicit sale-to-cost-layer allocations rather than calculating manufactured item cost from the current catalog value.
- `src/services/domain/service-revenue-service.ts`: services already support percentage allocations, approval, staff earnings and settlements. Its component types do not include machines, and its calculations are for service orders. Reuse reviewed patterns, but introduce explicit production rules and source references; do not change existing service-sharing semantics implicitly.
- The current dashboard combines product margin, service payments and approved expenses. Production reporting must distinguish earned revenue, receipts, inventory costs and distributions and prevent production expenses being counted twice.

## Screens to add or adjust

| Area | Screen and required behavior |
| --- | --- |
| Items for production | Extend product setup with material, finished-good and dual-use roles; units and conversions; material costs; reorder levels. Add versioned recipes (bill of materials) with expected output and material quantities. |
| Production | New batch list, batch detail and approval screens: recipe version, planned output, material issue/return, actual consumption, staff, machine usage, expenses, good output and waste. Show estimated versus actual cost. |
| Production sales | Extend existing POS, sales, returns and customer statements to sell finished-good SKUs. Link sold quantities to finished-stock cost layers and originating batches. Add a production-sales filter and margin report. |
| Production expenses | Extend expenses with batch/machine links, category, supporting reference, approval and allocation method. Show allocated and unallocated amounts. |
| Production items store | Extend warehouse, stock card, transfer and count screens with raw materials, work in progress and finished goods. Show available/reserved quantities, batch traceability and valuation. |
| Machines | New machine register: code, branch, owner/payee, availability, usage, maintenance and applicable share rule. Machine ownership and company shareholding are separate concepts. |
| Profit and shares | New calculation preview, approval, machine-owner/staff statements and settlement screens. Show sales, costs, profit before shares, each allocation, business remainder and paid/unpaid balances. |
| Settings and access | Production module enablement, recipe/cost rules, effective-dated sharing rules and permissions for storekeeper, production operator, manager, accountant and owner. Enforce permissions on the Server. |
| Platform and Monitoring | Synchronized production quantities, cost, waste, sales margin and share liabilities, with branch filters and last-sync time. Operational edits remain local. |
| Setup kit | Production CSV templates, examples and guides in the downloadable ZIP, with application/schema compatibility in the manifest. |

## Owner-managed setup in the Business Server

Confirmed: the Business Server is the setup and configuration location. The locally authenticated business owner controls machine registration, staff registration and production share configuration. Platform administrators do not determine these rates.

Proposed screens under Production setup:

1. Machines: register a unique machine code, name, branch, owner/payee and active status. Link usage to production batches. A company-owned machine can name the business as beneficiary; ownership alone does not automatically grant a share.
2. Production staff: register staff through the existing local staff/user management flow or select existing staff, then enroll them for production participation. Keep one staff identity rather than creating duplicate service and production employees. Enrollment does not grant permission to change shares.
3. Share configuration: the owner selects registered machines and staff, sets their percentages, reviews the business remainder and activates a dated rule version. Provide a calculation preview before activation. No default payable percentages are assumed.

Proposed first-release configuration uses an explicit percentage per participating machine and staff member, all applied to the same profit-after-costs pool. Machine shares are payable to their configured beneficiaries. The machine total and staff total are sums of those individual allocations; do not apply another percentage to them. The owner can enter zero explicitly. Combined allocations must be between 0% and 100%, with the remainder retained by the business.

For each batch, record the participating machines and staff and snapshot the applicable owner-configured rule before production starts. Missing configuration must be shown as incomplete, not silently treated as zero. Excluding a participant does not redistribute its percentage to other participants automatically; show the resulting business remainder in the preview. Rule scope and any batch-specific changes must remain explicit and owner-controlled.

Validate owner authorization, branch scope, registered active participants, duplicate entries and decimal percentage bounds on the Server, including CSV imports. Staff and machine operators cannot change their own entitlement. Historical beneficiaries and rates remain attached to posted records even if a machine or staff member is later deactivated. Changes create an audited rule version for future batches; corrections to posted allocations use linked adjustment entries.

Acceptance coverage must include unauthorized changes, cross-branch references, duplicate participants, totals over 100%, missing configuration, zero shares, deactivation, rule changes after batch start and deterministic rounding. CSV setup templates should reference existing machine/staff codes and use the same owner-controlled validation and activation workflow. Never include passwords in templates or exports.

## Proposed workflow and costing policy

Purchase materials -> receive into material store -> create batch -> issue materials into work in progress -> record actual production -> approve completion -> receive finished stock -> sell -> approve profit allocation -> settle beneficiaries.

Use draft, approved, in-progress and completed batch states. Cancel unposted drafts; correct posted work through linked reversal/correction records. Do not erase consumed material or finished-stock history. Prevent a completion reversal that would invalidate subsequent sales; require a controlled correction workflow.

Proposed first release: one finished product per batch, with recorded waste; multi-output/by-product cost apportionment follows later if required. Track lots/expiry where applicable.

Record actual material costs at issue, actual consumed quantities after returns, and approved conversion costs. Allocate normal production waste through the batch's good output; report exceptional loss separately under an explicit policy. Reject zero-output completion unless it is posted through the loss workflow.

Batch cost = consumed material cost + eligible production expenses.

Unit production cost = batch cost / accepted good output quantity.

Propose FIFO cost layers with immutable sale allocations, subject to validation against existing inventory flows before implementation. Keep unsold finished-goods cost in inventory. Charge only the cost associated with sold units to their margin. Show incomplete or unfinalized batch costs as provisional and block final share approval until required costs are finalized. Late costs create auditable adjustments, never silent historical edits.

Fixed wages, machine hire, fuel, electricity and maintenance may be production costs under configured allocation rules. A machine-owner or staff profit share is a subsequent distribution: do not also enter that same share as a production expense. Each expense may be allocated only up to its approved amount, and an expense included in batch cost must not be deducted again by consolidated reports.

## Profit and share calculation

Proposed production profit report:

1. Net sales = recognized finished-goods sales less discounts and returns, excluding tax collected on behalf of authorities.
2. Profit before shares = net sales minus cost of finished goods sold minus applicable expenses not already included in that cost.
3. Share pool = positive profit before shares. A loss remains visible; it does not become an automatic negative staff or machine-owner payout.
4. Machine-owner share = share pool multiplied by the configured machine percentage.
5. Staff share = share pool multiplied by the configured staff percentage.
6. Business remainder = profit before shares minus machine-owner share minus staff share.

Both percentages apply to the same pool. Their combined percentage must not exceed 100%; the remainder belongs to the business. Keep operating profit and the business remainder separately labelled: a distribution does not mean production itself became less profitable.

Illustrative example only, with all output sold: sales 100,000; consumed materials 40,000; production expenses 10,000; profit before shares 50,000. At illustrative machine/staff rates of 20%/30%, machine owners receive 10,000, staff receive 15,000 and the business retains 25,000.

If only half that output sells for 50,000, recognize 25,000 of its batch cost and retain 25,000 in finished inventory. Profit before shares is 25,000 before any separate period expenses. Do not deduct the entire batch cost from that partial sale.

Record beneficiary entitlements in ledgers; settlement reduces the payable and records the payment without deducting the share from profit a second time. Credit sales and cash collections must appear separately. Returns reverse the original revenue, cost allocation and related entitlements through linked entries, including recoverable balances if already paid.

Use decimal-safe monetary arithmetic, explicit quantity precision and deterministic rounding so allocation totals reconcile exactly. Freeze recipe, cost references, beneficiaries and rule versions for each approved calculation.

## Decisions to settle during phase 1

- Whether owner-configured rules vary by product, batch or branch. Actual rates are entered by each business owner in the Business Server.
- Validate the proposed direct percentage per participating machine/staff member with the owner; determine whether hours/output-based allocation is needed in a later release.
- Whether profit closes per batch or per period; how partial sales, later losses and already-paid shares reconcile. Recommended starting point: show provisional margins continuously and approve a cumulative batch allocation with adjustments, with the close policy explicitly defined.
- Whether payouts require collected cash or may settle accrued credit-sale profit. Keep entitlement and payment eligibility separate.
- Which overhead costs are eligible and how to allocate them; treatment of exceptional waste and late costs.
- Whether first-release single-output batches fit the actual manufacturing business.

These are proposed software policies, not a determination of statutory accounting treatment.

## Architecture and data contracts

The Business Server owns validation, SQLite transactions, stock movements, costing, approvals, audit history and ledgers. Business App screens call authenticated local APIs and never write SQLite. Platform receives versioned synchronization events; Monitoring reads Platform data only.

Proposed records: recipes/versions/lines, batches, material issues/returns, outputs, cost entries/layers, sale cost allocations, machines/ownership, machine usage, batch staff participation, sharing rule versions, allocations and beneficiary ledger/settlement entries. All synchronized identities use UUIDs with explicit tenant and branch scope.

Post domain changes, movement/ledger entries, audit records and synchronization outbox records atomically. Use command idempotency keys and unique source references to prevent duplicate completion, sales allocation and settlement after retries or restarts. Validate compatible contract versions across the standalone projects. Existing retail and service behavior must remain covered by regression tests.

## Implementation phases and readiness gates

| Phase | Deliverables | Exit gate |
| --- | --- | --- |
| 1. Rules and contracts | Resolve the decisions above; sample costing workbook; screen flows; permission matrix; schema/API/sync design and migration strategy. | Representative full-sale, partial-sale, loss, return and credit-sale examples reconcile and policies are explicit. |
| 2. Materials, machines and owner setup | Product roles, recipes, stores, machine registration, existing staff enrollment, owner-controlled share configuration and master-data CSVs. | Owner authorization, percentage validation, rule snapshots, unit conversions, branch isolation, opening-stock validation and duplicate-import handling pass. |
| 3. Batch production | Material issue/return, work in progress, completion, waste, expenses and immutable output costing. | Stock and value reconcile from receipt through completion; concurrency and crash/retry tests prove no duplicate consumption or output. |
| 4. Sales and sharing | Finished-stock sales allocation, returns, historical cost reporting, profit rules, approval, ledgers and payouts. | Partial sales, changed prices/rules, rounding, negative profit, refunds after payout and duplicate settlement tests pass. |
| 5. Cloud and onboarding | Versioned sync ingestion, owner reports, Monitoring views, CSV exports and production setup-kit ZIP. | Offline/reconnect replay and tenant isolation pass; local/cloud totals reconcile and old clients handle version boundaries. |
| 6. Pilot and release | Backup/restore drill, upgrade migrations, signed compatible installers, onboarding runbook and pilot production cycle. | Existing retail/service regressions pass; pilot stock, cost and payables reconcile; clean install and upgrade are verified before publishing. |

CSV import should cover materials/products, recipe headers/lines, machines, ownership, approved configuration and draft batches/expenses where appropriate. Imports of opening balances must use controlled movement/ledger workflows. Completed batches, sales and settlements should initially be export-only; CSV must not bypass approvals or rewrite posted history. Extend the existing preview, validation, permissions and import receipt framework.

Deploy compatible Platform synchronization support before enabling desktop production synchronization. Enable the module per business/branch after setup validation. Take a verified pre-upgrade backup; do not downgrade an application onto a database it cannot read. Define a recovery procedure that preserves post-upgrade transactions before pilot rollout.