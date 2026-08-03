<?php

namespace App\Domain\Shareholders\Policies;

use App\Models\User;

class ShareholdersPolicy
{
    public function view(User $user): bool
    {
        return $user->can('view_shareholders');
    }

    public function manage(User $user): bool
    {
        return $user->can('manage_shareholders');
    }
}
