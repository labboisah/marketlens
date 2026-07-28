# API Error Format

All MarketLens APIs use a consistent error envelope.

```json
{
  "success": false,
  "message": "Validation failed",
  "code": "VALIDATION_ERROR",
  "errors": {},
  "trace_id": "uuid"
}
```

## Fields

| Field | Description |
| --- | --- |
| `success` | Always `false` for errors. |
| `message` | Human-readable safe summary. |
| `code` | Stable machine-readable error code. |
| `errors` | Field-level or record-level details. |
| `trace_id` | Correlation ID for logs and support. |

## Standard Error Codes

| Code | HTTP Status | Meaning |
| --- | --- | --- |
| `VALIDATION_ERROR` | 422 | Request shape or field validation failed. |
| `AUTHENTICATION_REQUIRED` | 401 | Missing or invalid authentication. |
| `AUTHORIZATION_DENIED` | 403 | User/device is authenticated but not allowed. |
| `NOT_FOUND` | 404 | Resource does not exist or is not visible to caller. |
| `CONFLICT` | 409 | State conflict or version conflict. |
| `IDEMPOTENCY_CONFLICT` | 409 | Reused idempotency key with different payload. |
| `RATE_LIMITED` | 429 | Request limit exceeded. |
| `SYNC_DEVICE_REVOKED` | 403 | Sync device is revoked. |
| `SYNC_BATCH_REJECTED` | 422 | Batch failed validation. |
| `SYNC_RECORD_CONFLICT` | 409 | One or more records require conflict handling. |
| `SERVER_ERROR` | 500 | Unexpected server error. |
| `SERVICE_UNAVAILABLE` | 503 | Temporary dependency or maintenance failure. |

## Validation Error Shape

Validation errors should use field names when possible:

```json
{
  "success": false,
  "message": "Validation failed",
  "code": "VALIDATION_ERROR",
  "errors": {
    "email": ["Email is required."],
    "records.0.record_uuid": ["Record UUID must be a valid UUID."]
  },
  "trace_id": "uuid"
}
```

## Security

Error responses must not expose:

- Stack traces.
- SQL queries.
- Password hashes.
- API tokens.
- Device signing secrets.
- Internal file paths.
- Customer data unrelated to the request.
