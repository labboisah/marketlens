# KernelBridge MarketLens Feature Guide

## Audience and status

This guide defines the proposed KernelBridge feature keys and how MarketLens should interpret them. KernelBridge Product Integration API owners and MarketLens Platform/Business Server owners must agree on the response shape and version it before implementation. It is not a substitute for authorization in MarketLens.

For purchasable plan SKUs, see [KernelBridge MarketLens Plan Guide](kernelbridge-marketlens-plans.md). For payment, activation-code, and environment setup, see [KernelBridge MarketLens Catalog and Purchase Setup](kernelbridge-marketlens-catalog-setup.md).

## Concepts that must remain separate

| Concept | Example | Authority |
| --- | --- | --- |
| Business industry | Pharmacy, supermarket, hardware store | MarketLens business profile |
| Branch operating modes | Sales, production, services | MarketLens Platform branch configuration |
| Purchased feature entitlement | Plan permits production | KernelBridge subscription/license |
| Branch module policy | Branch override for shareholder module | MarketLens module configuration plus license |
| User permission | User may approve a production action | Business Server or Platform authorization |

`business_type` is an industry/profile field. It is not an operating mode. A KernelBridge plan permits modes at the business/license scope; a branch selects one or more of those permitted modes. A mode is usable only when the license allows it, the branch enables it, the branch/device is active, and the user has permission.

```text
effective operation access
= active license
AND feature entitlement granted
AND branch mode configured
AND active branch/device
AND authenticated user permission
```

Hiding a menu item is not enforcement. Business Server must reject prohibited local API operations; Platform APIs and Monitoring App must filter data by licensed feature, branch mode, user permission, and authorized branch scope.

## Canonical feature keys

Use stable lowercase snake-case keys. The feature key is an API contract, not a display label. KernelBridge plan feature records should return enabled state for boolean features and a value for limits. Missing boolean features must not be interpreted as granted.

| Feature key | Type | Scope | Behavior |
| --- | --- | --- | --- |
| `mode_sales` | Boolean | Business/license | Allows sales mode to be configured on branches |
| `mode_production` | Boolean | Business/license | Allows production mode to be configured on branches |
| `mode_services` | Boolean | Business/license | Allows services mode to be configured on branches |
| `module_shareholders` | Boolean | Business/license | Allows shareholder/ownership management, independent of operating mode |
| `max_branches` | Integer or `unlimited` | Business/license | Maximum active branches associated with the business |
| `max_server_installations` | Integer | Business/license | Maximum active Business Server installations across the licensed business |
| `max_client_app_devices` | Integer | Business/license | Maximum active and pending Business App devices across the licensed business |
| `max_operational_users` | Integer or `unlimited` | Business/license | Optional; do not sell until consistently enforced in Platform and Business Server |

Common retail features such as product catalog, inventory, reporting, and dashboard access should be included in the appropriate operating bundle or represented by additional explicit keys if they are independently sold. Do not rely on a plan's marketing copy to grant functionality.

## Operating-mode mapping

| Entitlement | Branch configuration | Business Server | Business App | Monitoring App |
| --- | --- | --- | --- | --- |
| `mode_sales` | Branch may include `sales` | Enforce sales, POS, sales-customer, and sales payment operations | Show sales, cashier, and sales-specific customer/payment views | Show sales/payment summaries only for selected branches configured for sales |
| `mode_production` | Branch may include `production` | Enforce production APIs and production-specific inventory/costing operations | Show production workspace only for configured branches | Show production summaries only for configured branches |
| `mode_services` | Branch may include `services` | Enforce service-order, service-payment, and service-earnings APIs | Show service workflows only for configured branches | Show service summaries only for configured branches |

Shared supporting workflows must be mapped intentionally. Product catalog, inventory, and procurement may support sales and production; they should not automatically expose sales transactions on a services-only branch. Document future workflow-to-mode mappings alongside the API contract.

Plan mode entitlements are the set of modes any branch under that business may select. Example: if the business license grants `mode_sales=true` and `mode_services=true`, a sales branch can select `sales`, a service branch can select `services`, and another branch can select both. A branch cannot select `production` unless `mode_production=true`.

## Shareholders

`module_shareholders` is not a fourth operating mode. It may be sold as an independent capability if KernelBridge supports MarketLens plan changes/add-ons with correct invoice, proration, renewal, refund, and entitlement-update behavior. Otherwise, include it in approved plan variants or all plans. Do not advertise it as a selectable add-on before the purchase flow can grant and remove it safely.

Shareholder permission checks remain separate. A license feature does not grant every user permission to view ownership, approve distributions, or record payments.

## Limit semantics

- `max_branches` counts active MarketLens branches covered by the business subscription. It does not count Server devices.
- `max_server_installations` counts active Server installations/devices. Define replacement, restore, and deactivation rules with KernelBridge before choosing a numeric value.
- `max_client_app_devices` counts active and pending Business App devices. KernelBridge plan limits replace the Platform's legacy local-subscription cap when a linked active license is present.
- The initial seeded limit is one Business App device per plan; administrators can edit each plan's value. A linked but inactive KernelBridge license does not fall back to a local subscription.
- `max_operational_users` counts operational users only if both Platform and Business Server reliably enforce the limit. Do not confuse this with administrative users on the KernelBridge customer portal.
- Define inclusive/exclusive count behavior, archived/suspended branch treatment, grace-period behavior, and over-limit upgrade/recovery flows.

KernelBridge's current license issue command supports `--max-activations`, and the MediFlow precedent binds a license permanently to an installation. For MarketLens, decide whether each Business Server consumes an activation or whether the business-level license claim consumes one activation and KernelBridge issues separate scoped Server credentials. Do not count the Platform claim and a Business Server twice. Until this is resolved, do not configure a public production activation limit.

## Entitlement response requirements

The Product Integration API response consumed by MarketLens must provide:

- Product code and public license/subscription identifiers.
- Current status and subscription expiry/grace timestamps.
- A stable entitlement revision/version.
- The canonical feature flags and limit values above.
- A signed payload/signature suitable for Business Server offline verification, or an explicitly agreed secure alternative.
- Clear errors for unpaid, expired, suspended, revoked, wrong-product, activation-limit, and invalid-license cases.

The MarketLens Platform should normalize provider responses in one adapter. Do not spread provider-specific payload field names through branch forms, Business Server route handlers, or Monitoring App feature screens. Business Server should enforce a signed cached snapshot offline and refresh it on its approved schedule; it must not call KernelBridge on every sale or local request.

## Configuration and client credential boundaries

- MarketLens Platform holds the product-scoped KernelBridge server-to-server credential and any webhook verification secret in its secret manager.
- Business Server receives a revocable installation credential through the approved provisioning flow and stores it using the trusted process/secure-storage design.
- Never ship a permanent `KERNELBRIDGE_API_TOKEN` in the Business Server or Business App installer, renderer variables, or customer-readable files.
- The Business App receives narrow allowed-feature/status information from Business Server, not the license key or raw entitlement payload.
- Monitoring App connects only to MarketLens Platform and receives authorized branch modes and summaries, never KernelBridge credentials or direct Server access.

## Feature contract acceptance tests

KernelBridge and MarketLens should jointly prove that:

1. A missing feature is denied, not treated as enabled.
2. A plan that grants a mode does not automatically enable that mode on every branch.
3. A branch mode cannot exceed the license feature set.
4. Server and branch limits count the correct things and honor replacement/archive rules.
5. Shareholder licensing can be enabled independently from operating modes and still requires user permissions.
6. Entitlement changes and payment events refresh MarketLens Platform and Business Server snapshots idempotently.
7. Expiry/revocation is enforced online and offline according to the approved grace policy while preserving business data and recovery operations.
8. A Monitoring user cannot see branches outside their assignment even if the license includes the relevant feature.