# Platform UI Foundation

The MarketLens Platform uses Laravel, Livewire, Alpine.js, Tailwind CSS, Flux UI,
and Lucide icons. Authenticated Platform screens should use the shared
MarketLens design system in `docs/product/design-system.md`.

## Styling Contract

- Import `resources/css/marketlens.css` from the Platform app stylesheet.
- Use Tailwind semantic colors such as `bg-background`, `bg-surface`,
  `text-foreground`, `text-muted`, `border-border`, `text-success`, and
  `text-danger`.
- Do not place literal color values in Blade or Livewire components.
- Use Flux UI primitives for forms, menus, modal structure, and confirmations
  where available.
- Use Alpine.js for local interface state only, such as sidebar collapse, theme
  switching, dropdown menus, and disclosure states.
- Use Lucide icons through the project's approved Blade icon strategy once the
  Laravel app is scaffolded.

## Required Layout Pieces

- `layouts/app.blade.php`: authenticated shell with sidebar and top navigation.
- `components/app/sidebar.blade.php`: primary Platform navigation.
- `components/app/top-nav.blade.php`: tenant, branch, sync, search, and account
  controls.
- `components/app/page-header.blade.php`: title, description, breadcrumbs, and
  actions.
- `components/app/card.blade.php`: standard surface container.
- `components/app/form-field.blade.php`: label, help text, and validation shell.
- `components/app/table.blade.php`: table surface with density guidance.
- `components/app/modal.blade.php`: dialog and confirmation shell.
- `components/app/status-badge.blade.php`: semantic status labels.
- `components/app/toast.blade.php`: toast notification shell.
- `components/app/empty-state.blade.php`: empty, loading, and error state base.
- `components/app/permission-denied.blade.php`: restricted access shell.
- `components/app/sync-indicator.blade.php`: offline and synchronization status.

These files are foundation shells. They should be wired into real Laravel,
Livewire, Flux UI, and routing code when the Platform app is scaffolded.

## Platform Patterns

Dashboard cards should show clear business metrics, trend context, and freshness
state. Tables should support compact and comfortable density. Forms should keep
labels visible and validation messages close to the failing field.

Permission-denied pages, destructive confirmations, loading states, and
synchronization warnings must use the semantic status system. Monitoring-facing
Platform APIs should be styled and documented as cloud Platform concerns, not
direct Shop App connections.

## Responsive Owner Dashboard

Owner dashboard layouts should:

- Put sales, stock alerts, branch health, subscription status, and sync freshness
  in the first viewport.
- Collapse to a single-column mobile layout.
- Keep dashboard card metrics readable with tabular numerals.
- Avoid nested cards and oversized marketing-style sections.
