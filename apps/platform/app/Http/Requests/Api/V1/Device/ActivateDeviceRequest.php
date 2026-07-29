<?php

namespace App\Http\Requests\Api\V1\Device;

use App\Domain\Tenancy\Models\Branch;
use Illuminate\Foundation\Http\FormRequest;

class ActivateDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->filled('branch_uuid')) {
            return true;
        }

        $branch = Branch::query()
            ->where('uuid', $this->string('branch_uuid')->toString())
            ->first();

        return ! $branch || $branch->canActivateShopDevices();
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
