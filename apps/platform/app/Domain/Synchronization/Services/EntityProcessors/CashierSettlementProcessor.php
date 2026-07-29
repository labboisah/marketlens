<?php

namespace App\Domain\Synchronization\Services\EntityProcessors;

class CashierSettlementProcessor extends SyncEntityProcessor
{
    public function entity(): string
    {
        return 'cashier_settlement';
    }
}
