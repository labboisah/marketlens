# MarketLens Product Roadmap

This roadmap defines the milestone path to the first MarketLens MVP. Each
milestone includes goal, deliverables, dependencies, acceptance criteria, tests,
and documentation requirements.

## Milestone 1: Foundation

### Goal

Establish the repository, architecture, shared tooling, design system, database
standards, API standards, synchronization protocol, security baseline, and CI
gates.

### Deliverables

- Four-application repository structure.
- Development environment documentation.
- Design system and styling foundations.
- Database architecture documentation.
- API standards and shared contracts.
- Synchronization protocol documentation.
- Security and testing strategy.
- Initial CI workflows.

### Dependencies

- Approved architecture.
- Approved technology stack.
- Shared coding standards.

### Acceptance Criteria

- Documentation exists for architecture, database, API, synchronization,
  security, testing, and setup.
- Shared contract packages expose versioned contracts.
- CI workflow files exist for Platform, Desktop, Monitoring, dependency audit,
  and documentation-link validation.

### Tests Required

- Contract package tests.
- Scaffold boundary tests.
- Documentation-link validation in CI.

### Documentation Required

- Architecture overview.
- Development environment guide.
- Design system.
- Database docs.
- API docs.
- Synchronization docs.
- Security docs.
- Testing docs.

## Milestone 2: Business Operations

### Goal

Build the Business Server as the local source of truth for daily retail operations.

### Deliverables

- Business configuration.
- Local user authentication.
- Roles and permissions.
- Products, categories, units, and prices.
- Customers and suppliers.
- Purchases and inventory movements.
- Sales with mixed payments.
- Expenses.
- Customer debt ledger.
- Cashier shifts.
- Local reports.
- Audit logs.
- Backup and recovery.

### Dependencies

- Database schema migrations.
- Local service/repository foundation.
- Shared validation and permission contracts.
- Design system implementation for desktop UI.

### Acceptance Criteria

- Business can sell, receive stock, track payments, record expenses, and manage
  customer debt without internet.
- Completed sales are immutable.
- Inventory quantity changes are movement-based.
- Debt balances are ledger-based.
- Operational user permissions are enforced server-side.
- Backups can be created and restored in test environments.

### Tests Required

- Unit and service tests for business rules.
- SQLite integration and migration tests.
- Fastify API tests.
- Permission tests.
- Sales, payment, inventory, debt, and shift tests.
- Backup and restore tests.
- Audit-log tests.

### Documentation Required

- Business Server setup guide.
- Local database schema notes.
- Operational user and permission guide.
- Backup and recovery guide.
- Local reports guide.

## Milestone 3: Local Business App

### Goal

Provide role-based workstations that connect to the Business Server over LAN or Wi-Fi.

### Deliverables

- Device pairing.
- Login.
- Role-aware navigation.
- Cashier POS.
- Product search.
- Customer selection.
- Mixed payment.
- Receipt printing.
- Shift opening and closing.
- Storekeeper stock screens.
- Manager approvals.
- Basic reports.

### Dependencies

- Business Server local API.
- Business Server device authorization.
- Shared API contracts.
- Shared desktop UI package.
- Secure preload API foundation.

### Acceptance Criteria

- Business App never opens SQLite.
- Business App only connects to an authorized Business Server.
- POS transaction status remains pending until confirmed by Business Server.
- Role navigation follows Business Server permissions.
- Receipt printing uses controlled preload APIs.

### Tests Required

- Component tests.
- Form validation tests.
- Permission tests.
- Connection and pairing tests.
- POS workflow tests.
- Printing tests.
- Playwright end-to-end smoke tests.

### Documentation Required

- Business App setup guide.
- Pairing and manual server configuration guide.
- Receipt printing guide.
- Role navigation guide.

## Milestone 4: Synchronization

### Goal

Synchronize approved Business Server records to the Platform safely and recoverably.

### Deliverables

- Local sync queue.
- Batch creation.
- Device-authenticated Platform sync upload.
- Idempotency handling.
- Partial success handling.
- Conflict tracking.
- Exponential retry and recovery.
- Last successful sync status.
- Sync status UI in Business Server.

### Dependencies

- Business Server operational records.
- Platform device activation.
- Shared sync contracts.
- Synchronization protocol.
- Platform replicated table foundation.

### Acceptance Criteria

- Duplicate batches do not create duplicate Platform records.
- Accepted records are acknowledged locally.
- Failed records remain in local queue.
- Retry uses exponential backoff.
- Cloud response identifies accepted, rejected, and conflicting records.
- Password hashes and operational access tokens are never synchronized.

### Tests Required

- Sync contract tests.
- Queue tests.
- Idempotency replay tests.
- Partial success tests.
- Conflict tests.
- Device authentication and revocation tests.
- Internet interruption recovery tests.

### Documentation Required

- Sync protocol implementation notes.
- Conflict-resolution guide.
- Sync troubleshooting guide.
- Device credential handling guide.

## Milestone 5: Platform Reporting

### Goal

Make synchronized data useful for owners and support teams through Platform
summaries and dashboards.

### Deliverables

- Businesses and branches.
- Plans and subscriptions.
- Device activation.
- Monitoring authentication.
- Replicated sales, payment, expense, stock, debt, shift, and alert summaries.
- Dashboard summary APIs.
- Application-version management.

### Dependencies

- Platform tenancy foundation.
- Synchronization ingestion.
- Database indexing and summary strategy.
- API standards and OpenAPI foundation.

### Acceptance Criteria

- Platform enforces tenant and branch isolation.
- Monitoring users can access only authorized businesses and branches.
- Dashboard APIs return synchronized summaries.
- Last synchronization timestamps are available per branch/device.
- Application versions can be listed for device update policy.

### Tests Required

- Policy tests.
- Tenant-isolation tests.
- Branch-isolation tests.
- API tests.
- Queue/report summary tests.
- Subscription and device activation tests.

### Documentation Required

- Platform setup and deployment notes.
- API endpoint documentation.
- Reporting summary documentation.
- Subscription and device activation guide.

## Milestone 6: Monitoring App

### Goal

Deliver Android-first remote owner visibility using Platform data only.

### Deliverables

- Login.
- Business and branch selection.
- Daily sales.
- Payment breakdown.
- Expenses.
- Stock alerts.
- Customer debt.
- Cashier performance.
- Last synchronization status.
- Push notifications.

### Dependencies

- Platform monitoring APIs.
- Platform authentication.
- Push notification setup.
- Monitoring App theme and navigation foundation.

### Acceptance Criteria

- Monitoring App never connects directly to Business Servers.
- Cached data is read-only.
- Stale data is clearly identified.
- Last successful business synchronization time is visible.
- Tokens use secure storage.
- Sensitive screens can require biometric re-authentication where configured.

### Tests Required

- Unit tests.
- Riverpod provider tests.
- Repository tests.
- Widget tests.
- Navigation tests.
- Cache tests.
- API integration tests.
- Android integration smoke tests.

### Documentation Required

- Monitoring App setup guide.
- Firebase setup guide.
- Secure storage and biometric notes.
- Push notification behavior notes.

## Milestone 7: Pilot Testing

### Goal

Validate MarketLens with real pilot businesses before commercial launch.

### Deliverables

- Pilot installation package.
- Pilot onboarding checklist.
- Data backup process.
- Support diagnostics.
- Pilot feedback tracker.
- Bug-fix and stabilization cycle.

### Dependencies

- Business operations.
- Business App POS workflows.
- Synchronization.
- Platform reporting.
- Monitoring App MVP.

### Acceptance Criteria

- Pilot businesses can operate for full business days.
- Offline operation is verified.
- Sync recovery is verified after internet interruptions.
- Pilot owners can view accurate remote summaries.
- Critical bugs have severity, owner, and resolution status.

### Tests Required

- End-to-end pilot scenarios.
- Offline/online transition tests.
- Backup restore drills.
- Installer smoke tests.
- Performance tests for common POS and reporting paths.

### Documentation Required

- Pilot runbook.
- Known limitations.
- Support escalation guide.
- Backup and restore checklist.

## Milestone 8: Commercial Release

### Goal

Release MarketLens MVP for paid commercial use.

### Deliverables

- Signed desktop installers.
- Android release build.
- Production Platform deployment.
- Subscription and plan enforcement.
- Support workflow.
- Release notes.
- Operational monitoring.

### Dependencies

- Successful pilot completion.
- Security review.
- Performance review.
- Release signing and deployment setup.
- Support readiness.

### Acceptance Criteria

- CI passes.
- No critical open security issues.
- No unresolved critical pilot defects.
- Production migrations have rollback review.
- API and synchronization contracts are versioned.
- Installers and release artifacts are signed.

### Tests Required

- Full regression suite.
- Production-like deployment smoke tests.
- Upgrade and rollback tests.
- Security smoke tests.
- Installer and Android release smoke tests.

### Documentation Required

- Release notes.
- Deployment runbook.
- Customer onboarding guide.
- Support playbook.
- Incident response checklist.
