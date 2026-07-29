<?php

use App\Http\Controllers\Auth\AccountDisabledController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\OnboardingCompletedController;
use App\Http\Controllers\PlatformContextController;
use App\Livewire\Onboarding\BusinessOnboardingWizard;
use App\Livewire\Platform\Administration\Dashboard;
use App\Livewire\Platform\Branches\BranchManagementPage;
use App\Livewire\Platform\Devices\DeviceActivationPage;
use App\Livewire\Platform\Inventory\InventoryMonitoringPage;
use App\Livewire\Platform\Payments\PaymentMonitoringPage;
use App\Livewire\Platform\PermissionDeniedPage;
use App\Livewire\Platform\Sales\SalesMonitoringPage;
use App\Livewire\PublicSite\BusinessTypesPage;
use App\Livewire\PublicSite\FeaturesPage;
use App\Livewire\PublicSite\HowItWorksPage;
use App\Livewire\PublicSite\PricingPage;
use App\Livewire\PublicSite\ProductPage;
use App\Livewire\PublicSite\WelcomePage;
use Illuminate\Support\Facades\Route;

Route::get('/', WelcomePage::class)->name('welcome');
Route::get('/product', ProductPage::class)->name('public.product');
Route::get('/features', FeaturesPage::class)->name('public.features');
Route::get('/business-types', BusinessTypesPage::class)->name('public.business-types');
Route::get('/how-it-works', HowItWorksPage::class)->name('public.how-it-works');
Route::get('/pricing', PricingPage::class)->name('public.pricing');

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

Route::middleware(['auth', 'verified', 'account.active'])->group(function (): void {
    Route::get('/onboarding', BusinessOnboardingWizard::class)->name('onboarding.wizard');
    Route::get('/onboarding/completed', OnboardingCompletedController::class)->name('onboarding.completed');
});

Route::middleware(['auth', 'verified', 'account.active', 'onboarding.complete', 'platform.context'])->group(function (): void {
    Route::get('/admin/dashboard', Dashboard::class)->name('platform.dashboard');
    Route::get('/sales', SalesMonitoringPage::class)->name('platform.sales');
    Route::get('/payments', PaymentMonitoringPage::class)->name('platform.payments');
    Route::get('/inventory', InventoryMonitoringPage::class)->name('platform.inventory');
    Route::get('/branches', BranchManagementPage::class)->name('platform.branches');
    Route::get('/devices', DeviceActivationPage::class)->name('platform.devices');
    Route::post('/platform/context', [PlatformContextController::class, 'update'])->name('platform.context.update');
    Route::get('/platform/permission-denied', PermissionDeniedPage::class)->name('platform.permission-denied');
});
