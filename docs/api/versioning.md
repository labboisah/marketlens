# API Versioning

MarketLens APIs are versioned under `/api/v1/`.

```text
/api/v1/auth/*
/api/v1/device/*
/api/v1/sync/*
/api/v1/monitoring/*
```

## Policy

- API versions are path-based.
- `v1` is the first supported public contract.
- Additive, backward-compatible changes may remain in the same version.
- Breaking changes require a new version.
- Shared TypeScript contracts must expose versioned modules, for example
  `@marketlens/api-contracts/v1`.

## Backward-Compatible Changes

Examples:

- Adding optional response fields.
- Adding optional request fields.
- Adding new endpoints.
- Adding new error codes where old behavior still works.

## Breaking Changes

Examples:

- Removing or renaming fields.
- Changing field type or meaning.
- Making optional fields required.
- Changing authentication semantics.
- Changing idempotency behavior.
- Reinterpreting synchronization payloads.

## Deprecation Policy

Deprecation requires:

1. Documentation update.
2. Changelog entry.
3. Compatibility window.
4. Runtime warning header where practical.
5. Migration path for affected apps.

Recommended headers:

```text
Deprecation: true
Sunset: 2027-01-31T00:00:00Z
Link: <https://docs.marketlens.example/api/versioning>; rel="deprecation"
```

## Version Compatibility

Shop App to Platform synchronization must remain tolerant of older Shop App
versions during rollout. Sync batches include `protocol_version`, app version,
and schema version where available.
