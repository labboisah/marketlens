<?php

namespace App\Http\Requests\Api\V1\Sync;

use Illuminate\Foundation\Http\FormRequest;

class StoreSyncBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'protocol_version' => ['required', 'string', 'in:1.0'],
            'batch_uuid' => ['required', 'uuid'],
            'business_uuid' => ['required', 'uuid'],
            'branch_uuid' => ['required', 'uuid'],
            'device_uuid' => ['required', 'uuid'],
            'created_at' => ['required', 'date'],
            'records' => ['required', 'array', 'min:1'],
            'records.*.entity' => ['required', 'string'],
            'records.*.record_uuid' => ['required', 'uuid'],
            'records.*.operation' => ['required', 'string'],
            'records.*.version' => ['required', 'integer', 'min:1'],
            'records.*.idempotency_key' => ['required', 'string', 'max:200'],
            'records.*.occurred_at' => ['required', 'date'],
            'records.*.payload' => ['required', 'array'],
        ];
    }
}
