<?php

namespace App\Policies;

use App\Domain\Tenancy\Models\Business;
use App\Models\User;

class BusinessPolicy
{
    public function view(User $user, Business $business): bool
    {
        return $user->ownedBusinesses()->whereKey($business->id)->exists();
    }

    public function manage(User $user, Business $business): bool
    {
        return $this->view($user, $business);
    }
}
