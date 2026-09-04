# Client App Installation (Windows)

This guide explains how to install MarketLens Client App on a Windows workstation.

## Prerequisites

- Windows 10 or Windows 11.
- Business Server reachable on local network.
- A paired and authorized workstation/device in Business Server.
- Installer package from approved release channel (stable or beta).

## Installer Type

Client App is packaged with Electron Forge using Squirrel.Windows.

Expected behavior:
- Setup executable installs per user.
- Start Menu shortcut is created.
- Desktop shortcut is created.
- App appears as MarketLens Client App.

## Install Steps

1. Close any running MarketLens Client App window.
2. Run the installer executable:
   - Stable example: MarketLensClientAppSetup-stable.exe
   - Beta example: MarketLensClientAppSetup-beta.exe
3. Wait for installation to finish.
4. Launch MarketLens Client App from Start Menu or Desktop shortcut.

## First Launch Checks

After launch:
- Confirm app opens with context isolation and sandbox enabled.
- Confirm connection screen appears if no saved session exists.
- Confirm current app version is visible in the top application banner.

## Uninstall

1. Open Windows Settings > Apps.
2. Find MarketLens Client App.
3. Select Uninstall.

## Notes for Operations

- Stable and beta installers are built as separate channel artifacts.
- Do not install unsigned or untrusted binaries.
- Keep installer artifacts in release archives for rollback scenarios.
