<?php

namespace App\Http\Requests\Api\V1\Device;

use Illuminate\Foundation\Http\FormRequest;

class DeviceStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }

    public function credential(): string
    {
        return str($this->bearerToken() ?: '')->toString();
    }
}
