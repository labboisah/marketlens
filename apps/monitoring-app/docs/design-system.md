# Monitoring App Design System

The MarketLens Monitoring App is an Android-first Flutter app for business
owners and authorized remote users. It reads synchronized Platform data only and
must never imply direct access to a Shop App.

Use the shared MarketLens design system in
`docs/product/design-system.md`. Flutter should map the same semantic color
system into Material 3 themes.

## Material 3 Theme

Use `lib/theme/marketlens_theme.dart` as the theme foundation.

```dart
MaterialApp(
  theme: MarketLensTheme.light(),
  darkTheme: MarketLensTheme.dark(),
  themeMode: ThemeMode.system,
)
```

Semantic mapping:

- `primary`: primary navy or dark-theme accessible equivalent.
- `secondary`: primary blue.
- `tertiary`: teal.
- `surface`: app cards, sheets, dialogs, and navigation.
- `error`: danger.
- success, warning, and information are exposed as `ThemeExtension` values.

## Dashboard Cards

Dashboard cards should show:

- A concise label.
- One primary metric.
- A time range or freshness label.
- Optional trend or status text.

Use tabular numerals for sales totals, stock counts, and branch comparisons.
Avoid nesting cards.

## Sales Summary Cards

Sales summary cards should support daily, weekly, monthly, and branch-filtered
views. They should include gross sales, paid sales, outstanding credit, refunds,
and last synchronization time where relevant.

## Stock Alerts

Stock alerts should use warning or danger semantics, include product name,
branch, current quantity, threshold, and the last synchronized time. Do not rely
on color alone; include labels such as `Low stock`, `Out of stock`, or
`Sync delayed`.

## Branch Selectors

Branch selectors should be easy to reach on dashboards and reports. Use clear
branch names, optional location text, and status indicators for stale or offline
data. Multi-branch owners should be able to scan branch health quickly.

## Charts

Charts must:

- Use semantic colors consistently.
- Include legends, axis labels, and accessible summaries.
- Avoid representing categories by color alone.
- Prefer simple bar, line, and trend charts before complex visualizations.

## Offline Cache Indicators

When cached data is shown, display an indicator near the page header or affected
card. Include last successful refresh time and whether the user can retry.

States:

- `Online`: fresh Platform data.
- `Cached`: data is available locally but may be stale.
- `Refreshing`: app is fetching Platform data.
- `Unavailable`: no usable cached data exists.

## Last-Synchronization Status

Last-sync status should appear on dashboards, branch detail screens, and reports.
Use plain labels such as `Synced 2 minutes ago`, `Sync delayed`, or
`Refresh failed`.

## Security Alerts

Security alerts include suspicious login, device changes, permission changes,
subscription risk, or failed authentication attempts. Use danger for urgent
items and warning for review-needed items. Include the affected branch or user
when safe.

## Notification Tiles

Notification tiles should include:

- Category label.
- Title.
- Short body.
- Timestamp.
- Read/unread state.
- Optional action.

Keep notification text short and localization-ready.

## Accessibility

- Use strong contrast in light and dark themes.
- Support large Android font sizes.
- Keep touch targets at least 48dp where practical.
- Do not communicate status by color alone.
- Keep English strings clear and concise for future Hausa localization.
