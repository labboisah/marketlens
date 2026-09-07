# Backup, restore points, and desktop update readiness audit

Date: 7 September 2026  
Scope: MarketLens Platform, Business Server, and Business App  
Assessment: **Not ready for an unrestricted production rollout.**

The core features exist and several important safeguards pass focused tests. However, branch authorization, unattended backup reliability, recovery after losing a server, and installer trust have gaps. A download menu or successful backup capture does not establish recoverability or a safe upgrade path.

## Scope and evidence limits

This is an audit of the current working trees, including uncommitted changes, plus the two Windows installers currently exposed by the Platform. Platform HEAD was `7f501c9`; Business Server HEAD was `072877c`. The Business App directory was not a Git repository, so its source revision could not be recorded. Both desktop package manifests report version `2.0.0`.

The audit used source review, isolated automated tests, and Windows Authenticode inspection. It did not restore a live business database, install either executable, change production settings, or publish releases. No claim is made that the existing installers contain every feature in the current source trees. Production deployment, published release records, offsite storage, network upload limits, and recovery credentials were not verified.

Severity: **High** means a release blocker for the affected scenario; **Medium** means a material reliability or operational gap. Source-confirmed findings identify code paths; they are not presented as successfully reproduced production incidents.

## Readiness assessment

| Capability | Assessment | Reason |
| --- | --- | --- |
| Local SQLite backups | Conditional | SQLite backup API and checksum records exist, but ordinary snapshots remain plaintext and retention is not implemented. |
| Encrypted restore-point capture/upload | Conditional | Encryption, scope checks, offline queuing, and idempotency exist; a failed scheduled capture can block the rest of the worker. |
| Restore on the existing registered server | Conditional | Confirmation, integrity checks, safety snapshots, security preservation, restart recovery, and synchronization hold exist. Operational recovery drills are still needed. |
| Recovery after total server/disk loss | Not ready | Recovery requires current local identity and authorization; replacement-device recovery is explicitly unsupported. |
| Platform backup administration | Not ready for branch-restricted delegates | Business permission checks omit branch authorization. |
| Business Server updates | Not ready for general rollout | Installer signing, session validation, legacy configuration migration, and installation coordination need correction. |
| Business App updates | Conditional; release artifacts not ready | Download/policy tests pass, but offered installers are unsigned and complete installed-upgrade behavior is unproven. |
| Release verification/CI | Incomplete | Platform workflow targets a removed directory; server backup tests have runtime/cleanup problems; packaged upgrade drills were not run. |

## Controls already implemented

- Business Server uses SQLite's backup API rather than copying the active database file for routine snapshots. Restore-point tests verify committed WAL data is included.
- Restore points use AES-256-GCM with a random salt/nonce and a passphrase-derived key. Decryption rejects incorrect passphrases or tampered ciphertext and removes failed plaintext output.
- The Platform validates device credentials, business/branch/device metadata, envelope format, size, and encrypted checksum. Upload UUIDs are idempotent; deletion leaves tombstones against delayed retries.
- Local recovery requires an active local session and an appropriate backup permission. A Platform request cannot automatically replace the database.
- Restore preparation checks plaintext/encrypted checksums, SQLite integrity, schema compatibility, and server identity. Recovery preserves current security records and revokes sessions.
- Restore installation drains synchronization and the local API, creates a safety snapshot, journals the file replacement, and keeps synchronization paused after restart.
- Release manifests separate Business Server and Business App, filter eligible releases, and issue expiring signed download URLs. Client policy parsing checks product, architecture, and channel.
- Desktop download helpers bound installer size, clean incomplete downloads, reject insecure download URLs in their enforced production mode, and verify checksums. Server updates create a pre-update snapshot and startup migrations create a pre-migration snapshot.

Evidence: [backup service](../../../business-server/src/backup/backup-service.ts), [backup crypto](../../../business-server/src/backup/backup-crypto.ts), [restore recovery](../../../business-server/src/backup/restore-recovery.ts), [Platform restore API](../../app/Http/Controllers/Api/V1/RestorePointController.php), [release service](../../app/Domain/Versions/Services/ApplicationVersionService.php), and [client policy validation](../../../business-app/src/main/client-release-policy.ts).

## Findings: backups, restore, and restore points

### B1 — High: Platform backup actions omit branch authorization

**Source-confirmed.** `RestorePointService::authorize()` checks the current business and `hasBusinessPermission()`, but does not call `canAccessBranch()`. The page also lists server devices across the entire business. `hasBusinessPermission()` evaluates the monitoring assignment's permissions independently of its allowed branches.

A monitoring user assigned to Branch A, with an applicable `backups.*` grant, can pass authorization for a server in Branch B of the same business. Depending on the grant, this exposes backup metadata or permits creation requests, restore requests, and deletion of the other branch's cloud copy. Local confirmation still protects actual restore execution. The device API itself does apply business/branch/device scoping; this finding concerns human-facing Platform authorization.

**Required:** apply branch checks in the service and device list, and test view/create/restore/delete/cancel across two branches of the same business. Preserve the intended owner/super-admin access.

Evidence: [service, lines 17–19](../../app/Domain/Backups/Services/RestorePointService.php), [page, lines 27–40 and 103–108](../../app/Livewire/Platform/Backups/RestorePointManagementPage.php), [separate permission and branch checks, lines 112–172](../../app/Models/User.php). Existing restore feature tests cover a different business and wrong device metadata, not this delegated same-business branch case.

### B2 — High: cloud copies do not provide a complete lost-server recovery path

**Documented limitation, confirmed in source.** Restoring requires an activated local installation, its device credential, a current authorized local session, and a candidate matching the same business, branch, and device. The recovery screen is inside the running application. A fresh replacement registration has a different identity; a missing/corrupt database may prevent the application reaching this screen.

Keeping the passphrase is necessary but insufficient for supported recovery after complete machine loss. The snapshots also omit external files and persistent configuration. The current feature is recovery on an existing registered installation, not complete disaster recovery.

**Required:** define and test an authorized replacement-server/recovery-mode workflow, credential rotation and security preservation rules, and an independently stored recovery kit. Record recovery-point and recovery-time objectives. If this remains out of scope, communicate the limitation explicitly to owners before relying on cloud backups.

Evidence: [recovery documentation](../../BACKUP-RESTORE.md), [scope/credential/prepare checks](../../../business-server/src/backup/restore-point-service.ts), [candidate identity validation](../../../business-server/src/backup/restore-recovery.ts), [startup database integrity checks](../../../business-server/src/services/application/business-application.ts).

### B3 — High: scheduled capture failure blocks upload retries and remote request polling

**Source-confirmed.** `tick()` performs an overdue scheduled capture before fetching cloud state, acknowledging recovery, or uploading pending points. The whole sequence shares one catch. Capture rejects when 100 local points exist; it can also fail on disk exhaustion, encryption, or size validation. `lastCreatedAt` advances only after a successful capture, so the same failure repeats on subsequent polls.

For example, after accumulating 100 local points, the next due capture fails before queued uploads or new Platform restore requests are processed. A prolonged offline period can therefore prevent queued copies from uploading when connectivity returns. Cloud deletion alone does not reduce the count of local points. Local deletion also requires a Platform request, making offline capacity recovery harder.

**Required:** isolate capture, upload retry, request polling, and recovery acknowledgement failures; implement a deliberate retention policy and a safe way to handle full/offline storage. Test the 100-point boundary, disk-full/oversize failures, and continued upload/request progress.

Evidence: [capture limit at line 163; tick at lines 247–333; deletion at line 222](../../../business-server/src/backup/restore-point-service.ts), [Platform limits](../../config/restore_points.php).

### B4 — High: plaintext snapshots accumulate alongside encrypted restore points

**Source-confirmed.** Every restore-point capture first creates an ordinary `.sqlite` backup. Encrypting the point does not remove that plaintext snapshot. Deleting a restore point removes its JSON/encrypted file, not the original snapshot. Routine and safety backups are also plaintext. `BUSINESS_BACKUP_RETENTION_DAYS` is defined but has no consumer in the reviewed server source.

Consequently, cloud encryption does not mean all local backups are encrypted. Copies containing operational and authentication data accumulate on the same machine; unbounded growth can ultimately threaten available storage for business operations. Older passphrases may still be needed after passphrase changes, as the documentation correctly states.

**Required:** establish local encryption/access controls and retention for each backup class, preserve reviewed recovery safety copies deliberately, and monitor available space and the age of the latest successful offsite copy. Make local versus cloud protection explicit in the UI/runbook.

Evidence: [capture, lines 167 onward](../../../business-server/src/backup/restore-point-service.ts), [plaintext status and scheduler](../../../business-server/src/backup/backup-service.ts), [retention setting, line 34](../../../business-server/src/shared/environment.ts).

### B5 — Medium: a processing backup request can become difficult to clear

**Source-confirmed.** For a Platform create request, the server sets status to `processing` before capture. A later capture/upload error records `lastError` but does not finalize the request as failed. The Platform permits cancellation only while pending and allows only one pending/processing request per server. The local UI offers reject controls for restore requests, not create requests.

A recoverable upload error can retry successfully; a persistent error or lost server can leave the request blocking future requests without a clear operator action.

**Required:** add bounded retry/failure visibility and a safe administrative resolution path for stuck processing requests. Test permanent failures and request recovery after restart.

Evidence: [worker request handling](../../../business-server/src/backup/restore-point-service.ts), [request exclusivity](../../app/Domain/Backups/Services/RestorePointService.php), [pending-only cancellation](../../app/Livewire/Platform/Backups/RestorePointManagementPage.php), [local request controls](../../../business-server/src/renderer/src/RestorePointsManager.tsx).

### B6 — Medium: “verified” is narrower than demonstrated recoverability

**Source-confirmed plus validation gap.** Ordinary backups are immediately labelled verified after a hash is calculated, and `verifyBackup()` checks file existence/hash. This does not independently reopen that snapshot and test its SQLite integrity. Restore points add candidate integrity validation, which is stronger. Neither a checksum nor the current automated tests establish that an owner can complete disaster recovery within an acceptable time.

After restore, Platform transactions remain ahead of the recovered database; synchronization remains paused until someone types `RESUME SYNC`. This is an important safeguard, but a tested transaction-reconciliation procedure is still required to avoid losing operational records created after the snapshot.

**Required:** distinguish checksum verification, database integrity verification, and a completed recovery drill. Demonstrate reconciliation of sales, stock movements, debts/ledgers, and synchronization after a restore. Retain documented evidence before resuming sync.

Evidence: [backup verification](../../../business-server/src/backup/backup-service.ts), [resume handler, line 126](../../../business-server/src/main/index.ts), [synchronization hold, line 224](../../../business-server/src/sync/synchronization-worker.ts), [documented reconciliation requirement](../../BACKUP-RESTORE.md).

## Findings: Business Server and Business App updates

### U1 — High: both offered 2.0.0 installers are unsigned

**Directly observed.** `Get-AuthenticodeSignature` returned `NotSigned` for:

- `public/downloads/MarketLensBusinessServerSetup-2.0.0.exe`
- `public/downloads/MarketLensBusinessAppSetup-2.0.0.exe`

Both Forge configurations make signing optional when certificate environment variables are missing. The update execution paths compare SHA-256 and invoke `shell.openPath()`; they do not independently verify the expected publisher. A matching hash validates correspondence with the supplied manifest, not publisher identity. The Business App obtains that manifest policy over its configured LAN connection, so HTTPS on the subsequent installer download alone is not an independent authenticity guarantee.

**Required:** sign production installers, fail production release builds when signing is absent, and verify the expected publisher or independently signed release metadata before execution. Test altered metadata as well as altered installer bytes.

Evidence: [server signing configuration, line 51](../../../business-server/forge.config.ts), [client signing configuration, line 32](../../../business-app/forge.config.ts), [server installation handler](../../../business-server/src/main/index.ts), [client policy fetch and installer launch](../../../business-app/src/main/index.ts).

### U2 — High: server update IPC accepts a token without validating its current session

**Source-confirmed.** Update check/download/install and Platform-address changes use `authorizeReporting()`. It verifies the token and role permission but does not check `user_sessions` for revocation/expiry, current user active status, or a server-local session. Backup authorization already performs these stronger checks.

A still-cryptographically-valid token whose session was revoked, or whose account was disabled while its role remains, can therefore pass the update IPC guard. This requires access to the desktop IPC surface; it is not a demonstrated unauthenticated remote endpoint.

**Required:** use a shared current-session authorization boundary for privileged IPC, including active-user and appropriate local-session checks, and test revoked/disabled sessions on installation and connection-setting changes.

Evidence: [update handlers, lines 129–135; authorizeReporting, lines 180–193](../../../business-server/src/main/index.ts), [stronger backup authorization](../../../business-server/src/backup/backup-authorization.ts).

### U3 — High: legacy upgrade bootstrapping can replace the secret needed by migrated data

**Source-confirmed; packaged legacy-upgrade drill not executed.** Packaged startup creates a new persistent `.env` with fresh secrets before calling `configurePersistentStorage()`. That helper copies the legacy configuration only if the persistent file does not exist. On a first migration to the persistent directory, database copying may succeed while the matching legacy configuration is skipped.

This can leave migrated encrypted credentials unreadable under the new `BUSINESS_JWT_SECRET`, despite retaining the SQLite file. The existing storage test exercises the helper alone and therefore does not cover this bootstrap ordering.

**Required:** migrate and validate the legacy configuration/database together before generating new secrets. Test the actual packaged bootstrap with existing activation and encrypted credentials, confirming successful decryption, sign-in, synchronization, and backup access after upgrading.

Evidence: [bootstrap, lines 310–325](../../../business-server/src/main/index.ts), [configuration-copy condition](../../../business-server/src/services/application/persistent-storage.ts), [helper-only test](../../../business-server/tests/update-safety.test.ts).

### U4 — High: server installation lacks one exclusive maintenance boundary

**Source-confirmed.** The update handler checks `restoring` only at entry and then awaits `prepareDownloadedInstall()`. The pre-update snapshot is taken before stopping restore-point work, scheduled backups, synchronization, and the local API. There is no corresponding `updating` guard covering the whole operation.

Business writes can occur after the pre-update snapshot but before shutdown. An overlapping restore or repeated install IPC call can also enter during awaits and compete over the database lifecycle. Pre-migration backups mitigate some schema-change recovery risk, but do not make this installation sequence exclusive.

**Required:** establish one maintenance lock across update, restore, and relevant background/IPC writes; stop accepting operational writes, drain active work, capture and validate the final safety snapshot, then close and install. Test concurrent install/restore requests and failure recovery at each stage.

Evidence: [installation sequence, lines 131–149; restore guard, lines 154–175](../../../business-server/src/main/index.ts), [pre-update backup, lines 118–123](../../../business-server/src/services/application/update-service.ts).

### U5 — High: packaged server defaults do not enforce production transport policy

**Source-confirmed.** `BUSINESS_APP_ENV` defaults to `local`; newly generated packaged configuration does not set it to production. Update HTTPS enforcement depends on this environment value, and restore-point upload accepts HTTP outside the literal production environment. Saved public Platform addresses are validated to require HTTPS, which reduces exposure, but environment configuration and the explicit manifest override do not share that same guarantee.

**Required:** make packaged production behavior secure by default. Require explicit development exceptions and validate all manifest/backup endpoint sources consistently. Test a clean packaged installation without pre-existing environment configuration.

Evidence: [environment default, line 16](../../../business-server/src/shared/environment.ts), [generated configuration, lines 311–318](../../../business-server/src/main/index.ts), [update URL check, line 146](../../../business-server/src/services/application/update-service.ts), [backup request transport](../../../business-server/src/backup/restore-point-service.ts), [saved address validation](../../../business-server/src/shared/platform-address.ts).

### U6 — Medium: download menus are disconnected from the published release catalog

**Source-confirmed.** Public/auth download links and the owner sidebar hard-code the two `2.0.0` files. They do not select the newest eligible release from Application Versions. Publishing 2.0.1, withdrawing 2.0.0, or changing architecture availability will not update these menus. The files are publicly accessible independently of signed release URLs.

This is acceptable only as an explicitly maintained fixed-version download mechanism; it is not a “latest installer” path. Also, the signed download controller checks file existence but does not recheck publication/active status, so a previously issued URL can remain usable until expiry after a release is withdrawn.

**Required:** unify manual download links and updater release selection, display platform/architecture/version, and define withdrawal behavior. Verify that each menu returns the intended product and the latest eligible installer.

Evidence: [shared downloads](../../resources/views/components/desktop-downloads.blade.php), [owner sidebar, lines 28–32](../../resources/views/components/app/sidebar.blade.php), [release selection](../../app/Domain/Versions/Services/ApplicationVersionService.php), [signed download controller](../../app/Http/Controllers/Platform/ApplicationVersionDownloadController.php).

### U7 — Medium: release verification does not yet cover installed upgrades end to end

**Observed configuration/test gap.** Platform CI still uses `working-directory: apps/platform`, which does not exist in this root-Laravel checkout. No `.github` workflow directory was found in either sibling desktop project. This does not exclude external CI, but no external release evidence was supplied.

Existing tests cover versions, bounded downloads, mocked policy routing, migrations, and restore primitives. The restore Playwright suite replaces native recovery calls with mocks. No installed 1.x-to-2.0.0 or 2.0.0-to-newer upgrade, interrupted installer, signature rejection, or replacement-machine recovery was executed in this audit.

**Required:** repair the Platform workflow, establish a repeatable Windows desktop release gate, record source/build/installer provenance, and run upgrade/recovery drills on disposable installations containing realistic synthetic business records.

Evidence: [Platform workflow](../../.github/workflows/platform.yml), [server update tests](../../../business-server/tests/update-safety.test.ts), [mocked restore UI tests](../../../business-server/tests/restore-points.e2e.ts), [deployment documentation placeholder](../deployment/README.md).

## Automated verification performed

| Check | Result | Interpretation |
| --- | --- | --- |
| Platform RestorePointManagementTest | 7 passed | Upload/scope/idempotency/tombstones, requests, confirmation, and basic permissions. |
| Platform DesktopReleaseManifestTest | 13 passed | Release selection, eligibility, selectors, and signed downloads. |
| Business App desktop-update + client-release-policy | 19 passed | Version ordering, bounded downloads, and policy identity/architecture/channel validation. |
| Business Server desktop-update | 10 passed under Electron | Download/version helpers. |
| Business Server client-update-route | 2 passed under Electron | Selector forwarding and offline response using mocked services. |
| Business Server update-safety | 4 passed under Electron | Persistent-storage helper, migration safeguards, and mocked pre-update backup. |
| Business Server restore-point-management | 10 passed under Electron | Crypto, WAL snapshot, offline retry/schedule, scope/schema, authorization, security preservation, and file-swap recovery. |
| Business Server backup-recovery | 2 failed under Electron | Windows `EBUSY` deleting the test SQLite file during fixture cleanup/setup; the suite does not close its database handles. These failures do not demonstrate a production restore defect. |
| Installer Authenticode inspection | Both NotSigned | Direct release-artifact finding. |

Final focused test tally: **65 passed, 2 failed**. The first server attempt under standalone Node 22 failed 14 tests due to native ABI mismatch (installed module ABI 136; Node expected 127). Rerunning with installed Electron 37.2.6, Node 22.17.1 / ABI 136, resolved the native loading failures; no dependencies were rebuilt. The two cleanup failures remained. Production PostgreSQL concurrency and real multipart network transfers were not tested; Platform tests used in-memory SQLite and fake storage.

Commands used, with installed PHP/Node/Electron binaries selected explicitly:

```text
php artisan test tests/Feature/RestorePointManagementTest.php tests/Feature/DesktopReleaseManifestTest.php
node node_modules/vitest/vitest.mjs run tests/desktop-update.test.ts tests/client-release-policy.test.ts
ELECTRON_RUN_AS_NODE=1 electron.exe node_modules/vitest/vitest.mjs run tests/restore-point-management.test.ts tests/backup-recovery.test.ts tests/update-safety.test.ts tests/desktop-update.test.ts tests/client-update-route.test.ts
Get-AuthenticodeSignature <BusinessServerSetup-2.0.0.exe>, <BusinessAppSetup-2.0.0.exe>
```

## Remediation and release gates

| Priority | Work | Acceptance evidence |
| --- | --- | --- |
| Before general rollout | Fix B1 and U2 authorization | Same-business cross-branch denials; revoked/disabled/local-session IPC tests. |
| Before unattended backup reliance | Fix B3–B5 retention, worker progress, and stuck requests | Full-disk, quota, offline, retry, deletion, and restart scenarios keep valid copies safe and surface actionable errors. |
| Before shipping production installers | Fix U1, U3–U5 | Signed artifacts; clean packaged startup enforces transport policy; legacy encrypted credentials survive; update/restore overlap is rejected. |
| Before claiming disaster recovery | Deliver B2 recovery workflow and B6 reconciliation runbook | Recover synthetic branch operations onto a replacement machine, measure data loss/time, reconcile records, then safely resume sync. |
| Before declaring release process ready | Fix U6–U7 and backup test cleanup | Catalog-backed downloads, passing relevant CI, artifact provenance, and recorded installed-upgrade/rollback drills. |

Deployment checks still required: apply the restore-point migration; confirm private durable storage and independent backups of Platform metadata/files; preserve recovery credentials outside the server; configure upload/time limits for 512 MB restore points and 750 MB installers; monitor failed/overdue backups and storage use; verify the actual release catalog and each download path from the deployed environment.

**Decision:** the implementation is suitable for further controlled testing with synthetic data. Do not treat it as production-ready disaster recovery or a fully verified desktop update system until the high-severity findings and applicable release gates are closed.