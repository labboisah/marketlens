<?php

namespace App\Domain\Synchronization\Services\EntityProcessors;

class BranchPerformanceSummaryProcessor extends SyncEntityProcessor
{
    public function entity(): string
    {
        return 'branch_performance_summary';
    }
}
