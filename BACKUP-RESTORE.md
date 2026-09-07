# Platform restore points

The Business Server stores SQLite snapshots as encrypted restore points and uploads them to the Platform using its existing device credential. Business operations do not require this connection.

## Setup

1. Deploy the Platform code and run its new migration:
   php artisan migrate --path=database/migrations/2026_09_07_000001_create_restore_points.php
2. Rebuild/restart the Business Server. SQLite permission migration 3 runs automatically.
3. On the server, open Admin > Backups & Restore. Save a recovery passphrase of at least 16 characters, and store a separate copy outside the computer. The Platform cannot recover this passphrase.
4. Set a backup interval in hours (0 disables scheduling), or create a named point manually.
5. Open Backups & Restore in the Platform sidebar, or /admin/platform/restore-points for a Platform administrator. Select a Business Server to inspect copies or request a backup/restore.

Use the server's existing Platform connection setting. Device API endpoints are under /api/v1/device/restore-points. HTTPS is required in production. No separate manifest endpoint or backup API key is needed.

## Permissions

Both sides provide backups.view, backups.create, backups.restore, and backups.delete. Local Owner/Administrator roles and Platform owners/super administrators have access; grant other staff only the required permissions. Server operations require a current, non-revoked local session. Client App sessions cannot invoke recovery.

## Recovery

The Platform requests a restore; it never replaces a live local database itself. A local administrator reviews the point, types RESTORE, and confirms. The server validates encrypted and plaintext checksums, authenticated decryption, SQLite integrity, database schema, business, branch, and device identity. It drains synchronization and active requests, creates a pre-restore safety snapshot, stages the replacement, and restarts.

The current installation credentials, users, device registrations, role/direct grants, and branch assignments are preserved. Users missing from the current server remain inactive. Sessions are revoked. A durable journal allows interrupted file swaps to roll back, and records the independent safety snapshot. The screen can restore this safety snapshot with another confirmation.

Synchronization stays paused after recovery. Review transactions created after the selected point and reconcile them with the safety snapshot and Platform records before typing RESUME SYNC. Restoring does not roll back Platform records or automatically merge newer transactions. Local audit records and Platform request history record the recovery.

Recovery is restricted to the same registered server identity, business, and branch. For a replacement computer, use the encrypted recovery kit procedure below. It retains the original server identity; do not activate a second server for the same recovery. These are database snapshots; installers, external documents, and other filesystem assets are not included. No live business restore is performed by the implementation or tests.

## Storage and retry behavior

Local restore point files, settings, and upload state are in LOCAL_BACKUP_PATH/restore-points, outside the database being restored. Recovery and synchronization-pause journals sit alongside the SQLite database. The passphrase is encrypted locally with BUSINESS_JWT_SECRET; preserve the persistent server configuration as part of disaster recovery. Changing the passphrase affects new points; older points still require their original passphrase.

The Platform defaults to the private local storage disk. Set RESTORE_POINT_DISK to another private Laravel disk if needed. Public disks are rejected. Limits are 512 MB per encrypted file and 100 retained points per device. Configure PHP upload_max_filesize to at least 512M, post_max_size and any reverse proxy body limit above 512 MB, and sufficient request timeouts. Platform config is config/restore_points.php.

Scheduled local capture works offline. Uploads retry every minute using the same UUID and encrypted bytes, so retries do not create duplicates. Deleted Platform points leave tombstones to prevent recreation by delayed upload retries. Deleting a Platform copy does not erase an already downloaded local copy. Local deletion requires a Platform connection to confirm cloud removal. Pre-restore safety snapshots and ordinary local BackupService snapshots are retained separately from encrypted restore points; keep them until recovery has been reviewed.

## Validation

Platform feature tests: tests/Feature/RestorePointManagementTest.php.
Server SQLite/crypto/recovery tests: tests/restore-point-management.test.ts.
Desktop screen tests: tests/restore-points.e2e.ts (renderer on port 4191).

## Replacement computer recovery (2.0.1)

Before an incident, use **Export recovery kit** in the Business Server backup screen. Choose a separate drive and a strong passphrase (at least 16 characters). Store the encrypted kit and a downloaded encrypted restore point off the server; keep their passphrases separately. Export a new kit after credential rotation. Kits contain sensitive identity/configuration credentials and do not replace data backups.

1. Retire or isolate the original server so that it cannot resume operations or synchronization.
2. Install the trusted Business Server release on a replacement Windows machine. Download the encrypted point from Platform **Download recovery copy**, using an owner or authorized branch account.
3. Run `business-server/scripts/recover-server.ps1`. Supply the installed executable path, kit path, backup path, a NEW empty data directory, and a recovery administrator username. The script prompts securely for passphrases and a new password; type `RECOVER`. Never pass passwords as command-line arguments.
4. Start the executable with `--user-data-dir` pointing to that recovered directory. Keep this argument in the shortcut used for future launches.
5. Recovery creates a new local administrator, disables historical users, revokes workstation registrations and sessions, and keeps synchronization paused. Review and reauthorize staff and workstations locally. The Platform remains outside operational authentication.
6. Reconcile the selected point with the most recent Platform records and any separately preserved records. Before **RESUME SYNC**, the replacement rotates its Platform device credential. A network failure leaves recovery paused and permits retry; the original token stops working after rotation.
7. Export a fresh recovery kit, configure a backup schedule/passphrase, capture/upload a point, and verify a second-machine drill before closing the incident.

Do not delete the old data, safety snapshot, recovery journals, or protected failed-recovery staging directory until reconciliation is signed off. Database snapshots exclude external documents and other filesystem assets: back these up separately. Lost kit/passphrases require an independently approved recovery procedure; the Platform cannot decrypt copies.

## Retention and protection

`BUSINESS_BACKUP_RETENTION_DAYS` controls automatic local snapshots and cloud restore-point age. The newest copy is retained; pending uploads and requested restore points are protected. At the local 100-point limit, confirmed uploaded copies can be pruned to free capacity. If all copies are unuploaded, capture stops visibly while polling/upload retries continue. Storage exhaustion still requires operator attention.

Plaintext intermediates for encrypted restore points are removed. Ordinary local snapshots and pre-update/pre-restore safety copies remain plaintext in directories restricted to the current OS account, administrators, and SYSTEM on Windows (0700 directories on POSIX). Use disk encryption and encrypted removable media for physical theft protection. Manual and safety snapshots are retained for deliberate review; archive/remove them through an approved maintenance procedure after recovery sign-off.

## Recovery acceptance record

For each pilot and periodic drill, record business/branch/device UUID, release and schema versions, point UUID/capture time, checksum, operator, start/end times, recovered transaction counts, sales totals, stock movement totals, customer/supplier ledger totals, and pending/failed/conflict sync counts. Compare post-point transactions with preserved evidence; apply approved compensating movements/ledger entries rather than rewriting immutable transactions. Obtain owner sign-off before resuming synchronization.

Set business-specific recovery objectives before rollout. A one-hour capture interval targets at most one hour of local data loss only while captures succeed; offsite loss exposure depends on the latest successful upload. Measure restoration duration on representative data and hardware. Check backup errors, free disk space, last capture and last uploaded point daily; alert operational staff when agreed limits are exceeded.
