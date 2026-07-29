<?php

namespace App\Domain\Synchronization\Services;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Devices\Models\Device;
use App\Domain\Devices\Services\ShopAppActivationService;
use App\Domain\Synchronization\Data\AuthenticatedSyncDevice;
use App\Enums\DeviceStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class SyncDeviceAuthenticator
{
    public function __construct(private readonly ShopAppActivationService $activationService) {}

    public function authenticate(Request $request, string $rawBody): AuthenticatedSyncDevice
    {
        $credential = $request->bearerToken();

        if (! $credential) {
            $this->auditFailure('missing_credential');
            throw new UnauthorizedHttpException('Bearer', 'Device credential is required.');
        }

        $device = Device::query()
            ->where('credential_hash', $this->activationService->hashSecret($credential))
            ->first();

        if (! $device || $device->status !== DeviceStatus::Active || $device->revoked_at) {
            $this->auditFailure('invalid_or_revoked_credential', $device);
            throw new UnauthorizedHttpException('Bearer', 'Device credential is invalid or revoked.');
        }

        $timestamp = (string) $request->header('X-ML-Timestamp');
        $signature = (string) $request->header('X-ML-Signature');

        try {
            $requestTime = $timestamp ? Carbon::parse($timestamp) : null;
        } catch (\Throwable) {
            $requestTime = null;
        }

        if (! $requestTime || abs(now()->diffInMinutes($requestTime, false)) > 5) {
            $this->auditFailure('stale_or_missing_timestamp', $device);
            throw new AccessDeniedHttpException('Request timestamp is outside the allowed synchronization window.');
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$rawBody, $credential);

        if (! hash_equals($expected, $signature)) {
            $this->auditFailure('invalid_signature', $device);
            throw new AccessDeniedHttpException('Request signature is invalid.');
        }

        $device->forceFill(['last_seen_at' => now()])->save();

        return new AuthenticatedSyncDevice($device, $credential);
    }

    private function auditFailure(string $reason, ?Device $device = null): void
    {
        AuditLog::query()->create([
            'business_id' => $device?->business_id,
            'branch_id' => $device?->branch_id,
            'action' => 'sync.authentication_failed',
            'subject_type' => $device ? Device::class : null,
            'subject_id' => $device?->id,
            'metadata' => ['reason' => $reason],
        ]);
    }
}
