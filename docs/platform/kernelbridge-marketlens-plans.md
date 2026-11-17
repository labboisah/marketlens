# KernelBridge MarketLens Plan Guide

## Audience and status

This guide is for KernelBridge Product, Sales, and Finance staff configuring the purchasable MarketLens plans. It proposes plan shapes and required catalogue fields; it does **not** approve prices, trial periods, billing cycles, or limits. Obtain Product/Sales/Finance approval for the worksheet below before publishing checkout.

For feature keys and enforcement semantics, see [KernelBridge MarketLens Feature Guide](kernelbridge-marketlens-features.md). For payment verification, activation-code issuance, and environment configuration, see [KernelBridge MarketLens Catalog and Purchase Setup](kernelbridge-marketlens-catalog-setup.md).

## Product identity

| KernelBridge field | MarketLens value |
| --- | --- |
| Product code | `MARKETLENS` |
| Product name | MarketLens |
| Slug | `marketlens` |
| Category | Business / Retail |
| Purchase scope | One subscription/license for a business account; entitlements may cover multiple branches and Business Server installations |

The business industry (for example pharmacy or supermarket) is profile data, not a plan. Plans grant capabilities and limits. A branch independently selects one or more operating modes allowed by the business plan.

## Existing catalogue warning

KernelBridge's product catalogue already registers `MARKETLENS`. Its generic `PlanSeeder` also creates Basic, Professional, and Enterprise plans for non-AlignEx products, including MarketLens. Those generic rows use generic product feature mappings and sample NGN annual amounts. They are not approved MarketLens commercial terms.

Before opening purchase to customers:

1. Inspect current MarketLens products, plans, plan features, payment cycles, trial/grace values, and activation settings in KernelBridge.
2. Replace, deactivate, or migrate generic plans that do not match the approved MarketLens plans. Do not leave duplicate public plans.
3. Preserve issued subscriptions and financial records; change catalogue availability without deleting historical purchase data.
4. Confirm the product code remains exactly `MARKETLENS`.

## Proposed plan bundles

Use product-specific plan slugs. These four are a starting point only; add mixed-mode bundles if customers commonly need them and paid package changes are not supported for MarketLens.

| Suggested plan slug | Sales | Production | Services | Shareholders | Intended buyer |
| --- | --- | --- | --- | --- | --- |
| `marketlens-sales` | Included | Not included | Not included | Decide: included or excluded | Branches primarily running sales and stock workflows |
| `marketlens-production` | Optional | Included | Not included | Decide: included or excluded | Businesses producing or processing goods |
| `marketlens-services` | Optional | Not included | Included | Decide: included or excluded | Businesses managing service jobs and revenue |
| `marketlens-complete` | Included | Included | Included | Decide: included or excluded | Businesses using multiple operating modes |

“Optional” above is a product decision, not an implied entitlement. A plan must explicitly grant the modes the customer purchased. If KernelBridge does not support MarketLens feature add-ons and proration, do not present production, services, or shareholders as separately purchasable add-ons. Use plan variants instead.

If shareholders is meant to be available to every MarketLens customer, include its entitlement on every plan. Otherwise decide whether it is an add-on supported by the purchase/change workflow or a separate plan variant. Do not use a branch operating mode to represent shareholders.

## KernelBridge plan fields

Create one plan record per public package and attach it to the `MARKETLENS` product. Set these values deliberately:

| Field | Guidance |
| --- | --- |
| `name` | Customer-facing plan name, such as `MarketLens Sales` |
| `slug` | Stable product-specific key from the table above |
| `description` | State included workflows and key limitations without implying unimplemented capabilities |
| `price` | Finance-approved amount only; never retain generic seeder values as a price approval |
| `currency` | Finance-approved ISO currency, initially likely NGN but not assumed here |
| `billing_cycle` | Approved monthly/annual/custom cycle; confirm supported enum values in the deployed KernelBridge release |
| `setup_fee` | Approved amount or zero |
| `trial_days` | Approved value; do not silently inherit the generic 14-day seed default |
| `grace_period_days` | Approved value; do not silently inherit the generic 7-day seed default |
| `is_active` | Enable only after product/API/payment tests pass |
| `is_public` | Keep false until commercial terms and checkout are approved |
| `sort_order` | Stable order in the MarketLens product catalogue |

KernelBridge's generic seed data currently uses sample annual prices for generic Basic/Professional plans and a custom Enterprise price. These must be reviewed/replaced before public purchase. Do not copy prices from another product.

The dedicated `MarketLensPlanSeeder` creates the four proposed plans as unpriced but active/public so they appear on the MarketLens pricing page. Checkout remains blocked until a price is entered; KernelBridge's checkout rejects plans whose `price` is null with a Contact Sales message. It seeds the mode/shareholder feature matrix and adds enabled `max_server_installations` and `max_client_app_devices` features, each with a provisional limit of `1`. Administrators can edit each limit from the plan's Features section; rerunning the seeder preserves those edits. These defaults are functional baselines, not commercial approval. Set approved per-plan limits before enabling checkout. It also makes the legacy generic `basic`, `professional`, and `enterprise` MarketLens plans private without deactivating them, so existing subscriptions remain intact. The generic `PlanSeeder` skips MarketLens to prevent those generic SKUs from being recreated.

Plan features are copied to a subscription when it is issued. For the two device-limit keys, KernelBridge supplies the current plan value when an older subscription snapshot has no row for that key. An explicit subscription entitlement row remains authoritative. After seeding or changing a plan limit, refresh the MarketLens Platform's linked-license entitlements so its cached license projection receives the value.

Run it in the KernelBridge project after review:

```bash
php artisan db:seed --class=MarketLensPlanSeeder
```

The seeder is repeatable and preserves later administrator edits. It does not approve or publish prices, assign trial/grace periods, or set branch/server limits. Review the four visible rows in the KernelBridge admin UI and apply approved commercial values before enabling checkout.

## Approval worksheet

Complete with KernelBridge Product/Sales/Finance. Use one row per actual plan SKU.

| Plan slug | Currency | Billing cycle | Price | Setup fee | Trial days | Grace days | Branch limit | Server limit | Business App device limit | Operational-user limit |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `marketlens-sales` | `APPROVE` | `APPROVE` | `APPROVE` | `APPROVE` | `APPROVE` | `APPROVE` | `APPROVE` | `APPROVE` | `APPROVE` | `APPROVE` |
| `marketlens-production` | `APPROVE` | `APPROVE` | `APPROVE` | `APPROVE` | `APPROVE` | `APPROVE` | `APPROVE` | `APPROVE` | `APPROVE` | `APPROVE` |
| `marketlens-services` | `APPROVE` | `APPROVE` | `APPROVE` | `APPROVE` | `APPROVE` | `APPROVE` | `APPROVE` | `APPROVE` | `APPROVE` | `APPROVE` |
| `marketlens-complete` | `APPROVE` | `APPROVE` | `APPROVE` | `APPROVE` | `APPROVE` | `APPROVE` | `APPROVE` | `APPROVE` | `APPROVE` | `APPROVE` |

`max_branches`, `max_server_installations`, and `max_operational_users` have separate meanings; do not enter the same value for all three without a commercial reason. See the feature guide for implementation gates and activation-limit alignment.

## Plan publication checklist

- The product code is `MARKETLENS`; no duplicate generic MarketLens plans remain public.
- Each plan has a unique stable slug and a reviewed description.
- The feature rows match the approved mode bundle and shareholder policy.
- Price, currency, billing cycle, setup fee, trial/grace, and limits are approved.
- Activation limits support the purchased Server count and replacement policy.
- Purchase, payment, renewal, refund, and entitlement changes pass the tests in the purchase setup guide.
- No plan is made public while required MarketLens claim/Server credential APIs remain unresolved.