<?php

namespace App\Domain\Devices\Services;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Devices\Models\Device;
use App\Domain\Devices\Models\DeviceActivation;
use App\Domain\Devices\Models\DeviceHeartbeatLog;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Domain\Versions\Models\ApplicationVersion;
use App\Enums\ApplicationVersionStatus;
use App\Enums\BranchStatus;
use App\Enums\BusinessStatus;
use App\Enums\DeviceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ShopAppActivationService
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{activation: DeviceActivation, code: string}
     */
    public function createActivation(Business $business, Branch $branch, User $actor, array $data): array
    {
        $this->assertBusinessCanActivate($business);
        $this->assertBranchCanActivate($branch);
        $this->assertDeviceLimit($business, (string) $data['device_type']);

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
        $branch = Branch::query()->findOrFail($device->branch_id);
        $business = Business::query()->with('currentSubscription')->findOrFail($device->business_id);

        try {
            $this->assertActivationUsable($activation);
            $this->assertBusinessCanActivate($business);
            $this->assertBranchCanActivate($branch);
            $this->assertDeviceLimit($business, $device->type, $device);
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

        $credential = 'ml_device_'.Str::random(64);

        DB::transaction(function () use ($activation, $device, $payload, $credential): void {
            $activation->forceFill([
                'activated_at' => now(),
                'used_at' => now(),
            ])->save();

            $device->forceFill([
                'status' => DeviceStatus::Active,
                'credential_hash' => $this->hashSecret($credential),
                'credential_preview' => substr($credential, 0, 14).'...',
                'identity_hash' => $this->hashSecret((string) $payload['device_identity']),
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
                'metadata' => ['device_uuid' => $device->uuid],
            ]);
        });

        return [
            'device_uuid' => $device->uuid,
            'credential' => $credential,
            'credential_preview' => substr($credential, 0, 14).'...',
            'expires_at' => null,
        ];
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

    private function assertBusinessCanActivate(Business $business): void
    {
        $business->refresh()->loadMissing('currentSubscription.plan.features');

        if ($business->status !== BusinessStatus::Active) {
            throw ValidationException::withMessages(['business' => 'Business is not active.']);
        }

        if (! in_array($business->currentSubscription?->status, [SubscriptionStatus::Trialing, SubscriptionStatus::Active], true)) {
            throw ValidationException::withMessages(['subscription' => 'Subscription is not active.']);
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

        if ($activeCount >= $this->maxDevices($business)) {
            throw ValidationException::withMessages(['device' => 'The current subscription does not allow another Shop App device.']);
        }
    }

    private function maxDevices(Business $business): int
    {
        $features = $business->currentSubscription?->plan?->features()
            ->pluck('name')
            ->map(fn (string $name): string => strtolower($name))
            ->all() ?? [];

        if (collect($features)->contains(fn (string $name): bool => str_contains($name, 'more monitoring users and devices'))) {
            return 25;
        }

        if (collect($features)->contains(fn (string $name): bool => str_contains($name, 'more client app devices'))) {
            return 5;
        }

        return 1;
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
            throw ValidationException::withMessages(['app_version' => 'This Shop App version is not allowed for the activation.']);
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
        ];
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
