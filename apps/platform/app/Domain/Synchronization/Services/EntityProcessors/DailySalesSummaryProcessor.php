<?php

namespace App\Domain\Synchronization\Services\EntityProcessors;

class DailySalesSummaryProcessor extends SyncEntityProcessor
{
    public function entity(): string
    {
        return 'daily_sales_summary';
    }
}
