<?php

namespace App\Domain\Devices\Services;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Billing\Models\PlanFeature;
use App\Domain\Billing\Services\SubscriptionManagementService;
use App\Domain\Devices\Models\Device;
use App\Domain\Devices\Models\DeviceActivation;
use App\Domain\Devices\Models\DeviceHeartbeatLog;
use App\Domain\Licensing\Models\BusinessLicenseAssociation;
use App\Domain\Licensing\Services\BusinessLicenseEntitlementService;
use App\Domain\Licensing\Services\KernelBridgeLicenseSyncService;
use App\Domain\Modules\Services\ModuleAccessService;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Domain\Versions\Models\ApplicationVersion;
use App\Enums\ApplicationVersionStatus;
use App\Enums\BranchStatus;
use App\Enums\BusinessModule;
use App\Enums\BusinessStatus;
use App\Enums\DeviceStatus;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ShopAppActivationService
{
    public function __construct(
        private readonly SubscriptionManagementService $subscriptions,
        private readonly ModuleAccessService $moduleAccess,
        private readonly BusinessLicenseEntitlementService $licenseEntitlements,
        private readonly KernelBridgeLicenseSyncService $kernelBridge,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{activation: DeviceActivation, code: string}
     */
    public function createActivation(Business $business, Branch $branch, User $actor, array $data): array
    {
        $deviceType = (string) $data['device_type'];
        $requiresKernelBridgeGrant = $this->requiresKernelBridgeGrant($deviceType);
        $usesKernelBridgeLicense = $requiresKernelBridgeGrant
            || ($deviceType === 'client_app' && $this->licenseEntitlements->license($business) !== null);
        $this->assertBusinessCanActivate($business, ! $usesKernelBridgeLicense);
        $this->assertBranchCanActivate($branch);
        if ($requiresKernelBridgeGrant) {
            $this->assertServerInstallationCapacity($business);
        } else {
            $this->assertDeviceLimit($business, (string) $data['device_type']);
        }

        $version = $this->resolveAllowedVersion($data['application_version_id'] ?? null);
        $code = $this->generateCode();

        return DB::transaction(function () use ($business, $branch, $actor, $data, $version, $code): array {
            $device = Device::query()->create([
                'business_id' => $business->id,
                'branch_id' => $branch->id,
                'application_version_id' => $version?->id,
                'name' => $data['device_label'],
                'type' => $data['device_type'],
                'status' => DeviceStatus::Pending,
            ]);

            $activation = DeviceActivation::query()->create([
                'device_id' => $device->id,
                'application_version_id' => $version?->id,
                'activation_code_hash' => $this->hashSecret($code),
                'expires_at' => $data['expires_at'],
            ]);

            $this->audit($actor, $business->id, $branch->id, 'device.activation_created', $device, [
                'expires_at' => $activation->expires_at?->toISOString(),
                'device_type' => $device->type,
            ]);

            return ['activation' => $activation, 'code' => $code];
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function activate(array $payload): array
    {
        $activation = DeviceActivation::query()
            ->with(['device'])
            ->where('activation_code_hash', $this->hashSecret((string) $payload['activation_code']))
            ->first();

        if (! $activation) {
            $this->auditFailure('device.activation_failed', ['reason' => 'invalid_code']);
            throw ValidationException::withMessages(['activation_code' => 'The activation code is invalid or expired.']);
        }

        $device = $activation->device;
        $expectedDeviceType = $payload['expected_device_type'] ?? null;
        if ($expectedDeviceType !== null && $device->type !== $expectedDeviceType) {
            throw ValidationException::withMessages([
                'activation_code' => "This activation code is for a {$device->type} device, not a {$expectedDeviceType} device. Generate a code for the Business Server.",
            ]);
        }

        $requiresKernelBridgeGrant = $this->requiresKernelBridgeGrant($device->type);
        if ($expectedDeviceType === 'shop_app' && ! $requiresKernelBridgeGrant) {
            throw ValidationException::withMessages([
                'activation_code' => 'KernelBridge grant enforcement is disabled on the Platform. Enable it before activating a Business Server.',
            ]);
        }

        if ($activation->used_at || $activation->activated_at) {
            return $this->retryActivation($activation, $device, $payload);
        }

        $branch = Branch::query()->findOrFail($device->branch_id);
        $business = Business::query()->with('currentSubscription')->findOrFail($device->business_id);
        $installationUuid = trim((string) ($payload['installation_uuid'] ?? ''));

        try {
            $this->assertActivationUsable($activation);
            $usesKernelBridgeLicense = $requiresKernelBridgeGrant
                || ($device->type === 'client_app' && $this->licenseEntitlements->license($business) !== null);
            $this->assertBusinessCanActivate($business, ! $usesKernelBridgeLicense);
            $this->assertBranchCanActivate($branch);
            if ($requiresKernelBridgeGrant) {
                if (! Str::isUuid($installationUuid)) {
                    throw ValidationException::withMessages([
                        'installation_uuid' => 'A stable Business Server installation UUID is required.',
                    ]);
                }
                $this->assertServerInstallationCapacity($business, $device);
            } else {
                $this->assertDeviceLimit($business, $device->type, $device);
            }
            $this->assertApplicationVersionAllowed($activation, (string) $payload['app_version']);
        } catch (ValidationException $exception) {
            $activation->increment('failed_attempts');
            $this->auditFailure('device.activation_failed', [
                'reason' => 'policy_rejected',
                'device_id' => $device->id,
                'business_id' => $business->id,
                'branch_id' => $branch->id,
            ]);
            throw $exception;
        }

        $license = $requiresKernelBridgeGrant ? $this->activeKernelBridgeLicense($business) : null;
        $kernelBridgeCode = $license
            ? $this->kernelBridge->issueInstallationActivationCode($business, $license, $installationUuid, $device->name)
            : null;
        $credential = 'ml_device_'.Str::random(64);
        $response = [
            'device_uuid' => $device->uuid,
            'device_type' => $device->type,
            'credential' => $credential,
            'credential_preview' => substr($credential, 0, 14).'...',
            'expires_at' => null,
            'business_uuid' => $business->uuid,
            'branch_uuid' => $branch->uuid,
            'business_name' => $business->name,
            'branch_name' => $branch->name,
            'subscription_state' => $license?->status ?? $business->currentSubscription?->status->value ?? 'unknown',
            'plan_features' => $this->planFeatures($business),
            'enabled_modules' => $this->enabledModules($business, $branch),
            'branch_settings' => [
                'business_modules' => $business->module_settings ?? [],
                'branch_modules' => $branch->module_settings ?? [],
                'operating_modes' => $branch->operating_modes ?? ['sales'],
                'branch_code' => $branch->code,
                'timezone' => $business->timezone,
                'currency' => $business->default_currency,
            ],
            ...($kernelBridgeCode ? [
                'kernelbridge_api_url' => $kernelBridgeCode['api_url'],
                'kernelbridge_installation_code' => [
                    'link_uuid' => $kernelBridgeCode['link_uuid'],
                    'license_uuid' => $license?->license_uuid,
                    'installation_uuid' => $kernelBridgeCode['installation_uuid'],
                    'activation_code' => $kernelBridgeCode['activation_code'],
                    'expires_at' => $kernelBridgeCode['expires_at'],
                ],
            ] : []),
            'sync_protocol_version' => '1.0',
        ];
        $encryptedResponse = Crypt::encryptString(json_encode($response, JSON_THROW_ON_ERROR));

        DB::transaction(function () use ($activation, $device, $payload, $credential, $license, $installationUuid, $kernelBridgeCode, $encryptedResponse): void {
            $activation->forceFill([
                'activated_at' => now(),
                'used_at' => now(),
                'response_encrypted' => $encryptedResponse,
            ])->save();

            $device->forceFill([
                'status' => DeviceStatus::Active,
                'credential_hash' => $this->hashSecret($credential),
                'credential_preview' => substr($credential, 0, 14).'...',
                'identity_hash' => $this->hashSecret((string) $payload['device_identity']),
                'kernelbridge_license_uuid' => $license?->license_uuid,
                'kernelbridge_installation_uuid' => $device->type === 'shop_app' ? $installationUuid : null,
                'kernelbridge_installation_grant_uuid' => null,
                'kernelbridge_grant_status' => $kernelBridgeCode ? 'activation_code_issued' : null,
                'app_version' => $payload['app_version'],
                'operating_system' => $payload['operating_system'] ?? [],
                'activated_at' => now(),
                'last_seen_at' => now(),
            ])->save();

            AuditLog::query()->create([
                'business_id' => $device->business_id,
                'branch_id' => $device->branch_id,
                'action' => 'device.activated',
                'subject_type' => Device::class,
                'subject_id' => $device->id,
                'metadata' => [
                    'device_uuid' => $device->uuid,
                    'kernelbridge_license_uuid' => $license?->license_uuid,
                    'kernelbridge_installation_uuid' => $kernelBridgeCode['installation_uuid'] ?? null,
                ],
            ]);
        });

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    public function status(string $credential): array
    {
        $device = $this->deviceFromCredential($credential);
        $device->forceFill(['last_seen_at' => now()])->save();

        return $this->deviceStatusPayload($device);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function heartbeat(string $credential, array $payload): array
    {
        $device = $this->deviceFromCredential($credential);

        $device->forceFill([
            'last_seen_at' => now(),
            'last_synchronized_at' => $payload['last_synchronized_at'] ?? $device->last_synchronized_at,
            'app_version' => $payload['app_version'] ?? $device->app_version,
            'operating_system' => $payload['operating_system'] ?? $device->operating_system,
            'synchronization_errors' => $payload['synchronization_errors'] ?? [],
        ])->save();

        DeviceHeartbeatLog::query()->create([
            'business_id' => $device->business_id,
            'branch_id' => $device->branch_id,
            'device_id' => $device->id,
            'device_uuid' => $device->uuid,
            'status' => $device->status->value,
            'app_version' => $device->app_version,
            'operating_system' => $device->operating_system ?? [],
            'synchronization_errors' => $device->synchronization_errors ?? [],
            'last_synchronized_at' => $device->last_synchronized_at,
            'reported_at' => now(),
        ]);

        return $this->deviceStatusPayload($device->refresh());
    }

    public function revokeActivation(DeviceActivation $activation, User $actor): void
    {
        $activation->forceFill(['revoked_at' => now()])->save();
        $device = $activation->device;

        $this->audit($actor, $device->business_id, $device->branch_id, 'device.activation_revoked', $device);
    }

    public function revokeDevice(Device $device, User $actor): void
    {
        $device->forceFill([
            'status' => DeviceStatus::Revoked,
            'credential_hash' => null,
            'revoked_at' => now(),
        ])->save();

        $this->audit($actor, $device->business_id, $device->branch_id, 'device.revoked', $device);
    }

    public function hashSecret(string $secret): string
    {
        return hash_hmac('sha256', $secret, (string) config('app.key'));
    }

    public function deviceForCredential(string $credential): Device
    {
        return $this->deviceFromCredential($credential);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function approvedClientAppDevices(string $credential): array
    {
        $serverDevice = $this->deviceFromCredential($credential);

        return Device::query()
            ->where('business_id', $serverDevice->business_id)
            ->where('branch_id', $serverDevice->branch_id)
            ->where('type', 'client_app')
            ->where('status', DeviceStatus::Active)
            ->orderBy('name')
            ->get()
            ->map(fn (Device $device): array => [
                'uuid' => $device->uuid,
                'name' => $device->name,
                'type' => $device->type,
                'status' => $device->status->value,
                'app_version' => $device->app_version,
                'last_seen_at' => $device->last_seen_at?->toISOString(),
                'activated_at' => $device->activated_at?->toISOString(),
            ])
            ->values()
            ->all();
    }

    private function generateCode(): string
    {
        return strtoupper(Str::random(4).'-'.Str::random(4));
    }

    private function assertActivationUsable(DeviceActivation $activation): void
    {
        if ($activation->revoked_at || $activation->used_at || $activation->activated_at || $activation->expires_at?->isPast()) {
            throw ValidationException::withMessages(['activation_code' => 'The activation code is invalid or expired.']);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function retryActivation(DeviceActivation $activation, Device $device, array $payload): array
    {
        $identity = (string) ($payload['device_identity'] ?? '');
        $installationUuid = trim((string) ($payload['installation_uuid'] ?? ''));
        $sameIdentity = $identity !== ''
            && is_string($device->identity_hash)
            && hash_equals($device->identity_hash, $this->hashSecret($identity));
        $sameInstallation = $device->type !== 'shop_app'
            || ($installationUuid !== '' && $device->kernelbridge_installation_uuid === $installationUuid);

        if ($device->status !== DeviceStatus::Active
            || ! $sameIdentity
            || ! $sameInstallation
            || ! $activation->used_at?->isAfter(now()->subMinutes(30))
            || blank($activation->response_encrypted)) {
            throw ValidationException::withMessages([
                'activation_code' => 'The activation code is invalid, expired, or has already been used by another installation.',
            ]);
        }

        $response = json_decode(Crypt::decryptString($activation->response_encrypted), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($response)) {
            throw new \UnexpectedValueException('The saved activation response is malformed.');
        }

        return $response;
    }

    private function assertBusinessCanActivate(Business $business, bool $requireLocalSubscription = true): void
    {
        $business->refresh()->loadMissing('currentSubscription.plan.features');

        if ($business->status !== BusinessStatus::Active) {
            throw ValidationException::withMessages(['business' => 'Business is not active.']);
        }

        if ($requireLocalSubscription && ! $this->subscriptions->allowsOperationalAccess($business->currentSubscription)) {
            throw ValidationException::withMessages(['subscription' => 'Subscription is not active.']);
        }
    }

    private function requiresKernelBridgeGrant(string $deviceType): bool
    {
        return $deviceType === 'shop_app' && (bool) config('kernelbridge.enforce_server_grants', true);
    }

    private function activeKernelBridgeLicense(Business $business): BusinessLicenseAssociation
    {
        $license = $business->kernelbridgeLicense()->first();

        if (! $license
            || ! in_array(strtolower((string) $license->status), ['active', 'trial'], true)
            || ($license->expires_at && $license->expires_at->isPast())
            || ($license->grace_expires_at && $license->grace_expires_at->isPast())) {
            throw ValidationException::withMessages([
                'license' => 'An active KernelBridge MarketLens license is required to register a Business Server.',
            ]);
        }

        return $license;
    }

    private function assertServerInstallationCapacity(Business $business, ?Device $currentDevice = null): void
    {
        $license = $this->activeKernelBridgeLicense($business);
        $limit = data_get($license->entitlements, 'max_server_installations');

        if (! is_numeric($limit) || (int) $limit < 1) {
            throw ValidationException::withMessages([
                'license' => 'The KernelBridge license has no valid max_server_installations entitlement.',
            ]);
        }

        $count = Device::query()
            ->where('business_id', $business->id)
            ->where('type', 'shop_app')
            ->whereIn('status', [DeviceStatus::Pending, DeviceStatus::Active])
            ->when($currentDevice, fn ($query) => $query->whereKeyNot($currentDevice->id))
            ->count();

        if ($count >= (int) $limit) {
            throw ValidationException::withMessages([
                'license' => 'The Business Server installation limit has been reached for this license.',
            ]);
        }
    }

    private function assertBranchCanActivate(Branch $branch): void
    {
        if ($branch->status !== BranchStatus::Active) {
            throw ValidationException::withMessages(['branch' => 'Branch is not active.']);
        }
    }

    private function assertDeviceLimit(Business $business, string $deviceType, ?Device $currentDevice = null): void
    {
        $activeCount = Device::query()
            ->where('business_id', $business->id)
            ->where('type', $deviceType)
            ->whereIn('status', [DeviceStatus::Pending, DeviceStatus::Active])
            ->when($currentDevice, fn ($query) => $query->whereKeyNot($currentDevice->id))
            ->count();

        $license = $deviceType === 'client_app' ? $this->licenseEntitlements->license($business) : null;
        $usesKernelBridgeLimit = $license !== null;

        if ($license && ! $this->licenseEntitlements->isActive($license)) {
            throw ValidationException::withMessages([
                'license' => 'The linked KernelBridge MarketLens license is not active.',
            ]);
        }

        $licenseLimit = $usesKernelBridgeLimit
            ? $this->licenseEntitlements->limit($business, 'max_client_app_devices')
            : null;

        if ($licenseLimit === 'unlimited') {
            return;
        }

        if ($usesKernelBridgeLimit && (! is_numeric($licenseLimit) || (int) $licenseLimit < 0)) {
            throw ValidationException::withMessages([
                'license' => 'The active KernelBridge license has no valid max_client_app_devices entitlement. Refresh its entitlements or configure the plan limit.',
            ]);
        }

        $limit = $licenseLimit !== null
            ? (int) $licenseLimit
            : $this->subscriptions->maxDevices($business, $deviceType);

        if ($activeCount >= $limit) {
            $message = $usesKernelBridgeLimit
                ? 'The Business App device limit has been reached for this license.'
                : 'The current subscription does not allow another Business App device.';
            throw ValidationException::withMessages(['device' => $message]);
        }
    }

    private function resolveAllowedVersion(mixed $versionId): ?ApplicationVersion
    {
        if (! $versionId) {
            return null;
        }

        $version = ApplicationVersion::query()->findOrFail($versionId);

        if ($version->status !== ApplicationVersionStatus::Published) {
            throw ValidationException::withMessages(['application_version_id' => 'Only published application versions can be assigned.']);
        }

        return $version;
    }

    private function assertApplicationVersionAllowed(DeviceActivation $activation, string $appVersion): void
    {
        $assigned = $activation->application_version_id
            ? ApplicationVersion::query()->find($activation->application_version_id)
            : null;

        if ($assigned && ($assigned->status !== ApplicationVersionStatus::Published || $assigned->version !== $appVersion)) {
            throw ValidationException::withMessages(['app_version' => 'This Business Server version is not allowed for the activation.']);
        }
    }

    private function deviceFromCredential(string $credential): Device
    {
        $device = Device::query()
            ->where('credential_hash', $this->hashSecret($credential))
            ->first();

        if (! $device || $device->status !== DeviceStatus::Active || $device->revoked_at) {
            $this->auditFailure('device.authentication_failed', ['reason' => 'invalid_or_revoked_credential']);
            throw ValidationException::withMessages(['credential' => 'Device credential is invalid or revoked.']);
        }

        return $device;
    }

    private function deviceStatusPayload(Device $device): array
    {
        $business = Business::query()->with('currentSubscription.plan.features')->findOrFail($device->business_id);
        $branch = Branch::query()->findOrFail($device->branch_id);
        $subscription = $business->currentSubscription;
        $plan = $subscription?->plan;
        $licenseEntitlements = app(BusinessLicenseEntitlementService::class);
        $license = $licenseEntitlements->license($business);
        $providerPlanName = $license ? $licenseEntitlements->planName($license) : null;
        $planFeatures = $this->planFeatures($business);
        $availableFeatures = array_fill_keys($planFeatures, true);

        $featureCatalog = PlanFeature::query()
            ->whereHas('plan', fn ($query) => $query->where('is_active', true))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->unique(fn (PlanFeature $feature): string => (string) ($feature->key ?: $feature->name))
            ->map(fn (PlanFeature $feature): array => [
                'key' => (string) ($feature->key ?: $feature->name),
                'name' => $feature->name,
                'description' => $feature->tooltip,
                'available' => isset($availableFeatures[(string) ($feature->key ?: $feature->name)]),
            ])
            ->values()
            ->all();

        return [
            'device_uuid' => $device->uuid,
            'status' => $device->status->value,
            'last_contact_at' => $device->last_seen_at?->toISOString(),
            'last_synchronized_at' => $device->last_synchronized_at?->toISOString(),
            'shop_app_version' => $device->app_version,
            'operating_system' => $device->operating_system ?? [],
            'synchronization_errors' => $device->synchronization_errors ?? [],
            'requires_update' => $device->requires_update_at !== null,
            'required_version' => $device->required_version,
            'business_uuid' => $business->uuid,
            'branch_uuid' => $branch->uuid,
            'subscription_state' => $license?->status ?? $subscription?->status->value ?? 'unknown',
            'subscription_plan' => $license ? [
                'uuid' => data_get($license->provider_metadata, 'plan_uuid'),
                'name' => $providerPlanName ?? 'KernelBridge plan',
                'starts_at' => data_get($license->provider_metadata, 'starts_at'),
                'ends_at' => $license->expires_at?->toISOString(),
            ] : ($plan ? [
                'uuid' => $plan->uuid,
                'name' => $plan->name,
                'billing_interval' => $plan->billing_interval,
                'starts_at' => $subscription?->starts_at?->toISOString(),
                'ends_at' => $subscription?->ends_at?->toISOString(),
            ] : null),
            'plan_features' => $planFeatures,
            'feature_catalog' => $featureCatalog,
            'enabled_modules' => $this->enabledModules($business, $branch),
            'branch_settings' => [
                'business_modules' => $business->module_settings ?? [],
                'branch_modules' => $branch->module_settings ?? [],
                'operating_modes' => $branch->operating_modes ?? ['sales'],
                'branch_code' => $branch->code,
                'timezone' => $business->timezone,
                'currency' => $business->default_currency,
            ],
            'kernelbridge_license_uuid' => $device->kernelbridge_license_uuid,
            'kernelbridge_installation_grant_uuid' => $device->kernelbridge_installation_grant_uuid,
            'kernelbridge_grant_status' => $device->kernelbridge_grant_status,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function planFeatures(Business $business): array
    {
        $licensedFeatures = app(BusinessLicenseEntitlementService::class)->featureNames($business);
        if ($licensedFeatures !== null) {
            return $licensedFeatures;
        }

        $business->loadMissing('currentSubscription.plan.features');

        return $business->currentSubscription?->plan?->features
            ->map(fn ($feature): string => (string) ($feature->key ?: $feature->name))
            ->values()
            ->all() ?? [];
    }

    /**
     * @return array<int, string>
     */
    private function enabledModules(Business $business, Branch $branch): array
    {
        $modules = ['sales', 'inventory', 'purchases', 'customers', 'reports'];

        foreach (BusinessModule::cases() as $module) {
            if ($this->moduleAccess->available($business, $module, $branch)) {
                $modules[] = $module->value;
            }
        }

        return array_values(array_unique($modules));
    }

    private function audit(?User $actor, ?int $businessId, ?int $branchId, string $action, ?Device $device = null, array $metadata = []): void
    {
        AuditLog::query()->create([
            'business_id' => $businessId,
            'branch_id' => $branchId,
            'actor_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $device ? Device::class : null,
            'subject_id' => $device?->id,
            'metadata' => [
                ...$metadata,
                'device_uuid' => $device?->uuid,
            ],
        ]);
    }

    private function auditFailure(string $action, array $metadata): void
    {
        AuditLog::query()->create([
            'action' => $action,
            'metadata' => $metadata,
        ]);
    }
}
