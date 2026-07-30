<?php

namespace App\Policies;

use App\Domain\Tenancy\Models\Business;
use App\Models\User;

class BusinessPolicy
{
    public function view(User $user, Business $business): bool
    {
        return $user->canAccessBusiness($business);
    }

    public function manage(User $user, Business $business): bool
    {
        return $user->isBusinessOwner($business) || $user->hasBusinessPermission($business, 'manage-monitoring-users');
    }

    public function manageSettings(User $user, Business $business): bool
    {
        return $user->isBusinessOwner($business) || $user->hasBusinessPermission($business, 'manage-business-settings');
    }

    public function manageSubscription(User $user, Business $business): bool
    {
        return $user->isBusinessOwner($business) || $user->hasBusinessPermission($business, 'manage-subscription');
    }

    public function manageMonitoringUsers(User $user, Business $business): bool
    {
        return $user->isBusinessOwner($business) || $user->hasBusinessPermission($business, 'manage-monitoring-users');
    }

    public function viewAudit(User $user, Business $business): bool
    {
        return $this->view($user, $business);
    }

    public function exportData(User $user, Business $business): bool
    {
        return $this->manageSettings($user, $business);
    }

    public function requestClosure(User $user, Business $business): bool
    {
        return $user->isBusinessOwner($business);
    }
}
