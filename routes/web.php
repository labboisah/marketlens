<?php

use App\Http\Controllers\Auth\AccountDisabledController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Health\LivenessController;
use App\Http\Controllers\Health\ReadinessController;
use App\Http\Controllers\OnboardingCompletedController;
use App\Http\Controllers\Platform\ApplicationVersionDownloadController;
use App\Http\Controllers\Platform\ReportExportDownloadController;
use App\Http\Controllers\PlatformContextController;
use App\Livewire\Legal\LegalAcceptancePage;
use App\Livewire\Legal\LegalDocumentPage;
use App\Livewire\Legal\LegalIndexPage;
use App\Livewire\Onboarding\BusinessOnboardingWizard;
use App\Livewire\Platform\Administration\Dashboard;
use App\Livewire\Platform\Branches\BranchManagementPage;
use App\Livewire\Platform\Branches\MultiBranchMonitoringPage;
use App\Livewire\Platform\Customers\CustomerDebtMonitoringPage;
use App\Livewire\Platform\Devices\DeviceActivationPage;
use App\Livewire\Platform\Expenses\ExpenseMonitoringPage;
use App\Livewire\Platform\Inventory\InventoryMonitoringPage;
use App\Livewire\Platform\Notifications\NotificationCentrePage;
use App\Livewire\Platform\MonitoringUsers\MonitoringUserManagementPage;
use App\Livewire\Platform\Payments\PaymentMonitoringPage;
use App\Livewire\Platform\PermissionDeniedPage;
use App\Livewire\Platform\Purchases\PurchaseMonitoringPage;
use App\Livewire\Platform\Reports\ReportCentrePage;
use App\Livewire\Platform\Settings\BusinessSettingsPage;
use App\Livewire\Platform\Services\ServicesModulePage;
use App\Livewire\Platform\Shareholders\ShareholdersModulePage;
use App\Livewire\Platform\Subscriptions\SubscriptionManagementPage;
use App\Livewire\Platform\Sales\SalesMonitoringPage;
use App\Livewire\Platform\Staff\StaffPerformanceMonitoringPage;
use App\Livewire\Platform\Support\SupportCentrePage;
use App\Livewire\Platform\Synchronization\SynchronizationMonitoringPage;
use App\Livewire\PublicSite\BusinessTypesPage;
use App\Livewire\PublicSite\FeaturesPage;
use App\Livewire\PublicSite\HowItWorksPage;
use App\Livewire\PublicSite\PricingPage;
use App\Livewire\PublicSite\ProductPage;
use App\Livewire\PublicSite\WelcomePage;
use App\Livewire\SuperAdmin\ApplicationVersionManagementPage;
use App\Livewire\SuperAdmin\BillingInvoicesPage;
use App\Livewire\SuperAdmin\BillingPlansPage;
use App\Livewire\SuperAdmin\BillingSubscriptionsPage;
use App\Livewire\SuperAdmin\SuperAdminDashboardPage;
use App\Livewire\SuperAdmin\SystemHealthPage;
use Illuminate\Support\Facades\Route;

Route::get('/health/live', LivenessController::class)->name('health.live');
Route::get('/health/ready', ReadinessController::class)->name('health.ready');

Route::get('/', WelcomePage::class)->name('welcome');
Route::get('/product', ProductPage::class)->name('public.product');
Route::get('/features', FeaturesPage::class)->name('public.features');
Route::get('/business-types', BusinessTypesPage::class)->name('public.business-types');
Route::get('/how-it-works', HowItWorksPage::class)->name('public.how-it-works');
Route::get('/pricing', PricingPage::class)->name('public.pricing');
Route::get('/legal', LegalIndexPage::class)->name('legal.index');
Route::middleware(['auth', 'verified', 'account.active'])->get('/legal/acceptance', LegalAcceptancePage::class)->name('legal.acceptance');
Route::get('/legal/{slug}', LegalDocumentPage::class)->name('legal.show');
Route::redirect('/terms', '/legal/terms-of-service')->name('legal.terms');
Route::redirect('/privacy', '/legal/privacy-policy')->name('legal.privacy');

Route::middleware('guest')->group(function (): void {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:login');
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->middleware('throttle:password-reset')->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->middleware('throttle:password-reset')->name('password.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/verify-email', EmailVerificationPromptController::class)->name('verification.notice');
    Route::get('/verify-email/{id}/{hash}', VerifyEmailController::class)->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])->middleware('throttle:6,1')->name('verification.send');
});

Route::get('/account-disabled', AccountDisabledController::class)->name('account.disabled');
Route::get('/application-versions/{version}/download', ApplicationVersionDownloadController::class)
    ->middleware('signed')
    ->name('platform.application-versions.download');

Route::middleware(['auth', 'verified', 'account.active', 'legal.accepted'])->group(function (): void {
    Route::get('/onboarding', BusinessOnboardingWizard::class)->name('onboarding.wizard');
    Route::get('/onboarding/completed', OnboardingCompletedController::class)->name('onboarding.completed');
});


Route::middleware(['auth', 'verified', 'account.active', 'legal.accepted', 'super.admin'])
    ->prefix('/admin/platform')
    ->name('super-admin.')
    ->group(function (): void {
        Route::get('/', SuperAdminDashboardPage::class)->name('dashboard');
        Route::get('/application-versions', ApplicationVersionManagementPage::class)->name('application-versions');
        Route::get('/plans', BillingPlansPage::class)->name('plans');
        Route::get('/subscription-requests', BillingSubscriptionsPage::class)->name('subscription-requests');
        Route::get('/invoices', BillingInvoicesPage::class)->name('invoices');
        Route::get('/system-health', SystemHealthPage::class)->name('system-health');
    });
Route::middleware(['auth', 'verified', 'account.active', 'legal.accepted', 'onboarding.complete', 'platform.context'])->group(function (): void {
    Route::get('/admin/dashboard', Dashboard::class)->name('platform.dashboard');
    Route::get('/sales', SalesMonitoringPage::class)->name('platform.sales');
    Route::get('/payments', PaymentMonitoringPage::class)->name('platform.payments');
    Route::get('/inventory', InventoryMonitoringPage::class)->name('platform.inventory');
    Route::get('/expenses', ExpenseMonitoringPage::class)->name('platform.expenses');
    Route::get('/customers/debts', CustomerDebtMonitoringPage::class)->name('platform.customer-debts');
    Route::get('/purchases', PurchaseMonitoringPage::class)->name('platform.purchases');
    Route::get('/staff/performance', StaffPerformanceMonitoringPage::class)->name('platform.staff-performance');
    Route::get('/branches/monitoring', MultiBranchMonitoringPage::class)->name('platform.branch-monitoring');
    Route::get('/branches', BranchManagementPage::class)->name('platform.branches');
    Route::get('/devices', DeviceActivationPage::class)->name('platform.devices');
    Route::get('/synchronization', SynchronizationMonitoringPage::class)->name('platform.synchronization');
    Route::get('/alerts', NotificationCentrePage::class)->name('platform.notifications');
    Route::get('/reports', ReportCentrePage::class)->name('platform.reports');
    Route::get('/services', ServicesModulePage::class)->middleware('module.available:services')->name('platform.services');
    Route::get('/shareholders', ShareholdersModulePage::class)->middleware('module.available:shareholders')->name('platform.shareholders');
    Route::get('/monitoring-users', MonitoringUserManagementPage::class)->name('platform.monitoring-users');
    Route::get('/subscription', SubscriptionManagementPage::class)->name('platform.subscription');
    Route::get('/settings', BusinessSettingsPage::class)->name('platform.settings');
    Route::get('/support', SupportCentrePage::class)->name('platform.support');
    Route::get('/reports/exports/{export}', ReportExportDownloadController::class)
        ->middleware('signed')
        ->name('platform.reports.download');
    Route::post('/platform/context', [PlatformContextController::class, 'update'])->name('platform.context.update');
    Route::get('/platform/permission-denied', PermissionDeniedPage::class)->name('platform.permission-denied');
});




