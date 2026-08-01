<?php

namespace App\Http\Middleware;

use App\Support\Observability\SensitiveDataSanitizer;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AttachTraceId
{
    public function __construct(private readonly SensitiveDataSanitizer $sanitizer) {}

    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);
        $traceId = $this->traceId($request);

        $request->attributes->set('trace_id', $traceId);
        Log::withContext(['trace_id' => $traceId]);

        $response = $next($request);
        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        $response->headers->set((string) config('observability.trace_header', 'X-Trace-Id'), $traceId);
        $this->recordLatency($request, $durationMs);
        $this->logRequest($request, $response, $durationMs, $traceId);

        return $response;
    }

    private function traceId(Request $request): string
    {
        $header = (string) $request->headers->get((string) config('observability.trace_header', 'X-Trace-Id'), '');

        return preg_match('/^[A-Za-z0-9._:-]{8,128}$/', $header) === 1 ? $header : (string) Str::uuid();
    }

    private function recordLatency(Request $request, int $durationMs): void
    {
        if (! $request->is('api/*')) {
            return;
        }

        $key = (string) config('observability.api_latency_key', 'observability:api_latency');
        $current = Cache::get($key, ['count' => 0, 'total_ms' => 0, 'max_ms' => 0]);

        Cache::put($key, [
            'count' => ((int) ($current['count'] ?? 0)) + 1,
            'total_ms' => ((int) ($current['total_ms'] ?? 0)) + $durationMs,
            'max_ms' => max((int) ($current['max_ms'] ?? 0), $durationMs),
            'last_ms' => $durationMs,
            'last_route' => $request->route()?->getName(),
            'last_seen_at' => now()->toIso8601String(),
        ], now()->addMinutes(30));
    }

    private function logRequest(Request $request, Response $response, int $durationMs, string $traceId): void
    {
        $context = $this->sanitizer->sanitize([
            'trace_id' => $traceId,
            'method' => $request->method(),
            'path' => '/'.ltrim($request->path(), '/'),
            'route' => $request->route()?->getName(),
            'status' => $response->getStatusCode(),
            'duration_ms' => $durationMs,
            'user_id' => $request->user()?->id,
            'headers' => [
                'user_agent' => $request->userAgent(),
                'authorization' => $request->headers->get('authorization'),
            ],
        ]);

        Log::info('platform.request.completed', $context);

        if (in_array($response->getStatusCode(), [401, 403, 429], true)) {
            Log::channel('security')->warning('platform.security.request_blocked', $context);
        }
    }
}
