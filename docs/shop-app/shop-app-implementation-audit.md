# Business Server Implementation Audit

Date: 2026-08-04

## Decision

**Not ready for Client App implementation handoff or pilot deployment.** The Shop
App has a useful local foundation, but critical financial workflows are partial,
authorization is inconsistent across local routes, and the automated validation
toolchain could not be installed or run.

The Business Server may continue as a development dependency for Client App contract
work only after callers treat unimplemented endpoints as unavailable. It must
not be used for operational pilot transactions until the blocking items below
are resolved and the full test/build matrix passes.

## Validation Environment

| Command group | Result | Evidence |
| --- | --- | --- |
| TypeScript | Blocked | Standalone server app type backlog requires follow-up. |
| ESLint | Blocked | Standalone server app lint backlog requires follow-up. |
| Prettier | Blocked | Formatting was not part of this audit run. |
| Vitest | Blocked | `vitest.cmd` is absent. |
| Fastify integration tests | Not present | No test uses `createLocalServer` or `server.inject`. |
| SQLite integration tests | Partial, unrun | Existing tests create temporary SQLite databases but Vitest is unavailable. |
| Playwright | Blocked and no Business Server E2E files | Playwright binary is absent; config matches only `*.e2e.ts`. |
| Electron smoke tests | Not present | No Electron smoke-test suite exists. |
| Production build | Blocked | TypeScript/Vite dependencies are absent. |
| Installer build | Blocked | Electron Forge dependencies are absent. |

The Business Server/local server now lives at `C:\laragon\www\business-server` and uses
npm. Run validation from that standalone app instead of the removed
`apps/desktop` workspace.

## Required Test Groups

| Group | Coverage status | Audit result |
| --- | --- | --- |
| Activation | No dedicated activation tests | Missing valid/invalid/expired/revoked/subscription/update cases. |
| Users and devices | Partial | Login and pairing tests exist; role permission and session-revocation integration tests are absent. |
| Products and inventory | Partial | Purchase receiving covers stock; unit conversion, barcode lookup, negative stock, expiry, serial uniqueness, and adjustment cases are incomplete. |
| Purchases and suppliers | Partial | Creation, receiving, partial payment, and supplier balance are covered; return is missing. |
| Customers and debts | Partial | Credit, limits, payment allocation, statement, and write-off are covered; write-off reversal is missing. |
| Sales | Missing | `sales.test.ts` is a placeholder assertion. Return and cancellation service methods are not implemented. |
| Cashier shifts | Partial | Opening, expected/actual cash, variance, and settlement have real service tests. |
| Services | Missing | `services-revenue-sharing.test.ts` is arithmetic only, not an integration test. |
| Shareholders | Missing | `shareholder-transactions.test.ts` is arithmetic only, not an integration test. |
| Synchronization | Partial | Offline failure and conflict are tested; successful/duplicate/partial/module/subscription/pull behavior lacks verified integration coverage. |
| Backup and recovery | Partial | Backup checksum and maintenance are tested; actual restore, compatibility rejection, and failed-restore recovery are not. |

## Criteria Verification

| # | Requirement | Status | Evidence / finding |
| --- | --- | --- | --- |
| 1 | Business Server owns SQLite | Verified | SQLite is opened only in `src/database/sqlite.ts` and owned by the Business Server application container. |
| 2 | Client Apps cannot access SQLite | Verified by boundary | Client App has no SQLite dependency; Electron boundaries preserve renderer isolation. |
| 3 | Operational users are local | Verified | Users and sessions are local SQLite records. |
| 4 | Client devices require authorization | Partial | Login verifies devices when supplied, but device approval/revocation routes do not consistently enforce an administrator session. |
| 5 | Daily work continues without internet | Partial | Local SQLite services work with sync disabled; no end-to-end offline workflow has run. |
| 6 | Subscription/module restrictions enforced | Partial | Services module gates itself; equivalent enforcement is not consistently applied to all modules/routes. |
| 7 | Sales use atomic transactions | Partial | Sale creation uses a SQLite transaction, but cancellation/return/credit-sale financial workflows are absent. |
| 8 | Completed sales cannot be deleted | Partial | No delete API is present, but no database constraint/policy test proves immutability. |
| 9 | Stock is movement-based | Verified for purchase/sale paths | Purchase receiving and sales create stock movement records. |
| 10 | Customer/supplier balances are ledger-based | Verified for implemented flows | Customer and supplier services maintain ledger/account records. |
| 11 | Cashier shifts reconcile correctly | Partially verified | Real cashier service tests cover expected cash and settlement variance; suite is unrun. |
| 12 | Services are optional | Verified by code | Service service checks plan/module/branch settings. |
| 13 | Service sharing follows effective settings | Partial | Rule resolution exists, but no integration test proves effective-setting precedence. |
| 14 | Direct expenses not double counted | Unverified | Allocation code divides expenses per item, but no multi-item integration test proves the invariant. |
| 15 | Staff earnings are ledger-based | Verified by code | Staff earning accounts and immutable earning transaction records are used. |
| 16 | Settlements cannot exceed earnings | Verified by code, unrun | Service rejects settlement above account balance. |
| 17 | Ownership cannot be edited locally | Verified for public API | No local CRUD route writes Platform shareholder ownership reference tables. |
| 18 | Payments cannot exceed entitlement | Verified by code, unrun | Shareholder service sums prior payments against approved Platform entitlement. |
| 19 | Synchronization is idempotent | Partial | Queue/batch idempotency fields exist, but duplicate-batch success behavior is not covered; trigger outbox and legacy manual enqueues can create duplicate semantic records with different keys. |
| 20 | Platform credentials are protected | Verified by code | Activation encrypts credentials with AES-256-GCM and supports revocation. |
| 21 | Backup and restore work | Partial | Backup/checksum/integrity code exists; real restore/relaunch recovery is untested. |
| 22 | Audit logs cover sensitive operations | Partial | Many implemented services audit, but coverage is incomplete for all listed approval and device actions. |
| 23 | Electron security preserved | Verified by code | `contextIsolation`, sandbox, web security, and no Node integration are enabled. |
| 24 | Tests cover critical financial rules | Failed | Sales, services, and shareholder tests are placeholders; suite is unrun. |
| 25 | Windows installer builds successfully | Unverified | Forge configuration exists but installer build has not run. |

## Safe Corrections Applied During Audit

- `SalesService.completeSale` now rejects empty sales, non-positive quantities,
  negative prices/payments, non-finite values, and discounts outside the range
  from zero through subtotal. This prevents invalid financial records before the
  SQLite transaction begins.

## Blocking Remediation

1. Install `C:\laragon\www\business-server` with npm and run every command
   group listed above from the standalone app.
2. Implement and test sales cancellation, returns, credit-sale posting,
   discount approval, mixed payments, and receipt reprint approval.
3. Replace placeholder sales, services, and shareholder tests with temporary
   SQLite integration tests that exercise actual services and records.
4. Add authenticated authorization middleware to every administrative/device
   mutation route; do not rely on request body user UUIDs.
5. Consolidate transactional outbox behavior. The database triggers and legacy
   post-commit `SyncQueueRepository.enqueue` calls must not emit separate
   idempotency keys for the same business event.
6. Add Fastify `server.inject` tests, Playwright/Electron smoke tests, and a
   signed Windows installer build verification.
7. Exercise real restore behavior in a controlled test process, including
   schema incompatibility and failed copy recovery.

## Pilot Readiness

**No-go.** Do not pilot financial operations, cashier terminals, services, or
shareholder payments until the blocking remediation is completed and the
required automated validation matrix passes on Windows.
