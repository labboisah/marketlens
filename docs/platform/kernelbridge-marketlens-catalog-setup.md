# KernelBridge MarketLens Catalog and Purchase Setup

## Purpose

This is the KernelBridge operator handoff for purchase/payment, activation-code issuance, and deployment configuration. KernelBridge remains the authority for customer payment, subscription, license, and entitlement status. MarketLens Platform owns the business/branch relationship and passes the resulting configuration to Business Server.

Plan bundles and commercial fields are maintained in [KernelBridge MarketLens Plan Guide](kernelbridge-marketlens-plans.md). Canonical feature keys, limits, and enforcement semantics are maintained in [KernelBridge MarketLens Feature Guide](kernelbridge-marketlens-features.md). Those guides are the source of truth for plan and feature details; this document covers operator workflow and deployment settings.

This is a setup specification, not a price approval or confirmation that the MarketLens integration endpoints are already implemented.

## Existing catalogue state to review

KernelBridge's `ProductSeeder` already registers product code `MARKETLENS`. Its generic `PlanSeeder` path also creates Basic, Professional, and Enterprise plans for non-AlignEx products, which includes MarketLens. Those generic plans use generic product-feature mappings and sample NGN annual amounts; KernelBridge's commercial documentation explicitly says these are not approved MarketLens prices. Before enabling public purchase:

1. Inspect the current MarketLens product, plans, plan features, billing cycles, trial/grace values, and activation limits in the KernelBridge admin UI/database.
2. Replace, deactivate, or migrate generic plans that do not match the approved MarketLens catalogue. Do not leave duplicate generic and MarketLens-specific plans publicly purchasable.
3. Set actual finance-approved prices and billing periods. Do not copy prices from other products or treat seeder values as approved.
4. Confirm product code remains exactly `MARKETLENS`; it is the integration identifier and must not be changed to a display name or branch mode.

## Purchase, payment, and activation-code rules

Use KernelBridge's normal customer → quotation/order → invoice → verified payment → subscription/license flow. Apply these rules to MarketLens:

| Purchase/payment state | Subscription/license action |
| --- | --- |
| Quote or order draft | No license and no activation code |
| Invoice issued, unpaid | No license and no activation code |
| Payment proof submitted but not verified | No license and no activation code |
| Partial payment | No activation code; keep the invoice/subscription pending |
| Payment gateway callback not verified or mismatched | Do not activate; send to finance review |
| Full payment verified and allocated to the correct customer, currency, product, and invoice | Activate the subscription and issue one MarketLens license/activation code idempotently |
| Duplicate payment callback/retry | Return the same issued license/code status; never create a second active license accidentally |
| Renewal invoice paid | Renew the existing subscription/license according to KernelBridge policy; preserve the customer's activation identity unless the product contract requires replacement |
| Refund/chargeback/cancellation | Apply the centrally approved suspended/revoked state and notify MarketLens Platform/Business Server |

KernelBridge's customer onboarding guide says activation keys are issued only after the matching invoice is paid and the subscription is active. Keep that gate. The activation code is secret material: show it once in the authenticated customer portal or deliver via an approved secure channel; never place it in email subject lines, URLs, analytics, or logs.

The customer enters the code in the approved MarketLens activation/linking flow. MarketLens Platform then associates the license with the customer's business and provisions the registered branch/server installation. Before implementation is released, KernelBridge and MarketLens must decide whether the code is redeemed once as a business-level claim or activated directly by each Business Server. The chosen flow must match `max_server_installations` and must not consume an extra activation for both the Platform claim and the first Server.

## Payment gateway configuration

KernelBridge's current `.env.example` and `config/services.php` use these Flutterwave variables. Configure the correct environment-specific values in KernelBridge's secret manager/deployment environment; never put real values in this MarketLens repository or the documentation:

| KernelBridge variable | Purpose |
| --- | --- |
| `FLUTTERWAVE_PUBLIC_KEY` | Checkout/public integration key |
| `FLUTTERWAVE_SECRET_KEY` | Server-side payment verification |
| `FLUTTERWAVE_ENCRYPTION_KEY` | Provider encryption/configuration key |
| `FLUTTERWAVE_WEBHOOK_SECRET_HASH` | Webhook authenticity validation |
| `FLUTTERWAVE_BASE_URL` | Provider API base; current default is `https://api.flutterwave.com/v3` |

Use Flutterwave test credentials in staging and production credentials only in the production secret manager. Verify server-to-server transaction status, currency, amount, customer, product, invoice reference, and final payment allocation before issuing a license. Do not trust a browser redirect as proof of payment.

## Environment values for the MarketLens integration

These values belong to the MarketLens Platform deployment unless marked otherwise. The names below are the proposed MarketLens integration contract; add them to `.env.example` and deployment documentation only when the integration is implemented. Real values belong in a secret manager, never committed files.

| Variable | Owner | Notes |
| --- | --- | --- |
| `KERNELBRIDGE_API_URL` | MarketLens Platform and Business Server | `https://kernelbridge.com/api/v1` in production; use a non-production endpoint in test/staging |
| `KERNELBRIDGE_PRODUCT_CODE` | Both | Exact value `MARKETLENS` |
| `KERNELBRIDGE_API_TOKEN` | MarketLens Platform only | Product-scoped, revocable server-to-server credential with the `licensing:account-link` scope; never ship in Business Server or Business App |
| `KERNELBRIDGE_WEBHOOK_SIGNING_SECRET` | MarketLens Platform | Proposed; only required if KernelBridge implements signed lifecycle webhooks |
| `KERNELBRIDGE_ENTITLEMENT_PUBLIC_KEY` | Business Server (public verification key) | Proposed configuration name; pin public key for signed offline entitlement verification; private signing key stays in KernelBridge |
| `KERNELBRIDGE_VERIFICATION_INTERVAL_MS` | Business Server | SDK setting; current example is `900000` (15 minutes), subject to production policy |
| `KERNELBRIDGE_OFFLINE_GRACE_MS` | Business Server | SDK setting; current example is `259200000` (72 hours); approve this value explicitly |
| `KERNELBRIDGE_REQUIRE_SIGNED_ENTITLEMENTS` | Business Server | Set `true` for production after KernelBridge returns signed payloads |
| `KERNELBRIDGE_API_TIMEOUT_MS` | Business Server/Platform client | SDK example uses `10000`; use bounded connect/request timeouts |

The TypeScript client supports `tokenProvider` for a runtime revocable installation credential. Use that for packaged Business Server deployments. `KERNELBRIDGE_API_TOKEN` is documented for controlled/private deployments only; a permanent token in an installer, environment file distributed to customers, renderer, or SQLite database is not acceptable. The exact public-key and webhook variable names must be reconciled with the KernelBridge API/client implementation before they are added to production configuration.

Existing MarketLens settings such as `PLATFORM_SYNC_BASE_URL`, `PLATFORM_SYNC_DEVICE_TOKEN`, and `BUSINESS_JWT_SECRET` remain MarketLens-owned and must not be reused as KernelBridge credentials.

## KernelBridge operator checklist

1. Confirm `MARKETLENS` is active and its public product page/details are correct.
2. Run `php artisan db:seed --class=MarketLensPlanSeeder` in KernelBridge, then review the four visible/unpriced MarketLens plan rows. Public pricing can display them, but checkout remains blocked until each price is approved and entered.
3. Approve plan names, slugs, descriptions, prices, currency, billing cycles, setup fees, trial/grace policy, mode bundles, shareholders inclusion, and branch/server/user limits.
4. Confirm feature keys exactly match the table above and are returned by the Product Integration API. Agree on a versioned entitlement response and public signature-verification key.
5. Agree on one-business/multiple-branch/multiple-Server activation semantics, `max-activations` mapping, replacement/deactivation behavior, and how the Platform gets revocable Server credentials.
6. Configure Flutterwave staging credentials and prove pending, partial, verified-full-payment, duplicate-callback, refund, and renewal behavior. Configure production credentials only after release gates pass.
7. Issue a dedicated `MARKETLENS` Platform API credential for each environment from KernelBridge Admin → MCP using the **Platform licensing integration** preset (`licensing:account-link`). Put only the returned credential into the MarketLens Platform secret store as `KERNELBRIDGE_API_TOKEN`; do not reuse a client-installation credential.
8. Test purchase through license issue, business claim, Server activation, entitlement refresh, expiry, renewal, refund/revocation, branch-limit rejection, and Server replacement before making purchase public.

## Values requiring approval

The following are intentionally not guessed in this document: MarketLens prices, currency/billing cycle, setup fee, trial/grace duration, included shareholder capability, branch/server/user limits, which mixed-mode bundles are public, activation limit semantics, and final webhook/signature configuration. These values must be approved by Product/Sales/Finance and confirmed with the KernelBridge licensing API owner before running a MarketLens plan seeder or enabling checkout.