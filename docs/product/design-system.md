# MarketLens Design System and Styling Foundation

MarketLens should feel like professional retail technology: clear, trustworthy,
calm under pressure, and easy for users with limited technical experience. The
visual language should communicate business visibility, inventory control, sales
intelligence, and operational confidence without becoming visually busy.

This document is the shared source of truth for Platform, Business Server, Business App,
and Monitoring App styling. Application components must use semantic tokens and
shared primitives instead of scattering hard-coded colors through views.

## Design Principles

- Clarity first: screens should support fast scanning and repeated daily use.
- Trust by restraint: use strong hierarchy, predictable spacing, and readable
  data presentation.
- Offline awareness: connection and synchronization state must always be visible
  where it affects user decisions.
- Operational durability: tables, forms, dashboards, and POS surfaces should
  work well in busy businesses and low-connectivity environments.
- Color supports meaning, but never carries meaning alone.

## Color System

All implementations should expose these semantic CSS variables or platform
equivalents. Component code should use semantic names such as `primary`,
`success`, `surface`, and `danger`, not literal hex values.

```css
:root {
  --ml-primary-navy: #0b1f3a;
  --ml-primary-blue: #2563eb;
  --ml-teal: #0f766e;
  --ml-emerald: #059669;
  --ml-slate-50: #f8fafc;
  --ml-slate-100: #f1f5f9;
  --ml-slate-200: #e2e8f0;
  --ml-slate-500: #64748b;
  --ml-slate-700: #334155;
  --ml-slate-900: #0f172a;

  --ml-background: #f8fafc;
  --ml-surface: #ffffff;
  --ml-surface-muted: #f1f5f9;
  --ml-border: #cbd5e1;
  --ml-text: #0f172a;
  --ml-text-muted: #475569;
  --ml-primary: #0b1f3a;
  --ml-accent: #2563eb;
  --ml-success: #059669;
  --ml-warning: #d97706;
  --ml-danger: #dc2626;
  --ml-information: #0284c7;
}

[data-theme='dark'] {
  --ml-background: #08111f;
  --ml-surface: #0f1c2e;
  --ml-surface-muted: #16263b;
  --ml-border: #334155;
  --ml-text: #f8fafc;
  --ml-text-muted: #cbd5e1;
  --ml-primary: #93c5fd;
  --ml-accent: #38bdf8;
  --ml-success: #34d399;
  --ml-warning: #fbbf24;
  --ml-danger: #f87171;
  --ml-information: #7dd3fc;
}
```

Recommended semantic usage:

- Primary navy: app chrome, high-emphasis navigation, key headings.
- Primary blue: primary actions, active states, links, selected tabs.
- Teal: synchronization, device connection, operational freshness.
- Emerald: success, completed payments, healthy stock movement.
- Neutral slate: text, borders, dividers, low-emphasis controls.
- Background: page canvas.
- Surface: panels, cards, tables, modals, menus.
- Border: field borders, table dividers, card outlines.
- Warning: low stock, delayed sync, pending review.
- Danger: destructive actions, failed payment, permission failure.
- Information: neutral system updates and setup guidance.

## Typography

Use system fonts by default for fast rendering and broad language support:

```text
font-sans: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont,
  "Segoe UI", sans-serif
font-mono: "JetBrains Mono", "SFMono-Regular", Consolas, monospace
```

Scale:

- `display`: 40px, line-height 48px, weight 700.
- `h1`: 32px, line-height 40px, weight 700.
- `h2`: 24px, line-height 32px, weight 700.
- `h3`: 20px, line-height 28px, weight 650.
- `body`: 16px, line-height 24px, weight 400.
- `body-sm`: 14px, line-height 20px, weight 400.
- `caption`: 12px, line-height 16px, weight 500.
- `numeric`: tabular numerals for prices, stock counts, and dashboard metrics.

Avoid viewport-scaled font sizes. Keep letter spacing at `0` unless a native
platform typography system requires otherwise.

## Spacing

Use a 4px spacing base:

- `0`: 0
- `1`: 4px
- `2`: 8px
- `3`: 12px
- `4`: 16px
- `5`: 20px
- `6`: 24px
- `8`: 32px
- `10`: 40px
- `12`: 48px
- `16`: 64px

Dense business screens may use 8px and 12px internal spacing, but page sections
should preserve enough space for readable scanning.

## Shape, Elevation, and Icons

Border radius:

- `sm`: 4px for badges and compact controls.
- `md`: 6px for buttons, inputs, menus, and table row affordances.
- `lg`: 8px for cards, dialogs, and dashboard panels.
- Avoid larger radii unless a native platform component requires them.

Shadows:

- `sm`: subtle menu and tooltip elevation.
- `md`: dialogs, popovers, and raised action surfaces.
- `none`: default for tables, page sections, and operational dashboards.

Icon sizing:

- `xs`: 14px for dense table affordances.
- `sm`: 16px for badges and compact buttons.
- `md`: 20px for standard buttons and navigation.
- `lg`: 24px for empty states and dashboard cards.

Use Lucide icons in Platform and desktop UI where appropriate. Icons must have
accessible labels when they are interactive.

## Controls

Form-field heights:

- Compact: 32px for dense filters and table controls.
- Default: 40px for standard forms.
- Large: 48px for POS, login, and touch-friendly workflows.

Focus states:

- Use a visible 2px focus ring.
- Keep keyboard focus visible on buttons, links, inputs, menus, tabs, table row
  actions, dialogs, and POS controls.

Table density:

- Compact: 32px rows for accounting-style scanning.
- Comfortable: 44px rows for operational tables.
- Spacious: 56px rows for touch-heavy views and mobile layouts.

Status badges:

- Use semantic tokens for `success`, `warning`, `danger`, `information`,
  `neutral`, `offline`, and `syncing`.
- Include text labels and optional icons. Do not rely on color alone.

## Reusable Patterns

Dashboard cards:

- Use a small label, a primary metric, a change indicator, and optional status.
- Use tabular numerals for money and counts.
- Avoid nested cards.

Empty states:

- Provide a short title, plain explanation, and one clear next action when
  appropriate.
- Use neutral illustration or Lucide icon sizing, not decorative color fields.

Loading states:

- Use skeleton rows for tables and dashboard cards.
- Use spinners only for short blocking actions.
- Preserve layout dimensions to avoid shifting.

Error states:

- State what failed and what the user can do next.
- Include retry controls when the action is recoverable.
- Log technical detail outside the user-facing message.

Confirmation states:

- Use confirmation dialogs for destructive or irreversible actions.
- Show the object name, consequence, and primary/secondary actions.
- Destructive buttons use `danger`.

Permission-denied screens:

- Explain that access is restricted.
- Show the required role or permission when safe.
- Offer a route back to an allowed area.

Offline and synchronization indicators:

- Always distinguish offline, connected, syncing, delayed, failed, and synced.
- Include last successful sync time where available.
- Business Server and Business App should show local server connection state in app
  chrome.
- Monitoring App should show cached-data and last-sync status.

## Platform Styling

Platform uses Laravel, Livewire, Alpine.js, Tailwind CSS, Flux UI, and Lucide
icons.

Configure:

- Tailwind theme colors from MarketLens semantic CSS variables.
- Flux UI components to inherit the semantic color system.
- Alpine.js only for small local interactions such as menus, theme toggles, and
  disclosure controls.
- Reusable Livewire layouts for sidebar, top navigation, page headers, cards,
  forms, tables, modals, confirmations, and toast notifications.
- Responsive owner dashboards that prioritize business health, sales movement,
  stock alerts, branch status, subscriptions, and synchronization freshness.

Platform screens should feel cloud-operational and data-dense. Avoid marketing
hero layouts inside authenticated product workflows.

## Desktop Styling

Business Server and Business App use Electron, React, Tailwind CSS, shadcn/ui, and
Lucide Icons. The standalone apps live at `C:\laragon\www\business-server` and
`C:\laragon\www\business-app`. Each app carries its own copied `packages/ui`
package and must keep those tokens aligned with the Platform design system.

The desktop UI package owns:

- Tailwind preset and semantic design tokens.
- Buttons, inputs, selects, dialogs, data tables, and status badges.
- Product search.
- POS keypad.
- Cart line.
- Payment-method selector.
- Shift-status indicator.
- Server-connection indicator.
- Synchronization-status indicator.

Business-specific and client-specific components may compose these primitives, but
must not fork token definitions or reintroduce a duplicate `apps/desktop`
workspace in the main repository.

## Monitoring App Styling

Monitoring App uses Flutter Material 3 with the same semantic color system.

The app should define:

- Material 3 light and dark themes.
- Dashboard cards and sales summary cards.
- Stock alerts.
- Branch selectors.
- Charts.
- Offline cache indicators.
- Last-synchronization status.
- Security alerts.
- Notification tiles.

Monitoring should feel owner-focused: quick to scan, secure, and calm. It must
never imply a direct connection to a Business Server.

## Accessibility

- Meet strong contrast requirements for text, controls, charts, badges, and
  status indicators.
- Support keyboard navigation in Platform, Business Server, and Business App.
- Provide visible focus states.
- Use large touch targets for POS and mobile workflows. Prefer at least 44px
  target size where practical.
- Keep tables readable with clear headers, alignment, row spacing, and tabular
  numbers.
- Do not represent information by color alone.
- Use plain English first. Leave room for future Hausa localization by avoiding
  text baked into images and by keeping labels concise.

## Implementation Rule

Business pages should not invent new visual systems. New UI starts with shared
tokens, then composes approved layout shells and primitives. If a screen needs a
new pattern, add it to this design system first.
