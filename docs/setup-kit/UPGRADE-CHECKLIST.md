# Feature updates and upgrades

## Existing business

1. Read the published release notes and compatibility requirements. Schedule a
   quiet period and finish active transactions. Record the current Server/App
   versions and verify a current backup and recovery access.
2. Upgrade Business Server first using System Update. Confirm the real Platform
   address in connection settings, check for the new release, download and
   confirm installation. Its updater verifies the installer and takes a
   pre-update backup after draining operational work.
3. Restart and confirm activation, users, configuration, stock/debt totals,
   backup health and synchronization. Check Data Setup & Transfers when this
   upgrade introduces CSV support.
4. Upgrade Business Apps using their update action. An older client that cannot
   use the updater can run the published signed App installer over the existing
   installation. Preserve its user-data directory and pairing settings.
5. Check pairing, role access and the operational workflow on a pilot
   workstation before updating the remaining workstations.

Upgrades preserve existing business data. Do not uninstall, delete data
directories, recreate the business or reimport opening stock/balances to upgrade.
Download the current setup pack when template fields change. If an upgrade fails,
retain the error and contact support; assess subsequent trading before restoring
a backup. Do not force an older application onto a newer database schema.

Internet is needed for release checks/downloads. Normal business operations
continue locally when it is unavailable; an offline check is not an upgrade.

## Release administrator

Build and test signed installers with the approved publisher certificate and
correct product, version, channel and architecture. Test clean installation and
upgrade over the supported previous release, backup recovery and offline use.
Deploy compatible Platform support first, publish Server next, then App. Use the
Platform Application Versions page to upload the signed EXE, release notes and
compatibility policy; publish only verified releases. The existing installer
manifests and checksum/signature checks remain the update mechanism.

Regenerate this pack from the exact Server source for a CSV feature release.
Review its schema, sample files and manifest; run the setup-pack and desktop
release tests. Deploy the pack and manifest together with the Platform. Pilot
with one business before wider rollout. CSV schema changes require a newly
reviewed version of this pack and explicit importer compatibility.
