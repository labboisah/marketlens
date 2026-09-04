<?php

namespace App\Http\Requests\Api\V1\Device;

use Illuminate\Foundation\Http\FormRequest;

class ApplicationUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_version' => ['nullable', 'string', 'max:80'],
            'application' => ['nullable', 'string', 'in:shop_app,client_app'],
            'platform' => ['nullable', 'string', 'max:80'],
            'architecture' => ['nullable', 'string', 'max:80'],
            'release_channel' => ['nullable', 'string', 'in:stable,beta,internal'],
        ];
    }

    public function credential(): string
    {
        return str($this->bearerToken() ?: '')->toString();
    }
}
