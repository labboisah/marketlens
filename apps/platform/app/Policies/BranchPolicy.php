<?php

namespace App\Policies;

use App\Domain\Tenancy\Models\Branch;
use App\Models\User;

class BranchPolicy
{
    public function view(User $user, Branch $branch): bool
    {
        return true;
    }

    public function manage(User $user, Branch $branch): bool
    {
        return true;
    }
}
