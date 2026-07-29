<?php

namespace App\Domain\Synchronization\Services\EntityProcessors;

class SaleItemProcessor extends SyncEntityProcessor
{
    public function entity(): string
    {
        return 'sale_item';
    }
}
