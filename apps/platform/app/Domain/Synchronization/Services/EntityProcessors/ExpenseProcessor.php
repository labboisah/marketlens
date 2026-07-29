<?php

namespace App\Domain\Synchronization\Services\EntityProcessors;

class ExpenseProcessor extends SyncEntityProcessor
{
    public function entity(): string
    {
        return 'expense';
    }
}
