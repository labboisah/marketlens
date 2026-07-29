<?php

namespace App\Domain\Synchronization\Services\EntityProcessors;

class StockSummaryProcessor extends SyncEntityProcessor
{
    public function entity(): string
    {
        return 'stock_summary';
    }
}
