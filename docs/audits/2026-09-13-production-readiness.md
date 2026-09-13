# Production implementation verification

Date: 2026-09-13
State: implemented and built in the working trees; not an installed or published 2.2.0 release.

## Delivered

- Business Server 2.2.0: additive schema 5, owner-controlled setup, recipe and sharing snapshots, draft/issue/return/complete/loss workflows, approved expenses, cost-preserving transfers, FIFO finished-stock costing, discounted returns, cumulative share approvals and ledger payouts.
- Business App 2.2.0: permission-gated production workspace using the local Server API, existing POS integration and personal earnings. Owner setup stays in the Server.
- Platform: production feature and business/branch enablement, branch-scoped synchronized reports, financial visibility permissions and CSV exports. Operational authentication stays local.
- CSV: seven production template/example sets, atomic preview-confirm imports for setup and drafts, restricted exports; public setup ZIP now has 25 template sets.
- RBAC: separate setup, production, stock, approval, expenses, finance, sale, return, allocation, settlement, own earnings and export permissions. Owner role/account changes are protected. Explicit user grants reach session snapshots; Administrator alone does not grant production authority.

## Verification evidence

These are targeted checks, not a claim that the entire repository test suite or a real-client pilot ran.

| Area | Passing evidence |
| --- | --- |
| Server production | 24 tests covering precision, snapshots, idempotency, rollback, FIFO, discounted returns, payouts/recoverables, CSV preview binding, transfers, losses, actual SalesService integration, owner-only setup, HTTP/device authorization, finance redaction, inactive participants and retired configurations |
| Server authentication/session | 4 authentication tests and 6 session-request tests; fixtures explicitly provision local accounts and close isolated databases |
| Server surrounding workflows | CSV 33, maintenance 1, inventory routes 3, backup recovery 2, sales 1, workstation checkout 22 and update safety 6 passed during integration |
| App | Production access 2 and application state 22; desktop update 10, release policy 9, installer trust 3 and boundary 2 passed |
| Platform | Production/public deployment/setup downloads: 11 tests, 205 assertions; module access 11 and sync intake 18 passed earlier during integration |
| Builds | Both desktop typechecks and Vite main/preload/renderer builds passed; App emits a non-failing large-bundle warning |
| Style | New production PHP files and related tests pass Pint; production TypeScript files formatted |

Earlier failing assertions were corrected and affected suites rerun: money string formatting, expanded ZIP inventory, strict batch input in a new test, and legacy authentication fixtures. The production participant type error was fixed before the final Server build.

## Rollout remaining

1. Deploy Platform code and run the production monitoring permission migration before Server production synchronization is enabled.
2. Build and verify compatible signed 2.2.0 installers. Successful Vite builds are application bundles, not published installers.
3. Back up a representative Server database; verify clean installation, schema 4-to-5 upgrade, restore and client pairing with the actual installers.
4. Enable the plan feature `module:production`, then business/branch production; refresh the Server subscription. Assign local production roles and cloud monitoring permissions.
5. Run an owner-approved batch through partial sales, expenses, returns, share approval and payout; reconcile physical stock, cash, payables and synchronized reports.
6. Publish after pilot and installation checks pass. No live operational database upgrade or new installer publication was performed in this implementation.

## Explicit first-release scope

See [operations and RBAC](../product/production-operations.md). Single-output batches and base-unit finished sales are supported. Material cost freezes the existing catalog/purchase price at issue. Reprocessing/co-products, raw-material FIFO reconstruction, arbitrary finished-stock count corrections, late cost amendments and machine-hour sharing remain future work. The original plan includes these broader ideas; they are not all claimed as delivered.

Staff and machine percentages apply directly to positive cumulative batch profit. Credit sales earn profit before collection; payouts require available cash and approved entitlement. Owner acceptance of these documented policies is part of the pilot.
