# Platform client setup ZIP

The public `/downloads/client-setup-kit` route downloads
`marketlens-client-setup-v1.zip`. Links appear in the public/auth download menu,
owner sidebar, device activation and Application-Version Management screens.
The pack contains 18 blank CSV templates, separate fictional examples, field
guides, installation and upgrade checklists, and a schema/source manifest.
It contains no customer records or credentials. Imports run on Business Server.

The reviewed release artifact lives in `resources/setup-kits/` so the deployed
Platform can serve it without a sibling checkout, Node.js, or ZIP generation at
request time. Its SHA-256 must match the accompanying manifest; an incomplete
or inconsistent deployment fails closed. Deploy the ZIP and manifest together.
The existing Server/App download links and signed update manifests still supply
installers. This pack is not an installer or a database migration.

## Refresh for a feature release

From this Platform checkout, with the exact standalone Server source and its
installed TypeScript dependency available, run:

    node scripts/build-client-setup-kit.cjs C:/path/to/business-server
    node scripts/build-client-setup-kit.cjs C:/path/to/business-server --check
    php artisan test tests/Feature/ClientSetupKitDownloadTest.php tests/Feature/DesktopReleaseManifestTest.php

The generator reads only the Server CSV codec, dataset registry, template library,
package version and owner guide. It uses the Server's template generator instead
of maintaining a second set of import rules in the Platform. PHP CLI with the ZIP
extension builds the archive; PHP_BINARY may select another CLI executable.
Example dates are fixed fictional dates for reproducible builds. `--check` fails
if the generated artifact differs from the reviewed files, without replacing them.

Review and commit both artifact files together with the source/checklist changes.
For a new CSV schema, explicitly version the generator, artifact, controller and
compatibility tests; do not relabel incompatible columns as schema 1. The manifest
records source hashes and application version, but version alone does not prove
CSV feature availability: users must have a Server build with Data Setup & Transfers.
Run the build/check command in release CI with that exact Server checkout.

The current templates include controlled opening positions and draft purchases/
counts; staff/configuration/ownership remain export-only according to the CSV audit.
Optional service templates require the relevant module. New workstations pair with
an existing Server and do not reimport opening data. Upgrade Server before Apps;
retain existing identity, configuration and business records.