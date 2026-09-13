# Public deployment pages and Server 2.1.0

Public pages:

- `/deployment`: first installation, additional workstations, upgrade order and
  published stable Windows installers selected by architecture.
- `/setup-kits`: schema/version details, all 18 template sets and field lists,
  file limits, import guidance and the ZIP download.
- `/downloads/client-setup-kit`: the checksum-verified schema-1 archive.

Main desktop/mobile navigation, footer and the owner sidebar link to the pages.
Unavailable or withdrawn desktop releases are not advertised as downloadable
by the deployment page. The existing signed installer routes remain authoritative.

Business Server is bumped from 2.0.1 to 2.1.0 for the additive CSV feature release
(schema 4). Both package metadata and lockfile root versions match. The setup
archive was regenerated from this source. Business App remains 2.0.1 because no
client change is required by these Server administration features; it is rebuilt
and checked against its current updater compatibility tests.

Production-trusted signing is not configured. A separate local development signing identity is now available through scripts/use-development-signing.ps1 for beta test builds only. Source builds do not produce publishable production
installers. Before publication, supply the existing signing configuration through
the release environment, rerun both apps' release checks/builds so pins are embedded,
then run `npm run make` in each standalone project. Verify signatures, checksums,
clean installation and upgrade/recovery on a pilot Windows machine. Deploy Platform
support and the setup archive first, then publish Server 2.1.0; publish an App
release only when its own source changes justify a new version. Do not upload an
old 2.0.1 Server installer as 2.1.0 or replace a published artifact in place.

See [desktop release operations](desktop-releases.md) and
[setup pack regeneration](client-setup-kit.md). No release is published to the
Platform catalog by adding these pages or rebuilding the source bundles.
## Verification (2026-09-13)

- Platform: 19 public-page/setup-pack/release tests passed (206 assertions); asset build and targeted Pint checks passed.
- Business App 2.0.1: 22 readiness tests, typecheck and main/preload/renderer builds passed. Existing renderer chunk-size warning remains informational.
- Business Server 2.1.0: 75 readiness tests passed with `--maxWorkers=1`, including the 10,000-product fixture; typecheck passed. The first concurrent run passed its assertions but failed during worker shutdown, so the successful single-worker rerun is the accepted test result.
- Setup pack: generated from Server 2.1.0 and reproducibility check passed.

These are local verification results, not evidence of production deployment,
signed-installer creation or installed-machine upgrade acceptance.

Business Server 2.1.0 main, preload and renderer production bundles also completed
successfully. Both desktop source rebuilds and the Platform asset build are complete.
