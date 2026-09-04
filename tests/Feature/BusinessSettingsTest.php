<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Notifications\Models\AlertNotificationPreference;
use App\Domain\Settings\Models\BusinessDataExport;
use App\Domain\Settings\Models\BusinessTrustedDevice;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Enums\AccountStatus;
use App\Enums\BranchStatus;
use App\Enums\BusinessStatus;
use App\Enums\SubscriptionStatus;
use App\Livewire\Platform\Settings\BusinessSettingsPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function businessSettingsFixture(): array
{
    $owner = User::factory()->create([
        'account_status' => AccountStatus::Active,
        'email_verified_at' => now(),
        'password' => 'password',
    ]);

    $plan = Plan::query()->create([
        'name' => 'Settings Plan',
        'slug' => 'settings-plan-'.Str::random(6),
        'billing_interval' => 'monthly',
        'price_minor' => 15000,
        'currency' => 'NGN',
        'trial_days' => 14,
        'sort_order' => 1,
        'is_active' => true,
    ]);
    $plan->features()->create(['name' => 'Reports', 'sort_order' => 1]);

    $business = Business::query()->create([
        'name' => 'Settings Stores',
        'status' => BusinessStatus::Active,
        'onboarding_status' => 'completed',
        'email' => 'settings@example.test',
        'phone' => '08000000000',
    ]);
    $branch = Branch::query()->create([
        'business_id' => $business->id,
        'name' => 'Main Branch',
        'code' => 'MAIN',
        'status' => BranchStatus::Active,
        'is_head_office' => true,
    ]);
    $owner->ownedBusinesses()->attach($business->id, ['role' => 'owner']);

    Subscription::query()->create([
        'business_id' => $business->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active,
        'starts_at' => now(),
        'ends_at' => now()->addMonth(),
    ]);

    return [$owner, $business, $branch];
}

it('renders business settings pages and account navigation', function (): void {
    [$owner, $business, $branch] = businessSettingsFixture();

    $this->actingAs($owner)
        ->withSession(['platform.business_id' => $business->id, 'platform.branch_id' => $branch->id])
        ->get(route('platform.settings'))
        ->assertOk()
        ->assertSee('Business settings')
        ->assertSee('General settings')
        ->assertSee('Monitoring settings')
        ->assertSee('Notification settings')
        ->assertSee('Security settings')
        ->assertSee('Active Platform sessions')
        ->assertSee('Monitoring App sessions')
        ->assertSee('Data settings')
        ->assertSee('Audit history');

    $this->actingAs($owner)
        ->withSession(['platform.business_id' => $business->id, 'platform.branch_id' => $branch->id])
        ->get(route('platform.dashboard'))
        ->assertOk()
        ->assertSee(route('platform.settings'));
});

it('updates general and monitoring settings with audit logging', function (): void {
    [$owner, $business, $branch] = businessSettingsFixture();

    Livewire::actingAs($owner)
        ->test(BusinessSettingsPage::class)
        ->set('name', 'Updated Settings Stores')
        ->set('legalName', 'Updated Settings Stores Ltd')
        ->set('phone', '08111111111')
        ->set('email', 'updated@example.test')
        ->set('address', '15 Market Street')
        ->set('state', 'Lagos')
        ->set('localGovernmentArea', 'Ikeja')
        ->set('businessType', 'Retail')
        ->set('defaultCurrency', 'ngn')
        ->set('timezone', 'Africa/Lagos')
        ->set('preferredLanguage', 'en')
        ->call('saveGeneral')
        ->assertDispatched('notify')
        ->set('defaultBranchId', $branch->id)
        ->set('defaultDateRange', '7_days')
        ->set('dashboardRefreshPreference', '30s')
        ->set('staleDataWarningThreshold', 45)
        ->set('summaryDisplayPreferences.sales', true)
        ->set('summaryDisplayPreferences.inventory', false)
        ->call('saveMonitoring')
        ->assertDispatched('notify');

    $business->refresh();

    expect($business->name)->toBe('Updated Settings Stores')
        ->and($business->legal_name)->toBe('Updated Settings Stores Ltd')
        ->and($business->default_currency)->toBe('NGN')
        ->and($business->default_branch_id)->toBe($branch->id)
        ->and($business->monitoring_preferences['default_date_range'])->toBe('7_days')
        ->and($business->monitoring_preferences['dashboard_refresh_preference'])->toBe('30s')
        ->and($business->monitoring_preferences['stale_data_warning_threshold'])->toBe(45)
        ->and($business->monitoring_preferences['summary_display_preferences']['inventory'])->toBeFalse()
        ->and(AuditLog::query()->where('business_id', $business->id)->where('action', 'business_settings.general_updated')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('business_id', $business->id)->where('action', 'business_settings.monitoring_updated')->exists())->toBeTrue();
});

it('rejects a default branch from another business', function (): void {
    [$owner] = businessSettingsFixture();
    $otherBusiness = Business::query()->create(['name' => 'Other Store', 'status' => BusinessStatus::Active, 'onboarding_status' => 'completed']);
    $otherBranch = Branch::query()->create(['business_id' => $otherBusiness->id, 'name' => 'Other Branch', 'code' => 'OTHER', 'status' => BranchStatus::Active]);

    Livewire::actingAs($owner)
        ->test(BusinessSettingsPage::class)
        ->set('defaultBranchId', $otherBranch->id)
        ->set('defaultDateRange', '30_days')
        ->set('dashboardRefreshPreference', '60s')
        ->set('staleDataWarningThreshold', 60)
        ->call('saveMonitoring')
        ->assertHasErrors(['defaultBranchId']);
});

it('saves notification channel preferences including placeholders', function (): void {
    [$owner, $business] = businessSettingsFixture();

    Livewire::actingAs($owner)
        ->test(BusinessSettingsPage::class)
        ->set('notificationPreferences.low_stock|email', true)
        ->set('notificationPreferences.low_stock|push', false)
        ->set('notificationPreferences.low_stock|sms', true)
        ->set('notificationPreferences.security|whatsapp', true)
        ->call('saveNotifications')
        ->assertDispatched('notify');

    expect(AlertNotificationPreference::query()
        ->where('business_id', $business->id)
        ->where('user_id', $owner->id)
        ->where('category', 'low_stock')
        ->where('channel', 'sms')
        ->where('enabled', true)
        ->exists())->toBeTrue()
        ->and(AlertNotificationPreference::query()
            ->where('business_id', $business->id)
            ->where('user_id', $owner->id)
            ->where('category', 'low_stock')
            ->where('channel', 'push')
            ->where('enabled', false)
            ->exists())->toBeTrue()
        ->and(AuditLog::query()->where('business_id', $business->id)->where('action', 'business_settings.notifications_updated')->exists())->toBeTrue();
});

it('changes passwords and revokes platform monitoring and trusted-device sessions', function (): void {
    [$owner, $business] = businessSettingsFixture();
    DB::table('sessions')->insert([
        'id' => 'session-to-revoke',
        'user_id' => $owner->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Chrome Test',
        'payload' => 'payload',
        'last_activity' => now()->timestamp,
    ]);
    $token = $owner->createToken('Monitoring App')->accessToken;
    $trusted = BusinessTrustedDevice::query()->create([
        'business_id' => $business->id,
        'user_id' => $owner->id,
        'name' => 'Chrome browser',
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Chrome Test',
        'trusted_at' => now(),
        'last_seen_at' => now(),
    ]);

    Livewire::actingAs($owner)
        ->test(BusinessSettingsPage::class)
        ->set('currentPassword', 'password')
        ->set('password', 'new-secure-password')
        ->set('passwordConfirmation', 'new-secure-password')
        ->call('changePassword')
        ->assertDispatched('notify')
        ->call('revokePlatformSession', 'session-to-revoke')
        ->assertDispatched('notify')
        ->call('revokeMonitoringSession', $token->id)
        ->assertDispatched('notify')
        ->call('revokeTrustedDevice', $trusted->id)
        ->assertDispatched('notify');

    expect(Hash::check('new-secure-password', $owner->refresh()->password))->toBeTrue()
        ->and(DB::table('sessions')->where('id', 'session-to-revoke')->exists())->toBeFalse()
        ->and(PersonalAccessToken::query()->whereKey($token->id)->exists())->toBeFalse()
        ->and($trusted->refresh()->revoked_at)->not->toBeNull()
        ->and(AuditLog::query()->where('business_id', $business->id)->where('action', 'business_settings.password_changed')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('business_id', $business->id)->where('action', 'business_settings.platform_session_revoked')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('business_id', $business->id)->where('action', 'business_settings.monitoring_session_revoked')->exists())->toBeTrue();
});

it('exports business records and records account closure without deleting synchronized data', function (): void {
    [$owner, $business, $branch] = businessSettingsFixture();
    config(['marketlens.billing.retention_days' => 365]);

    Livewire::actingAs($owner)
        ->test(BusinessSettingsPage::class)
        ->call('requestDataExport')
        ->assertDispatched('notify')
        ->set('closureReason', 'Business sold')
        ->call('requestAccountClosure')
        ->assertDispatched('notify');

    $export = BusinessDataExport::query()->where('business_id', $business->id)->firstOrFail();

    expect($export->status)->toBe('completed')
        ->and($export->expires_at)->not->toBeNull()
        ->and(json_decode($export->payload, true, 512, JSON_THROW_ON_ERROR)['retention_policy_days'])->toBe(365)
        ->and($business->refresh()->account_closure_requested_at)->not->toBeNull()
        ->and($business->account_closure_requested_by)->toBe($owner->id)
        ->and(Business::query()->whereKey($business->id)->exists())->toBeTrue()
        ->and(Branch::query()->whereKey($branch->id)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('business_id', $business->id)->where('action', 'business_settings.data_export_requested')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('business_id', $business->id)->where('action', 'business_settings.account_closure_requested')->exists())->toBeTrue();
});

