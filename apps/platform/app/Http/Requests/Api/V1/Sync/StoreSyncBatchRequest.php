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
            'batch_uuid' => ['required', 'uuid'],
            'business_uuid' => ['required', 'uuid'],
            'branch_uuid' => ['required', 'uuid'],
            'device_uuid' => ['required', 'uuid'],
            'records' => ['present', 'array'],
        ];
    }
}
