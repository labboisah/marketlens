# Business App Connection And Pairing

This guide covers connection setup, pairing, and session behavior for MarketLens Business App.

## Connection Flow

Business App depends on Business Server APIs.

1. Open Business App.
2. On the connection screen, provide Business Server base URL.
3. Save connection settings.
4. Pair or confirm device authorization.
5. Sign in with an authorized account.

## Pairing Behavior

- Device UUID is used for authorization and request headers.
- Session tokens and connection settings are stored through secure main-process stores.
- If session is missing or expired, Business App redirects to connection/session recovery states.

## Online And Offline States

- Connected: normal operations.
- Disconnected: read and write actions may be limited depending on workflow.
- Reconnect: Business App re-checks auth/session and refetches authoritative data.

For POS safeguards:
- In-progress sales are kept as safe drafts in session storage.
- Financial completion is blocked while disconnected.
- Pending sale status is verified on reconnect to prevent duplicate completion.

## Re-Authentication

On reconnect, Business App attempts:
1. Auth/session validation.
2. Refresh token fallback when required.
3. Role and permission refresh.

If re-authentication fails:
- Session is cleared.
- User is moved to session-expired flow.

## Security Notes

- Never enter platform credentials into unknown prompts.
- Confirm Business Server URL is expected before sign-in.
- External links open only with explicit user confirmation.
