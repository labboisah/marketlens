# Local development signing

This self-signed identity is for controlled testing only. It is not a publicly
trusted publisher and must not be uploaded as a stable production release.

The setup installs a one-year RSA code-signing certificate in the current
Windows user's Personal store and its public certificate in that user's Trusted
Root and Trusted Publishers stores. It does not change machine-wide trust or
another computer's trust. It is labelled MarketLens DEVELOPMENT ONLY.

Private material is stored outside the repositories in
`%LOCALAPPDATA%\MarketLens\DevelopmentSigning`, accessible to the current user
and SYSTEM. The PFX has a random password protected separately with Windows
DPAPI. Its password can be decrypted only in the corresponding Windows user
context. Never share the PFX, password file or private key.

During first setup, Windows asks you to confirm installation of the development root certificate. Review the publisher name and approve that local prompt to finish trusting it. If declined, the key remains protected and setup can be rerun without generating a new identity.

To load the configuration for a development build in PowerShell (if local scripts are blocked, start a new shell with powershell -NoProfile -ExecutionPolicy RemoteSigned; this affects that shell only):

    . C:\laragon\www\marketlens\scripts\use-development-signing.ps1

This sets the three WINDOWS signing variables in that shell only, selects beta
channels for both desktop applications, and keeps signature verification enabled.
It does not persist passwords as User/Machine environment variables or alter
production configuration. Load it before building so the test publisher pin is
embedded. Close that shell after building.

Run readiness, typecheck, build and make inside the intended standalone project.
Check the resulting installer with Get-AuthenticodeSignature and retain it as a
test artifact. Do not publish these artifacts to the stable download catalog.
A different test computer needs an explicitly approved trust setup using only
the public .cer file. Never transfer the signing private key to a client.

Validation without loading build environment variables:

    powershell -NoProfile -File scripts/use-development-signing.ps1 -VerifyOnly

Re-running configure-development-signing.ps1 reuses the existing identity and
checks its signature. It refuses to overwrite incomplete credentials.

To retire this identity, close signing shells, read its thumbprint from the
private settings.json and remove only that matching certificate from CurrentUser
My, Root and TrustedPublisher through Windows certificate management (certmgr.msc).
Then securely remove its development-signing files when no longer needed.
Production releases require a separate trusted signing identity and rebuilding
with the production publisher configuration.

## Build both development installers

Run from the Platform checkout:

    powershell -NoProfile -ExecutionPolicy Bypass -File scripts/build-development-installers.ps1

The execution-policy option applies only to that process. The script loads the
protected signing identity, selects beta, runs readiness and typechecking, then
uses Electron Forge to compile/package/sign each installer. It verifies installer
version, signature status and publisher thumbprint, and prints SHA-256 checksums.
Passwords are redacted before build output is logged; logs stay in the restricted
local signing directory. Original process environment settings are restored.

Use `-Application server` or `-Application app` to build just one application.
`-ServerPath` and `-AppPath` can select relocated standalone projects. Existing
stable installer files are not the output of this development build: use the
new Setup-beta.exe files reported by the script. Signature validation on this
computer does not establish public trust on other computers or a production pilot.

The first signed Server packaging run exposed foreign-platform Argon2 prebuilds
in the Windows package. The Server copy hook now retains only the target
platform/architecture prebuild while keeping its JavaScript loader and metadata.
Two copy-fixture tests cover Windows x64 and Linux arm64 selection; typecheck
passes, and these tests are included in future readiness runs. Signing remains
enabled for the Windows native modules and executable.

## Verified development installers (2026-09-13)

Both beta installers were built with the local development identity and verified
with Windows Authenticode as Valid for this account. Packaged executable versions
and public publisher pins match. Archive inspection found no PFX/P12, DPAPI
password file or .env file in either app archive.

| Application | Version | Installer | SHA-256 |
| --- | --- | --- | --- |
| Business Server | 2.1.0 | marketlens-business-server/out/make/squirrel.windows/x64/MarketLensBusinessServerSetup-beta.exe | 11E692AED9217EDF582E4698F06D0572F7A53CC812B1FC8D20436454EB76E69B |
| Business App | 2.0.1 | marketlens-business-app/out/make/squirrel.windows/x64/MarketLensClientAppSetup-beta.exe | 0677599CD24331254549F4BE23F0CF90A7D6A4A006B9C31D09D063F10CE18B8B |

Validation: 75 Server readiness tests, two new native-copy tests, 22 App readiness
tests, both typechecks, both Forge make commands, installer/executable signatures,
versions and archive inspection. Server packaging was corrected to omit native
prebuilds for other operating systems. Build the two applications sequentially:
Electron Packager's shared temporary workspace caused an App failure during a
parallel attempt; its separate retry completed successfully.

These installers have not been executed on business installations or published
in the Platform release catalog. The existing public stable download links are
not changed by this test build. Other test computers do not automatically trust
the self-signed development identity. Production-trusted signing and installed
upgrade acceptance remain separate requirements.
