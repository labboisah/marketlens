# Services Extension Settings

The Services and Revenue Sharing module is configured at business level and can
be refined per branch when branch overrides are allowed.

## Business Settings

Business settings live under `businesses.module_settings.services`:

- Enable Services module
- Enable staff revenue sharing
- Default calculation basis
- Deduct direct expenses before sharing
- Staff percentage
- Business percentage
- Expense-reserve percentage
- Require service completion before allocation
- Require confirmed payment before allocation
- Require manager approval
- Allow multiple staff per service
- Allow service-specific rules
- Allow branch-specific rules
- Settlement frequency
- Business-level lock preventing branch overrides

Calculation bases:

- `gross_amount`
- `net_after_discount`
- `net_after_direct_expenses`
- `net_after_discount_and_direct_expenses`
- `fixed_amount`

Settlement frequencies:

- `daily`
- `weekly`
- `monthly`
- `manual`

Percentage components must total 100% unless the calculation basis is
`fixed_amount`.

## Shareholder Settings

Shareholder settings live under `businesses.module_settings.shareholders`:

- Enable Shareholders module
- Ownership type
- Distribution basis
- Require approved resolution
- Require closed accounting period
- Require retained-profit allocation
- Require tax provision
- Allow partial payments
- Allow shareholder loans
- Allow capital contributions
- Business-level lock preventing branch overrides

Ownership types:

- `sole_proprietorship`
- `partnership`
- `limited_company`
- `cooperative`
- `other`

Distribution bases:

- `ownership_percentage`
- `eligible_shares`
- `share_class_rights`
- `fixed_approved_allocation`

## Branch Settings

Branch settings live under `branches.module_settings.{module}` and include:

- Module inheritance status: `inherit`, `enabled`, or `disabled`
- Default branch rule
- Approval requirements
- Settlement frequency
- Branch service pricing flag

Branch screens display inherited value, override value, effective value, and
source of the effective value. Branches cannot override a locked business
module and cannot enable modules unavailable at plan or business level.
