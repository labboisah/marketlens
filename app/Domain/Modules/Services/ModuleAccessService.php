<?php

namespace App\Domain\Modules\Services;

use App\Domain\Billing\Services\SubscriptionManagementService;
use App\Domain\Licensing\Services\BusinessLicenseEntitlementService;
use App\Domain\Modules\Data\ModuleAccessResult;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Enums\BranchModuleStatus;
use App\Enums\BusinessModule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class ModuleAccessService
{
    public function resolve(Business $business, BusinessModule|string $module, ?Branch $branch = null): ModuleAccessResult
    {
        $module = $this->module($module);
        $business->loadMissing('currentSubscription.plan.features');
        $branch?->loadMissing('business.currentSubscription.plan.features');

        $key = $this->cacheKey($business->id, $branch?->id, $module);

        // Cache a plain array to avoid object serialization issues during
        // unserialize (which can produce __PHP_Incomplete_Class). Rebuild
        // ModuleAccessResult from the array when returning.
        $data = Cache::remember($key, now()->addMinutes(10), fn () => $this->uncachedResolve($business, $module, $branch)->toArray());

        if (is_array($data)) {
            return new ModuleAccessResult(
                BusinessModule::from($data['module']),
                (bool) ($data['plan_allows'] ?? false),
                (bool) ($data['business_enabled'] ?? false),
                (bool) ($data['branch_enabled'] ?? false),
                (bool) ($data['available'] ?? false),
                $data['source'] ?? 'business',
                $data['branch_status'] ?? null,
                $data['reason'] ?? null,
            );
        }

        // Fallback: if cache contains an unexpected value, recompute and store array
        $result = $this->uncachedResolve($business, $module, $branch);
        Cache::put($key, $result->toArray(), now()->addMinutes(10));

        return $result;
    }

    public function available(Business $business, BusinessModule|string $module, ?Branch $branch = null): bool
    {
        return $this->resolve($business, $module, $branch)->available;
    }

    /**
     * @return array<string, ModuleAccessResult>
     */
    public function resolveAll(Business $business, ?Branch $branch = null): array
    {
        $results = [];

        foreach (BusinessModule::cases() as $module) {
            $results[$module->value] = $this->resolve($business, $module, $branch);
        }

        return $results;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function validateBranchSettings(Business $business, Branch $branch, array $settings): void
    {
        foreach (BusinessModule::cases() as $module) {
            $status = $settings[$module->value]['status'] ?? BranchModuleStatus::Inherit->value;
            $businessSettings = $this->businessModuleSettings($business, $module);

            if ($status !== BranchModuleStatus::Inherit->value && ($businessSettings['business_level_locks']['prevent_branch_overrides'] ?? false) === true) {
                throw ValidationException::withMessages([
                    'moduleSettings.'.$module->value.'.status' => $module->displayName().' branch overrides are locked at business level.',
                ]);
            }

            if ($status !== BranchModuleStatus::Enabled->value) {
                continue;
            }

            $businessResult = $this->resolve($business, $module);

            if (! $businessResult->planAllows || ! $businessResult->businessEnabled) {
                throw ValidationException::withMessages([
                    'moduleSettings.'.$module->value.'.status' => $module->displayName().' cannot be enabled for a branch until the plan and business settings allow it.',
                ]);
            }
        }
    }

    public function forget(Business|int $business, ?Branch $branch = null): void
    {
        $businessId = $business instanceof Business ? $business->id : $business;
        $branchIds = $branch ? [$branch->id] : [null, ...Branch::query()->where('business_id', $businessId)->pluck('id')->all()];

        foreach ($branchIds as $branchId) {
            foreach (BusinessModule::cases() as $module) {
                Cache::forget($this->cacheKey($businessId, $branchId, $module));
            }
        }
    }

    private function uncachedResolve(Business $business, BusinessModule $module, ?Branch $branch): ModuleAccessResult
    {
        $businessSettings = $this->businessModuleSettings($business, $module);
        $branchSettings = $branch ? $this->branchModuleSettings($branch, $module) : [];
        $planAllows = $this->planAllows($business, $module);
        // A subscribed module is available by default until a business explicitly disables it.
        // This keeps legacy businesses without module settings aligned with their plan.
        $businessEnabled = array_key_exists('enabled', $businessSettings)
            ? (bool) $businessSettings['enabled']
            : $planAllows;
        $branchStatus = $branch ? (string) ($branchSettings['status'] ?? BranchModuleStatus::Inherit->value) : null;
        $branchEnabled = match ($branchStatus) {
            BranchModuleStatus::Enabled->value => true,
            BranchModuleStatus::Disabled->value => false,
            default => $businessEnabled,
        };
        $available = $planAllows && $businessEnabled && $branchEnabled;

        return new ModuleAccessResult(
            module: $module,
            planAllows: $planAllows,
            businessEnabled: $businessEnabled,
            branchEnabled: $branchEnabled,
            available: $available,
            source: $branchStatus === BranchModuleStatus::Inherit->value || $branchStatus === null ? 'business' : 'branch',
            branchStatus: $branchStatus,
            reason: $this->reason($planAllows, $businessEnabled, $branchEnabled),
        );
    }

    /** @return array<string, mixed> */
    public function businessModuleSettings(Business $business, BusinessModule|string $module): array
    {
        $module = $this->module($module);

        return (array) (($business->module_settings ?? [])[$module->value] ?? []);
    }

    /** @return array<string, mixed> */
    public function branchModuleSettings(Branch $branch, BusinessModule|string $module): array
    {
        $module = $this->module($module);

        return (array) (($branch->module_settings ?? [])[$module->value] ?? []);
    }

    private function planAllows(Business $business, BusinessModule $module): bool
    {
        $licensedMode = app(BusinessLicenseEntitlementService::class)->allowsMode($business, $module);

        if ($licensedMode !== null) {
            return $licensedMode;
        }

        $subscription = $business->currentSubscription;

        if (! $subscription || ! app(SubscriptionManagementService::class)->allowsOperationalAccess($subscription)) {
            return false;
        }

        return $subscription->plan?->features
            ->contains(function ($feature) use ($module): bool {
                $key = strtolower((string) ($feature->key ?? ''));
                $name = strtolower((string) $feature->name);

                return $key === 'module:'.$module->value
                    || str_contains($name, strtolower($module->displayName()))
                    || str_contains($name, $module->value);
            }) ?? false;
    }

    private function module(BusinessModule|string $module): BusinessModule
    {
        return $module instanceof BusinessModule ? $module : BusinessModule::from($module);
    }

    private function cacheKey(int $businessId, ?int $branchId, BusinessModule $module): string
    {
        return "module-access:business:{$businessId}:branch:".($branchId ?: 'none').":{$module->value}";
    }

    private function reason(bool $planAllows, bool $businessEnabled, bool $branchEnabled): ?string
    {
        if (! $planAllows) {
            return 'subscription_plan_unavailable';
        }

        if (! $businessEnabled) {
            return 'business_module_disabled';
        }

        if (! $branchEnabled) {
            return 'branch_module_disabled';
        }

        return null;
    }
}
