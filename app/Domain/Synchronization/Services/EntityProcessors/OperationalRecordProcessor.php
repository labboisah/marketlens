<?php

namespace App\Domain\Synchronization\Services\EntityProcessors;

use App\Domain\Synchronization\Data\SyncRecordResult;
use App\Domain\Synchronization\Models\SyncBatch;

class OperationalRecordProcessor extends SyncEntityProcessor
{
    private const MAX_PAYLOAD_BYTES = 1048576;

    private const SENSITIVE_KEYS = [
        'password',
        'password_hash',
        'pin',
        'access_token',
        'refresh_token',
        'credential',
        'credential_hash',
        'secret',
    ];

    public function entity(): string
    {
        return 'operational_record';
    }

    /**
     * @param  array<string, mixed>  $record
     */
    public function process(SyncBatch $batch, array $record): SyncRecordResult
    {
        $payload = $record['payload'] ?? null;

        if (! is_array($payload) || $payload === []) {
            return new SyncRecordResult('rejected', $record, 'VALIDATION_FAILED', 'Operational record payload must be a non-empty object.', false);
        }

        if (strlen(json_encode($payload, JSON_THROW_ON_ERROR)) > self::MAX_PAYLOAD_BYTES) {
            return new SyncRecordResult('rejected', $record, 'PAYLOAD_TOO_LARGE', 'Operational record payload exceeds 1 MiB.', false);
        }

        if ($this->containsSensitiveKey($payload)) {
            return new SyncRecordResult('rejected', $record, 'SENSITIVE_FIELD_REJECTED', 'Operational record payload contains a prohibited sensitive field.', false);
        }

        if (isset($payload['uuid']) && $payload['uuid'] !== $record['record_uuid']) {
            return new SyncRecordResult('rejected', $record, 'UUID_MISMATCH', 'Payload UUID must match record_uuid.', false);
        }

        return parent::process($batch, $record);
    }

    /** @param array<array-key, mixed> $payload */
    private function containsSensitiveKey(array $payload): bool
    {
        foreach ($payload as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::SENSITIVE_KEYS, true)) {
                return true;
            }

            if (is_array($value) && $this->containsSensitiveKey($value)) {
                return true;
            }
        }

        return false;
    }
}
