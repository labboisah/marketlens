# KernelBridge Feature Implementation Agent Guide

## Purpose

This guide explains how to add a new capability or limit to a KernelBridge plan and make it work correctly in both the product Platform and the client app.

Important rule:

- A KernelBridge feature key defines the license entitlement.
- Product-side configuration defines how that entitlement is enforced in the app.
- A feature is not considered implemented until both the entitlement and the product behavior are verified.

## Core principle

A KernelBridge plan change alone is not enough. The product must:

1. accept the entitlement from KernelBridge,
2. map it to the correct product behavior,
3. enforce it on the server,
4. apply the correct rules in the client UI, and
5. test the full path end-to-end.

Never treat the plan feature as the final implementation. The plan only provides the grant source.

## Required workflow

### Phase 1: define the feature contract

Before editing code, decide these points:

- product code and scope
- exact canonical key name
- type: boolean or numeric limit
- business scope or per-device scope
- default value and whether it is editable
- behavior when the feature is missing or malformed
- whether the feature is enforced by product server, client UI, or both

Examples:

- `mode_sales` => boolean; allows sales mode at the business/license level
- `max_server_installations` => numeric limit; counts active server installations
- `max_client_app_devices` => numeric limit; counts active and pending client devices
- `module_shareholders` => boolean; allows shareholder access only if the product enforces it

Rules:

- Use stable lowercase snake_case feature keys.
- Do not reuse a key for another meaning.
- Missing boolean features must not be interpreted as granted.
- Missing numeric limits should fail closed unless the product intentionally defines a safe default.

### Phase 2: Add the feature to KernelBridge plan catalog

In KernelBridge:

- add the feature key to the plan feature matrix,
- assign a label and description,
- set a default provisional value if it is a limit,
- preserve admin edits when the seeder runs again,
- keep the feature in the Product Integration API response.

Implementation requirements:

- The feature must be included in the plan model used by KernelBridge.
- If this is a seeded plan, use `firstOrCreate` semantics so rerunning the seeder does not overwrite admin edits.
- The default limit should be safe and explicit, not accidental.

Example:

```php
$plan->features()->firstOrCreate(
    ['feature_key' => 'max_client_app_devices'],
    [
        'feature_name' => 'Business App devices',
        'description' => 'Maximum active and pending Business App devices covered by this plan.',
        'enabled' => true,
        'limit_value' => '1',
    ],
);
```

### Phase 3: ensure legacy subscriptions are handled safely

Older subscriptions may not have a snapshot row for a new feature. The product must backfill from the plan when the feature is missing.

KernelBridge must:

- check whether the entitlement row already exists,
- prefer the explicit row if present,
- otherwise use the plan feature as a fallback,
- do not silently override an explicit admin change.

This is essential for backward compatibility.

### Phase 4: normalize the entitlement in the product platform

On the product Platform side:

- consume KernelBridge entitlement data from the normalized response,
- map it to product-specific feature keys,
- store the effective entitlement snapshot,
- reject malformed or missing data,
- refresh entitlements after admin changes or account-link approval.

The platform should not spread provider-specific fields through the app. Instead, normalize everything into a single entitlement map.

For example:

```php
$entitlements = [
    'mode_sales' => true,
    'max_client_app_devices' => 1,
    'module_shareholders' => false,
];
```

### Phase 5: enforce the feature in product server logic

This is the part that makes the feature real.

Server-side rules must:

- verify the KernelBridge license is active,
- read the correct entitlement key,
- ensure the value is valid,
- reject access when the entitlement is absent or invalid,
- count only the relevant active/pending resources,
- fail closed instead of silently falling back to a weaker local rule when a valid KernelBridge license exists.

Examples:

- If `mode_sales` is disabled, sales branch configuration should be rejected.
- If `max_client_app_devices` is set to 1, only one active/pending Business App device should be able to be activated.
- If `module_shareholders` is missing, shareholder operations must be denied even if the UI still shows them.

### Phase 6: apply the feature in the Platform UI

The Platform UI should:

- show available functionality only when the entitlement is valid,
- hide or disable actions when the entitlement is not allowed,
- show clear messages when a feature is unavailable,
- link users to the license refresh or account-link flow when appropriate.

Important: UI hiding is not enforcement. The server must still reject forbidden actions.

For a consistent KernelBridge activation experience, render `@kernelbridge/activation-ui-react` on product account-link pages, including Laravel/Livewire platforms. The browser may submit an activation code only to authenticated same-origin product routes; those routes must call KernelBridge server-side, bind the link to the current business, and never persist or expose the integration credential. Keep product-specific account ownership, status refresh, completion redirects, and unlink operations in the product backend.

### Phase 7: apply it in the client app

The client app must:

- read the effective entitlement snapshot from the trusted server or signed payload,
- enforce the feature on the client side only as UX guidance,
- never trust the client as the security boundary,
- keep the same business rules as the server for final authorization.

Examples:

- Business Server or desktop app must reject activation when the license limit has been reached.
- Client app must not create a new device if the active license cap is already exhausted.
- If the product requires a license-linked account, the client must rely on server validation and not use local-only business rules.

### Phase 8: write tests to cover the release path

For every new feature, add tests for:

- seeded plan feature exists with correct default value,
- rerunning the seeder preserves admin edits,
- missing entitlement row falls back to plan correctly,
- valid active KernelBridge license accepts the feature,
- inactive or missing license rejects it,
- limit enforcement counts active and pending resources correctly,
- product Platform and client app both reject forbidden actions,
- no local fallback is used when a valid linked license is active.

Recommended test categories:

- KernelBridge plan seeder tests
- entitlement snapshot fallback tests
- Platform account-link + entitlement refresh tests
- product activation tests
- client app enrollment/activation tests
- negative validation tests for missing/invalid entitlements

### Phase 9: update docs and handoff notes

Update directly related docs:

- canonical feature key list
- plan limits and purpose
- product-side enforcement rules
- account-link and entitlement refresh workflow
- deployment or release guidance for the feature

This is critical for future products. Without good documentation, each new product will repeat the same mistakes.

## Required implementation checklist

### KernelBridge

- [ ] Add feature key to plan feature catalog
- [ ] Add default value and description
- [ ] Ensure seeder is idempotent and preserves admin edits
- [ ] Add entitlement snapshot fallback when rows are missing
- [ ] Ensure Product Integration API returns the key
- [ ] Add test coverage for the new key

### Product Platform

- [ ] Map feature key to product capability
- [ ] Validate active license and entitlement value
- [ ] Enforce the rule server-side
- [ ] Fail closed when the feature is missing or invalid
- [ ] Refresh linked license entitlements properly
- [ ] Add product tests for the rule

### Client app / desktop app

- [ ] Use the server-issued active entitlement snapshot
- [ ] Hide or disable unsupported actions in UX
- [ ] Reject forbidden actions in runtime logic
- [ ] Keep all final enforcement on the trusted server side
- [ ] Add activation and enforcement tests

## Anti-patterns to avoid

- adding a feature to a plan and assuming the product works automatically
- enforcing rules only in the UI
- silently falling back to local subscription rules if a linked KernelBridge license is active
- allowing missing/invalid entitlement values to be treated as enabled
- reusing old keys with different semantics
- updating only one side of the stack (KernelBridge or product) and calling the feature complete

## Recommended delivery standard

A feature is complete only when all of the following are true:

- KernelBridge plan exposes the entitlement.
- Product Platform consumes and enforces it.
- Client app uses the same behavior in runtime logic.
- Tests cover both success and failure flows.
- Docs describe the key, meaning, and enforcement path.

## Example: MarketLens `max_client_app_devices`

This was the expected flow for the Business App device limit:

1. Add `max_client_app_devices` to the MarketLens KernelBridge plan.
2. Seed it with a provisional default value such as `1`.
3. Ensure older subscription snapshots backfill from the plan if the row is missing.
4. MarketLens Platform checks the active KernelBridge license before allowing Business App activation.
5. Platform server verifies the active limit and counts pending + active devices.
6. Client app uses the same entitlement and rejects activation when the limit is reached.
7. The system fails closed if the license is active but the entitlement is missing or malformed.

This is the standard pattern to follow for any future feature.

## Final rule

The implementation question is never just:

- “Did we add it to the plan?”

It is always:

- “Did we add it to the plan, did the product read it, did the product enforce it, and did both platform and client app behave correctly under the same license state?”
