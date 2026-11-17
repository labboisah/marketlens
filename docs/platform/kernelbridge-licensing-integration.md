# KernelBridge Licensing Integration

## Purpose and decision

For KernelBridge product plans, purchase/payment gates, activation-code issuance, and environment setup, see [KernelBridge MarketLens Catalog and Purchase Setup](kernelbridge-marketlens-catalog-setup.md).

KernelBridge at `C:\laragon\www\kernelbridge` is the licensing and payment authority for MarketLens. MarketLens Platform remains the authority for businesses, branches, operating-mode configuration, users, devices, monitoring, and synchronized business data. The Business Server at `C:\laragon\www\marketlens-business-server` is the local enforcement point for licensing because it owns operational writes and must continue through temporary internet outages.

The integration has two distinct parts:

- MarketLens Platform links a KernelBridge license to a MarketLens business and keeps a synchronized, tenant-scoped license projection.
- Business Server validates the license and its entitlements locally using the KernelBridge TypeScript licensing client in the trusted Electron main process / Node server process.

Do not install the KernelBridge Laravel licensing package as a global license guard for MarketLens Platform. Its local state is per product installation, whereas MarketLens Platform is one multi-tenant service with many independently licensed businesses. Do not put a KernelBridge machine token or licensing logic in Business App renderer code. Business App access remains controlled by the Business Server.

MarketLens requests a KernelBridge account link through `POST /api/v1/platform/account-links/requests`, forwarding the customer's activation code from the authenticated Platform workflow. KernelBridge validates the code and links the business immediately. Product API credentials remain on trusted servers. An installation-bound activation code can then be issued for the Business Server's stable installation UUID and exchanged through the TypeScript licensing client.

## Authority boundaries

| Concern | Authority | Local copy / enforcement |
| --- | --- | --- |
| Payment, renewal, cancellation, suspension, license status | KernelBridge | MarketLens Platform projection; Business Server signed cache |
| Product plan features and limits | KernelBridge | MarketLens Platform projection; Business Server signed cache |
| Business identity and ownership | MarketLens Platform | Business Server receives its business UUID during activation |
| Branches and selected operating modes | MarketLens Platform | Business Server receives the assigned branch configuration |
| Operational permissions and transactions | Business Server | Business App consumes Server decisions |
| Monitoring user/branch access | MarketLens Platform | Monitoring App consumes authorized Platform APIs |

MarketLens Platform may cache license status for business setup, monitoring, reporting, and provisioning decisions. It must not independently extend, downgrade, or override a KernelBridge license. Business Server must not accept a renderer-provided license status or trust an unsigned Platform response as proof of current license validity.

Platform access is evaluated against the business selected in the authenticated session; a license linked to another business owned or monitored by the same account does not unlock this business. Feature pages must enforce their entitlement on the server for direct navigation as well as hiding unavailable navigation items. The activation and account-link pages remain accessible so the owner can restore the selected business's license. To switch licenses, the owner can unlink the current account through the trusted Platform backend; KernelBridge revokes that link's active installations and unused installation codes before MarketLens clears the local projection.

## Product and entitlement model

Register a distinct KernelBridge product code `MARKETLENS`. Configure paid plans and feature keys in KernelBridge; do not maintain a second customer-facing subscription catalogue or payment workflow in MarketLens Platform once the integration is live. Existing MarketLens subscription records should be migrated or retained as read-only history according to a separately reviewed data migration; do not delete billing history as part of this integration.

Keep these concepts independent:

- **Industry**: the existing business-level `business_type` value, such as pharmacy or supermarket.
- **Operating modes**: one or more branch-level values from `sales`, `production`, and `services`.
- **License entitlements**: centrally purchased features and limits.
- **Shareholders**: an independent supportive capability/add-on, not a fourth operating mode.
- **User permissions**: local operational permissions and Platform monitoring permissions.

Sales may be included in the base MarketLens plan. Production and services may be priced as plan inclusions or add-ons. Shareholder management can be sold separately and be usable alongside any configured operating mode. These are product packaging decisions owned by KernelBridge's MarketLens plan catalogue, not branch configuration rules.

Effective access to an operation must require all applicable gates:

```text
branch mode configured
AND license entitlement active
AND branch/device active
AND authenticated user has the required permission
```

For example, a production entitlement does not configure every branch for production, and configuring a branch for production does not grant the plan entitlement. UI visibility is not an authorization boundary; the Business Server must enforce the same gates inside protected API handlers and business services.

Suggested stable entitlement keys (final names must be agreed with KernelBridge and versioned):

| Key | Meaning |
| --- | --- |
| `mode.sales` | Sales workflow entitlement; normally included in the base plan |
| `mode.production` | Production workflow entitlement |
| `mode.services` | Services workflow entitlement |
| `module.shareholders` | Shareholder and ownership capability |
| `limit.branches` | Maximum licensed branches |
| `limit.server_installations` | Maximum activated Business Server installations |
| `limit.operational_users` | Maximum operational users, if commercially required |

Plan entitlements are business-wide. Branch operating modes are configured separately and must be a subset of the licensed modes. If KernelBridge uses a different entitlement payload shape, define a versioned mapping in one MarketLens adapter rather than spreading provider-specific keys through application code.

## License lifecycle

1. The customer purchases or renews the MarketLens plan in KernelBridge. KernelBridge issues the activation code and owns payment, subscription status, expiry, and entitlements.
2. The customer registers a business and its initial branch in MarketLens Platform. The owner links the activation code to that business through an authenticated Platform workflow.
3. MarketLens Platform sends the account-link request server-to-server using a product-scoped, revocable credential held only in Platform secret storage. KernelBridge validates the activation code and returns a pending link plus public license/subscription identifiers. The Platform stores a non-authoritative projection and never stores the raw activation code.
4. The trusted Platform backend obtains an installation-bound activation code for the Business Server's stable installation UUID. The Business Server exchanges that short-lived code through the TypeScript SDK and stores the resulting credential securely; KernelBridge applies installation limits during issuance/exchange.
5. Business Server uses the KernelBridge TypeScript client in its trusted process to verify with the scoped credential and uses the SDK's Ed25519 verifier for signed entitlements. MarketLens retains its existing SQLite snapshot and synchronous license evaluation so protected writes can be checked locally; migrating that cache to the SDK's higher-level cache is a separate change.
6. Business Server reports license UUID, status, entitlement revision, expiry, last verification time, and its business/branch/device UUIDs to MarketLens Platform over its authenticated device channel. Never report the activation code, raw credential, or decrypted entitlement signature material.
7. KernelBridge sends signed license lifecycle events to MarketLens Platform where supported. Platform also runs a scheduled reconciliation against KernelBridge so dropped webhooks cannot leave stale projections indefinitely. Webhook event IDs must be idempotent and replay-protected.
8. Renewals and plan changes update KernelBridge first. Updated signed entitlements reach Business Server on verification; the Platform projection is refreshed by webhook/reconciliation. No desktop release is required for a normal renewal or plan upgrade.

The initial owner flow may collect the code in MarketLens Platform, but the code must be redeemed server-to-server and never placed in a URL, renderer state, logs, analytics, or support diagnostics. A Business Server must not ship with a permanent KernelBridge product machine token. KernelBridge's TypeScript client documentation explicitly recommends revocable installation credentials for distributed Electron applications.

### KernelBridge contract to confirm

Before implementation, agree and test these points with KernelBridge:

- A business-level license can authorize multiple branch/server installations up to explicit limits. The current MediFlow package documentation describes a one-permanent-installation binding; that behavior cannot be copied unchanged if a MarketLens business may have a Server per branch.
- A Platform-side operation can link/redeem a license to an external MarketLens business UUID without pretending that the cloud Platform is a Business Server device activation.
- KernelBridge can issue, rotate, expire, and revoke narrowly scoped installation credentials without embedding a long-lived product machine token in the desktop package.
- Entitlement responses can be cryptographically signed, with a stable public verification key available to MarketLens Server builds. Production Business Server configuration should require signed entitlements.
- KernelBridge can notify MarketLens Platform of renewal, expiry, suspension, cancellation, refund, revocation, and entitlement changes, or provide an API suitable for bounded-interval reconciliation.
- Activation/deactivation semantics, transfer/replacement rules, and installation-limit accounting are documented for branch replacement, server repair, restore, and disaster recovery.

### Resolved MarketLens activation policy

The MarketLens activation flow links the account immediately after KernelBridge validates the activation code:

- MarketLens Platform calls `POST /api/v1/platform/account-links/requests` with the activation code and its business UUID. The code is resolved only by KernelBridge; the product Platform does not treat the code as proof of approval or store it.
- A valid activation code creates an active account link immediately; no separate license-owner approval is required. The account-link API credential remains on the trusted Platform backend.
- The trusted Platform backend may then issue a short-lived activation code bound to one stable Business Server installation UUID. The TypeScript client exchanges it through `/api/v1/installations/activate`; the product API credential is never sent to the desktop.
- The account-link API and the older `/api/v1/licenses/installation-grants` path are distinct contracts. Do not mix their credentials; use the installation-code flow for new TypeScript SDK integrations.

This policy is codified in `App\Domain\Licensing\Services\KernelBridgeActivationPolicy` and `config/kernelbridge.php`, and the business-claim behavior is covered by KernelBridge API and MarketLens projection tests.

The Platform-side claim, provider grant endpoint, MarketLens device-registration orchestration, and encrypted Business Server grant storage are implemented. The next step is grant-authenticated license activation/verification in the Server client, followed by signed local enforcement as described in the implementation pipeline below.

If KernelBridge cannot issue a scoped installation credential, do not compensate by embedding its permanent product token in the Business Server installer. Block production rollout until a secure broker/bootstrap flow is agreed. The product machine token is server-side only.

## MarketLens Platform changes

### Business/license association

Add a tenant-scoped license association/projection, preferably as a dedicated model/table rather than overloading the existing billing subscription model. Store only the fields needed for authorization and support, for example:

- `business_id`, provider (`kernelbridge`), product code (`MARKETLENS`), public license UUID, public subscription UUID;
- normalized status, entitlement payload/revision, `expires_at`, offline-grace deadline, last successful verification;
- webhook/reconciliation timestamps and a provider event/version identifier.

Do not store raw activation keys, machine tokens, installation credentials, or signing private keys. Add an append-only audit/event record for link, renewal, entitlement change, suspension, revocation, unlink, and support-assisted replacement. Enforce one active MarketLens license association per business unless the product requirements explicitly support multiple concurrent licenses.

Keep KernelBridge HTTP calls behind a dedicated integration service with connect/read timeouts, bounded retries for idempotent operations, request IDs, redaction, and explicit handling for timeout, invalid code, revoked credential, and provider outage. The Platform uses the Laravel SDK's account-link client for server-side transport; the Business Server uses the TypeScript SDK for verification transport and signature validation. Both remain bound to product code `MARKETLENS`; the SDK may differ by runtime, not by product policy. Verify webhook signatures before accepting events; do not trust webhook payload business IDs without resolving the stored license association.

### Branch modes and provisioning

Add branch-level configuration for one or more canonical operating modes (`sales`, `production`, `services`). Store the selected values separately from `module_settings`, validate them against the canonical enum, preserve the existing industry `business_type`, and audit mode changes. The current Platform implementation stores the small fixed set as a JSON array on the branch.

On Business Server activation and subsequent status refresh, deliver a minimal configuration containing business UUID, branch UUID, operating modes, effective license features/limits, entitlement revision, status, expiry/grace deadlines, and currency/timezone settings. A server registered to one branch must not receive another branch's mode configuration. Branch mode changes and license changes must invalidate relevant Platform caches and reach the Server on its next authenticated status refresh.

The current MarketLens branch-mode implementation stores `operating_modes` separately from `module_settings`. Device activation and `/device/status` include the selected branch modes under `branch_settings.operating_modes`; Business Server stores the received branch settings JSON and exposes normalized modes in its authenticated local system status.

Extend device provisioning and monitoring APIs to return authorized branch modes and effective feature availability. Apply delegated monitoring users' assigned-branch restrictions before resolving any requested branch. Preserve historical synchronized records when a mode is removed; the mode controls current feature availability and presentation, not data deletion.

### Existing local subscription logic

Audit `SubscriptionManagementService`, `ModuleAccessService`, onboarding, plan management, device activation, monitoring authorization, billing pages, and queued jobs for assumptions that MarketLens owns customer subscription status. Migrate those checks to the provider projection through one licensing/entitlement abstraction. Keep subscription tables and screens only if they have a clearly separate MarketLens-owned purpose; do not allow local records to grant features denied by KernelBridge.

## Business Server changes

Install the KernelBridge TypeScript licensing client from the approved package source and pin the package version. Use the sibling path package only for local development; production builds must resolve a reviewed immutable package version. Keep the SDK and all licensing calls in the trusted Electron main/Node process, not in the renderer.

The current Business Server integration uses the SDK client for verification requests and the SDK Ed25519 verifier for signed snapshots, while preserving MarketLens' existing local cache, binding checks, offline grace, and synchronous guard behavior. The configured public key is currently a single trusted key; add explicit key-ID-to-public-key configuration before enabling overlapping signing-key rotation.

The Server should:

- Generate one stable installation UUID and bind it to its MarketLens business, branch, and registered device UUID. Keep it in protected local application state; do not use a renderer-generated value.
- Initialize `KernelBridgeLicenseClient`, encrypted license store, `safeStorage` vault, cache service, verification service, `LicenseGuard`, and a cancellable periodic verifier at startup/shutdown.
- Require signed entitlement verification in production. Pin only the public verification key in the release; the signing private key remains in KernelBridge.
- Check active license, feature, and limit inside local API/business-service boundaries. Navigation/UI checks are supplementary only.
- Expose a narrow local status response to Business App: active/read-only/activation-required, allowed feature keys, renewal notice, and expiry/grace summary. Never expose the license key, API credential, encrypted state, or raw signed payload.
- Enforce the licensed branch and features when accepting local writes, including when offline. A Business App must not be able to change the active license or branch scope through request payloads.
- Keep operating-mode configuration and user permissions as separate gates from license entitlements.
- Provide an authenticated owner/admin activation, renewal status, and deactivation/replacement workflow. Do not expose license management to ordinary operational roles.

Normal operations use the local signed cache; do not make a KernelBridge call per request. On a temporary network failure, permit use only until the earlier of central expiry and the configured offline-grace deadline. The KernelBridge spec/client currently documents a 72-hour default; select and publish the MarketLens production value with KernelBridge rather than silently inheriting a development default.

When the license is expired, suspended, revoked, deactivated, tampered with, or beyond offline grace, block new protected operational writes with a stable error code (HTTP `402` for licensing-required API responses, subject to the existing local API error contract). Preserve data and provide an owner-facing resolution path. Define explicitly which reads, exports, backups, restore/recovery operations, and synchronization remain available; licensing failure must never delete records, corrupt ledgers, prevent a safe backup, or silently discard queued synchronization data. Explicit central rejection invalidates the local cache immediately.

## Business App and Monitoring App

Business App obtains allowed features from authenticated Business Server session/status APIs. It may hide unavailable navigation, but the Server remains responsible for rejection. It must not contact KernelBridge or persist a license key.

Monitoring App continues to use MarketLens Platform only. Platform APIs filter by tenant, authorized branch, branch modes, license projection, and monitoring permissions. The app should present only supported summaries for the selected branch and retain the last-sync/stale-data indicators. Do not connect Monitoring App directly to KernelBridge or a Business Server.

## Availability and failure policy

| Condition | Business Server | MarketLens Platform |
| --- | --- | --- |
| KernelBridge reachable; active license | Refresh signed local state; permit licensed operations | Refresh projection and allow entitled setup/monitoring features |
| Temporary KernelBridge outage; unexpired cache and grace valid | Continue only within signed expiry/grace; queue verification retry | Use last known projection for bounded display/provisioning policy; mark status stale |
| License expired or grace elapsed | Block protected writes; preserve data; show renewal/verification path | Mark projection expired/stale; stop new activations or entitled feature exposure according to policy |
| Explicit suspension/revocation/rejection | Invalidate cache and block immediately | Apply verified event and audit it |
| Invalid signature, corrupt cache, or local tampering | Fail closed for protected operations; preserve recovery paths | Reject invalid webhook/snapshot; alert operations |

The business owner must see when license data is stale. Do not treat a Platform heartbeat failure as proof that the central license was revoked, and do not treat an old Platform projection as a substitute for Server-side verification.

## Security and operational requirements

- Use separate KernelBridge credentials for development, staging, and production; scope each to the `MARKETLENS` product and least required operations.
- Store Platform credentials in its secret manager/environment. Never expose them through activation responses, Vite variables, desktop configuration, source control, logs, crash reports, or diagnostics.
- Store Business Server installation credentials and license state through the approved SDK secure storage. Exclude licensing databases/state from support bundles and non-encrypted backups where possible; apply strict OS file permissions.
- Redact activation codes, credentials, signatures, and entitlement payloads from logs. Record public IDs, request IDs, status transitions, and reason codes for audit.
- Use HTTPS, validate webhook signatures and timestamps, enforce idempotency, rate-limit redemption and activation, and alert on repeated activation failures or unexpected installation counts.
- Use signed time-bounded entitlement snapshots. Account for clock rollback as documented by the TypeScript SDK; do not claim that local encryption prevents a hostile machine owner from modifying the application.
- Keep backup/restore and replacement procedures compatible with permanent installation binding. A restored database must not silently clone an active installation identity; require the documented rebind/replacement process.

## Implementation pipeline

### 1. Account-link request and approval — implemented

KernelBridge issues the paid MarketLens license and activation code. The owner enters that code in the MarketLens account activation page. MarketLens calls `POST /api/v1/platform/account-links/requests`; a valid code links the license to the business immediately. MarketLens stores only the link/license projection, never the raw activation code.

### 2. Approved installation-code issuance — implemented

KernelBridge supports scoped credentials through `POST /api/v1/licenses/installation-grants` and the newer account-link activation-code flow through `POST /api/v1/platform/account-links/{link}/activation-codes`. The account-link flow binds each short-lived code to a stable installation UUID; the TypeScript SDK exchanges it at `/api/v1/installations/activate`. Do not expose the product API credential or activation code to the desktop.

The MarketLens Platform's current device-registration path and the account-link activation-code flow must remain aligned: a pending link must block device activation, and any code delivered to the Business Server must be bound to that install's stable UUID. Verify the complete desktop activation, encrypted persistence, signed-entitlement verification, offline grace, and replacement flows before enabling the new path for production tenants.

### 3. MarketLens device-registration orchestration — implemented

Keep the existing owner workflow: generate a short-lived MarketLens device code in `/devices`, then redeem it in the Business Server first-run setup against `POST /api/v1/device/activate` over HTTPS. `shop_app` registration requires an approved KernelBridge account-link projection and `max_server_installations` entitlement, then requests a one-time KernelBridge installation code bound to the Server's stable pre-activation installation UUID. The Platform keeps its integration credential server-side and returns the short-lived code only to the activated Business Server. The Server exchanges it through the TypeScript SDK and stores the resulting credential encrypted in its local credential table. Client App device registration remains on the existing Platform-only path.

### 4. Business Server local licensing — in progress

The standalone Business Server now exchanges the short-lived, installation-bound code through the TypeScript SDK and stores the resulting bearer credential encrypted with its KernelBridge API URL. Continue validating signed entitlements at startup and on a cancellable schedule, expose a narrow local status contract, and enforce license/feature/limit checks at server API and business-service boundaries. During temporary outages, allow only the configured offline grace. Explicit revocation, expiry, invalid signatures, tampering, or elapsed grace must block protected writes while preserving safe reads, backups, recovery, and queued synchronization as defined by policy.

### 5. Application behavior and Platform projection

The Business App reads license status and allowed features from the authenticated Business Server; it never calls KernelBridge or handles license grants. The Server remains authoritative for operational writes. MarketLens refreshes its projection by the agreed signed webhook and/or bounded reconciliation process, applies status to owner setup and monitoring views, and keeps tenant/branch authorization independent from plan entitlements. Existing MarketLens local subscription history remains intact until its migration or read-only treatment is separately reviewed.

### 6. Verification and rollout gate

Before production enforcement, test fresh business claim, duplicate claim, conflicting business claim, first server activation, retries, installation limit reached, multiple branches, credential rotation/revocation, renewal and entitlement changes, suspension/refund, offline grace, expiry, server replacement, restore, backup, and KernelBridge outage. Verify that the business claim consumes zero installation slots and each active Business Server consumes exactly one. Pilot with monitoring and reconciliation before enforcing licensing for existing installations. Do not ship a product-wide secret in a desktop package or enable fail-closed enforcement until grant issuance, cache verification, recovery behavior, and the rollout/migration plan are proven.

## Required test coverage

Platform tests must cover successful/failed code linking, duplicate/replayed requests, provider timeouts, signed webhook verification, out-of-order and duplicate events, reconciliation recovery, tenant isolation, delegated branch isolation, entitlements/limits, and audit history.

Business Server Vitest tests must cover encrypted activation state, valid signature and tamper rejection, active/feature/limit gates, offline grace, expiry while offline, explicit revocation, refresh after renewal, renderer isolation, blocked API writes, preserved backups/data, and safe replacement/recovery.

Cross-application tests must verify that configured branch modes, KernelBridge entitlements, and user permissions are independently necessary; Business App cannot bypass Server rejection; Monitoring App cannot see an unauthorized branch or unlicensed mode; and existing synchronized history remains readable according to the expiry policy.

## Existing implementation references

MarketLens device provisioning and local module snapshots currently live in `app/Domain/Devices/Services/ShopAppActivationService.php`; module entitlement resolution currently lives in `app/Domain/Modules/Services/ModuleAccessService.php`; branch configuration currently lives in `app/Domain/Tenancy/Models/Branch.php` and `app/Domain/Tenancy/Services/BranchManagement.php`. These are integration surfaces, not proof that central KernelBridge licensing is already wired.

KernelBridge package behavior and API details are documented in `C:\laragon\www\kernelbridge\docs\LICENSING_CLIENT_SPEC.md`, `C:\laragon\www\kernelbridge\packages\kernelbridge\licensing-client-typescript\README.md`, and `C:\laragon\www\kernelbridge\docs\MULTI_PRODUCT_INFRASTRUCTURE.md`. Confirm package versions and API behavior against the actual deployed KernelBridge release before implementation.