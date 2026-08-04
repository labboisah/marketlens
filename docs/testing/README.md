# MarketLens Testing Documentation

Testing is a release gate for MarketLens. Critical business rules, permission
checks, tenant isolation, branch isolation, synchronization behavior, stock
movement logic, debt ledger behavior, and API contracts require automated tests.

## Documents

- [Test Strategy](test-strategy.md)

## Required Gates

- No untested critical business rules.
- No merge with failing CI.
- No production migrations without rollback review.
- No breaking API changes without versioning.
- No synchronization changes without compatibility tests.

## CI Scope

CI workflows cover:

- Platform lint and tests.
- Desktop lint, type-check, and tests.
- Monitoring lint and tests.
- Dependency audit.
- Documentation-link validation.

## Minimum Expectations

- Unit tests for deterministic business logic.
- Integration tests for database, queue, API, and sync boundaries.
- End-to-end tests for the highest-risk user workflows.
- Contract tests for shared API and synchronization packages.
- Regression tests for bugs fixed in critical workflows.
