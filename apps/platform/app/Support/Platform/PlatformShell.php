<?php

namespace App\Support\Platform;

use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Services\SubscriptionManagementService;
use App\Domain\Notifications\Models\PlatformNotification;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Domain\Tenancy\Services\BranchContext;
use App\Domain\Tenancy\Services\BusinessContext;
use App\Models\User;
use Illuminate\Support\Collection;

class PlatformShell
{
    public function __construct(
        private readonly BusinessContext $businessContext,
        private readonly BranchContext $branchContext,
        private readonly SubscriptionManagementService $subscriptions,
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

        $businesses = $user->ownedBusinesses()
            ->with(['branches', 'currentSubscription.plan.features'])
            ->orderBy('name')
            ->get();

        $business = $businesses->firstWhere('id', $this->businessContext->id()) ?? $businesses->first();
        $branches = $business?->branches->sortBy('name')->values() ?? collect();
        $branch = $branches->firstWhere('id', $this->branchContext->id()) ?? $branches->first();
        $subscription = $business?->currentSubscription;
        $featureNames = $subscription?->plan?->features->pluck('name')->map(fn (string $name): string => strtolower($name))->all() ?? [];

        return [
            'businesses' => $businesses,
            'branches' => $branches,
            'business' => $business,
            'branch' => $branch,
            'subscription' => $subscription,
            'navigation' => $this->navigation($featureNames, $this->subscriptions->allowsOperationalAccess($subscription)),
            'featureNames' => $featureNames,
            'unreadNotifications' => $business ? $this->unreadNotifications($business->id) : 0,
        ];
    }

    /**
     * @param  array<int, string>  $featureNames
     * @return array<int, NavigationItem>
     */
    private function navigation(array $featureNames, bool $operationalAccess): array
    {
        return collect($this->items())
            ->map(fn (array $item): NavigationItem => new NavigationItem(
                label: $item['label'],
                href: $item['href'],
                section: $item['section'],
                feature: $item['feature'],
                icon: $item['icon'],
                allowed: $this->featureAllowed($item['feature'], $featureNames, $operationalAccess),
            ))
            ->all();
    }

    /**
     * @return array<int, array{label: string, href: string, section: string, feature: string, icon: string}>
     */
    private function items(): array
    {
        return [
            ['label' => 'Overview', 'href' => route('platform.dashboard'), 'section' => 'Business', 'feature' => 'base', 'icon' => 'layout-dashboard'],
            ['label' => 'Remote Monitoring', 'href' => '#monitoring', 'section' => 'Business', 'feature' => 'remote dashboard', 'icon' => 'monitor-smartphone'],
            ['label' => 'Sales', 'href' => route('platform.sales'), 'section' => 'Monitoring', 'feature' => 'sales', 'icon' => 'receipt'],
            ['label' => 'Payments', 'href' => route('platform.payments'), 'section' => 'Monitoring', 'feature' => 'sales', 'icon' => 'credit-card'],
            ['label' => 'Inventory', 'href' => route('platform.inventory'), 'section' => 'Monitoring', 'feature' => 'inventory', 'icon' => 'package-check'],
            ['label' => 'Expenses', 'href' => route('platform.expenses'), 'section' => 'Monitoring', 'feature' => 'expenses', 'icon' => 'wallet-cards'],
            ['label' => 'Customers', 'href' => route('platform.customer-debts'), 'section' => 'Monitoring', 'feature' => 'customer debts', 'icon' => 'users'],
            ['label' => 'Purchase & Supplier', 'href' => route('platform.purchases'), 'section' => 'Monitoring', 'feature' => 'base', 'icon' => 'truck'],
            ['label' => 'Cashier & Staff', 'href' => route('platform.staff-performance'), 'section' => 'Monitoring', 'feature' => 'base', 'icon' => 'user-check'],
            ['label' => 'Branch Performance', 'href' => route('platform.branch-monitoring'), 'section' => 'Monitoring', 'feature' => 'base', 'icon' => 'bar-chart-3'],
            ['label' => 'Branches', 'href' => route('platform.branches'), 'section' => 'Control', 'feature' => 'base', 'icon' => 'building-2'],
            ['label' => 'Devices', 'href' => route('platform.devices'), 'section' => 'Control', 'feature' => 'base', 'icon' => 'monitor'],
            ['label' => 'Synchronization', 'href' => route('platform.synchronization'), 'section' => 'Control', 'feature' => 'base', 'icon' => 'refresh-cw'],
            ['label' => 'Alerts', 'href' => route('platform.notifications'), 'section' => 'Control', 'feature' => 'security alerts', 'icon' => 'bell-ring'],
            ['label' => 'Reports', 'href' => route('platform.reports'), 'section' => 'Insights', 'feature' => 'reports', 'icon' => 'bar-chart-3'],
            ['label' => 'Subscription', 'href' => route('platform.subscription'), 'section' => 'Account', 'feature' => 'base', 'icon' => 'badge-dollar-sign'],
            ['label' => 'Settings', 'href' => route('platform.settings'), 'section' => 'Account', 'feature' => 'base', 'icon' => 'settings'],
            ['label' => 'Support', 'href' => '#support', 'section' => 'Account', 'feature' => 'base', 'icon' => 'life-buoy'],
        ];
    }

    /**
     * @param  array<int, string>  $featureNames
     */
    private function featureAllowed(string $feature, array $featureNames, bool $operationalAccess): bool
    {
        if ($feature === 'base') {
            return true;
        }

        if (! $operationalAccess) {
            return false;
        }

        return collect($featureNames)->contains(fn (string $name): bool => str_contains($name, $feature));
    }

    private function unreadNotifications(int $businessId): int
    {
        return PlatformNotification::query()
            ->where('business_id', $businessId)
            ->whereNull('read_at')
            ->count();
    }
}





