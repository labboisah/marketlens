<?php

namespace App\Http\Requests\Api\V1\Device;

use Illuminate\Foundation\Http\FormRequest;

class DeviceHeartbeatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'app_version' => ['nullable', 'string', 'max:80'],
            'last_synchronized_at' => ['nullable', 'date'],
            'operating_system' => ['nullable', 'array'],
            'operating_system.name' => ['nullable', 'string', 'max:120'],
            'operating_system.version' => ['nullable', 'string', 'max:120'],
            'synchronization_errors' => ['nullable', 'array'],
        ];
    }

    public function credential(): string
    {
        return str($this->bearerToken() ?: '')->toString();
    }
}
