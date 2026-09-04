<?php

namespace App\Enums;

enum ShareholderDistributionBasis: string
{
    case OwnershipPercentage = 'ownership_percentage';
    case EligibleShares = 'eligible_shares';
    case ShareClassRights = 'share_class_rights';
    case FixedApprovedAllocation = 'fixed_approved_allocation';
}
