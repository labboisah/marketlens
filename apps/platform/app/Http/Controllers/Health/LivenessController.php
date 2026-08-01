<?php

namespace App\Http\Controllers\Health;

use App\Domain\Observability\Services\PlatformHealthService;
use Illuminate\Http\JsonResponse;

class LivenessController
{
    public function __invoke(PlatformHealthService $health): JsonResponse
    {
        return response()->json($health->liveness());
    }
}
