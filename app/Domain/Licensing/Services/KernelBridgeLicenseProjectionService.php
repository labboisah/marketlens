<?php

namespace App\Domain\Licensing\Services;

use App\Domain\Licensing\Models\BusinessLicenseAssociation;
use App\Domain\Tenancy\Models\Business;
use Illuminate\Validation\ValidationException;

class KernelBridgeLicenseProjectionService
{
    public function linkBusinessLicense(Business $business, string $activationCode, array $payload = []): BusinessLicenseAssociation
    {
        if (blank($activationCode)) {
            throw ValidationException::withMessages([
                'activation_code' => 'A valid activation code is required.',
            ]);
        }

        $provider = 'kernelbridge';
        $productCode = strtoupper((string) ($payload['product_code'] ?? config('kernelbridge.product_code', 'MARKETLENS')));

        $existing = BusinessLicenseAssociation::query()
            ->where('business_id', $business->id)
            ->where('provider', $provider)
            ->where('product_code', $productCode)
            ->first();

        if ($existing) {
            if ($existing->license_uuid !== ($payload['license_uuid'] ?? null)) {
                throw ValidationException::withMessages([
                    'license' => 'A different KernelBridge license is already linked to this business.',
                ]);
            }

            return $this->updateAccountLink($existing, $payload);
        }

        $attributes = [
            'business_id' => $business->id,
            'provider' => $provider,
            'product_code' => $productCode,
            'license_uuid' => $payload['license_uuid'] ?? null,
            'subscription_uuid' => $payload['subscription_uuid'] ?? null,
            'status' => ($payload['account_link_status'] ?? null) === 'approved' ? ($payload['status'] ?? 'active') : 'pending',
            'expires_at' => $payload['expires_at'] ?? null,
            'grace_expires_at' => $payload['grace_expires_at'] ?? null,
            'last_verified_at' => $payload['last_verified_at'] ?? now(),
            'entitlements' => $this->normalizeEntitlements($payload['entitlements'] ?? []),
            'entitlement_revision' => $payload['entitlement_revision'] ?? null,
            'provider_metadata' => [
                'provider_event_id' => $payload['provider_event_id'] ?? null,
                'provider_status' => $payload['status'] ?? 'active',
                'activation_code_redacted' => true,
                'account_link_uuid' => $payload['account_link_uuid'] ?? null,
                'account_link_status' => $payload['account_link_status'] ?? null,
                'api_base_url' => $payload['api_base_url'] ?? null,
                'plan_uuid' => data_get($payload, 'plan.uuid'),
                'plan_name' => data_get($payload, 'plan.name'),
                'starts_at' => $payload['starts_at'] ?? null,
            ],
            'provider_event_id' => $payload['provider_event_id'] ?? null,
        ];

        return BusinessLicenseAssociation::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateAccountLink(BusinessLicenseAssociation $license, array $payload): BusinessLicenseAssociation
    {
        $businessUuid = $license->business()->value('uuid');
        if (($payload['license_uuid'] ?? null) !== $license->license_uuid
            || ($payload['business_uuid'] ?? null) !== $businessUuid) {
            throw ValidationException::withMessages(['license' => 'KernelBridge returned account-link data for a different license or business.']);
        }

        $linkStatus = $payload['account_link_status'] ?? null;
        $metadata = (array) $license->provider_metadata;
        $metadata['account_link_uuid'] = $payload['account_link_uuid'] ?? $metadata['account_link_uuid'] ?? null;
        $metadata['account_link_status'] = $linkStatus ?? $metadata['account_link_status'] ?? null;
        $metadata['provider_status'] = $payload['status'] ?? $metadata['provider_status'] ?? 'active';
        $metadata['api_base_url'] = $payload['api_base_url'] ?? $metadata['api_base_url'] ?? null;
        $metadata['plan_uuid'] = data_get($payload, 'plan.uuid', $metadata['plan_uuid'] ?? null);
        $metadata['plan_name'] = data_get($payload, 'plan.name', $metadata['plan_name'] ?? null);
        $metadata['starts_at'] = $payload['starts_at'] ?? $metadata['starts_at'] ?? null;

        $license->forceFill([
            'status' => $linkStatus === 'approved' ? ($payload['status'] ?? 'active') : ($linkStatus ?? 'pending'),
            'subscription_uuid' => $payload['subscription_uuid'] ?? $license->subscription_uuid,
            'expires_at' => $payload['expires_at'] ?? $license->expires_at,
            'grace_expires_at' => $payload['grace_expires_at'] ?? $license->grace_expires_at,
            'entitlements' => $this->normalizeEntitlements($payload['entitlements'] ?? $license->entitlements),
            'entitlement_revision' => $payload['entitlement_revision'] ?? $license->entitlement_revision,
            'provider_metadata' => $metadata,
            'last_verified_at' => now(),
        ])->save();

        return $license->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function normalizeEntitlements(mixed $entitlements): array
    {
        if (is_array($entitlements)) {
            if (array_is_list($entitlements)) {
                return collect($entitlements)
                    ->filter(fn (mixed $item): bool => is_array($item) && filled($item['key'] ?? null))
                    ->mapWithKeys(fn (array $item): array => [
                        $item['key'] => $item['limit'] ?? $item['enabled'] ?? true,
                    ])
                    ->all();
            }

            return $entitlements;
        }

        if (is_string($entitlements) && filled($entitlements)) {
            $decoded = json_decode($entitlements, true);

            return is_array($decoded) ? $decoded : ['raw' => $entitlements];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function refreshEntitlements(
        BusinessLicenseAssociation $license,
        array $subscription,
        array $entitlements,
    ): BusinessLicenseAssociation {
        if (($subscription['uuid'] ?? null) !== $license->subscription_uuid
            || ($entitlements['subscription_uuid'] ?? null) !== $license->subscription_uuid) {
            throw ValidationException::withMessages([
                'license' => 'KernelBridge returned data for a different subscription.',
            ]);
        }

        $metadata = (array) $license->provider_metadata;
        $metadata['plan_uuid'] = data_get($subscription, 'plan.uuid');
        $metadata['plan_name'] = data_get($subscription, 'plan.name');
        $metadata['provider_status'] = $subscription['status'] ?? $license->status;
        $metadata['starts_at'] = $subscription['starts_at'] ?? null;
        unset($metadata['last_reconcile_error'], $metadata['last_reconcile_error_at']);
        $linkStatus = $metadata['account_link_status'] ?? null;

        $license->forceFill([
            'status' => $linkStatus === null || $linkStatus === 'approved'
                ? ($subscription['status'] ?? $license->status)
                : $linkStatus,
            'expires_at' => $subscription['ends_at'] ?? null,
            'grace_expires_at' => $subscription['grace_ends_at'] ?? null,
            'provider_metadata' => $metadata,
            'entitlements' => $this->normalizeEntitlements($entitlements['entitlements'] ?? []),
            'last_verified_at' => now(),
        ])->save();

        return $license->refresh();
    }
}
