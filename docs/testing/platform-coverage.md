# Platform Testing Coverage

This document maps the current Platform test suite to the required production
coverage groups. The Platform suite is primarily Pest feature coverage under
`tests/Feature`.

## Covered Groups

- Public pages: `ExampleTest`, `PublicPricingPageTest`,
  `LegalPagesTest`, `PlatformCoverageCompletionTest`
- Authentication: `BusinessOwnerAuthenticationTest`,
  `PlatformCoverageCompletionTest`
- Onboarding: `BusinessOnboardingWizardTest`, `BranchManagementTest`
- Tenancy: `AuthenticatedPlatformShellTest`, all monitoring feature tests,
  `ReportCentreTest`, `BusinessSettingsTest`
- Super-admin separation: `SuperAdminDashboardTest`,
  `ApplicationVersionManagementTest`, `PlatformObservabilityTest`,
  `PlatformCoverageCompletionTest`
- Devices: `ShopAppDeviceActivationTest`,
  `ApplicationVersionManagementTest`, `SubscriptionManagementTest`
- Synchronization: `SynchronizationIntakeApiTest`,
  `SynchronizationMonitoringTest`
- Monitoring: `BusinessOverviewDashboardTest`, `SalesMonitoringTest`,
  `PaymentMonitoringTest`, `InventoryMonitoringTest`,
  `ExpenseMonitoringTest`, `CustomerDebtMonitoringTest`,
  `PurchaseMonitoringTest`, `StaffPerformanceMonitoringTest`,
  `MultiBranchMonitoringTest`, `AlertNotificationCentreTest`
- Subscription: `SubscriptionManagementTest`, `BranchManagementTest`,
  `ShopAppDeviceActivationTest`
- Security: `BusinessOwnerAuthenticationTest`, `MonitoringUserManagementTest`,
  `ReportCentreTest`, `SupportCentreTest`, `PlatformObservabilityTest`,
  `PlatformCoverageCompletionTest`

## Specific Requirements

- Registration, verification, login, password reset, suspension, and auth rate
  limiting are covered by `BusinessOwnerAuthenticationTest` and
  `PlatformCoverageCompletionTest`.
- Business creation, branch creation, plan selection, trial creation, and
  duplicate prevention are covered by `BusinessOnboardingWizardTest` and
  `BranchManagementTest`.
- Business isolation, branch isolation, monitoring-user restrictions, and
  super-admin separation are covered by `AuthenticatedPlatformShellTest`,
  `MonitoringUserManagementTest`, `SuperAdminDashboardTest`, and the monitoring
  module tests.
- Device activation, expiration, revocation, limits, heartbeat, and application
  update policy are covered by `ShopAppDeviceActivationTest` and
  `ApplicationVersionManagementTest`.
- Synchronization authentication, idempotency, partial success, conflict
  handling, queue processing, unsupported entities, and safe staff profile
  replication are covered by `SynchronizationIntakeApiTest` and
  `SynchronizationMonitoringTest`.
- Dashboard, sales, payments, inventory, expenses, customer debt, supplier
  accounts, cashiers, branches, sync status, and alerts are covered by the
  monitoring feature tests listed above.
- Plan limits, expiration, grace period, upgrade, downgrade, and billing
  restrictions are covered by `SubscriptionManagementTest`.
- Policies, sensitive fields, audit logs, API rate limits, file access, export
  authorization, observability endpoints, and system-health restrictions are
  covered by `ReportCentreTest`, `SupportCentreTest`,
  `PlatformObservabilityTest`, and `PlatformCoverageCompletionTest`.

## Coverage Gaps

- Browser tests are not configured in this Platform application. There is no
  Dusk or Playwright configuration/package in this app at the time of review.
- Frontend validation is limited to the Vite production build unless a browser
  runner is added.
