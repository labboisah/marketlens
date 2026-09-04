<?php

namespace Database\Seeders;

use App\Domain\Billing\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->plans() as $planData) {
            $features = $planData['features'];
            unset($planData['features']);

            $plan = Plan::query()->updateOrCreate(
                [
                    'slug' => $planData['slug'],
                    'billing_interval' => $planData['billing_interval'],
                ],
                $planData,
            );

            foreach ($features as $index => $feature) {
                $plan->features()->updateOrCreate(
                    ['name' => $feature['name']],
                    [
                        'tooltip' => $feature['tooltip'],
                        'is_highlighted' => $feature['is_highlighted'] ?? false,
                        'sort_order' => $index + 1,
                    ],
                );
            }
        }
    }

    private function plans(): array
    {
        return [
            ...$this->planRows(
                name: 'Basic',
                slug: 'basic',
                description: 'Suitable for a small business.',
                monthlyPriceMinor: 1500000,
                annualPriceMinor: 15000000,
                sortOrder: 1,
                features: [
                    ['name' => 'One business', 'tooltip' => 'One registered business account is included.', 'is_highlighted' => true],
                    ['name' => 'One branch', 'tooltip' => 'Designed for a single operating location.', 'is_highlighted' => true],
                    ['name' => 'Limited Client App devices', 'tooltip' => 'A small number of cashier or staff computers can connect to the Business Server.'],
                    ['name' => 'Sales', 'tooltip' => 'Record daily sales locally in the Business Server.'],
                    ['name' => 'Products', 'tooltip' => 'Maintain product, category, unit, barcode, and pricing foundations.'],
                    ['name' => 'Inventory', 'tooltip' => 'Track stock through inventory movement records.'],
                    ['name' => 'Expenses', 'tooltip' => 'Capture shop expenses for local and cloud summaries.'],
                    ['name' => 'Customer debts', 'tooltip' => 'Track customer debt through ledger-based balances.'],
                    ['name' => 'Local backup', 'tooltip' => 'Prepare local backup and recovery workflows.'],
                    ['name' => 'Remote dashboard', 'tooltip' => 'Owners can view synchronized Platform summaries.'],
                    ['name' => 'Standard synchronization interval', 'tooltip' => 'Business data synchronizes on the standard Platform interval.'],
                ],
            ),
            ...$this->planRows(
                name: 'Standard',
                slug: 'standard',
                description: 'Suitable for supermarkets and growing businesses.',
                monthlyPriceMinor: 3500000,
                annualPriceMinor: 35000000,
                sortOrder: 2,
                features: [
                    ['name' => 'More Client App devices', 'tooltip' => 'Supports more cashier and staff computers than Basic.', 'is_highlighted' => true],
                    ['name' => 'Purchases and suppliers', 'tooltip' => 'Track purchases and supplier account activity.', 'is_highlighted' => true],
                    ['name' => 'Cashier shifts', 'tooltip' => 'Open, close, and review cashier shifts.'],
                    ['name' => 'Cashier settlement', 'tooltip' => 'Compare expected and counted payment totals.'],
                    ['name' => 'Advanced stock reports', 'tooltip' => 'Prepare deeper inventory summaries and stock movement reports.'],
                    ['name' => 'WhatsApp-ready reporting foundation', 'tooltip' => 'Reporting foundations are prepared for WhatsApp delivery workflows later.'],
                    ['name' => 'Faster synchronization', 'tooltip' => 'More frequent sync scheduling than Basic.'],
                    ['name' => 'More monitoring users', 'tooltip' => 'Allow more owner or manager monitoring access.'],
                ],
            ),
            ...$this->planRows(
                name: 'Professional',
                slug: 'professional',
                description: 'Suitable for wholesalers and multi-branch businesses.',
                monthlyPriceMinor: 7500000,
                annualPriceMinor: 75000000,
                sortOrder: 3,
                features: [
                    ['name' => 'Multiple branches', 'tooltip' => 'Designed for businesses operating more than one branch.', 'is_highlighted' => true],
                    ['name' => 'Consolidated reports', 'tooltip' => 'View business-wide summaries across branches.', 'is_highlighted' => true],
                    ['name' => 'Stock transfers', 'tooltip' => 'Move stock between branches or warehouses through auditable records.'],
                    ['name' => 'Branch comparison', 'tooltip' => 'Compare sales, payments, expenses, and stock signals across branches.'],
                    ['name' => 'Security alerts', 'tooltip' => 'Highlight suspicious or important operational activity.'],
                    ['name' => 'Advanced audit reports', 'tooltip' => 'Review deeper audit activity for important records and users.'],
                    ['name' => 'Priority support', 'tooltip' => 'Prioritized product support for larger operations.'],
                    ['name' => 'More monitoring users and devices', 'tooltip' => 'Higher limits for Monitoring App users and Shop or Client App devices.'],
                    ['key' => 'module:services', 'name' => 'Services and Revenue Sharing', 'tooltip' => 'Enable service revenue-sharing settings and branch-level service allocation controls.', 'is_highlighted' => true],
                    ['key' => 'module:shareholders', 'name' => 'Shareholders and Profit Distribution', 'tooltip' => 'Enable shareholder ownership, resolution, and profit-distribution settings.', 'is_highlighted' => true],
                ],
            ),
        ];
    }

    private function planRows(
        string $name,
        string $slug,
        string $description,
        int $monthlyPriceMinor,
        int $annualPriceMinor,
        int $sortOrder,
        array $features,
    ): array {
        return [
            [
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'billing_interval' => 'monthly',
                'price_minor' => $monthlyPriceMinor / 100,
                'currency' => 'NGN',
                'trial_days' => 14,
                'sort_order' => $sortOrder,
                'is_active' => true,
                'features' => $features,
            ],
            [
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'billing_interval' => 'annual',
                'price_minor' => $annualPriceMinor / 100,
                'currency' => 'NGN',
                'trial_days' => 14,
                'sort_order' => $sortOrder,
                'is_active' => true,
                'features' => $features,
            ],
        ];
    }
}
