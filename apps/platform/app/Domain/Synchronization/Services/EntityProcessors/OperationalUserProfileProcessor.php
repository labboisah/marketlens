<?php

namespace App\Domain\Synchronization\Services\EntityProcessors;

class OperationalUserProfileProcessor extends SyncEntityProcessor
{
    public function entity(): string
    {
        return 'operational_user_profile';
    }
}
