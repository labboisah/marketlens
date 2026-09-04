# Services Monitoring

This document describes the Platform-facing, read-only monitoring pages and API endpoints for services.

Navigation:

- Services
  - Overview: `/api/v1/services` (dashboard)
  - Service Orders: `/api/v1/services/orders`
  - Payments: `/api/v1/services/payments`
  - Expenses: `/api/v1/services/expenses`
  - Allocations: `/api/v1/services/allocations`
  - Settlements: `/api/v1/services/settlements`

Notes:

- All monitoring records are sourced from Business Server synchronization and are read-only on the Platform.
- Summaries are cached to avoid expensive real-time aggregation. Use `php artisan services:refresh-summaries {business_id}` to refresh cached summaries.
- Authorization must use module access checks and tenant isolation.
