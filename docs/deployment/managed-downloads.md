# Managed desktop downloads

The Platform serves prepared ZIPs from its own downloads folder. Application-Version Management has no file upload: select a prepared package and the application, version, channel and architecture are filled automatically.

Public links stay the same:
- /downloads/business-server
- /downloads/business-app

Each ZIP contains the Windows installer, START-HERE.md, REQUIREMENTS.md and setup-templates.zip. Electron and the application runtime are bundled; end users do not install development tools. The current 2.2.0 Windows x64 packages use development signing and the beta channel.

## Folder layout

downloads/
  business-server/beta/2.2.0/x64/
    release.json
    business-server-2.2.0-x64-setup.exe
    business-server-2.2.0-x64.zip
  business-app/beta/2.2.0/x64/
    release.json
    business-app-2.2.0-x64-setup.exe
    business-app-2.2.0-x64.zip

The files stay outside public/. Laravel selects complete packages and verifies the ZIP checksum before download. Never place credentials, source databases, private keys or customer data in this folder. Deploy this folder with the Platform; generated binaries are excluded from Git.

## Updating a release

1. Increase the desktop version and build/test/sign its installer.
2. On Windows run scripts/publish-download-package.ps1 with -Application business-server or business-app, -Installer pointing to the .exe, and -Channel stable or beta. It verifies the installer signature and reads its actual product version.
3. Packaging stages a complete version directory and renames it into place. The permanent public link automatically selects the highest stable version for the architecture, or the highest beta when no stable package exists. Internal packages are never selected publicly.
4. To offer an in-app update, open Application-Version Management, select the prepared package, set the policy/notes and publish. Its signed update URL serves the matching .exe. The public ZIP download works without a version-management record.

The existing build-development-installers.ps1 now also packages successful builds into this folder. Build and package Server first, then App. It runs readiness tests and uses the previously configured local development certificate.

For packaging on a non-Windows deployment host after signature/version verification by the release pipeline:

    php artisan releases:pack business-server /path/to/setup.exe --release-version=2.2.0 --channel=beta --architecture=x64

Use --release-version, since Laravel reserves --version. Do not overwrite an existing version directory: publish a new version so an outstanding installer URL and checksum continue to identify the same file. Retaining older directories also permits controlled rollback of download availability. Never downgrade a live database to an incompatible executable.

After deploying the new filesystem configuration, refresh any cached Laravel configuration. No database migration is needed for managed download storage.

## Verified delivery on 2026-09-13

Both Windows x64 2.2.0 beta installers were rebuilt and their Authenticode signatures verified on this build account. The public Server ZIP is 126,316,225 bytes and App ZIP is 121,558,223 bytes. Both archives contain exactly the installer, START-HERE.md, REQUIREMENTS.md and setup-templates.zip; embedded installer hashes match the manifests.

Server readiness: 101 passing tests. App readiness: 24 passing tests. Managed packages, version management, release manifests and public setup/download suites passed (31 tests across final runs). The App build recovered from a transient download connection reset. Both public download routes were checked on marketlens.test.

These are development-signed beta downloads, not a completed production pilot or automatic update rollout. No version policy was published in the database and no client installation was changed. A release administrator can now select either prepared package without uploading a file.
