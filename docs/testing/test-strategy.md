# MarketLens Test Strategy

MarketLens testing follows ownership boundaries. Tests should live near the app
or package that owns the behavior.

## Platform

Platform uses Laravel testing tools with Pest conventions where available.

Required categories:

- Pest unit tests for domain services, casts, enums, value objects, and pure
  calculations.
- Feature tests for HTTP workflows and application behavior.
- Policy tests for authorization rules.
- Tenant-isolation tests for every tenant-owned module.
- Branch-isolation tests for branch-scoped records and reports.
- API tests for `/api/v1/*` response formats, validation, authentication, and
  rate limits.
- Synchronization tests for batch ingestion, idempotency, conflicts, partial
  success, and acknowledgements.
- Queue tests for sync processing, reports, notifications, and retries.
- Livewire tests for administrator and owner dashboard components.

## Shop App

Shop App tests must prove the local source of truth remains correct while
offline.

Required categories:

- Unit tests for pure business rules and validation.
- Service tests for authentication, permissions, sales, inventory, shifts,
  backup, and synchronization services.
- Repository tests for Drizzle access patterns.
- SQLite integration tests for constraints, PRAGMAs, transactions, and
  persistence behavior.
- Migration tests for local schema creation and upgrade safety.
- Fastify API tests for local endpoints, validation, authentication,
  idempotency, and error format.
- Synchronization tests for local queue status, batch creation, retries, and
  conflict handling.
- Backup and restore tests.
- Socket.IO tests for connection and sync status events.
- Electron smoke tests for secure BrowserWindow settings and preload exposure.

## Client App

Client App tests must prove role-based UI behavior and Shop App API dependence.

Required categories:

- Component tests for shared shell, connection status, navigation, dialogs, and
  workflow surfaces.
- Form tests with React Hook Form and Zod validation.
- Permission tests to ensure hidden UI does not imply server authorization.
- Connection tests for discovery, manual IP configuration, pairing, login, and
  reconnect states.
- POS workflow tests for pending/confirmed transaction behavior.
- Printing tests for controlled preload APIs.
- Playwright end-to-end tests for connection, login, role navigation, and core
  cashier smoke flows.

## Monitoring App

Monitoring App tests must prove Platform-only, read-only, freshness-aware
monitoring behavior.

Required categories:

- Unit tests for formatting, freshness, cache policy, and notification policy.
- Riverpod provider tests.
- Repository tests with mock and fake API clients.
- Widget tests for dashboards, status banners, branch selectors, and security
  alert tiles.
- Navigation tests for GoRouter routes.
- Cache tests for Drift read-only summaries and stale-data labeling.
- API integration tests against Platform test doubles.
- Android integration tests for secure storage, biometric prompts, push
  notification behavior, and release smoke flows.

## Shared Packages

Required categories:

- Contract tests for `api-contracts`.
- Contract tests for `sync-contracts`.
- Permission matrix tests for `permissions`.
- Calculation tests for `calculations`.
- Validation tests for `validation`.
- UI component tests where practical.

## Migration Review

Production migrations require:

- Rollback plan.
- Test data migration run.
- Large-table risk assessment.
- Synchronization compatibility review when synced entities change.
- Documentation update.

## API Compatibility Review

Breaking API changes require:

- New API version or explicitly documented compatibility plan.
- Updated shared contracts.
- Consumer migration notes.
- Contract tests.

## Synchronization Compatibility Review

Synchronization changes require:

- Old payload compatibility tests.
- Duplicate replay tests.
- Partial success tests.
- Conflict handling tests.
- Device authentication and revocation tests.
