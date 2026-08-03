<?php

namespace App\Domain\Services\Sync\Processors;

class ServiceOrderProcessor
{
    public function process(array $payload): array
    {
        // This processor will validate device, ownership, module availability,
        // enforce idempotency, prevent edits of completed transactions and
        // write replicated_service_* tables.
        return ['status' => 'ok'];
    }
}
