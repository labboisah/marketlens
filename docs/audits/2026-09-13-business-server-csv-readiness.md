# Business Server CSV audit and implementation plan

Date: 2026-09-13

Status: source audit and proposed delivery plan; implementation has not started.

## Scope and evidence

Reviewed the current working tree at `C:/laragon/www/marketlens-business-server`, including renderer screens, local routes, repositories, domain services, export processing, and test inventory. Its HEAD is `072877c`, but it contains substantial existing uncommitted work; findings describe the working tree, not that commit alone. No Business Server files were changed.

This is a code-based workflow audit. No live owner session, packaged application walkthrough, customer dataset, load benchmark, or test suite was run. Performance and usability concerns below require measurement during delivery; they are not measured production failures.

The root architecture and agent guides name `C:/laragon/www/business-server`, which does not exist in this environment. The actual sibling project is named `marketlens-business-server`. Some desktop README setup and implementation statements are also stale. Correct these references during documentation readiness without recreating desktop sources inside Platform.

### Principal findings

1. **Bulk onboarding is missing.** No business-data CSV import, downloadable template library, validation preview, or import history was found in the inspected source. Reports already supports CSV export; export is therefore incomplete, not wholly absent.
2. **Products has a data-entry mismatch.** Its form accepts name, category, and reorder level and generates the SKU. The API supports substantially more catalog fields. Existing business SKUs, units, barcodes, and prices need deliberate entry and matching workflows.
3. **Purchases is a major operational bottleneck.** The form constructs a single-item purchase, fixes the base-unit multiplier at `1`, and immediately calls receive after creation. Bulk invoice upload needs multiple lines, explicit unit conversion, draft review, and a separate receiving decision.
4. **List usability must accompany import.** Products, Customers, and Suppliers load their collections and render rows without search or pagination controls in those managers. A large upload would make navigation harder unless these screens are adjusted.
5. **Report export can omit records.** The HTTP report route defaults to 1,000 rows, allows at most 2,000, and supplies truncation metadata. The renderer downloads its loaded rows and then requests a separate export job. It does not give the user that completed job's file in this workflow. Verify both HTTP and Electron IPC paths, since the preview limit is specifically evidenced in the HTTP route.
6. **Report export permission is checked too late for the browser download.** The renderer downloads before calling the server endpoint guarded by `reports.export`, then suppresses server errors. A report viewer can invoke that download without successful export authorization. Fix the application action and error reporting; export permission cannot prevent someone manually copying data they are entitled to view.
7. **CSV formatting and large-job handling need consolidation.** Renderer and service use different serializers. Neither inspected serializer neutralizes spreadsheet formulas. The report job materializes rows and writes synchronously; `setImmediate` schedules this work but does not make it nonblocking. A responsive bulk engine needs bounded work and persistence.

### Evidence index

All paths in this table are relative to the actual Business Server project. Lines are starting points in the audited working tree.

| Evidence | Location |
| --- | --- |
| Navigation and complete screen inventory | `src/renderer/src/App.tsx:124` |
| Report download before server export, suppressed failure | `src/renderer/src/App.tsx:578` |
| Renderer CSV serialization | `src/renderer/src/App.tsx:755` |
| Generic table renders supplied rows | `src/renderer/src/App.tsx:850` |
| Inventory reconciliation workflow | `src/renderer/src/App.tsx:885` |
| Categories / warehouses / units | `src/renderer/src/App.tsx:1014`, `:1083`, `:1148` |
| Product form and generated SKU | `src/renderer/src/App.tsx:1216` |
| Customer / supplier forms | `src/renderer/src/App.tsx:1308`, `:1393` |
| Single-line purchase and automatic receiving | `src/renderer/src/App.tsx:1476` |
| Service catalog and sharing formulas | `src/renderer/src/App.tsx:1824`, `:1920` |
| Catalog field schemas, barcode and price endpoints | `src/server/routes/product-routes.ts:24` |
| Product-unit, barcode, price repository methods | `src/repositories/product-repository.ts:238` |
| Existing reconciliation transaction and movement | `src/server/routes/inventory-routes.ts:281` |
| Customer creation supports an opening ledger entry | `src/services/domain/customer-service.ts:62` |
| HTTP report limits and export job endpoints | `src/server/routes/reporting-routes.ts:34` |
| Synchronous export processing and second serializer | `src/services/domain/local-reporting-service.ts:649` |
| Global API authentication and rate limiting | `src/server/local-server.ts:52` |
| Existing backup/update readiness checks | `package.json`, `tests/maintenance-gate.test.ts`, `tests/update-safety.test.ts` |

## Screens requiring adjustment

Priority: P0 = required for the first useful bulk-onboarding release; P1 = operational expansion; P2 = later or optional. A new screen is explicitly identified below; other names are existing navigation entries or sections.

| Screen or area | Required adjustment | Import / template / export policy | Priority |
| --- | --- | --- | --- |
| **New: Data Setup & Transfers** under Admin | Guided setup checklist, template library, multi-file batch, dependency order, validation preview, progress, results and history | All authorized templates; shared import/export jobs; visible branch and dataset totals | P0 |
| Products | Preserve owner SKU; expose units and price fields; searchable, paginated list; product detail sections for barcodes and unit conversions | Catalog import, template and full filtered export; separate prices, barcodes and product-unit files | P0 |
| Categories | Parent-aware import and duplicate resolution; searchable hierarchy | Template/import/export; parent reference resolved before child | P0 |
| Units | Explain base units and conversions; searchable references | Template/import/export; conversion belongs to the product-unit relationship | P0 |
| Warehouses | Searchable codes and clear branch ownership | Template/import/export; reject foreign-branch references | P0 |
| **New: Brands section** within Catalog | Expose existing brand API capability for setup and correction | Template/import/export; do not silently create spelling variants | P0 |
| Customers | Search, pagination, stable customer codes, separate profile and financial data | Profile template/import/export; opening receivables in a separate approved workflow | P0 |
| Suppliers | Search, pagination, stable supplier codes and payment terms | Profile template/import/export; opening payables in a separate approved workflow | P0 |
| Inventory | Opening-stock wizard; warehouse filters; stock-count sessions and variance preview | Opening stock template/import and stock export in P0; stock-count import/export in P1 | P0/P1 |
| Reports | Authorized server-generated download, all matching rows, visible limits and filters, progress and failures | Export only; separate report files from files designed for re-import | P0 |
| **New: Opening Balances** linked from People/Finance setup | Cutover date, customer/supplier review, control totals, approval and posting receipt | Separate receivable/payable templates; ledger entries, never direct balance edits | P0 |
| Purchases | Multiple invoice lines; searchable product picker; explicit unit conversion; draft and receive stages | Header/line templates, draft import, invoice/line export; receiving is a separate authorized action | P1 |
| Inventory counts, transfers and adjustments | Session-based count sheet and draft review; show approvals and affected warehouses | Count templates first; transfer/adjustment draft imports later; movements only upon authorized posting | P1 |
| Services | Search and bulk setup where the module is enabled | Service-catalog template/import/export after rules and formula references are validated | P1 |
| Sharing Formulas | Version-aware references and effective dates | Initially export/reference lookup; bulk changes only as new reviewed versions | P2 |
| Users | Bulk staff registration with role review and per-user activation | Optional profile template/import; safe roster export; no passwords, PINs, tokens or credential hashes | P2 |
| Roles and Permissions | Granular template, validation, commit, export and approval capabilities | Configure permissions through existing controlled UI; no generic permission CSV import | P0 support |
| Bank Accounts, Cash Accounts, Payment Methods, Terminals | Consistent filtering and safe reference exports; bank details remain permission-controlled | Optional configuration import later; opening cash/bank balances need a separately designed ledger workflow | P2 |
| Sales, Expenses, Cashier Shifts, Staff Performance | Reliable scoped exports with dates, totals and filters | Export through Reports first; no completed-history CSV overwrite | P1 |
| Shareholders, Shares & Transfers, Commissions | Scoped reference/history exports where useful | Defer ownership, payout, settlement and approval imports pending dedicated business rules | P2 |
| Backups & Restore, Synchronization, System Update | Show active transfer jobs, prevent unsafe restore/update overlap, expose sync backlog after import | No generic CSV mutation; backup files remain distinct from data exports | P0 support |
| Dashboard, Devices, Receipt Settings, Subscription | Setup entry point or status only where useful | No bulk CSV needed for activation, pairing, device authorization or subscription controls | No direct change |

## Owner experience and file pack

The owner should be able to download templates before bringing data to the installation team. Make **Download template**, **Import CSV**, and **Export** available on each eligible screen, with the same wizard behind every entry point. The central setup screen coordinates several files rather than forcing the owner to understand internal UUIDs.

Proposed flow:

1. Choose the dataset or download the business setup pack. Show module availability and branch.
2. Give the owner an empty CSV, a separate fictional example, a column guide, and reference-code exports. Never mix example records into the empty template.
3. Select several files locally. Identify each dataset and schema version; show missing dependencies.
4. Map columns, then validate all files without operational writes. Show row/column errors, duplicates, unresolved references and proposed creates/updates/skips.
5. Download an error CSV, correct it, and validate again. Retain source row identifiers so corrections are traceable.
6. Review counts and financial/stock totals. Confirm the specific branch, mode and posting effect. Financial and stock postings require the appropriate approval.
7. Run locally with persistent progress. Show committed, failed and remaining records, then offer a reconciliation summary and authorized exports.

### Proposed template contract

These are design proposals, not implemented or approved API contracts. Phase 0 must map every field to existing domain rules and settle required columns.

| File family | Illustrative columns | Dependency / effect |
| --- | --- | --- |
| `categories.csv`, `brands.csv` | category reference, name, parent reference; brand reference, name | Explicit stable external references where existing codes are absent |
| `units.csv`, `warehouses.csv` | code, name; warehouse address | Existing branch-local reference data |
| `products.csv` | sku, name, category reference, brand reference, base unit code, purchase/sales unit codes, reorder level, active | Creates or explicitly updates catalog; no stock or debt columns |
| `product_units.csv` | sku, unit code, base-unit multiplier | Positive validated conversion; base unit must exist |
| `product_barcodes.csv` | sku, barcode | Preserve text and leading zeros; enforce uniqueness policy |
| `product_prices.csv` | sku, price type, amount, currency, effective date | Defined supported price types and price-history semantics |
| `customers.csv`, `suppliers.csv` | code, name, contact fields, address, allowed business attributes | Profiles only; names alone are never update keys |
| `opening_stock.csv` | source row ID, sku, warehouse code, unit code, quantity, unit cost, batch/expiry where applicable | Dated opening-stock movements; serial-tracked products need a linked serial file and count validation |
| `opening_receivables.csv`, `opening_payables.csv` | source row ID, account code, document reference, amount, currency, as-of date, due date | Dedicated ledger postings and agreed sign/credit policy; reconcile against owner totals |
| `purchase_headers.csv`, `purchase_lines.csv` | external invoice key, supplier code, date; invoice key, line ID, sku, unit, quantity, unit cost | Join lines by stable key; one validated draft per invoice |
| `stock_counts.csv` | count session reference, source row ID, sku, warehouse, unit, counted quantity | Compare against a defined stock snapshot and intervening movements |
| `services.csv` | service reference, name, category, price, allowed formula reference | Optional enabled module; final schema follows service rules |

Import order: categories/brands/units/warehouses, then products, product units/barcodes/prices, then people, then opening stock and balances. Purchases follow suppliers and products. Block dependent files when prerequisite records failed.

CSV rules must cover UTF-8/BOM, quoted commas and newlines, line endings, optional semicolon-delimiter mapping, explicit dates, decimal precision, permitted enum values, blank-versus-clear behavior, and unknown headers. Preserve SKUs, barcodes, phone and bank numbers as text. Document spreadsheet column formatting to avoid scientific notation and lost leading zeros.

Use versioned headers and deterministic schemas. Prevent spreadsheet formula execution in every exported textual cell, including error files, and define/test how protected values are restored during supported re-import. Report exports and re-importable data exports must be clearly distinguished. Empty exports still include defined headers.

## Implementation architecture and integrity

- Keep work inside the Business Server: typed preload/local API boundaries, Zod validation, application/domain services, and SQLite. Never make the Platform the operational import authority or let a renderer/Business App write SQLite.
- Introduce shared dataset adapters for templates, mapping, validation, identity resolution, preview, commit and export. Extract affected screens from the large `App.tsx` incrementally; do not make a whole-app rewrite a prerequisite.
- Persist jobs, files, validation errors, commit checkpoints, source-to-UUID mappings and audit metadata. Job states should distinguish uploading, validating, awaiting review, ready, running, partially completed, completed, failed and cancelled. Restart recovery must not guess whether a row committed.
- Use bounded parsing and a background execution design that preserves local API responsiveness. Keep a controlled SQLite writer; do not create competing uncontrolled workers. Stream or page exports from a consistent snapshot rather than loading the whole dataset into renderer memory.
- Validate the full dependency set before committing. Default to create-only; explicit update mode shows field differences and preserves omitted values. Reject ambiguous matches. Missing rows never imply delete/deactivate. Generate UUIDs locally and retain external reference mappings.
- Bind approval to the validated file checksum, schema version, options and revision snapshot. Any changed file, mapping or relevant data invalidates the preview. Recheck permissions and module entitlement at commit and download, including revoked sessions.
- Use atomic record/document commits: domain write, audit, sync outbox and row checkpoint must succeed together. A purchase invoice is one unit, not independent line commits. Audit existing services before reuse: some domain writes and later audit/sync calls are separate and cannot simply be wrapped in an asynchronous SQLite transaction callback.
- Commit large independent master-data sets in bounded chunks. Promise partial completion with exact counts and safe resume, not whole-file rollback. Cancel between commits; retain committed records. Stock and finance posting batches use bounded, explicitly reviewed business units.
- Define idempotency by branch, dataset, source batch/document and row identity plus committed payload/version. Retrying a timed-out request, restarting, or resubmitting identical data must not duplicate stock, debt, invoices or audit effects. A changed payload under a committed key must be a conflict, not a silent replay.
- Opening stock must create movements with cost/batch/serial rules; opening balances must create ledger entries. Master-data updates cannot overwrite balances or completed transactions. Imported financial history must not be presented as newly earned sales.
- Coordinate a cutover time for opening balances and stock. Track completed opening postings by account/location/source reference, prevent duplicate openings, and handle corrections through referenced reversals or compensating entries. A blanket database restore is inappropriate once subsequent trading has occurred.
- Scope data to the configured business/branch independently of supplied CSV values. Require dataset permissions and separate posting approvals. Do not infer authorization solely from a visible button or a generic authenticated session.
- Store temporary files privately outside the application installation directory; impose measured row, column, field-length, byte and disk-space limits. Restrict job/download access by actor and authorized administrator scope, audit exports, expire artifacts, and avoid raw personal data in logs.
- Share validated domain behavior between individual and bulk operations. Review copied API/validation/sync packages deliberately; test the receiving Platform and Business App contracts for any changed payloads. Ordinary imported records follow normal UUID-based synchronization; source CSV files are not synchronized as business records.
- Connect jobs to maintenance, backup and update handling. Finish or safely pause writes before restore/update. A backup and documented recovery path are required before opening stock/balance posting, with explicit reconciliation after recovery.

## Delivery phases and exit gates

Phases are dependency-ordered. Durations should be estimated after Phase 0 confirms business scale, data quality and service gaps.

| Phase | Deliverables | Exit gate |
| --- | --- | --- |
| **0. Confirm workflows and contracts** | Anonymized representative samples; record/warehouse/user volumes; target hardware; cutover policy; field dictionary; duplicate/update rules; permission matrix; supported dataset list; screen wireframes; baselines for API latency and exports | Product and implementation owners agree the owner pack and acceptance criteria; every proposed column has a defined rule; unresolved ledger/unit/identity behavior is recorded |
| **1. Shared transfer foundation and export repair** | Persistent jobs, templates, parser, staging, error CSV, preview, checkpoint/idempotency model, history UI, authorized downloadable exports; correct current report download path; searchable/paginated shared lists | Validating a file writes no operational data; cross-branch and unauthorized actions fail; report exports exceed preview limits correctly; restart/resume demonstrated with a small reference dataset |
| **2. Reference and catalog onboarding** | Categories, brands, units, warehouses, products, barcodes, conversions, prices; full filtered exports; preserve owner SKUs; reference resolution and create/update differences | Owner sample catalog imports and reconciles; duplicate retries create nothing extra; price/conversion and barcode tests pass; large lists remain usable |
| **3. People and controlled opening position** | Customer/supplier profiles; separate receivable/payable and opening-stock wizards; batch/serial support where applicable; approvals, cutover, backup and reconciliation receipts | Owner counts, stock valuation, receivables and payables reconcile; restart cannot double-post; rejected rows post nothing; correction and sync cases pass |
| **4. Daily bulk operations** | Multi-line purchase drafts with header/line templates, separate receiving; stock-count sessions; approved price updates; optional service catalog; additional operational exports | Invoice grouping and totals match source; receipt cannot run twice; concurrent sales/count conflicts are handled; role-specific Business App flows remain correct |
| **5. Scale and release hardening** | Performance/load fixtures, bounded memory/storage limits, cancellation/restart recovery, security and accessibility coverage, upgrade migrations, contract verification, support runbook | All functional and nonfunctional gates below pass on the supported Windows hardware and packaged build; no unresolved defect can duplicate or corrupt business records |
| **6. Pilot and production readiness** | Supervised installations, owner template training, staged rollout, signed installer, monitoring and escalation ownership | Representative owners complete setup and exports successfully; financial/stock reconciliations signed off; pilot issues closed; release evidence recorded |

The first useful release is **Phases 0–3 plus the applicable Phase 5–6 gates**. Phases 4 and P2 features can follow. This delivers complete catalog/people/opening-position onboarding rather than a product upload that leaves opening stock and debts to manual entry.

## Verification and readiness criteria

Existing tests include purchases, inventory routes, customer debt, reporting, synchronization, security, backup/recovery and update safety. Their presence is not evidence they currently pass. Add tests for every new business rule; extend the readiness command so new CSV suites actually run.

| Area | Required evidence |
| --- | --- |
| CSV and templates | Blank/header-only, BOM, quotes/newlines, alternate mapped delimiter, Unicode, huge fields, malformed files, leading zeros, decimals, dates, formula text, duplicate/unknown columns and supported schema versions |
| Identity and validation | Duplicate within/across files, conflicting external keys, create/update/clear semantics, missing parents, disabled units, barcode collision and stable correction row IDs |
| Domain integrity | Stock movements and valuation, conversions, batch/expiry/serial counts, customer/supplier ledger signs and totals, invoice atomicity, no completed-history overwrite |
| Recovery | Crash before/after each commit boundary, duplicate submit, disk full, database busy, cancellation, app restart, maintenance/update/restore overlap and exact partial-result reporting |
| Access and privacy | Unauthorized preview/commit/export/download, stale approval, revoked session, disabled module, wrong branch, another user's job/file and masked restricted fields |
| Synchronization | Offline import, reconnect, repeated delivery, update after import, backlog drainage, no duplicate Platform records and accurate Monitoring data |
| UX and accessibility | Owner downloads and fills templates without internal IDs; keyboard/focus navigation, actionable row errors, no silent truncation, all filtered records versus selected records clearly labeled |
| Exports | Known fixture counts/totals above 2,000 rows, deterministic headers/order, consistent snapshot, dates/timezone rules, protected text, empty exports and authorized final download |
| Scale | Proposed benchmark tiers: 1,000 / 10,000 / 100,000 product rows plus representative barcodes and transactional lines. Confirm actual supported maximum in Phase 0. Measure peak memory, job duration, disk use, SQLite contention and simultaneous checkout latency |
| Packaged release | Run functional CSV tests, existing affected suites, `npm run test:readiness`, `npm run typecheck`, `npm run build`, Playwright flows, then signed Windows installation/upgrade and offline smoke tests |

Proposed performance acceptance: no renderer freeze during transfer; progress remains available; bounded memory as files grow; no checkout failures attributable to import; agree an acceptable p95 checkout-latency regression against measured baseline before release. These are targets pending measurements, not current guarantees.

Pilot with representative stock-heavy businesses and a business with opening customer/supplier balances; include multi-warehouse and optional service workflows when in the release scope. First rehearse using protected sample data in an isolated installation, then perform a supervised cutover with owner-approved control totals. Observe a complete trading, export and synchronization cycle before wider rollout.

Release documentation must include the owner template guide, implementation checklist, supported file limits, error guide, recovery/correction procedure, permission setup and release notes. Mark readiness only after recording actual results, hardware, dataset size and unresolved limitations.

## Implementation follow-through (2026-09-13)

The implementation is in `C:\laragon\www\marketlens-business-server`, the standalone sibling project. See its [CSV owner and implementation guide](../../../marketlens-business-server/docs/CSV-DATA-TRANSFERS.md).

Delivered: template/example/guide ZIP; multiple-file selection and mapping; snapshot validation; row errors; explicit create/update preview; approvals, checkpoints, cancellation/history/resume; screen CSV toolbars and central browser; full filtered exports and repaired report downloads. Catalog, people, opening stock/debts, purchase drafts, count drafts and optional services are implemented. Staff/configuration/ownership remain export-only under the P2 boundaries.

Opening positions use movements/ledgers and require approval plus backup. Purchase receipt and count approval are separate confirmed actions. CSV changes queue versioned UUID snapshots using existing Platform entities. Schema migration 4 adds local tables and permissions. No live business records were imported during implementation.

Automated results are recorded in the sibling guide. A synthetic 10,000-product preview/commit passed in about 38 seconds on this development machine, using 100 bounded commits with event-loop yielding during validation. This is not a checkout-latency or supported-hardware guarantee. Imports are capped at 10,000 rows / 8 MiB per batch; split larger datasets in dependency order.

Phase 1-4 implementation is present within the documented scope, with automated Phase 5 checks. Owner samples/control totals and operational acceptance remain release gates: reconciliation, restore rehearsal, live Platform/Monitoring synchronization, target-hardware concurrency measurements, accessibility review and signed Windows installation/upgrade. Production readiness is not established by a source build alone.
