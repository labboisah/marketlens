<?php

namespace App\Http\Controllers\Health;

use App\Domain\Observability\Services\PlatformHealthService;
use Illuminate\Http\JsonResponse;

class ReadinessController
{
    public function __invoke(PlatformHealthService $health): JsonResponse
    {
        $payload = $health->publicReadiness();
        $statusCode = $payload['status'] === 'unhealthy' ? 503 : 200;

        return response()->json($payload, $statusCode);
    }
}
