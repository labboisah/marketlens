<?php

namespace App\Domain\Performance\Services;

use Closure;
use Illuminate\Support\Facades\Cache;

class MonitoringPerformanceService
{
    public function remember(int $businessId, string $namespace, array $dimensions, Closure $callback, ?int $ttlSeconds = null): mixed
    {
        return Cache::remember(
            $this->cacheKey($businessId, $namespace, $dimensions),
            now()->addSeconds($ttlSeconds ?? $this->ttlSeconds($namespace)),
            $callback,
        );
    }

    public function invalidateBusiness(int $businessId): void
    {
        Cache::increment($this->versionKey($businessId));
    }

    public function cacheKey(int $businessId, string $namespace, array $dimensions): string
    {
        ksort($dimensions);

        return implode(':', [
            'monitoring',
            $namespace,
            'business',
            $businessId,
            'v'.$this->version($businessId),
            sha1(json_encode($dimensions, JSON_THROW_ON_ERROR)),
        ]);
    }

    public function version(int $businessId): int
    {
        return (int) (Cache::get($this->versionKey($businessId)) ?: 1);
    }

    private function versionKey(int $businessId): string
    {
        return 'monitoring:business:'.$businessId.':version';
    }

    private function ttlSeconds(string $namespace): int
    {
        return (int) config('performance.monitoring.cache_ttl.'.$namespace, config('performance.monitoring.cache_ttl.default', 300));
    }
}
