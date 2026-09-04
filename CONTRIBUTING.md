# Contributing to MarketLens

Thank you for contributing to MarketLens. This project handles sales,
inventory, debt, and reporting workflows that businesses depend on, so changes
should be deliberate, tested, and documented.

## Before You Start

- Read [README.md](README.md).
- Read [AGENTS.md](AGENTS.md).
- Read the nearest application-level `AGENTS.md` when working inside an
  application or package.
- Identify the application or shared package that owns the change.

## Development Workflow

1. Create a focused branch for the change.
2. Keep implementation scoped to the relevant application or package.
3. Add or update tests for every business rule.
4. Update documentation when setup, architecture, security, synchronization, or
   contracts change.
5. Run the relevant formatter, static analysis, and test suite.
6. Open a pull request using the project template.

## Architectural Expectations

- Preserve the Platform, Business Server, Business App, and Monitoring App boundaries.
- Keep the Business Server as the local operational source of truth.
- Route Business App operational writes through the Business Server API.
- Route Monitoring App reads through the Platform API.
- Use UUIDs for synchronized records.
- Preserve immutable transactional records.
- Use stock movements for inventory changes.
- Use ledger transactions for debt balance changes.
- Avoid unreviewed shared contract changes.

## Code Quality

- Prefer simple, explicit, maintainable code.
- Use strict typing where supported by the application stack.
- Validate data at process, API, synchronization, and persistence boundaries.
- Keep user-facing workflows accessible, clear, and role-aware.
- Do not introduce new frameworks or major dependencies without architectural
  justification.

## Documentation

Update documentation in `docs/` when a change affects:

- System architecture.
- API contracts.
- Database design.
- Synchronization behavior.
- Security controls.
- Deployment steps.
- Testing strategy.
- Product behavior.

Significant architecture changes should be captured with an ADR based on
[docs/architecture/adr-template.md](docs/architecture/adr-template.md).

## Pull Request Checklist

- The change is scoped and named clearly.
- Business rules are covered by tests.
- Shared contracts are versioned or documented when changed.
- Documentation is updated where needed.
- No secrets, credentials, production environment values, local databases, or
  generated private files are committed.
