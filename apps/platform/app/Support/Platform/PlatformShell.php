<?php

namespace App\Support\Platform;

use App\Domain\Billing\Models\Subscription;
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
            'navigation' => $this->navigation($featureNames),
            'featureNames' => $featureNames,
        ];
    }

    /**
     * @param  array<int, string>  $featureNames
     * @return array<int, NavigationItem>
     */
    private function navigation(array $featureNames): array
    {
        return collect($this->items())
            ->map(fn (array $item): NavigationItem => new NavigationItem(
                label: $item['label'],
                href: $item['href'],
                section: $item['section'],
                feature: $item['feature'],
                allowed: $this->featureAllowed($item['feature'], $featureNames),
            ))
            ->all();
    }

    /**
     * @return array<int, array{label: string, href: string, section: string, feature: string}>
     */
    private function items(): array
    {
        return [
            ['label' => 'Overview', 'href' => route('platform.dashboard'), 'section' => 'Business', 'feature' => 'base'],
            ['label' => 'Monitoring', 'href' => '#monitoring', 'section' => 'Business', 'feature' => 'remote dashboard'],
            ['label' => 'Sales', 'href' => '#sales', 'section' => 'Operations', 'feature' => 'sales'],
            ['label' => 'Payments', 'href' => '#payments', 'section' => 'Operations', 'feature' => 'sales'],
            ['label' => 'Inventory', 'href' => '#inventory', 'section' => 'Operations', 'feature' => 'inventory'],
            ['label' => 'Expenses', 'href' => '#expenses', 'section' => 'Operations', 'feature' => 'expenses'],
            ['label' => 'Customers', 'href' => '#customers', 'section' => 'Operations', 'feature' => 'customer debts'],
            ['label' => 'Suppliers', 'href' => '#suppliers', 'section' => 'Operations', 'feature' => 'purchases and suppliers'],
            ['label' => 'Cashiers', 'href' => '#cashiers', 'section' => 'Operations', 'feature' => 'cashier shifts'],
            ['label' => 'Branches', 'href' => '#branches', 'section' => 'Control', 'feature' => 'multiple branches'],
            ['label' => 'Devices', 'href' => '#devices', 'section' => 'Control', 'feature' => 'devices'],
            ['label' => 'Synchronization', 'href' => '#synchronization', 'section' => 'Control', 'feature' => 'base'],
            ['label' => 'Alerts', 'href' => '#alerts', 'section' => 'Control', 'feature' => 'security alerts'],
            ['label' => 'Reports', 'href' => '#reports', 'section' => 'Insights', 'feature' => 'reports'],
            ['label' => 'Subscription', 'href' => '#subscription', 'section' => 'Account', 'feature' => 'base'],
            ['label' => 'Settings', 'href' => '#settings', 'section' => 'Account', 'feature' => 'base'],
            ['label' => 'Support', 'href' => '#support', 'section' => 'Account', 'feature' => 'base'],
        ];
    }

    /**
     * @param  array<int, string>  $featureNames
     */
    private function featureAllowed(string $feature, array $featureNames): bool
    {
        if ($feature === 'base') {
            return true;
        }

        return collect($featureNames)->contains(fn (string $name): bool => str_contains($name, $feature));
    }
}
