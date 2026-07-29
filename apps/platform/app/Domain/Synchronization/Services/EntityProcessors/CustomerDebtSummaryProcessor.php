<?php

namespace App\Domain\Synchronization\Services\EntityProcessors;

class CustomerDebtSummaryProcessor extends SyncEntityProcessor
{
    public function entity(): string
    {
        return 'customer_debt_summary';
    }
}
