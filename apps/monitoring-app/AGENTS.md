# MarketLens Monitoring App Agent Guide

This application is the Android-first mobile Monitoring App for MarketLens
owners and authorized remote users.

## Application Boundary

- The Monitoring App connects only to the MarketLens Platform.
- It must never connect directly to Business Servers.
- It displays synchronized Platform data, not live Business Server databases.
- It should retrieve summaries and report aggregates rather than full
  transaction databases.
- Business and branch access must follow Platform authorization.

## Data Freshness

- Always show the last successful business synchronization time where synchronized
  data is displayed.
- Clearly identify stale data.
- Cached reports may be available for offline viewing.
- Cached records are read-only and must never be edited locally.

## Security

- Authentication tokens must use secure storage.
- Sensitive screens may require biometric re-authentication.
- Push notifications must not expose sensitive financial information on locked
  screens by default.
- Do not commit Firebase credentials, API tokens, production endpoints, customer
  data, or local cache databases.

## Implementation Rules

- Keep production API integration behind repository interfaces.
- Use mock repositories for scaffold screens until Platform API contracts are
  implemented.
- Keep feature screens summary-oriented and branch/business scoped.
- Use Material 3 and the shared MarketLens semantic color system.
