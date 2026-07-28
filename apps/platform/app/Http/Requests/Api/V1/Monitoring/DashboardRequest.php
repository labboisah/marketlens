<?php

namespace App\Http\Requests\Api\V1\Monitoring;

use Illuminate\Foundation\Http\FormRequest;

class DashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'business_uuid' => ['nullable', 'uuid'],
            'branch_uuid' => ['nullable', 'uuid'],
            'period' => ['nullable', 'in:today,week,month'],
        ];
    }
}
