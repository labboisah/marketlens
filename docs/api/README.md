# MarketLens API Standards

This document defines shared API standards for all MarketLens API boundaries:

- Business App to Business Server local API.
- Business Server to Platform synchronization and cloud APIs.
- Monitoring App to Platform cloud API.

All HTTP APIs must be versioned under `/api/v1/`.

## Documents

- [Error Format](error-format.md)
- [Versioning](versioning.md)
- [Pagination](pagination.md)
- [Authentication](authentication.md)

## Standard Success Response

```json
{
  "success": true,
  "message": "Operation completed",
  "data": {},
  "meta": {}
}
```

## Standard Error Response

```json
{
  "success": false,
  "message": "Validation failed",
  "code": "VALIDATION_ERROR",
  "errors": {},
  "trace_id": "uuid"
}
```

## Validation Conventions

- Validate every request at the API boundary.
- Reject unknown dangerous fields for write requests.
- Return validation failures with `code: "VALIDATION_ERROR"`.
- Use stable field names in `errors`.
- Do not leak SQL, stack traces, secrets, tokens, or internal exception details.

## Dates and Timezones

- Use ISO-8601 strings for timestamps.
- Store and exchange API timestamps in UTC.
- Include timezone offsets when accepting user-entered business dates.
- Business reporting filters may accept local date strings, but responses should
  identify the timezone used to compute summaries.

Examples:

```text
2026-07-28T12:30:00Z
2026-07-28
```

## Money Format

Prefer integer minor units:

```json
{
  "amount_minor": 125000,
  "currency": "NGN"
}
```

Never use floating-point values for currency. If decimal strings are required
for display or integration, send them as strings, not JSON numbers.

## Quantity Format

Quantities should be decimal strings to preserve precision:

```json
{
  "quantity": "12.500000",
  "unit_uuid": "uuid"
}
```

## Filtering and Sorting

Use query parameters:

```text
?filter[branch_uuid]=uuid&filter[status]=active&sort=-created_at,name
```

Rules:

- Filter keys must be allow-listed per endpoint.
- Sort fields must be allow-listed per endpoint.
- Prefix descending sort fields with `-`.
- Default sorting should be deterministic.

## Idempotency

Write endpoints that can be retried must accept an idempotency key.

Recommended header:

```text
Idempotency-Key: unique-operation-key
```

Synchronization records also include `idempotency_key` inside the payload.

## Correlation IDs

Every API request should have a correlation ID.

Recommended header:

```text
X-Correlation-ID: uuid
```

If the client does not provide one, the server should generate one. Error
responses return `trace_id`, which may equal the correlation ID.

## Rate Limits

Rate limits are applied by API boundary:

- Business App to Business Server: per client device, user, and LAN source where useful.
- Business Server to Platform: per business, branch, device, and endpoint group.
- Monitoring App to Platform: per user, business, token, and endpoint group.

Use `429 RATE_LIMITED` with retry metadata when possible.

## Shared Contracts

Shared TypeScript contracts live in:

- `C:\laragon\www\business-server\packages\api-contracts`
- `C:\laragon\www\business-server\packages\sync-contracts`
- `C:\laragon\www\business-app\packages\api-contracts`

Contracts use Zod schemas as runtime validators and inferred TypeScript types.
Breaking changes require versioning and documentation.
