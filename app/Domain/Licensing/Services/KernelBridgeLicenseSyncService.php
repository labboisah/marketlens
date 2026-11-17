<?php

namespace App\Domain\Licensing\Services;

use App\Domain\Devices\Models\Device;
use App\Domain\Licensing\Models\BusinessLicenseAssociation;
use App\Domain\Modules\Services\ModuleAccessService;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use KernelBridge\LicensingClient\Exceptions\KernelBridgeApiException;
use KernelBridge\LicensingClient\Exceptions\KernelBridgeUnavailableException;
use KernelBridge\LicensingClient\Platform\PlatformAccountLinkClient;

class KernelBridgeLicenseSyncService
{
    public function __construct(
        private readonly KernelBridgeLicenseProjectionService $projectionService,
        private readonly PlatformAccountLinkClient $platformClient,
    ) {}

    public function redeemActivationCode(Business $business, string $activationCode): BusinessLicenseAssociation
    {
        $code = trim($activationCode);

        if ($code === '') {
            throw ValidationException::withMessages([
                'activation_code' => 'A valid activation code is required.',
            ]);
        }

        $this->assertApiTokenConfigured('activation_code');

        $data = $this->platformRequest(
            fn (): array => $this->platformClient->requestLinkWithActivationCode(
                $code,
                $business->uuid,
                config('kernelbridge.api_url'),
            ),
            'activation_code',
        );
        $payload = $this->normalizeProviderPayload($data);
        if (blank($payload['account_link_uuid']) || blank($payload['license_uuid'])
            || blank($payload['subscription_uuid']) || $payload['business_uuid'] !== $business->uuid
            || ! in_array($payload['account_link_status'], ['pending', 'approved', 'rejected', 'revoked'], true)) {
            throw ValidationException::withMessages([
                'activation_code' => 'KernelBridge returned an incomplete or mismatched account-link request.',
            ]);
        }
        $payload['api_base_url'] = rtrim((string) config('kernelbridge.api_url'), '/');

        return $this->projectionService->linkBusinessLicense($business, $code, $payload);
    }

    public function requestAccountLink(Business $business): BusinessLicenseAssociation
    {
        $license = $business->kernelbridgeLicense()->first();
        if (! $license || blank($license->license_uuid)) {
            throw ValidationException::withMessages(['license' => 'No KernelBridge license is available to request an account link.']);
        }

        $this->assertApiTokenConfigured('license');
        $data = $this->platformRequest(
            fn (): array => $this->platformClient->requestLink(
                $license->license_uuid,
                $business->uuid,
                config('kernelbridge.api_url'),
            ),
            'license',
        );
        $payload = $this->normalizeProviderPayload($data);
        $payload['api_base_url'] = rtrim((string) config('kernelbridge.api_url'), '/');

        return $this->projectionService->updateAccountLink($license, $payload);
    }

    public function refreshAccountLink(Business $business): BusinessLicenseAssociation
    {
        $license = $business->kernelbridgeLicense()->first();
        $linkUuid = data_get($license?->provider_metadata, 'account_link_uuid');
        if (! $license || ! is_string($linkUuid) || $linkUuid === '') {
            throw ValidationException::withMessages(['license' => 'No KernelBridge account-link request is available to refresh.']);
        }

        $this->assertApiTokenConfigured('license');
        $baseUrl = (string) data_get($license->provider_metadata, 'api_base_url', config('kernelbridge.api_url'));
        $data = $this->platformRequest(
            fn (): array => $this->platformClient->status($linkUuid, $baseUrl),
            'license',
        );
        $payload = $this->normalizeProviderPayload($data);
        $payload['api_base_url'] = $baseUrl;

        return $this->projectionService->updateAccountLink($license, $payload);
    }

    public function unlinkAccount(Business $business): void
    {
        $license = $business->kernelbridgeLicense()->first();
        $linkUuid = data_get($license?->provider_metadata, 'account_link_uuid');

        if (! $license || ! is_string($linkUuid) || $linkUuid === '') {
            throw ValidationException::withMessages([
                'license' => 'No KernelBridge account link is available to unlink.',
            ]);
        }

        $this->assertApiTokenConfigured('license');
        $baseUrl = (string) data_get($license->provider_metadata, 'api_base_url', config('kernelbridge.api_url'));
        $result = $this->platformRequest(
            fn (): array => $this->platformClient->unlink($linkUuid, $baseUrl),
            'license',
        );

        if (($result['uuid'] ?? null) !== $linkUuid
            || ($result['license_uuid'] ?? null) !== $license->license_uuid
            || ($result['business_uuid'] ?? null) !== $business->uuid
            || ($result['status'] ?? null) !== 'revoked') {
            throw ValidationException::withMessages([
                'license' => 'KernelBridge returned an incomplete or mismatched unlink response.',
            ]);
        }

        DB::transaction(function () use ($business, $license): void {
            Device::query()
                ->where('business_id', $business->id)
                ->where('kernelbridge_license_uuid', $license->license_uuid)
                ->update(['kernelbridge_grant_status' => 'revoked', 'updated_at' => now()]);
            $license->delete();
        });
        app(ModuleAccessService::class)->forget($business);
    }

    public function verifyLicense(Business $business, string $licenseUuid): BusinessLicenseAssociation
    {
        $this->assertApiTokenConfigured('license');

        $data = $this->platformRequest(
            fn (): array => $this->platformClient->verifyLicense(
                $licenseUuid,
                $business->uuid,
                config('kernelbridge.product_code', 'MARKETLENS'),
                config('kernelbridge.api_url'),
            ),
            'license',
        );
        $payload = $this->normalizeProviderPayload($data);

        return $this->projectionService->linkBusinessLicense($business, (string) ($payload['activation_code'] ?? 'provider-verify'), $payload);
    }

    public function refreshEntitlements(Business $business): BusinessLicenseAssociation
    {
        $license = $business->kernelbridgeLicense()->first();

        if (! $license || blank($license->subscription_uuid)) {
            throw ValidationException::withMessages([
                'license' => 'No linked KernelBridge subscription is available to refresh.',
            ]);
        }

        $this->assertApiTokenConfigured('license');

        $subscription = $this->platformRequest(
            fn (): array => $this->platformClient->subscriptionStatus(
                $license->subscription_uuid,
                (string) data_get($license->provider_metadata, 'api_base_url', config('kernelbridge.api_url')),
            ),
            'license',
        );
        $entitlements = $this->platformRequest(
            fn (): array => $this->platformClient->entitlements(
                $license->subscription_uuid,
                (string) data_get($license->provider_metadata, 'api_base_url', config('kernelbridge.api_url')),
            ),
            'license',
        );

        if (! is_array($subscription) || ! is_array($entitlements)) {
            throw ValidationException::withMessages([
                'license' => 'KernelBridge returned invalid subscription or entitlement data.',
            ]);
        }

        $refreshed = $this->projectionService->refreshEntitlements($license, $subscription, $entitlements);
        app(ModuleAccessService::class)->forget($business);

        return $refreshed;
    }

    /**
     * @return array<string, mixed>
     */
    public function issueInstallationGrant(
        Business $business,
        Branch $branch,
        Device $device,
        BusinessLicenseAssociation $license,
        string $installationUuid,
    ): array {
        $this->assertApiTokenConfigured('license');

        $payload = $this->platformRequest(
            fn (): array => $this->platformClient->issueInstallationGrant(
                $license->license_uuid,
                $business->uuid,
                $branch->uuid,
                $device->uuid,
                $installationUuid,
                (string) data_get($license->provider_metadata, 'api_base_url', config('kernelbridge.api_url')),
            ),
            'license',
        );
        if (! is_array($payload) || blank($payload['grant_uuid'] ?? null) || blank($payload['credential'] ?? null)) {
            throw ValidationException::withMessages([
                'license' => 'KernelBridge did not return a valid installation grant.',
            ]);
        }

        return $payload;
    }

    /**
     * @return array{link_uuid: string, installation_uuid: string, activation_code: string, expires_at: string, api_url: string}
     */
    public function issueInstallationActivationCode(
        Business $business,
        BusinessLicenseAssociation $license,
        string $installationUuid,
        string $deviceName,
    ): array {
        $linkUuid = data_get($license->provider_metadata, 'account_link_uuid');
        if ($license->business_id !== $business->id
            || ! is_string($linkUuid) || $linkUuid === ''
            || data_get($license->provider_metadata, 'account_link_status') !== 'approved') {
            throw ValidationException::withMessages([
                'license' => 'The KernelBridge account must be linked before this installation can activate.',
            ]);
        }

        $this->assertApiTokenConfigured('license');
        $apiBaseUrl = (string) data_get($license->provider_metadata, 'api_base_url', config('kernelbridge.api_url'));
        $payload = $this->platformRequest(
            fn (): array => $this->platformClient->issueInstallationCode(
                $linkUuid,
                $installationUuid,
                $deviceName,
                $apiBaseUrl,
            ),
            'license',
            retryUnavailable: true,
        );
        if (! is_array($payload)
            || ($payload['link_uuid'] ?? null) !== $linkUuid
            || ($payload['installation_uuid'] ?? null) !== $installationUuid
            || blank($payload['activation_code'] ?? null)
            || blank($payload['expires_at'] ?? null)) {
            throw ValidationException::withMessages([
                'license' => 'KernelBridge did not return a valid installation activation code.',
            ]);
        }

        return [
            'link_uuid' => $linkUuid,
            'installation_uuid' => $installationUuid,
            'activation_code' => $payload['activation_code'],
            'expires_at' => $payload['expires_at'],
            'api_url' => $apiBaseUrl,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizeProviderPayload(array $payload): array
    {
        $payload = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;

        return [
            'product_code' => strtoupper((string) ($payload['product_code'] ?? config('kernelbridge.product_code', 'MARKETLENS'))),
            'license_uuid' => $payload['license_uuid'] ?? $payload['license_id'] ?? null,
            'business_uuid' => $payload['business_uuid'] ?? null,
            'subscription_uuid' => $payload['subscription_uuid'] ?? $payload['subscription_id'] ?? null,
            'status' => $payload['license_status'] ?? 'active',
            'account_link_uuid' => $payload['uuid'] ?? null,
            'account_link_status' => $payload['status'] ?? null,
            'plan' => $payload['plan'] ?? [],
            'starts_at' => $payload['starts_at'] ?? null,
            'ends_at' => $payload['ends_at'] ?? null,
            'expires_at' => $payload['expires_at'] ?? null,
            'grace_expires_at' => $payload['grace_expires_at'] ?? null,
            'last_verified_at' => $payload['last_verified_at'] ?? now(),
            'entitlement_revision' => $payload['entitlement_revision'] ?? null,
            'provider_event_id' => $payload['provider_event_id'] ?? $payload['event_id'] ?? null,
            'entitlements' => $this->projectionService->normalizeEntitlements($payload['entitlements'] ?? []),
        ];
    }

    private function assertApiTokenConfigured(string $field): void
    {
        $token = trim((string) config('kernelbridge.api_token', ''));

        if ($token === '' || $token === 'test-token') {
            throw ValidationException::withMessages([
                $field => 'KernelBridge API credentials are not configured. Set KERNELBRIDGE_API_TOKEN on the MarketLens server.',
            ]);
        }
    }

    /**
     * @param  callable(): array<string, mixed>  $request
     * @return array<string, mixed>
     */
    private function platformRequest(callable $request, string $field, bool $retryUnavailable = false): array
    {
        try {
            return $request();
        } catch (KernelBridgeApiException $exception) {
            $message = $exception->errorCode === 'insufficient_scope' && $field === 'activation_code'
                ? 'The configured KernelBridge credential lacks the licensing:account-link scope. Issue a dedicated MarketLens Platform credential with the Platform licensing integration scope and update KERNELBRIDGE_API_TOKEN in the MarketLens server secret store.'
                : $exception->getMessage();

            throw ValidationException::withMessages([$field => $message]);
        } catch (KernelBridgeUnavailableException $exception) {
            if ($retryUnavailable) {
                return $this->platformRequest($request, $field);
            }

            throw ValidationException::withMessages([$field => $exception->getMessage()]);
        }
    }
}
