<?php

namespace App\Domain\Synchronization\Services\EntityProcessors;

class CashierShiftProcessor extends SyncEntityProcessor
{
    public function entity(): string
    {
        return 'cashier_shift';
    }
}
