# Summary

Describe the change and why it is needed.

## Scope

- [ ] Platform
- [ ] Business Server
- [ ] Client App
- [ ] Monitoring App
- [ ] Standalone desktop package
- [ ] Documentation
- [ ] Infrastructure

## Architecture Checklist

- [ ] Preserves the four-application architecture.
- [ ] Keeps Business Server as the local operational source of truth.
- [ ] Does not allow Client Apps to write directly to SQLite.
- [ ] Does not allow Monitoring App to connect directly to Business Server.
- [ ] Maintains tenant and branch isolation.
- [ ] Preserves immutable transactional records.
- [ ] Uses stock movements for inventory changes.
- [ ] Uses ledger transactions for debt balance changes.
- [ ] Documents shared API or synchronization contract changes.

## Testing

Describe tests run and business rules covered.

- [ ] No critical business rule is left untested.
- [ ] Synchronization changes include compatibility tests.
- [ ] API contract changes include versioning or are backward-compatible.

## Documentation

List documentation updated, or explain why no documentation change is needed.

- [ ] Production migrations include rollback review notes, if applicable.

## Security

- [ ] No secrets, generated credentials, production environment values, or local
      databases are committed.
- [ ] Input validation and authorization boundaries are preserved.
- [ ] CI is expected to pass before merge.
