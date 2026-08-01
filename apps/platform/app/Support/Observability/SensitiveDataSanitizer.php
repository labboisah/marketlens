<?php

namespace App\Support\Observability;

class SensitiveDataSanitizer
{
    /** @var array<int, string> */
    private array $sensitiveKeys = [
        'authorization',
        'cookie',
        'set-cookie',
        'x-api-key',
        'token',
        'access_token',
        'refresh_token',
        'password',
        'secret',
        'credential',
        'credential_hash',
        'device_credential',
        'activation_code',
        'private_key',
        'database',
        'username',
        'dsn',
    ];

    /** @param array<string, mixed> $data */
    public function sanitize(array $data): array
    {
        return collect($data)
            ->mapWithKeys(fn ($value, string|int $key): array => [
                $key => $this->isSensitiveKey((string) $key)
                    ? '[redacted]'
                    : (is_array($value) ? $this->sanitize($value) : $this->safeScalar($value)),
            ])
            ->all();
    }

    private function isSensitiveKey(string $key): bool
    {
        $key = strtolower($key);

        return collect($this->sensitiveKeys)->contains(fn (string $sensitive): bool => str_contains($key, $sensitive));
    }

    private function safeScalar(mixed $value): mixed
    {
        if (is_scalar($value) || $value === null) {
            return $value;
        }

        return '[unserializable]';
    }
}
