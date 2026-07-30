<?php

namespace App\Policies;

use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Models\User;

class BranchPolicy
{
    public function viewAny(User $user, Business $business): bool
    {
        return $user->canAccessBusiness($business) && $user->hasBusinessPermission($business, 'view-branches');
    }

    public function create(User $user, Business $business): bool
    {
        return $user->isBusinessOwner($business);
    }

    public function view(User $user, Branch $branch): bool
    {
        return $user->canAccessBusiness($branch->business_id)
            && $user->hasBusinessPermission($branch->business_id, 'view-branches')
            && $user->canAccessBranch($branch->business_id, $branch->id);
    }

    public function manage(User $user, Branch $branch): bool
    {
        return $user->isBusinessOwner($branch->business_id);
    }
}
