# Client App Update Process

This guide documents packaging channels, update policy resolution, download, verification, and restart flow.

## Update Architecture

- Source of truth for update policy is Business Server endpoint:
  - GET /api/v1/system/update-policy
- Business Server resolves policy from synchronized Platform update manifest.
- Client App treats socket events as triggers and APIs as authority.

## Channels

Supported channels:
- stable
- beta

Packaging:
- Forge Squirrel maker emits channel-specific setup executable names.
- Build scripts set MARKETLENS_UPDATE_CHANNEL for artifact channel targeting.

## Runtime Update Lifecycle

1. Client App calls preload updates.check().
2. Main process calls Business Server update-policy with active session token.
3. Policy fields include latestVersion, minimumSupportedVersion, mandatory, releaseNotes, checksum, and downloadUrl.
4. Mandatory is true if policy says mandatory or currentVersion is below minimumSupportedVersion.
5. Renderer can call updates.download().
6. Main process downloads installer to userData/updates and tracks progress.
7. Main process verifies SHA-256 checksum before marking update ready.
8. Renderer can call updates.restartToUpdate().
9. Main process launches installer and exits app.

## Mandatory Update Behavior

- Mandatory update state can lock normal workspace usage through application-state resolution.
- Non-mandatory availability does not force lock; users can schedule update.

## Validation And Integrity

Current controls:
- SHA-256 checksum verification against Business Server policy.
- Invalid checksum causes download failure and file removal.

Current limitation:
- Publisher signature trust-chain verification is not yet implemented in app logic.

## Operational Recommendations

- Publish checksums with each channel artifact.
- Keep stable and beta release notes explicit.
- Test download and restart flow on representative Windows images before rollout.
- Keep rollback installer for last stable release.
