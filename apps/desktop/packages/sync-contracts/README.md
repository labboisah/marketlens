# MarketLens Sync Contracts

Versioned synchronization payload contracts shared by Shop App and Platform
integration code.

The package exports Zod schemas as runtime validators and inferred TypeScript
types for:

- Synchronization protocol version `1.0`.
- Canonical batch envelope.
- Synchronized record operations.
- Accepted, rejected, and conflicting record response items.
- Batch response envelope.

Import versioned contracts from `@marketlens/sync-contracts/v1`.
