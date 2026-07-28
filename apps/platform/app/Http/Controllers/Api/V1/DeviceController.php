<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Device\ActivateDeviceRequest;
use App\Http\Resources\Api\SuccessResource;

class DeviceController extends Controller
{
    public function activate(ActivateDeviceRequest $request): SuccessResource
    {
        return new SuccessResource([
            'message' => 'Device activation endpoint placeholder.',
            'device_name' => $request->string('device_name')->toString(),
        ]);
    }

    public function versions(): SuccessResource
    {
        return new SuccessResource(['versions' => []]);
    }
}
