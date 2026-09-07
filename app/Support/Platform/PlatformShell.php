<?php

namespace App\Support\Platform;

use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Services\SubscriptionManagementService;
use App\Domain\Modules\Services\ModuleAccessService;
use App\Domain\Notifications\Models\PlatformNotification;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Domain\Tenancy\Services\BranchContext;
use App\Domain\Tenancy\Services\BusinessContext;
use App\Enums\BusinessModule;
use App\Models\User;
use Illuminate\Support\Collection;

class PlatformShell
{
    public function __construct(
        private readonly BusinessContext $businessContext,
        private readonly BranchContext $branchContext,
        private readonly SubscriptionManagementService $subscriptions,
        private readonly ModuleAccessService $moduleAccess,
    ) {}

    /**
     * @return array{
     *     businesses: Collection<int, Business>,
     *     branches: Collection<int, Branch>,
     *     business: ?Business,
     *     branch: ?Branch,
     *     subscription: ?Subscription,
     *     navigation: array<int, NavigationItem>,
     *     featureNames: array<int, string>,
     *     unreadNotifications: int,
     * }
     */
    public function forUser(?User $user): array
    {
        if (! $user) {
            return [
                'businesses' => collect(),
                'branches' => collect(),
                'business' => null,
                'branch' => null,
                'subscription' => null,
                'navigation' => [],
                'featureNames' => [],
                'unreadNotifications' => 0,
            ];
        }

        $businesses = $user->accessibleBusinesses();
        $business = $businesses->firstWhere('id', $this->businessContext->id()) ?? $businesses->first();
        $branches = $business?->branches->sortBy('name')->values() ?? collect();

        if ($business && ! $user->isBusinessOwner($business)) {
            $assignment = $user->monitoringAssignments()
                ->where('business_id', $business->id)
                ->where('status', 'active')
                ->whereNull('suspended_at')
                ->whereNull('revoked_at')
                ->first();

            if ($assignment && $assignment->branches()->exists()) {
                $branches = $branches->whereIn('id', $assignment->branches()->pluck('branches.id')->all())->values();
            }
        }

        $branch = $branches->firstWhere('id', $this->branchContext->id()) ?? $branches->first();
        $subscription = $business?->currentSubscription;
        $featureNames = $subscription?->plan?->features->pluck('name')->map(fn (string $name): string => strtolower($name))->all() ?? [];

        return [
            'businesses' => $businesses,
            'branches' => $branches,
            'business' => $business,
            'branch' => $branch,
            'subscription' => $subscription,
            'navigation' => $this->navigation($user, $business, $branch, $featureNames, $this->subscriptions->allowsOperationalAccess($subscription)),
            'featureNames' => $featureNames,
            'unreadNotifications' => $business ? $this->unreadNotifications($business->id) : 0,
        ];
    }

    /**
     * @param  array<int, string>  $featureNames
     * @return array<int, NavigationItem>
     */
    private function navigation(User $user, ?Business $business, ?Branch $branch, array $featureNames, bool $operationalAccess): array
    {
        return collect($this->items())
            ->map(fn (array $item): NavigationItem => new NavigationItem(
                label: $item['label'],
                href: $item['href'],
                section: $item['section'],
                feature: $item['feature'],
                icon: $item['icon'],
                allowed: $this->featureAllowed($item['feature'], $featureNames, $operationalAccess, $business, $branch)
                    && $this->permissionAllowed($user, $business, $item['permission']),
            ))
            ->all();
    }

    /**
     * @return array<int, array{label: string, href: string, section: string, feature: string, icon: string, permission: string}>
     */
    private function items(): array
    {
        return [
            ['label' => 'Overview', 'href' => route('platform.dashboard'), 'section' => 'Business', 'feature' => 'base', 'icon' => 'layout-dashboard', 'permission' => 'view-dashboard'],
            ['label' => 'Remote Monitoring', 'href' => '#monitoring', 'section' => 'Business', 'feature' => 'remote dashboard', 'icon' => 'monitor-smartphone', 'permission' => 'view-dashboard'],
            ['label' => 'Sales', 'href' => route('platform.sales'), 'section' => 'Monitoring', 'feature' => 'sales', 'icon' => 'receipt', 'permission' => 'view-sales'],
            ['label' => 'Payments', 'href' => route('platform.payments'), 'section' => 'Monitoring', 'feature' => 'sales', 'icon' => 'credit-card', 'permission' => 'view-sales'],
            ['label' => 'Inventory', 'href' => route('platform.inventory'), 'section' => 'Monitoring', 'feature' => 'inventory', 'icon' => 'package-check', 'permission' => 'view-inventory'],
            ['label' => 'Expenses', 'href' => route('platform.expenses'), 'section' => 'Monitoring', 'feature' => 'expenses', 'icon' => 'wallet-cards', 'permission' => 'view-expenses'],
            ['label' => 'Customers', 'href' => route('platform.customer-debts'), 'section' => 'Monitoring', 'feature' => 'customer debts', 'icon' => 'users', 'permission' => 'view-customer-debts'],
            ['label' => 'Purchase & Supplier', 'href' => route('platform.purchases'), 'section' => 'Monitoring', 'feature' => 'base', 'icon' => 'truck', 'permission' => 'view-suppliers'],
            ['label' => 'Cashier & Staff', 'href' => route('platform.staff-performance'), 'section' => 'Monitoring', 'feature' => 'base', 'icon' => 'user-check', 'permission' => 'view-cashiers'],
            ['label' => 'Branch Performance', 'href' => route('platform.branch-monitoring'), 'section' => 'Monitoring', 'feature' => 'base', 'icon' => 'bar-chart-3', 'permission' => 'view-branches'],
            ['label' => 'Branches', 'href' => route('platform.branches'), 'section' => 'Control', 'feature' => 'base', 'icon' => 'building-2', 'permission' => 'view-branches'],
            ['label' => 'Backups & Restore', 'href' => route('platform.restore-points'), 'section' => 'Control', 'feature' => 'base', 'icon' => 'database', 'permission' => 'backups.view'],
            ['label' => 'Devices', 'href' => route('platform.devices'), 'section' => 'Control', 'feature' => 'base', 'icon' => 'monitor', 'permission' => 'view-devices'],
            ['label' => 'Synchronization', 'href' => route('platform.synchronization'), 'section' => 'Control', 'feature' => 'base', 'icon' => 'refresh-cw', 'permission' => 'view-synchronization'],
            ['label' => 'Alerts', 'href' => route('platform.notifications'), 'section' => 'Control', 'feature' => 'security alerts', 'icon' => 'bell-ring', 'permission' => 'view-alerts'],
            ['label' => 'Reports', 'href' => route('platform.reports'), 'section' => 'Insights', 'feature' => 'reports', 'icon' => 'bar-chart-3', 'permission' => 'export-reports'],
            ['label' => 'Services and Revenue Sharing', 'href' => route('platform.services'), 'section' => 'Insights', 'feature' => 'module:services', 'icon' => 'hand-coins', 'permission' => 'view-sales'],
            ['label' => 'Shareholders and Profit Distribution', 'href' => route('platform.shareholders'), 'section' => 'Insights', 'feature' => 'module:shareholders', 'icon' => 'landmark', 'permission' => 'export-reports'],
            ['label' => 'Monitoring Users', 'href' => route('platform.monitoring-users'), 'section' => 'Account', 'feature' => 'base', 'icon' => 'user-plus', 'permission' => 'manage-monitoring-users'],
            ['label' => 'Subscription', 'href' => route('platform.subscription'), 'section' => 'Account', 'feature' => 'base', 'icon' => 'badge-dollar-sign', 'permission' => 'manage-subscription'],
            ['label' => 'Settings', 'href' => route('platform.settings'), 'section' => 'Account', 'feature' => 'base', 'icon' => 'settings', 'permission' => 'manage-business-settings'],
            ['label' => 'Support', 'href' => route('platform.support'), 'section' => 'Account', 'feature' => 'base', 'icon' => 'life-buoy', 'permission' => 'view-dashboard'],
        ];
    }

    /** @param array<int, string> $featureNames */
    private function featureAllowed(string $feature, array $featureNames, bool $operationalAccess, ?Business $business, ?Branch $branch): bool
    {
        if ($feature === 'base') {
            return true;
        }

        if (! $operationalAccess) {
            return false;
        }

        if (str_starts_with($feature, 'module:')) {
            return $business !== null && $this->moduleAccess->available($business, BusinessModule::from(substr($feature, 7)), $branch);
        }

        return collect($featureNames)->contains(fn (string $name): bool => str_contains($name, $feature));
    }

    private function permissionAllowed(User $user, ?Business $business, string $permission): bool
    {
        return ! $business || $user->hasBusinessPermission($business, $permission);
    }

    private function unreadNotifications(int $businessId): int
    {
        return PlatformNotification::query()
            ->where('business_id', $businessId)
            ->whereNull('read_at')
            ->count();
    }
}