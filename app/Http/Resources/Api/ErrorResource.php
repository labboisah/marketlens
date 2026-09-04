<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class ErrorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $payload = is_array($this->resource) ? $this->resource : [];

        return [
            'success' => false,
            'message' => $payload['message'] ?? 'The request could not be completed.',
            'code' => $payload['code'] ?? 'SERVER_ERROR',
            'errors' => $payload['errors'] ?? [],
            'trace_id' => $payload['trace_id'] ?? (string) Str::uuid(),
        ];
    }
}
