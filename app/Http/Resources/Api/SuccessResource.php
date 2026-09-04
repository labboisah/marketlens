<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SuccessResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $payload = is_array($this->resource) ? $this->resource : ['data' => $this->resource];
        $message = $payload['message'] ?? 'Operation completed';
        $meta = $payload['meta'] ?? [];

        unset($payload['message'], $payload['meta']);

        return [
            'success' => true,
            'message' => $message,
            'data' => $payload,
            'meta' => $meta,
        ];
    }
}
