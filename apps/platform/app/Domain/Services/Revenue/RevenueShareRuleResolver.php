<?php

namespace App\Domain\Services\Revenue;

use App\Domain\Services\Models\ServiceRevenueShareRule;

class RevenueShareRuleResolver
{
    /**
     * Resolve the applicable rule for a given service, branch and business.
     * Returns the most specific active rule according to priority.
     */
    public function resolve(?int $serviceId, ?int $branchId, int $businessId)
    {
        // Skeleton implementation:
        // 1. Look for service+branch specific rule
        // 2. Service specific business rule
        // 3. Branch default rule
        // 4. Business default rule

        return null;
    }

    /**
     * Preview allocation for a total amount given rule components
     */
    public function previewAllocation($rule, float $total): array
    {
        return [];
    }
}
