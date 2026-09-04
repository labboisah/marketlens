# MarketLens Synchronization Documentation

This section defines the Business Server to Platform synchronization protocol.

The Business Server pushes local authoritative operational records to the Platform.
The Platform validates, stores replicated/reporting data, and acknowledges
accepted records. The Platform does not become the daily operational authority
for business transactions.

## Documents

- [Protocol](protocol.md)
- [Conflict Resolution](conflict-resolution.md)
- [Security](security.md)
- [Failure Recovery](failure-recovery.md)

## Core Rules

- Business Server pushes records to the Platform.
- Platform acknowledges accepted records.
- Synchronization works in batches.
- Every batch has a UUID.
- Every synchronized record has a UUID.
- Every operation has an idempotency key.
- Every synchronized record has a version.
- Every device has a signed credential.
- Failed records remain in the local queue.
- Retry uses exponential backoff.
- Duplicate batches do not create duplicate records.
- Partial batch success is supported.
- Cloud responses identify accepted, rejected, and conflicting records.
- Payload compression is supported.
- Sync status is visible in the Business Server.
- Monitoring App sees last synchronization timestamps through Platform APIs.
- Password hashes and operational access tokens are never synchronized.

## Data Boundary

The synchronization channel may carry operational transaction records,
summaries, audit events, and limited operational-user profile data. It must not
carry local password hashes, local operational access tokens, or unrestricted
local database dumps.

```text
Business Server SQLite
    -> local sync queue
    -> signed HTTPS batch upload
    -> Platform validation and replication
    -> Platform acknowledgement
    -> Business Server checkpoint update
```

## Status Vocabulary

Recommended local sync status values:

- `pending`: queued locally and not yet uploaded.
- `uploading`: included in an in-flight batch.
- `accepted`: acknowledged by Platform.
- `rejected`: rejected by Platform and requires local remediation.
- `conflict`: accepted for review path or blocked by conflict policy.
- `retrying`: failed for retryable reason and scheduled with backoff.
- `dead_letter`: retry limit exceeded and operator/support attention required.
