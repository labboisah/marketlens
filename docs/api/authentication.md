# API Authentication

Authentication depends on the API boundary.

## Business App to Business Server

The Business App authenticates against the Business Server local API.

Requirements:

- Device pairing is required before login.
- User authentication is handled by the Business Server.
- Business App permissions come from the Business Server.
- All transaction writes must include an authenticated session.
- Business Apps must never bypass the Business Server API or open SQLite.

Recommended headers:

```text
Authorization: Bearer local-session-token
X-MarketLens-Device-UUID: uuid
X-Correlation-ID: uuid
Idempotency-Key: unique-operation-key
```

## Business Server to Platform

The Business Server authenticates to the Platform with activated device credentials.

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
- Branch responses include configured operating modes; feature summaries must be limited to the selected branch's modes.
- Sensitive screens may require biometric re-authentication.
- Monitoring APIs return synchronized summaries, not direct Business Server data.

## Correlation and Traceability

All API callers should send:

```text
X-Correlation-ID: uuid
```

Servers should log correlation IDs and return `trace_id` on error responses.
