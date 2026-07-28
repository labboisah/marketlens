<?php

namespace App\Policies;

use App\Domain\Tenancy\Models\Branch;
use App\Models\User;

class BranchPolicy
{
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
