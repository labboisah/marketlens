<?php

namespace App\Domain\Synchronization\Services\EntityProcessors;

class SecurityAlertProcessor extends SyncEntityProcessor
{
    public function entity(): string
    {
        return 'security_alert';
    }
}
