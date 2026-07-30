<?php

namespace App\Domain\Settings\Services;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Notifications\Models\AlertNotificationPreference;
use App\Domain\Settings\Models\BusinessDataExport;
use App\Domain\Settings\Models\BusinessTrustedDevice;
use App\Domain\Settings\Models\UserSecuritySetting;
use App\Domain\Tenancy\Models\Business;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class BusinessSettingsService
{
    /**
     * @param array<string, mixed> $data
     */
    public function updateGeneral(Business $business, User $actor, array $data): Business
    {
        $business->forceFill([
            'name' => $data['name'],
            'legal_name' => $data['legal_name'] ?? null,
            'logo_path' => $data['logo_path'] ?? $business->logo_path,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
            'state' => $data['state'] ?? null,
            'local_government_area' => $data['local_government_area'] ?? null,
            'business_type' => $data['business_type'] ?? null,
            'default_currency' => strtoupper((string) $data['default_currency']),
            'timezone' => $data['timezone'],
            'preferred_language' => $data['preferred_language'],
        ])->save();

        $this->audit($business, $actor, 'business_settings.general_updated', $business, [
            'fields' => array_keys($data),
        ]);

        return $business->refresh();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateMonitoring(Business $business, User $actor, array $data): Business
    {
        $defaultBranchId = $data['default_branch_id'] ?? null;

        if ($defaultBranchId && ! $business->branches()->whereKey($defaultBranchId)->exists()) {
            throw ValidationException::withMessages(['defaultBranchId' => 'The default branch must belong to this business.']);
        }

        $preferences = array_merge($business->monitoring_preferences ?? [], [
            'default_date_range' => $data['default_date_range'],
            'dashboard_refresh_preference' => $data['dashboard_refresh_preference'],
            'stale_data_warning_threshold' => (int) $data['stale_data_warning_threshold'],
            'summary_display_preferences' => $data['summary_display_preferences'] ?? [],
        ]);

        $business->forceFill([
            'default_branch_id' => $defaultBranchId ?: null,
            'monitoring_preferences' => $preferences,
        ])->save();

        $this->audit($business, $actor, 'business_settings.monitoring_updated', $business, [
            'default_branch_id' => $business->default_branch_id,
            'preferences' => $preferences,
        ]);

        return $business->refresh();
    }

    /**
     * @param array<string, bool> $preferences
     */
    public function updateNotificationPreferences(Business $business, User $actor, array $preferences): void
    {
        foreach ($preferences as $key => $enabled) {
            [$category, $channel] = explode('|', $key, 2);

            AlertNotificationPreference::query()->updateOrCreate(
                [
                    'business_id' => $business->id,
                    'user_id' => $actor->id,
                    'category' => $category,
                    'channel' => $channel,
                ],
                ['enabled' => $enabled]
            );
        }

        $this->audit($business, $actor, 'business_settings.notifications_updated', $business, [
            'preference_count' => count($preferences),
        ]);
    }

    public function changePassword(Business $business, User $actor, string $currentPassword, string $password): void
    {
        if (! Hash::check($currentPassword, $actor->password)) {
            throw ValidationException::withMessages(['currentPassword' => 'The current password is incorrect.']);
        }

        $actor->forceFill(['password' => $password])->save();

        $this->audit($business, $actor, 'business_settings.password_changed', $actor);
    }

    public function securitySetting(User $user): UserSecuritySetting
    {
        return UserSecuritySetting::query()->firstOrCreate(['user_id' => $user->id]);
    }

    public function trustCurrentDevice(Business $business, User $actor, Request $request): BusinessTrustedDevice
    {
        $device = BusinessTrustedDevice::query()->create([
            'business_id' => $business->id,
            'user_id' => $actor->id,
            'name' => $this->deviceName((string) $request->userAgent()),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'trusted_at' => now(),
            'last_seen_at' => now(),
        ]);

        $this->audit($business, $actor, 'business_settings.trusted_device_added', $device);

        return $device;
    }

    public function revokeTrustedDevice(Business $business, User $actor, int $deviceId): void
    {
        $device = BusinessTrustedDevice::query()
            ->where('business_id', $business->id)
            ->where('user_id', $actor->id)
            ->whereKey($deviceId)
            ->firstOrFail();

        $device->forceFill(['revoked_at' => now()])->save();

        $this->audit($business, $actor, 'business_settings.trusted_device_revoked', $device);
    }

    public function revokePlatformSession(Business $business, User $actor, string $sessionId): void
    {
        DB::table('sessions')
            ->where('user_id', $actor->id)
            ->where('id', $sessionId)
            ->delete();

        $this->audit($business, $actor, 'business_settings.platform_session_revoked', $actor, ['session_id' => $sessionId]);
    }

    public function revokeMonitoringSession(Business $business, User $actor, int $tokenId): void
    {
        PersonalAccessToken::query()
            ->where('tokenable_type', $actor->getMorphClass())
            ->where('tokenable_id', $actor->id)
            ->whereKey($tokenId)
            ->delete();

        $this->audit($business, $actor, 'business_settings.monitoring_session_revoked', $actor, ['token_id' => $tokenId]);
    }

    public function requestDataExport(Business $business, User $actor): BusinessDataExport
    {
        $payload = [
            'business' => $business->only(['uuid', 'name', 'legal_name', 'business_type', 'phone', 'email', 'address', 'state', 'local_government_area', 'default_currency', 'timezone', 'preferred_language']),
            'counts' => [
                'branches' => $business->branches()->count(),
                'owners' => $business->owners()->count(),
                'trusted_devices' => $business->trustedDevices()->count(),
                'data_exports' => $business->dataExports()->count(),
            ],
            'retention_policy_days' => (int) config('marketlens.billing.retention_days', 180),
        ];

        $export = BusinessDataExport::query()->create([
            'business_id' => $business->id,
            'requested_by' => $actor->id,
            'status' => 'completed',
            'format' => 'json',
            'filters' => ['scope' => 'business_records'],
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'requested_at' => now(),
            'expires_at' => now()->addDays(7),
        ]);

        $this->audit($business, $actor, 'business_settings.data_export_requested', $export, [
            'expires_at' => $export->expires_at?->toISOString(),
        ]);

        return $export;
    }

    public function requestAccountClosure(Business $business, User $actor, ?string $reason): Business
    {
        $business->forceFill([
            'account_closure_requested_at' => now(),
            'account_closure_requested_by' => $actor->id,
            'account_closure_reason' => $reason,
        ])->save();

        $this->audit($business, $actor, 'business_settings.account_closure_requested', $business, [
            'retention_policy_days' => (int) config('marketlens.billing.retention_days', 180),
        ]);

        return $business->refresh();
    }

    private function audit(Business $business, User $actor, string $action, object $subject, array $metadata = []): void
    {
        AuditLog::query()->create([
            'business_id' => $business->id,
            'actor_id' => $actor->id,
            'action' => $action,
            'subject_type' => $subject::class,
            'subject_id' => $subject->id ?? null,
            'metadata' => $metadata,
        ]);
    }

    private function deviceName(string $userAgent): string
    {
        if (str_contains($userAgent, 'Firefox')) {
            return 'Firefox browser';
        }

        if (str_contains($userAgent, 'Chrome')) {
            return 'Chrome browser';
        }

        if (str_contains($userAgent, 'Safari')) {
            return 'Safari browser';
        }

        return 'Platform browser';
    }
}
