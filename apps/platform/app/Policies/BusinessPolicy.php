<?php

namespace App\Policies;

use App\Domain\Tenancy\Models\Business;
use App\Models\User;

class BusinessPolicy
{
    public function view(User $user, Business $business): bool
    {
        return true;
    }

    public function manage(User $user, Business $business): bool
    {
        return true;
    }
}
