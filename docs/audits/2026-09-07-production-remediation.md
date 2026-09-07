# Backup, recovery and desktop update remediation

Date: 7 September 2026  
Scope: Platform, standalone Business Server, standalone Business App  
Release source: Business Server 2.0.1 / Business App 2.0.1  
Decision: **Source fixes verified; production rollout remains gated on signing, deployment configuration and installed acceptance drills.**

This is the follow-up to [the readiness audit](2026-09-07-backup-restore-updates-readiness.md). Changes were made in the existing working trees, preserving other in-progress work. No live database was restored, production service deployed, release published, or installer installed during remediation.

## Findings and disposition

| Finding | Remediation | Remaining production action |
| --- | --- | --- |
| B1: branch authorization | Backup service and device selector enforce assigned branches. Recovery-copy downloads use authenticated tenant context and restore permission. | Verify intended owner/delegate grants in production. |
| B2: lost-server recovery | Encrypted recovery-kit export; explicit recovery mode into a new empty directory; identity/schema/integrity validation; new local administrator; historical access disabled; new JWT/pairing secrets; credential rotation with retry; synchronization hold. | Export independent kits and encrypted copies before an incident. Retire the original server and perform a replacement-machine drill. Revoked/lost credentials require an approved administrative recovery procedure. |
| B3: unattended worker failure | Polling, captures, request handling and upload retries are isolated. Confirmed uploaded copies can free local capacity. Pending and requested copies are protected; cloud retention preserves the newest point. | Establish storage capacity and monitor failed/overdue uploads. Full offline storage still needs operator intervention; pending data is not silently discarded. |
| B4: plaintext/retention | Encrypted capture intermediates are removed; protected local directories; automatic snapshot/cloud retention; visible low-disk and stale-offsite warnings. | Local manual/update/safety snapshots remain plaintext and deliberately retained. Configure disk encryption, separately archive reviewed copies and inspect legacy snapshots from older releases. The Platform storage disk must be private and backed by tested offsite infrastructure. |
| B5: stuck requests | Processing create requests can be cancelled; executing restores remain protected. | Review stale requests during operations. |
| B6: recovery evidence | Snapshot integrity checks and an automated replacement recovery drill verify captured sales, stock movements and ledger entries without reviving historical access. Recovery/reconciliation acceptance instructions added. | Measure real recovery point/time objectives and reconcile representative post-point records before owner sign-off and synchronization resumption. The software does not merge newer Platform transactions automatically. |
| U1: installer trust | Both installers and application executables have signing configuration. Packaged updates require valid Authenticode signatures from build-pinned publishers plus checksum validation. Production make rejects missing signing inputs. Unsigned public 2.0.0 files moved to private quarantine. | Supply the real certificate/pins, build and inspect signed artifacts, then publish a pilot release. Existing older clients must bootstrap onto the trusted version using the verified installer. |
| U2: privileged sessions | Server IPC validates current local session, active user and current grants; workstation sessions cannot invoke privileged local operations. | Recheck production administrator assignments. |
| U3: legacy configuration | Original legacy configuration is preserved before generating fresh secrets; an existing database without its configuration fails explicitly. | Test the actual previous installation layout and retain an independent configuration copy. |
| U4: update coordination | Exclusive update/restore gate; drain workers and API before verified pre-update snapshot; checkpoint/close before installer launch; recover services after pre-close failure. | Run concurrent-activity and interrupted installed-update drills on spare hardware. |
| U5: production transport | Packaged Business Server uses production mode; cloud backup and release transport checks require HTTPS. | Configure the real HTTPS Platform URL, proxy trust and certificates. Local operational LAN APIs keep their established architecture. |
| U6: downloads | Public and owner-sidebar menus resolve the current eligible stable catalog release. Artifact downloads recheck withdrawal/future-release status. | Publish signed releases in the catalog; links return 404 while no eligible artifact exists. |
| U7: release verification | Platform CI root fixed; native Electron test runner and cleanup repaired; strict desktop builds fixed; compatible App Zod dependencies pinned; Windows readiness and protected manual signed-release workflows added. | Configure repository/environment secrets and publisher variable; run hosted CI. Business App currently lacks a Git repository, so source provenance and hosted automation still require team setup. |

## Verification performed

- Platform backup/release feature tests: **25 passed**, 109 assertions, isolated SQLite database and fake storage.
- Business Server readiness suite: **42 passed** across eight files, using Electron's native-module ABI. Covers crypto/tampering, WAL capture, offline retries, branch/schema rejection, restore interruption/safety snapshots, local permissions, disaster recovery, 100-point retention, cloud retention, credential rotation retries, update integrity and publisher policy, maintenance exclusion and backup-health warnings.
- Additional server activation/health tests passed in the expanded run; three inventory route tests passed after repairing stale synthetic-admin setup and Windows handle cleanup. Initial failures/timeouts were addressed and affected tests rerun.
- Business App full unit suite: **104 passed** across 25 files, including shared package tests.
- Both desktop `tsc -b` checks passed with strict settings retained. Both production Vite builds passed. The App build reports a non-failing large-chunk warning.
- App clean-install lockfile plan (`npm ci --dry-run --offline --ignore-scripts`) passed after correcting stale nested Zod entries. This was not a fresh hosted CI run.
- Changed PHP formatted with Pint; Platform patch whitespace checked; PowerShell recovery/release scripts parsed successfully.

The tested builds are source build outputs, not signed release installers. No certificate was provided and the manual signed-release workflow has not been executed. Unit tests of signature policy do not replace verification of a real signed installer.

## Release procedure and outstanding inputs

1. Provide the production Platform URL and configure HTTPS, APP_URL, trusted proxy settings and upload/time limits on the deployment host. Configure private offsite restore-point storage, backup monitoring and access controls. No production environment values were inferred or committed.
2. Configure the protected `desktop-release` environment in each desktop source repository. Supply `WINDOWS_CERTIFICATE_BASE64` and `WINDOWS_CERTIFICATE_PASSWORD` as secrets, and `WINDOWS_PUBLISHER_THUMBPRINTS` as a variable. Run the manual signed Windows release candidate workflow. It builds x64, verifies installer signatures/publishers and produces checksums/artifacts without publishing to the Platform.
3. Follow [desktop release publishing](../deployment/desktop-releases.md) and [backup/recovery operations](../../BACKUP-RESTORE.md). Perform clean-install, in-place upgrade, interrupted-update, restore and replacement-machine drills with representative synthetic/copied test data.
4. Record measured recovery objectives and transaction/stock/ledger reconciliation; obtain owner acceptance. Deploy Platform support, pilot Business Server first, then Business App. Promote only after these gates pass.

The original unsigned files are retained at `storage/app/private/quarantined-desktop-releases/2.0.0` for review, outside the web root. Existing data and unrelated working-tree changes were not reset. Do not label this deployment production-ready until the outstanding operational gates have evidence.
