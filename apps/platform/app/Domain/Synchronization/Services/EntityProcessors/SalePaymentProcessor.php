<?php

namespace App\Domain\Synchronization\Services\EntityProcessors;

class SalePaymentProcessor extends SyncEntityProcessor
{
    public function entity(): string
    {
        return 'sale_payment';
    }
}
