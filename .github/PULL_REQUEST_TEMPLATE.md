# Summary

Describe the change and why it is needed.

## Scope

- [ ] Platform
- [ ] Shop App
- [ ] Client App
- [ ] Monitoring App
- [ ] Shared desktop package
- [ ] Documentation
- [ ] Infrastructure

## Architecture Checklist

- [ ] Preserves the four-application architecture.
- [ ] Keeps Shop App as the local operational source of truth.
- [ ] Does not allow Client Apps to write directly to SQLite.
- [ ] Does not allow Monitoring App to connect directly to Shop App.
- [ ] Maintains tenant and branch isolation.
- [ ] Preserves immutable transactional records.
- [ ] Uses stock movements for inventory changes.
- [ ] Uses ledger transactions for debt balance changes.
- [ ] Documents shared API or synchronization contract changes.

## Testing

Describe tests run and business rules covered.

## Documentation

List documentation updated, or explain why no documentation change is needed.

## Security

- [ ] No secrets, generated credentials, production environment values, or local
      databases are committed.
- [ ] Input validation and authorization boundaries are preserved.
