# Pagination, Filtering, and Sorting

MarketLens APIs use cursor pagination for list endpoints.

## Request

```text
GET /api/v1/monitoring/branches?limit=25&cursor=opaque-cursor
```

## Response Meta

```json
{
  "success": true,
  "message": "Branches retrieved",
  "data": [],
  "meta": {
    "pagination": {
      "limit": 25,
      "next_cursor": "opaque-cursor",
      "previous_cursor": null,
      "has_more": true
    }
  }
}
```

## Cursor Rules

- Cursors are opaque to clients.
- Cursor results must be deterministic.
- Default limit should be endpoint-specific.
- Maximum limit should be enforced server-side.
- Cursor pagination should include a stable tie-breaker, usually UUID or ID.

## Filtering

Use allow-listed `filter` query parameters:

```text
?filter[business_uuid]=uuid&filter[branch_uuid]=uuid&filter[status]=active
```

## Date Filtering

Use ISO-8601 timestamps or date strings:

```text
?filter[from]=2026-07-01T00:00:00Z&filter[to]=2026-07-28T23:59:59Z
```

Business-day reports may accept local dates:

```text
?filter[date]=2026-07-28&filter[timezone]=Africa/Lagos
```

## Sorting

Use comma-separated sort fields:

```text
?sort=-created_at,name
```

Rules:

- Sort fields must be allow-listed.
- Prefix descending fields with `-`.
- Default sorting must be documented per endpoint.
