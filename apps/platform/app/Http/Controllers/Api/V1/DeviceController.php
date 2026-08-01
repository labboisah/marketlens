<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Devices\Services\ShopAppActivationService;
use App\Domain\Versions\Services\ApplicationVersionService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Device\ActivateDeviceRequest;
use App\Http\Requests\Api\V1\Device\ApplicationUpdateRequest;
use App\Http\Requests\Api\V1\Device\DeviceHeartbeatRequest;
use App\Http\Requests\Api\V1\Device\DeviceStatusRequest;
use App\Http\Resources\Api\SuccessResource;

class DeviceController extends Controller
{
    public function activate(ActivateDeviceRequest $request, ShopAppActivationService $activationService): SuccessResource
    {
        return new SuccessResource([
            'message' => 'Device activated.',
            ...$activationService->activate($request->validated()),
        ]);
    }

    public function status(DeviceStatusRequest $request, ShopAppActivationService $activationService): SuccessResource
    {
        return new SuccessResource($activationService->status($request->credential()));
    }

    public function heartbeat(DeviceHeartbeatRequest $request, ShopAppActivationService $activationService): SuccessResource
    {
        return new SuccessResource([
            'message' => 'Device heartbeat received.',
            ...$activationService->heartbeat($request->credential(), $request->validated()),
        ]);
    }

    public function applicationUpdate(ApplicationUpdateRequest $request, ShopAppActivationService $activationService, ApplicationVersionService $versions): SuccessResource
    {
        $device = $activationService->deviceForCredential($request->credential());
        $device->forceFill(['last_seen_at' => now()])->save();

        return new SuccessResource($versions->updatePayload($device, $request->validated()));
    }

    public function versions(): SuccessResource
    {
        return new SuccessResource(['versions' => []]);
    }
}
