# Managed download update

Release files now come from the Platform downloads folder. The version-management upload field has been replaced with a prepared-package selector. See [managed downloads](managed-downloads.md) for current packaging and update steps.

# Desktop release publishing and updates

## Platform setup

The existing Super Admin > Application Versions page now publishes desktop manifests at:

- /api/v1/releases/business-server/manifest
- /api/v1/releases/business-app/manifest

These endpoints expose public release metadata only; no tenant or business records. Installer downloads use signed URLs that expire after 30 minutes. A fresh check gets a fresh URL. No database migration is required for this change; it uses the existing application_versions table.

On the Platform host, deploy the changed source and run php artisan optimize:clear. Configure HTTPS and the correct APP_URL (including trusted reverse-proxy HTTPS settings). Store installers on the existing local storage disk. PHP upload_max_filesize and post_max_size, and the reverse proxy body limit, must allow your installer size (up to 750 MB); configure PHP request timeouts for uploads. Livewire's temporary upload limit is now 750 MB and its upload window is 30 minutes.

## Publish a release

1. Increase the desktop package.json version and matching package-lock.json version. This change prepares Business Server 2.0.1 and Business App 2.0.1.
2. Run each project's checks and npm run make on Windows, selecting the intended architecture. Set BUSINESS_UPDATE_CHANNEL for the server or MARKETLENS_UPDATE_CHANNEL for the client to stable or beta before building. The selected channel is embedded into the app; runtime environment configuration can override it.
3. Configure WINDOWS_CERTIFICATE_FILE, WINDOWS_CERTIFICATE_PASSWORD, and WINDOWS_PUBLISHER_THUMBPRINTS in the build environment to sign each installer. Never commit credentials.
4. In Super Admin > Application Versions, choose Business Server or Business App, enter the version, platform windows, and the exact architecture x64, arm64, or ia32. Upload the Squirrel Setup .exe from out/make, not a ZIP or .nupkg. Enter release notes and a valid minimum supported version if needed.
5. Choose stable or beta, Published, and Active. Leave the release date empty for immediate availability, or select a past/current date. The Platform calculates SHA-256 from the uploaded installer.
6. Open the appropriate manifest URL with ?channel=stable&platform=windows&architecture=x64. HTTP 200 returns the release; HTTP 204 means no eligible release. Draft, inactive, future, missing-file, ZIP, wrong-channel, wrong-architecture, and wrong-platform releases are excluded. The highest eligible version wins, regardless of upload order.

Publishing Business Server and Business App releases is independent. Keep local APIs compatible during staged rollouts. Database migrations belong to the Business Server and must preserve operational data. Update checks never authorize deleting sales or resetting databases.

## Installed Business Server

The default endpoint is derived from the Platform address saved in System > System Update > Platform connection settings, or PLATFORM_SYNC_BASE_URL. BUSINESS_UPDATE_MANIFEST_URL remains an optional override for the server only. Remove an obsolete example override to use automatic discovery.

The server checks after sign-in and every 15 minutes while the administration window is running. It shows a banner when a newer release exists. System Update also offers a manual check, download, and confirmed install. Update checks failing offline do not interrupt local business operations. Downloads are bounded, staged, and SHA-256 verified; the server verifies the installer again and creates a verified pre-update backup after stopping operational requests, draining synchronization and backups, and acquiring the exclusive update/restore gate. It then installs.

## Installed Business App

The client checks on startup when a saved session exists and on its role dashboard, then every 15 minutes while that dashboard is open. Check updates is also available on the dashboard. It requests its own version, channel, and architecture from the authenticated, paired-device Business Server API:

/api/v1/system/update-policy?application=business-app&currentVersion=1.0.1&channel=stable&architecture=x64

The server fetches the Business App manifest, never its own server manifest. The client rejects another product, architecture, or channel; downloads the signed installer URL in the main process; verifies SHA-256; and asks before launching installation. Operational requests still use the local Business Server. Internet is needed to download a new release, not to conduct normal business.

## First rollout for existing installations

Deploy the Platform changes first. For an older installed Business Server, set BUSINESS_UPDATE_MANIFEST_URL in %APPDATA%\MarketLens Business Server\.env to your real HTTPS Platform URL plus /api/v1/releases/business-server/manifest. Restart it, then check and install the published server release. This bootstraps automatic endpoint discovery without uninstalling or deleting data.

Update the Business Server before clients: old server code may send its own installer policy to clients. If an existing client cannot use the corrected update flow, run the new Business App Setup .exe once over the existing installation. Preserve its user-data directory and activation settings. Subsequent releases use the in-app updater. Do not uninstall or clear business data to update.

This implementation does not deploy the Platform, upload a release, or install software onto operational machines by itself. Before production rollout, test an installer on a spare Windows machine with a copied test database, including restart, activation persistence, backup restoration, and a failed-download retry.

## Production release gate (2.0.1)

Both desktop builds embed `WINDOWS_PUBLISHER_THUMBPRINTS`: comma-separated 40-character certificate thumbprints. Windows installation checks require a valid Authenticode signature from one of these pinned certificates, in addition to the release SHA-256. Set pins BEFORE compilation. During certificate rotation, release a build trusting both certificates before switching publishers. `MARKETLENS_ALLOW_UNSIGNED_DEVELOPMENT=1` is for development packaging only; it does not disable packaged installer verification and must not be used for production releases.

Run the Windows readiness workflow and full build in each standalone project. Keep package lockfiles with the release; local shared packages must use compatible pinned Zod versions. The Business App source directory must be put into the team's version control/release process before claiming reproducible releases.

The public and owner-sidebar download menus resolve `/downloads/business-server` and `/downloads/business-app` to the active stable Windows x64 release (an explicit supported architecture may be selected by query). No eligible release returns 404. Signed artifact downloads also recheck publication, active status, and release date. Withdrawing a release stops its existing signed URLs. The former unsigned 2.0.0 files were moved from public downloads into private quarantine; do not republish them as trusted production artifacts.

Before publishing: build with the real signing certificate; inspect the resulting signature/publisher and checksum; test clean install, upgrade over the previous version, restart, persistent identity/configuration, rollback/recovery, offline operation, interrupted download, and pairing on spare Windows machines. Verify representative database migrations and the post-update snapshot cutoff. Publish Platform support first, then Business Server, then Business App. Promote a small pilot before broad rollout.

A source build is not evidence that a signed installer was made or deployed. Production APP_URL/HTTPS, reverse-proxy upload limits, private offsite storage credentials/lifecycle, monitoring, a real certificate, and installed upgrade/recovery acceptance remain deployment prerequisites.

## Downloadable client setup templates

The Platform offers one [client setup ZIP](client-setup-kit.md) alongside the
Server/App installer downloads and on device activation. It includes all 18
supported CSV template sets and new-installation/upgrade checklists. Regenerate
and verify the pack from the exact Business Server source when CSV features change.
