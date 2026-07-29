<?php

namespace App\Domain\Synchronization\Services\EntityProcessors;

class SaleProcessor extends SyncEntityProcessor
{
    public function entity(): string
    {
        return 'sale';
    }
}
