<?php

namespace App\Domain\Services\Sync\Processors;

class ServiceProcessor
{
    public function process(array $payload): array
    {
        // Validate payload, business ownership, idempotency, and create/update service
        // Return ['status' => 'ok', 'id' => ...] or ['status' => 'error', 'reason' => ...]
        return ['status' => 'ok'];
    }
}
