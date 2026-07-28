# Database Documentation

This section will document database design for the Platform and Shop App.

Key rules:

- Preserve tenant and branch isolation.
- Use UUIDs for synchronized records.
- Preserve immutable transactional records.
- Use stock movements for stock changes.
- Use ledger transactions for debt balance changes.
