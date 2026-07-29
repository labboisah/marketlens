<?php

namespace App\Policies;

use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Models\User;

class BranchPolicy
{
    public function viewAny(User $user, Business $business): bool
    {
        return $user->ownedBusinesses()
            ->whereKey($business->id)
            ->exists();
    }

    public function create(User $user, Business $business): bool
    {
        return $this->viewAny($user, $business);
    }

    public function view(User $user, Branch $branch): bool
    {
        return $user->ownedBusinesses()
            ->whereKey($branch->business_id)
            ->exists();
    }

    public function manage(User $user, Branch $branch): bool
    {
        return $this->view($user, $branch);
    }
}
