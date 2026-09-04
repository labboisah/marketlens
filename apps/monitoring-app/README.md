# MarketLens Monitoring App

The MarketLens Monitoring App is an Android-first Flutter app for owners and
authorized staff who need remote visibility into synchronized Platform data. It
never connects directly to Business Servers.

## Purpose

- Show business, branch, sales, payment, expense, stock, debt, performance, and
  security summaries.
- Make data freshness obvious with last-synchronization status on dashboards and
  report screens.
- Support offline viewing of cached, read-only reports.

## Stack

- Flutter and Dart
- Material 3
- Riverpod
- GoRouter
- Dio
- Freezed and json_serializable
- Drift
- flutter_secure_storage
- Firebase Cloud Messaging
- Firebase Crashlytics
- fl_chart
- local_auth
- connectivity_plus
- Flutter Test

## Setup

Install Flutter and Android Studio, then run:

```bash
flutter pub get
flutter test
```

Use `.env.example` as environment guidance. Do not commit real Firebase files,
production tokens, or customer data.

## Firebase Setup

Create Firebase Android configuration for the MarketLens project and keep the
generated credentials out of source control unless the team explicitly approves
environment-specific checked-in files. Crashlytics and FCM are scaffolded behind
`core/firebase/firebase_bootstrap.dart`.

## API Configuration

The app connects only to the MarketLens Platform API. Production API integration
is not implemented yet; current screens use mock repositories.

## Authentication

Authentication screens are placeholders. Tokens must be saved through
`flutter_secure_storage` via the secure token store.

## Local Caching

Drift is included for read-only offline report caching. Cached records are not
authoritative and must not be edited locally.

## Push Notifications

Push notifications should identify category and urgency without exposing
sensitive financial information on locked screens by default.

## Biometric Login

Sensitive screens may require biometric re-authentication through `local_auth`.
The scaffold includes a biometric gate service for future feature wiring.

## Testing

Run:

```bash
flutter test
```

## Android Build and Release

Use the standard Flutter Android build flow:

```bash
flutter build apk --release
flutter build appbundle --release
```

Before release, configure signing, Firebase, Crashlytics upload, Play Store
metadata, environment values, and Platform API endpoints.
