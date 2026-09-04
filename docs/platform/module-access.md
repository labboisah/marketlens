# Platform Module Access

MarketLens Platform modules are optional capabilities resolved through one
shared service: `App\Domain\Modules\Services\ModuleAccessService`.

## Modules

- `services`: Services and Revenue Sharing
- `shareholders`: Shareholders and Profit Distribution

## Availability Rule

A module is available only when all three gates pass:

1. The current subscription plan allows the module.
2. The business enables the module in `businesses.module_settings`.
3. The selected branch enables the module or inherits an enabled business value.

Branch status values are:

- `inherit`
- `enabled`
- `disabled`

A branch cannot enable a module when the subscription plan or business setting
does not allow it. A business-level lock can also prevent any branch override.

## Plan Features

Plan module features use keyed `plan_features` rows:

- `module:services`
- `module:shareholders`

The resolver also recognizes the display names for compatibility with older
feature rows, but new plan records should use keys.

## Enforcement Points

- Navigation uses `PlatformShell` and hides unavailable module links.
- Web routes use `module.available:{module}` middleware to block direct URLs.
- Monitoring API module endpoints validate access through `ModuleAccessService`
  and return `MODULE_UNAVAILABLE` with module metadata.
- `User::canAccessModule(...)`, `BusinessPolicy::accessModule(...)`, and Blade
  conditions `@moduleAvailable` / `@moduleUnavailable` provide reusable checks.

Existing records are preserved when a module becomes unavailable. The Platform
blocks access and configuration paths without deleting synchronized history.

## Caching

Module decisions are cached per business, optional branch, and module for ten
minutes. Business and branch module-setting writes call
`ModuleAccessService::forget(...)` to invalidate affected keys.

## Audit

Business module setting changes write `business_settings.modules_updated`.
Branch module overrides write `branch.modules_updated`.
