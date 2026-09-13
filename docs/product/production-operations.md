# Production operations and access control

Status: source implementation verified for Server/App 2.2.0; targeted tests and desktop builds pass. Installation, pilot and installer publication remain pending.

## Enable and configure

1. Deploy Platform code and run its migrations, including the production monitoring permissions. Deploy the Platform version that recognizes the additive `production_record` synchronization entity before enabling new Server synchronization.
2. In Platform plan administration, add the feature key `module:production` with the name Production and Profit Sharing to the intended plan. This does not change prices.
3. In business settings, enable Production; choose branch inheritance or enablement in branch settings. Refresh the Server subscription so its locally cached module access includes production.
4. On the Business Server, sign in as the local Owner. Use Users to register staff, then Production > Staff to enroll the existing local users. Operational authentication stays on the Server.
5. Register products, units and warehouses through the existing setup screens. Enroll material SKUs and finished-good SKUs in Production > Production items. Finished-good enrollment requires zero existing stock: historical retail stock cannot silently acquire a manufacturing cost.
6. Register machines and their owners/payees. Create recipes using base units, then create share configurations. Enter each participating machine and staff member's percentage of profit after costs. Total percentages may not exceed 100%; the business retains the remainder.
7. Assign production permissions using Roles/Permissions. A general Administrator does not automatically obtain production access or Owner-only share configuration. Only an existing Owner can change Owner roles or accounts.

The Business App provides production operations through the authenticated local API. Setup remains in the Business Server interface. Platform provides synchronized monitoring and exports with separate operational and financial permissions.

## Production cycle

Create a draft batch with a recipe version, share configuration, planned output and material/output stores. All participants in the selected share configuration are the batch participants. Create a separate configuration for a different team or set of machines.

A production storekeeper issues the recipe quantities into the batch. Quantities scale to planned output, with thousandth-of-base-unit precision. Return unused material before completion. Material cost is the existing purchasing/catalog unit cost (up to six decimal places), frozen at issue; later price changes do not change the batch. Recipe base units are also checked against subsequent catalog changes.

Record batch expenses with unique references and approve them. These entries are production cost allocations, not expense payments; any payment must use the appropriate existing payment workflow. Do not enter the same production cost again as a separate business expense. Approved costs cannot be edited after completion.

A production manager completes the batch using actual good output and waste. Normal waste is absorbed into the good output cost. A failed batch with no good output can instead be posted as a loss, with a reason. Drafts without expenses may be cancelled; posted material consumption is never deleted.

Completion creates stock movements and FIFO cost layers. Approved transfers in the batch screen move finished goods between stores while preserving batch cost. Use Production for finished-stock changes; generic stock adjustments, receipts and CSV stock posting must not bypass these layers.

Sell produced SKUs through the existing Sales/POS screen in their base unit. A user needs both normal POS/shift permissions and `production.sell`. Sales spanning batches receive explicit quantity and cost allocations; unsold cost remains in inventory. The Server and Platform production reports show net sales, cost and profit separately from receipts.

## Shares and payments

Profit before shares = net production sales - cost of sold production - applicable batch losses.

Each configured percentage applies to positive profit from the same batch. There are no automatic negative payouts for a loss. The batch screen shows calculated shares and the business remainder; these previews are distinct from approved entitlements.

Approve/reconcile shares to record cumulative entitlement adjustments in beneficiary ledgers. Repeated approval cannot accrue the same amount twice. Payouts require `production.settle`, a positive approved balance, sufficient cash-account funds and a reference. Payout permission cannot approve new entitlements. A payment reduces cash and the payable; it does not deduct the share from profit again.

The first implementation uses cumulative per-batch profit from recognized sales, including credit sales. Settlement may use available business cash; it is not automatically restricted to collections from that particular sale. Later sales or returns can change a batch's cumulative profit. Reconcile shares before additional payouts. Returns reverse original discounted revenue and recorded costs and adjust existing entitlements; amounts already paid can become recoverable balances.

Production returns require an authorized immediate return through the existing return workflow. Bill replacement and sale cancellation are blocked for costed production sales; use a return to preserve cost provenance. Bulk import cannot create posted sales, approved production, allocations or settlements.

## Production permission matrix

| Permission | Authority |
| --- | --- |
| production.own_earnings | View only the authenticated staff member's production ledger |
| production.view | Production records, material/recipe/store information |
| production.setup | Local Owner only: enroll items/staff, register machines, create recipes and sharing rules, retire participants/recipes/rules, import setup |
| production.produce | Create draft batches |
| production.stock | Issue materials and return unused materials |
| production.approve | Complete/cancel batches, record failed batches and transfer finished stock |
| production.expenses | Record and view batch expenses |
| production.approve_expenses | Approve and view batch expenses |
| production.sell | Sell finished goods, in addition to normal POS permissions |
| production.return | Reverse production costs and entitlements on an authorized sales return |
| production.finance | View costs, margin, sharing configurations and beneficiary balances |
| production.allocate | Approve/reconcile share entitlements |
| production.settle | Pay approved entitlements |
| production.export | Export only production records the actor can view |

Role presets: Production Worker, Production Operator, Production Storekeeper, Production Manager, Production Accountant, Production Auditor, Production Cashier and Production Returns Supervisor. Combine the cashier/returns presets with existing sales and shift roles as needed. Permissions are checked in the Server service as well as at authenticated API boundaries; hiding buttons is not the security boundary.

Cloud permissions are `view-production`, `view-production-finance` and `export-production`. Owners may grant them through Monitoring Users. Branch assignments restrict both selected-branch and all-branch results. Users without financial access do not receive rules, costs, sales margin or share records in report data or exports.

## CSV and setup kit

The Production CSV panel downloads templates and validates/imports owner-managed setup. The public setup ZIP includes a production folder with seven template sets, fictional examples and this guide.

Import order: materials -> machines -> enrolled staff -> recipes -> share configurations -> draft batches -> draft expenses. Products, warehouses and local users must already exist. Templates reference SKU, warehouse code, username and machine code. Recipe and rule rows sharing a name form one record. Use a distinct recipe/rule version name for future changes. Retire old recipes/rules to remove them from future selection; existing batch snapshots remain unchanged. Inactive participants cannot start new work.

Production imports allow 500 rows per preview, require the same actor and exact file contents at confirmation, and revalidate everything in one transaction. A failed file leaves no partial operational records. Retry the same confirmation identifier after a connection interruption; do not submit a new identifier for the same intended import.

Exports include permitted record fields, with structured details encoded as JSON in CSV cells. Exported transactions are for reporting; they are not posting templates. No passwords or operational credentials are included.

## First-release boundaries and readiness

- One finished product per recipe/batch; co-products, reprocessing finished goods as ingredients and machine-hour-based sharing are outside this version.
- Material valuation freezes existing catalog/purchase cost at issue; it does not introduce a retrospective raw-material FIFO ledger.
- Finished goods are sold in their base units and tracked by batch. Retrospective cost changes and arbitrary finished-stock count adjustments require a future explicit correction workflow.
- Posted batch costs and completed output are immutable. Failed batches are handled through a loss entry; no destructive rollback of sold production is provided.
- Operational writes remain offline-capable on the Server using cached activation/module access. Internet is needed for synchronization and subscription refresh.
- Schema version 5 is additive. Back up before upgrading; never open a version-5 database with a version-4 executable.
- Validate clean installation, upgrade/restore, actual business examples, role assignments and printer/POS behavior during the pilot. Publish compatible signed installers only after the release checks pass.
