<?php

namespace App\Domain\Synchronization\Services\EntityProcessors;

class StockAlertProcessor extends SyncEntityProcessor
{
    public function entity(): string
    {
        return 'stock_alert';
    }
}
