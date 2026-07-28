# API Authentication

Authentication depends on the API boundary.

## Client App to Shop App

The Client App authenticates against the Shop App local API.

Requirements:

- Device pairing is required before login.
- User authentication is handled by the Shop App.
- Client App permissions come from the Shop App.
- All transaction writes must include an authenticated session.
- Client Apps must never bypass the Shop App API or open SQLite.

Recommended headers:

```text
Authorization: Bearer local-session-token
X-MarketLens-Device-UUID: uuid
X-Correlation-ID: uuid
Idempotency-Key: unique-operation-key
```

## Shop App to Platform

The Shop App authenticates to the Platform with activated device credentials.

Requirements:

- HTTPS is required.
- Every device has a signed credential.
- Sync requests use HMAC signatures.
- Timestamps and nonces are validated for replay protection.
- Revoked devices are rejected.
- Password hashes and operational access tokens are never synchronized.

## Monitoring App to Platform

The Monitoring App authenticates to the Platform using Platform-issued tokens.

Requirements:

- Tokens must be stored in secure storage.
- Business and branch access follows Platform authorization.
- Sensitive screens may require biometric re-authentication.
- Monitoring APIs return synchronized summaries, not direct Shop App data.

## Correlation and Traceability

All API callers should send:

```text
X-Correlation-ID: uuid
```

Servers should log correlation IDs and return `trace_id` on error responses.
