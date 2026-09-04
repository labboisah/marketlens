<?php

namespace App\Policies;

use App\Domain\Devices\Models\Device;
use App\Models\User;

class DevicePolicy
{
    public function view(User $user, Device $device): bool
    {
        return true;
    }

    public function manage(User $user, Device $device): bool
    {
        return true;
    }
}
