<?php

namespace App\Http\Requests\Api\V1\Device;

use Illuminate\Foundation\Http\FormRequest;

class ActivateDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'activation_code' => ['required', 'string', 'max:128'],
            'device_identity' => ['required', 'string', 'max:255'],
            'expected_device_type' => ['nullable', 'string', 'in:shop_app,client_app'],
            'installation_uuid' => ['nullable', 'uuid'],
            'app_version' => ['required', 'string', 'max:80'],
            'operating_system' => ['nullable', 'array'],
            'operating_system.name' => ['nullable', 'string', 'max:120'],
            'operating_system.version' => ['nullable', 'string', 'max:120'],
        ];
    }
}
