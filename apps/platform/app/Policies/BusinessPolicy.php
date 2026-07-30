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

    public function manageSettings(User $user, Business $business): bool
    {
        return $this->manage($user, $business);
    }

    public function viewAudit(User $user, Business $business): bool
    {
        return $this->view($user, $business);
    }

    public function exportData(User $user, Business $business): bool
    {
        return $this->manage($user, $business);
    }

    public function requestClosure(User $user, Business $business): bool
    {
        return $this->manage($user, $business);
    }
}
