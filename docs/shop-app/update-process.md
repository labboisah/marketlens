# Shop App Update Process

## Platform Policy

The Platform supplies update metadata for the configured `stable` or `beta`
channel: latest version, minimum supported version, mandatory flag, release
notes, download URL, and SHA-256 checksum.

The Shop App verifies the installer checksum before any update is accepted. A
mandatory update blocks continued use only when the current version is below the
Platform minimum supported version.

## Safe Update Sequence

1. Check the signed Platform update policy.
2. Download through the approved deployment channel.
3. Verify the supplied SHA-256 checksum.
4. Create a `pre_update` SQLite backup.
5. Run the installer.
6. On next launch, run SQLite integrity checks and safe migrations before the
   local API accepts Client App connections.

## Rollback

Keep the previous signed installer and the pre-update backup. If the updated
application cannot safely start, stop Client Apps, install the previous signed
version, and restore the pre-update backup through the authorized restore
workflow. Record the incident and do not manually edit SQLite files.