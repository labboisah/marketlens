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
            'device_name' => ['required', 'string', 'max:120'],
            'branch_uuid' => ['required', 'uuid'],
        ];
    }
}
