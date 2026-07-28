# MarketLens API Contracts

Versioned API contracts shared across MarketLens desktop apps and Platform API
integration code.

The package exports Zod schemas as runtime validators and inferred TypeScript
types for:

- Standard success and error responses.
- API error codes.
- Cursor pagination metadata.
- Date, money, and quantity formats.
- Correlation and idempotency headers.
- Client App to Shop App authentication headers.
- Shop App to Platform synchronization headers.
- Monitoring App to Platform authentication headers.

Import versioned contracts from `@marketlens/api-contracts/v1`.

Contracts must be application-independent. Breaking changes require
documentation and coordinated updates across consumers.
