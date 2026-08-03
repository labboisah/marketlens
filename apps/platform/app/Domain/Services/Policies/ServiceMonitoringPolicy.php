<?php

namespace App\Domain\Services\Policies;

use App\Models\User;

class ServiceMonitoringPolicy
{
    public function view(User $user): bool
    {
        // Skeleton policy: ensure user has platform monitoring module enabled
        // Replace with real module check / role checks
        return $user->can('view-monitoring');
    }
}
