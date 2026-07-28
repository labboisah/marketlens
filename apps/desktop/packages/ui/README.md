# MarketLens Desktop UI Package

This package is the shared styling and component foundation for the Shop App and
Client App. Both desktop applications must use these design tokens and reusable
components instead of defining separate visual systems.

## Stack

- React
- Tailwind CSS
- shadcn/ui conventions
- Lucide Icons
- MarketLens semantic design tokens

## Usage Contract

- Import `src/tokens.css` once in each desktop app stylesheet.
- Extend the app Tailwind config with `marketlensTailwindPreset`.
- Compose app screens from shared primitives before adding app-specific
  components.
- Do not hard-code colors inside component files. Use semantic classes such as
  `bg-surface`, `text-foreground`, `border-border`, `text-success`, and
  `text-danger`.

## Shared Components

Foundation primitives:

- `Button`
- `Input`
- `Select`
- `Dialog`
- `DataTable`
- `StatusBadge`

Retail workflow primitives:

- `ProductSearch`
- `PosKeypad`
- `CartLine`
- `PaymentMethodSelector`
- `ShiftStatusIndicator`
- `ServerConnectionIndicator`
- `SynchronizationStatusIndicator`

These components are intentionally business-light. They provide consistent
structure, styling, density, focus behavior, and status language. Business
modules should add behavior and data ownership in the Shop App or Client App.

## Accessibility

- All interactive elements must support keyboard focus.
- Touch-heavy controls such as POS keypad and payment selection should use at
  least 44px targets.
- Status indicators include labels, not color alone.
- Tables should use readable headers, aligned numeric columns, and density
  options.
