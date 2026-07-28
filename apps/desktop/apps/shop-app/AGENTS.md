# Shop App Agent Guide

The Shop App owns the local API server and local SQLite database for a shop or
branch. It is the local source of truth for operational activity.

- Client Apps must call Shop App APIs for all transaction writes.
- Do not expose unrestricted Node.js APIs to renderer processes.
- Use typed preload APIs and authenticated local-network requests.
- Keep operational records auditable and immutable once business modules exist.
