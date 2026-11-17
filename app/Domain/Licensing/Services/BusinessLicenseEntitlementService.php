<?php

namespace App\Domain\Licensing\Services;

use App\Domain\Licensing\Models\BusinessLicenseAssociation;
use App\Domain\Tenancy\Models\Business;
use App\Enums\BusinessModule;

class BusinessLicenseEntitlementService
{
    public function license(Business $business): ?BusinessLicenseAssociation
    {
        return $business->kernelbridgeLicense()->first();
    }

    public function isActive(BusinessLicenseAssociation $license): bool
    {
        return in_array(strtolower($license->status), ['active', 'trial'], true)
            && (! $license->expires_at || $license->expires_at->isFuture())
            && (! $license->grace_expires_at || $license->grace_expires_at->isFuture());
    }

    public function businessHasActiveLicense(Business $business): bool
    {
        $license = $this->license($business);

        return $license !== null && $this->isActive($license);
    }

    /** @return array<int, string>|null Null means no KernelBridge license is linked. */
    public function featureNames(Business $business): ?array
    {
        $license = $this->license($business);

        if (! $license) {
            return null;
        }

        if (! $this->isActive($license)) {
            return [];
        }

        $entitlements = (array) $license->entitlements;
        $features = [];

        if (($entitlements['mode_sales'] ?? false) === true) {
            $features = [...$features, 'sales', 'payments', 'inventory', 'expenses', 'customer debts', 'purchases', 'suppliers', 'cashier settlement', 'reports', 'remote dashboard'];
        }

        if (($entitlements['mode_production'] ?? false) === true) {
            $features = [...$features, 'production', 'inventory', 'purchases', 'suppliers', 'expenses', 'reports', 'remote dashboard'];
        }

        if (($entitlements['mode_services'] ?? false) === true) {
            $features = [...$features, 'services', 'expenses', 'reports', 'remote dashboard'];
        }

        if (($entitlements['module_shareholders'] ?? false) === true) {
            $features = [...$features, 'module:shareholders', 'shareholders and profit distribution', 'shareholder management'];
        }

        return array_values(array_unique($features));
    }

    public function allowsMode(Business $business, BusinessModule $module): ?bool
    {
        $license = $this->license($business);

        if (! $license) {
            return null;
        }

        if (! $this->isActive($license)) {
            return false;
        }

        $key = match ($module) {
            BusinessModule::Production => 'mode_production',
            BusinessModule::Services => 'mode_services',
            BusinessModule::Shareholders => 'module_shareholders',
        };

        return ($license->entitlements[$key] ?? false) === true;
    }

    public function limit(Business $business, string $key): int|string|null
    {
        $license = $this->license($business);

        if (! $license || ! $this->isActive($license)) {
            return null;
        }

        $value = $license->entitlements[$key] ?? null;

        return is_numeric($value) || $value === 'unlimited' ? $value : null;
    }

    /** @param array<int, string> $needles */
    public function allowsAny(Business $business, array $needles): bool
    {
        $features = $this->featureNames($business);

        if ($features === null) {
            $business->loadMissing('currentSubscription.plan.features');
            $features = $business->currentSubscription?->plan?->features
                ->pluck('name')
                ->map(fn (string $name): string => strtolower($name))
                ->all() ?? [];
        }

        return collect($features)->contains(fn (string $feature): bool => collect($needles)
            ->contains(fn (string $needle): bool => str_contains($feature, strtolower($needle))));
    }

    public function planName(BusinessLicenseAssociation $license): ?string
    {
        $name = data_get($license->provider_metadata, 'plan_name');

        return is_string($name) && $name !== '' ? $name : null;
    }
}
